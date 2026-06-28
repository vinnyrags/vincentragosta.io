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

POST_ID=$(wp post create \
    --post_type=post \
    --post_status=publish \
    --post_title="$TITLE" \
    --post_excerpt="$EXCERPT" \
    --post_date="${PDATE} 12:00:00" \
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
