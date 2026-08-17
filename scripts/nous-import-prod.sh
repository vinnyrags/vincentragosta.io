#!/bin/bash
#
# Import a Nous Signal post into a REMOTE WordPress (production/staging) over SSH.
#
# Additive and non-destructive: it runs the same `wp post create` + `wp post
# term set` as scripts/nous-import.sh, but against the remote droplet. It does
# NOT touch any other content. This is the correct way to publish a single post
# live — never `make push-production`, which replaces the entire remote DB.
#
# Usage (via Makefile):
#   make nous-import-production FILE=path.php TITLE="..." EXCERPT="..." DATE="YYYY-MM-DD" TAGS="a,b"
#
# Required env (set by the Makefile target): PROD_HOST, PROD_WP
#
# Quoting strategy: the post file is copied to the droplet and its block markup
# is read SERVER-SIDE (so embedded quotes never survive SSH arg-splitting), and
# the title/excerpt/tags are base64-encoded locally and decoded on the server
# (so apostrophes, spaces, and punctuation can never break the remote command).

set -euo pipefail

FILE="${1:?FILE required}"
TITLE="${2:?TITLE required}"
EXCERPT="${3:-}"
POST_DATE="${4:?DATE required (YYYY-MM-DD)}"
TAGS="${5:-}"

: "${PROD_HOST:?PROD_HOST not set}"
: "${PROD_WP:?PROD_WP not set}"

if [ ! -f "$FILE" ]; then
    echo "Error: File not found: $FILE" >&2
    exit 1
fi

REMOTE_TMP="/tmp/nous-import-$$.php"

# base64 encode with no line wrapping (portable across macOS/Linux)
b64() { printf '%s' "$1" | base64 | tr -d '\n'; }

echo "Copying $(basename "$FILE") → $PROD_HOST:$REMOTE_TMP"
scp -q "$FILE" "$PROD_HOST:$REMOTE_TMP"

echo "Creating post on remote: $TITLE  ($POST_DATE)"
ssh "$PROD_HOST" \
    "WP='$PROD_WP' RFILE='$REMOTE_TMP' PDATE='$POST_DATE' B_TITLE='$(b64 "$TITLE")' B_EXCERPT='$(b64 "$EXCERPT")' B_TAGS='$(b64 "$TAGS")' bash -s" <<'REMOTE'
set -euo pipefail
TITLE="$(printf '%s' "$B_TITLE"   | base64 -d)"
EXCERPT="$(printf '%s' "$B_EXCERPT" | base64 -d)"
TAGS="$(printf '%s' "$B_TAGS"     | base64 -d)"
CONTENT="$(cat "$RFILE")"

# Resolve the publish timestamp in SITE-LOCAL time.
#
# Posts are conventionally dated noon on their own day, but WordPress silently
# downgrades post_status publish -> future when the timestamp is ahead of site
# time. A same-day post imported before noon would therefore be SCHEDULED, not
# published, and the caller's HTTP verification would see a 404. Clamp anything
# still in the future back to "now".
#
# post_date_gmt is passed explicitly because WP does NOT recompute it from
# post_date on a later `wp post update` — and the GMT column is the one the
# future-check actually reads, so a stale value keeps re-flipping the post back
# to `future` no matter how many times you set the status to publish.
wpdate() {   # arg: PHP expression echoing a Y-m-d H:i:s string
    wp eval "$1" --path="$WP" --allow-root 2>/dev/null \
      | grep -oE '[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}' | head -1
}

NOW_LOCAL="$(wpdate 'echo current_time("mysql");' || true)"
POST_LOCAL="${PDATE} 12:00:00"
if [ -n "$NOW_LOCAL" ] && [ "$POST_LOCAL" \> "$NOW_LOCAL" ]; then
    echo "  ↻ ${POST_LOCAL} is ahead of site time (${NOW_LOCAL}) — clamping so it publishes now."
    POST_LOCAL="$NOW_LOCAL"
fi

POST_GMT="$(wpdate "echo get_gmt_from_date('${POST_LOCAL}');" || true)"
if [ -z "$POST_GMT" ]; then
    echo "Error: could not resolve GMT time for ${POST_LOCAL} on remote" >&2
    rm -f "$RFILE"
    exit 1
fi

POST_ID=$(wp post create \
    --post_type=post \
    --post_status=publish \
    --post_title="$TITLE" \
    --post_excerpt="$EXCERPT" \
    --post_date="$POST_LOCAL" \
    --post_date_gmt="$POST_GMT" \
    --post_content="$CONTENT" \
    --path="$WP" --allow-root --porcelain 2>/dev/null | grep -E '^[0-9]+$' || true)

if [ -z "$POST_ID" ]; then
    echo "Error: post create failed on remote" >&2
    rm -f "$RFILE"
    exit 1
fi

if [ -n "$TAGS" ]; then
    IFS=',' read -ra T <<< "$TAGS"
    wp post term set "$POST_ID" post_tag "${T[@]}" --path="$WP" --allow-root >/dev/null 2>&1
fi

URL=$(wp post get "$POST_ID" --field=url --path="$WP" --allow-root 2>/dev/null | grep -v 'Deprecated' || true)
echo "  → published ID $POST_ID : $URL"
rm -f "$RFILE"
REMOTE

echo "Done."
