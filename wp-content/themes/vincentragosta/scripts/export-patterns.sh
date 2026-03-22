#!/usr/bin/env bash
#
# Export WordPress synced patterns (wp_block CPT) from a remote server
# to PHP pattern files, routed to provider directories by category.
#
# Patterns whose category matches a provider directory name (e.g., "project"
# → src/Providers/Project/) are placed in that provider's patterns/ subdirectory.
# Unmatched patterns fall back to the theme-root patterns/ directory for
# WordPress auto-discovery.
#
# Usage:
#   ./scripts/export-patterns.sh
#
# Environment variables (set by Makefile targets):
#   REMOTE_HOST  - SSH host (e.g., root@174.138.70.29)
#   REMOTE_WP    - Remote WordPress path for WP-CLI --path flag
#   REMOTE_URL   - Remote site URL for upload path replacement

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
THEME_DIR="$(dirname "$SCRIPT_DIR")"
PATTERNS_DIR="$THEME_DIR/patterns"
PROVIDERS_DIR="$THEME_DIR/src/Providers"

# Validate required environment variables
if [[ -z "${REMOTE_HOST:-}" || -z "${REMOTE_WP:-}" || -z "${REMOTE_URL:-}" ]]; then
    echo "Error: REMOTE_HOST, REMOTE_WP, and REMOTE_URL must be set."
    echo "Use 'make pull-patterns' or 'make pull-patterns-staging' instead."
    exit 1
fi

echo "Fetching synced patterns from $REMOTE_HOST..."

# Fetch all wp_block posts as JSON (title, slug, content) in a single SSH call
PATTERNS_JSON=$(ssh "$REMOTE_HOST" "wp post list \
    --post_type=wp_block \
    --post_status=publish \
    --fields=ID,post_title,post_name,post_content \
    --format=json \
    --path=$REMOTE_WP \
    --allow-root" 2>/dev/null)

if [[ -z "$PATTERNS_JSON" || "$PATTERNS_JSON" == "[]" ]]; then
    echo "No synced patterns found on $REMOTE_HOST."
    exit 0
fi

# Fetch pattern category assignments (term relationships)
CATEGORIES_JSON=$(ssh "$REMOTE_HOST" "wp post list \
    --post_type=wp_block \
    --post_status=publish \
    --fields=ID,post_name \
    --format=json \
    --path=$REMOTE_WP \
    --allow-root" 2>/dev/null)

# Build a map of post ID -> category slugs using WP-CLI on the remote
CATEGORY_MAP_JSON=$(ssh "$REMOTE_HOST" "
    ids=\$(wp post list --post_type=wp_block --post_status=publish --field=ID --path=$REMOTE_WP --allow-root 2>/dev/null)
    echo '{'
    first=true
    for id in \$ids; do
        cats=\$(wp post term list \$id wp_pattern_category --field=slug --format=csv --path=$REMOTE_WP --allow-root 2>/dev/null | tr '\n' ',' | sed 's/,\$//')
        if [ \"\$first\" = true ]; then
            first=false
        else
            echo ','
        fi
        echo -n \"\\\"\$id\\\": \\\"\$cats\\\"\"
    done
    echo ''
    echo '}'
" 2>/dev/null)

# Count patterns
PATTERN_COUNT=$(node -e "
    const patterns = JSON.parse(process.argv[1]);
    console.log(patterns.length);
" "$PATTERNS_JSON")

echo "Found $PATTERN_COUNT pattern(s). Generating PHP files..."

# Process each pattern and generate PHP files, routing by category to providers
node -e "
    const patterns = JSON.parse(process.argv[1]);
    const categoryMap = JSON.parse(process.argv[2] || '{}');
    const remoteUrl = process.argv[3];
    const patternsDir = process.argv[4];
    const providersDir = process.argv[5];
    const fs = require('fs');
    const path = require('path');

    // Convert a category slug to PascalCase provider directory name
    // e.g., 'project' -> 'Project', 'post-type' -> 'PostType'
    function toPascalCase(slug) {
        return slug.split('-').map(s => s.charAt(0).toUpperCase() + s.slice(1)).join('');
    }

    // Find a matching provider directory for a list of categories
    function findProviderDir(categories) {
        for (const cat of categories) {
            const pascalName = toPascalCase(cat);
            const providerPath = path.join(providersDir, pascalName);
            if (fs.existsSync(providerPath) && fs.statSync(providerPath).isDirectory()) {
                return { dir: path.join(providerPath, 'patterns'), provider: pascalName };
            }
        }
        return null;
    }

    // Build upload URL pattern for replacement
    const uploadUrlPattern = remoteUrl.replace(/[.*+?^\${}()|[\]\\\\]/g, '\\\\\$&') + '/wp-content/uploads/';

    patterns.forEach(pattern => {
        const slug = pattern.post_name;
        const title = pattern.post_title;
        let content = pattern.post_content;

        // Get categories for this pattern
        const cats = categoryMap[String(pattern.ID)] || '';
        const categoryList = cats ? cats.split(',').filter(Boolean).join(', ') : 'vincentragosta';
        const categories = categoryList.split(',').map(s => s.trim()).filter(Boolean);

        // Replace hardcoded production upload URLs with dynamic PHP expression
        content = content.replace(
            new RegExp(uploadUrlPattern, 'g'),
            \"<?php echo esc_url(content_url('uploads/')); ?>\"
        );

        // Build the PHP pattern file
        const phpContent = [
            '<?php',
            '/**',
            ' * Title: ' + title,
            ' * Slug: vincentragosta/' + slug,
            ' * Categories: ' + categoryList,
            ' * Inserter: true',
            ' */',
            '',
            '?>',
            content,
            '',
        ].join('\n');

        // Route to provider directory or fall back to theme-root patterns/
        const match = findProviderDir(categories);
        let outputDir;
        let logLabel;

        if (match) {
            outputDir = match.dir;
            logLabel = 'src/Providers/' + match.provider + '/patterns/' + slug + '.php';
        } else {
            outputDir = patternsDir;
            logLabel = 'patterns/' + slug + '.php';
        }

        // Create output directory if needed
        if (!fs.existsSync(outputDir)) {
            fs.mkdirSync(outputDir, { recursive: true });
        }

        const filePath = path.join(outputDir, slug + '.php');
        fs.writeFileSync(filePath, phpContent);
        console.log('  Generated: ' + logLabel);
    });
" "$PATTERNS_JSON" "$CATEGORY_MAP_JSON" "$REMOTE_URL" "$PATTERNS_DIR" "$PROVIDERS_DIR"

echo "Done — $PATTERN_COUNT pattern(s) exported."
