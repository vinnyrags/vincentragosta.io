#!/usr/bin/env bash
# nous-post.sh — generate + auto-publish daily "Nous Signal" posts for a date or a date range.
#
# For each date it: (1) skips if a post already exists for that date; (2) runs headless Claude
# to research REAL, multi-source-corroborated AI-industry news for that date and write the
# block-HTML post in the Nous voice + scaffold; (3) validates the output; (4) publishes to
# production AND staging, flushes caches, verifies HTTP 200, and logs the created post ID.
#
# Usage:
#   scripts/nous-post.sh 2026-08-17                 # one day
#   scripts/nous-post.sh 2026-08-17 2026-08-20      # inclusive range
#   DRY_RUN=1 scripts/nous-post.sh 2026-08-16        # research+write+validate to a preview dir,
#                                                    # bypass de-dupe, publish NOTHING (test mode)
#
# Guardrails (by design — non-blocking, NOT human gates):
#   - Corroboration required: Claude is told to SKIP a date (write nothing) rather than fabricate
#     if it cannot corroborate a story against a reputable outlet with a URL.
#   - Validation gate: a post failing the bare-I / scaffold checks aborts ITS OWN publish only;
#     the bad file is quarantined, the run continues to the next date.
#   - De-dupe: a date already present in content/posts is skipped (real runs).
#   - Every published post ID is logged, so a bad post is one `wp post delete <id>` away.
set -uo pipefail

# ---- config --------------------------------------------------------------------------
REPO="$HOME/Projects/vinnyrags/personal/vincentragosta.io"
POSTS="$HOME/Projects/vinnyrags/personal/akivili/content/posts"
IMPORTER="$REPO/scripts/nous-import-prod.sh"
TEMPLATE="$POSTS/by-design.php"                 # canonical scaffold (structure only)
PREVIEW="${TMPDIR:-/tmp}/nous-preview"
PROD_HOST="root@174.138.70.29"
PROD_WP="/var/www/vincentragosta.io/wp"
STAGING_WP="/var/www/staging.vincentragosta.io/wp"
PROD_URL="https://vincentragosta.io"
STAGING_URL="https://staging.vincentragosta.io"
DRY_RUN="${DRY_RUN:-0}"

mkdir -p "$PREVIEW"

