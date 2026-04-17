#!/bin/bash
#
# Import a Nous Signal post from a PHP block markup file into WordPress.
#
# Usage:
#   make nous-import FILE=path/to/post.php TITLE="Post Title" EXCERPT="..." DATE="2026-03-24" TAGS="tag1,tag2"
#
# The FILE should contain WordPress block markup (the post content).
# All other arguments are required.

set -e

FILE="$1"
TITLE="$2"
EXCERPT="$3"
POST_DATE="$4"
TAGS="$5"

if [ -z "$FILE" ] || [ -z "$TITLE" ] || [ -z "$POST_DATE" ]; then
    echo "Usage: $0 FILE TITLE EXCERPT DATE [TAGS]"
    echo ""
    echo "  FILE     Path to the PHP file containing block markup"
    echo "  TITLE    Post title"
    echo "  EXCERPT  Post excerpt (2-3 sentences)"
    echo "  DATE     Publication date (YYYY-MM-DD)"
    echo "  TAGS     Comma-separated tags (optional)"
    exit 1
fi

if [ ! -f "$FILE" ]; then
    echo "Error: File not found: $FILE"
    exit 1
fi

# Read the file content
CONTENT=$(cat "$FILE")

# Create the post via WP-CLI (filter deprecation warnings from porcelain output)
echo "Creating post: $TITLE"
POST_ID=$(ddev wp post create \
    --post_type=post \
    --post_status=publish \
    --post_title="$TITLE" \
    --post_excerpt="$EXCERPT" \
    --post_date="${POST_DATE} 12:00:00" \
    --post_content="$CONTENT" \
    --porcelain 2>&1 | grep -E '^[0-9]+$')

if [ -z "$POST_ID" ]; then
    echo "Error: Failed to create post"
    exit 1
fi

echo "Created post ID: $POST_ID"

# Set tags if provided
if [ -n "$TAGS" ]; then
    echo "Setting tags: $TAGS"
    IFS=',' read -ra TAG_ARRAY <<< "$TAGS"
    ddev wp post term set "$POST_ID" post_tag "${TAG_ARRAY[@]}" 2>/dev/null
fi

POST_URL=$(ddev wp post get "$POST_ID" --field=url 2>&1 | grep -v "Deprecated\|^$")

echo ""
echo "Done — post published (ID: $POST_ID, Date: $POST_DATE)"
echo "View: $POST_URL"