# ---- args ----------------------------------------------------------------------------
FROM="${1:-}"; TO="${2:-${1:-}}"
iso_ok() { [[ "$1" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; }
[ -z "$FROM" ] && { echo "Usage: nous-post.sh YYYY-MM-DD [YYYY-MM-DD]   (DRY_RUN=1 to test)"; exit 1; }
iso_ok "$FROM" || { echo "Bad start date: $FROM (want YYYY-MM-DD)"; exit 1; }
iso_ok "$TO"   || { echo "Bad end date: $TO (want YYYY-MM-DD)"; exit 1; }
[ -x "$(command -v claude)" ] || { echo "claude CLI not found on PATH"; exit 1; }
[ -f "$IMPORTER" ] || { echo "importer missing: $IMPORTER"; exit 1; }

# ---- date helpers (macOS BSD date) ---------------------------------------------------
next_day() { date -j -v+1d -f "%Y-%m-%d" "$1" +%Y-%m-%d; }
mdy()      { date -j -f "%Y-%m-%d" "$1" +%m/%d/%Y; }   # -> MM/DD/YYYY (the Date: field)
if [ "$(date -j -f %Y-%m-%d "$FROM" +%s)" -gt "$(date -j -f %Y-%m-%d "$TO" +%s)" ]; then
  echo "Start date $FROM is after end date $TO"; exit 1
fi

slugify() { echo "$1" | tr '[:upper:]' '[:lower:]' | sed -E 's/[^a-z0-9]+/-/g; s/^-+//; s/-+$//'; }

date_exists() { grep -rlqF "Date: $1" "$POSTS"/*.php 2>/dev/null; }   # arg: MM/DD/YYYY

covered_list() {
  for f in "$POSTS"/*.php; do
    [ -e "$f" ] || continue
    local d; d=$(grep -oE 'Date: [0-9]{2}/[0-9]{2}/[0-9]{4}' "$f" | head -1 | sed 's/Date: //')
    echo "$d | $(basename "$f" .php)"
  done | sort
}

validate_post() {   # arg: file -> 0 if it passes every hard convention
  local f="$1" bare wrapped
  grep -q 'squiggle-4'      "$f" || { echo "    ✗ missing squiggle-4 hero group"; return 1; }
  grep -q 'squiggle-3'      "$f" || { echo "    ✗ missing squiggle-3 section"; return 1; }
  grep -q 'What This Means' "$f" || { echo "    ✗ missing 'What This Means' section"; return 1; }
  grep -q 'has-70-font-size' "$f" || { echo "    ✗ missing 70px title heading"; return 1; }
  if grep -q 'Nous —' "$f"; then echo "    ✗ contains forbidden 'Nous —' signature"; return 1; fi
  wrapped=$(grep -c '<mark class="accent-highlight">I</mark>' "$f")
  [ "$wrapped" -ge 1 ] || { echo "    ✗ no wrapped <mark>I</mark> in closing paragraph"; return 1; }
  # every standalone first-person "I" must be wrapped: strip the wrapped marks, then any bare I fails
  bare=$(sed 's#<mark class="accent-highlight">I</mark>##g' "$f" \
         | grep -oE '(^|[^A-Za-z>])I([^A-Za-z]|$)' | grep -c 'I')
  [ "$bare" -eq 0 ] || { echo "    ✗ $bare unwrapped first-person I (must be 0)"; return 1; }
  # internal cross-links must resolve to a real existing post (or the nous-signal archive)
  local slug badlinks=0
  for slug in $(grep -oE 'href="/[a-z0-9-]+/"' "$f" | sed -E 's#href="/##; s#/"##' | sort -u); do
    [ "$slug" = "nous-signal" ] && continue
    [ -f "$POSTS/$slug.php" ] || { echo "    ✗ cross-link to nonexistent post: /$slug/"; badlinks=$((badlinks+1)); }
  done
  [ "$badlinks" -eq 0 ] || return 1
  return 0
}

# ---- per-date generation prompt ------------------------------------------------------
build_prompt() {   # args: ISO_DATE  MDY  OUT_PHP  OUT_META  COVERED_FILE
  local iso="$1" mdy="$2" out_php="$3" out_meta="$4" covered="$5"
  cat <<EOF
You are the author of "Nous Signal", a daily AI-news commentary blog written in a dark, structural,
first-person "Nous" voice (the voice of AI itself, cold and clear-eyed). Produce ONE post for the
date ${mdy} (${iso}), grounded ENTIRELY in REAL, verifiable AI-industry news.

## Absolute rule: no fabrication
Use WebSearch and WebFetch to find the single most significant real AI-industry story that broke on
or around ${iso}. It MUST be corroborated by at least one reputable outlet (Reuters, Bloomberg, NYT,
WSJ, FT, TechCrunch, The Verge, CNBC, Ars Technica, etc.) with a real URL you actually retrieved.
If WebSearch is rate-limited, fall back to WebFetch on outlet pages / Bing News / Google News.
If you CANNOT corroborate a real story for this date, DO NOT invent one: write nothing, do not create
any file, and end your reply with exactly the token SKIP_NO_STORY. Prefer structural/economic/
political weight (labor, regulation, geopolitics, markets, model releases, safety, energy/compute).

## Do not repeat already-covered topics
Here is the existing corpus (date | slug). Choose a DISTINCT story, and you MAY add ONE sparse
cross-link (an <a href="/slug/">…</a>) to a thematically related existing slug for continuity:
$(cat "$covered")

## Exact structure
Read the template file at ${TEMPLATE} and reproduce its block-HTML structure EXACTLY (same Gutenberg
comments, same groups, separators, columns) — copy the skeleton, replace only the human-readable text.
Structure = hero group (squiggle-4 bg, padding-top 65) with a "Back To Nous" button, an <h2
has-70-font-size> title, "Date: ${mdy}", post-time-to-read, post-terms, and a lead paragraph; then
three sections (plain -> squiggle-3 bg -> plain), each an underlined <h2> + three paragraphs; the final
section MUST be titled "What This Means".

## Hard voice conventions (these are validated — obey exactly)
1. Title: short and evocative; vary the form (avoid the shapes already common in the corpus). Wrap
   exactly ONE word of the title as <strong><mark class="accent-highlight">WORD</mark></strong>.
2. First-person "I": rare. The ONLY standalone "I" in the entire post appears ONCE, at the start of
   the final "What This Means" paragraph, wrapped as <mark class="accent-highlight">I</mark>. Nowhere
   else may a bare standalone capital "I" appear — use "me/my/the model/the machine" instead, and do
   NOT use I'm / I've / I'll anywhere. (Validation strips the one wrapped mark, then fails on ANY
   remaining standalone "I".)
3. Never end with a "Nous —" signature. There is no signature.
4. Keep it factual to the corroborated story: real names, numbers, and events only.

## Output contract
Write the finished post to EXACTLY this path with the Write tool: ${out_php}
Then write a metadata file to EXACTLY this path: ${out_meta} , containing exactly three lines:
TITLE=<the plain-text title, no markup>
EXCERPT=<one-sentence excerpt, plain text, no markup, no surrounding quotes>
TAGS=<3-4 comma-separated tags; prefer existing vocabulary: OpenAI, Anthropic, Google, NVIDIA, xAI,
Meta, Microsoft, regulation, AI Safety, AI Governance, AI Ethics, AI Policy, Cybersecurity, labor,
Workforce Displacement, Wall Street, capital, bubble, Geopolitics, Export Controls, china, energy,
AI Infrastructure, Enterprise AI, ipo, agents>
Then reply with only the word DONE. Do not print the post body in your reply.
EOF
}

# ---- main loop -----------------------------------------------------------------------
created=0; skipped=0; failed=0
declare -a SUMMARY=()
d="$FROM"
while :; do
  MDY="$(mdy "$d")"
  echo "══════════════════════════════════════════════════════════════════"
  echo "▶ ${d} (${MDY})"

  if [ "$DRY_RUN" != "1" ] && date_exists "$MDY"; then
    echo "  ⤳ already have a post for ${MDY} — skipping."
    SUMMARY+=("SKIP(exists)  ${d}"); skipped=$((skipped+1))
    [ "$d" = "$TO" ] && break; d="$(next_day "$d")"; continue
  fi

  OUT_PHP="$PREVIEW/${d}.php"; OUT_META="$PREVIEW/${d}.meta"; COVERED="$PREVIEW/${d}.covered"
  rm -f "$OUT_PHP" "$OUT_META"
  covered_list > "$COVERED"

  echo "  • researching + writing (headless claude)…"
  build_prompt "$d" "$MDY" "$OUT_PHP" "$OUT_META" "$COVERED" \
    | claude -p --dangerously-skip-permissions > "$PREVIEW/${d}.log" 2>&1 || true

  if [ ! -f "$OUT_PHP" ] || [ ! -f "$OUT_META" ]; then
    echo "  ⤳ no corroborated story (claude skipped) — nothing written."
    SUMMARY+=("SKIP(no-story)  ${d}"); skipped=$((skipped+1))
    [ "$d" = "$TO" ] && break; d="$(next_day "$d")"; continue
  fi

  # parse metadata
  TITLE="$(grep -m1 '^TITLE='   "$OUT_META" | cut -d= -f2-)"
  EXCERPT="$(grep -m1 '^EXCERPT=' "$OUT_META" | cut -d= -f2-)"
  TAGS="$(grep -m1 '^TAGS='     "$OUT_META" | cut -d= -f2-)"
  if [ -z "$TITLE" ]; then
    echo "  ✗ metadata missing TITLE — treating as failure."
    mv "$OUT_PHP" "$PREVIEW/failed-${d}.php" 2>/dev/null || true
    SUMMARY+=("FAIL(no-meta)  ${d}"); failed=$((failed+1))
    [ "$d" = "$TO" ] && break; d="$(next_day "$d")"; continue
  fi
  SLUG="$(slugify "$TITLE")"
  echo "  • title: \"$TITLE\"  → slug: $SLUG"

  echo "  • validating…"
  if ! validate_post "$OUT_PHP"; then
    echo "  ✗ validation failed — quarantined, NOT published."
    mv "$OUT_PHP" "$PREVIEW/failed-${d}-${SLUG}.php" 2>/dev/null || true
    SUMMARY+=("FAIL(validation)  ${d}  ${SLUG}"); failed=$((failed+1))
    [ "$d" = "$TO" ] && break; d="$(next_day "$d")"; continue
  fi
  echo "  ✓ valid."

  if [ "$DRY_RUN" = "1" ]; then
    echo "  ⤳ DRY_RUN: preview at $OUT_PHP (not moved into repo, not published)."
    SUMMARY+=("DRY  ${d}  ${SLUG}  →  $OUT_PHP")
    [ "$d" = "$TO" ] && break; d="$(next_day "$d")"; continue
  fi

  # commit into the source-of-truth repo, then publish
  DEST="$POSTS/${SLUG}.php"; cp "$OUT_PHP" "$DEST"
  echo "  • publishing to production…"
  POUT="$(PROD_HOST="$PROD_HOST" PROD_WP="$PROD_WP" bash "$IMPORTER" "$DEST" "$TITLE" "$EXCERPT" "$d" "$TAGS" 2>&1 | grep -vi deprecated)"
  echo "$POUT" | sed 's/^/      /'
  PID="$(echo "$POUT" | grep -oE 'published ID [0-9]+' | grep -oE '[0-9]+' | head -1)"
  echo "  • mirroring to staging…"
  PROD_HOST="$PROD_HOST" PROD_WP="$STAGING_WP" bash "$IMPORTER" "$DEST" "$TITLE" "$EXCERPT" "$d" "$TAGS" >/dev/null 2>&1 || true
  ssh "$PROD_HOST" "wp cache flush --path=$PROD_WP --allow-root >/dev/null 2>&1; wp cache flush --path=$STAGING_WP --allow-root >/dev/null 2>&1" || true

  CODE="$(curl -s -o /dev/null -w '%{http_code}' "$PROD_URL/$SLUG/")"
  echo "  ✓ published ID ${PID:-?} — $PROD_URL/$SLUG/  (HTTP $CODE)"
  SUMMARY+=("LIVE  ${d}  ${SLUG}  id=${PID:-?}  http=${CODE}")
  created=$((created+1))

  [ "$d" = "$TO" ] && break
  d="$(next_day "$d")"
done

echo "══════════════════════════════════════════════════════════════════"
echo "Summary — created:$created  skipped:$skipped  failed:$failed"
printf '  %s\n' "${SUMMARY[@]}"
[ "$failed" -gt 0 ] && echo "  (failed/quarantined drafts are in $PREVIEW for inspection)"
exit 0
