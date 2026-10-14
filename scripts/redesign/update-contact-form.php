<?php
/**
 * Update the contact form (WPForms #60) to the redesign copy: placeholders on
 * the name, email, and message fields, and "Send message" on the submit button.
 *
 * Form settings live in the database (the form post's JSON), so — like page
 * content — they ship by script, never by a DB push. Matches fields by their
 * label, so it fails loudly if the form was restructured. Idempotent; values
 * already in place are skipped. DRY_RUN=1 writes nothing.
 *
 * Temporary — part of the 2026 redesign cutover (docs/redesign-2026.md).
 * Delete scripts/redesign/ after launch.
 *
 * Usage:
 *   DRY_RUN=1 ddev wp eval-file scripts/redesign/update-contact-form.php
 *   ddev wp eval-file scripts/redesign/update-contact-form.php
 */

if (!defined('ABSPATH')) {
    echo "This script must be run via WP-CLI: ddev wp eval-file scripts/redesign/update-contact-form.php\n";
    exit(1);
}

if (!function_exists('wpforms_decode') || !function_exists('wpforms_encode')) {
    echo "Error: WPForms is not active.\n";
    exit(1);
}

$formId = (int) (getenv('FORM_ID') ?: 60);
$dryRun = !empty(getenv('DRY_RUN'));
$prefix = $dryRun ? '[dry-run] ' : '';

$placeholders = [
    'Your full name'  => 'Your name',
    'Email'           => 'you@company.com',
    'Write a message' => 'What are you building, and what does success look like?',
];
$submitText = 'Send message';

$post = get_post($formId);
if (!$post || $post->post_type !== 'wpforms') {
    echo "Error: form #{$formId} not found.\n";
    exit(1);
}

$form = wpforms_decode($post->post_content);
if (!is_array($form) || empty($form['fields'])) {
    echo "Error: form #{$formId} has no fields.\n";
    exit(1);
}

$changes = 0;
$found = [];

foreach ($form['fields'] as $id => $field) {
    $label = $field['label'] ?? '';
    if (!array_key_exists($label, $placeholders)) {
        continue;
    }
    $found[] = $label;
    if (($field['placeholder'] ?? '') === $placeholders[$label]) {
        echo "✓ {$label}: placeholder already set\n";
        continue;
    }
    $form['fields'][$id]['placeholder'] = $placeholders[$label];
    echo "{$prefix}✓ {$label}: placeholder → \"{$placeholders[$label]}\"\n";
    $changes++;
}

$missing = array_diff(array_keys($placeholders), $found);
if ($missing) {
    echo 'Error: fields not found by label: ' . implode(', ', $missing) . "\n";
    exit(1);
}

if (($form['settings']['submit_text'] ?? '') === $submitText) {
    echo "✓ Submit button already \"{$submitText}\"\n";
} else {
    $form['settings']['submit_text'] = $submitText;
    echo "{$prefix}✓ Submit button → \"{$submitText}\"\n";
    $changes++;
}

if ($changes === 0 || $dryRun) {
    echo "Done ({$changes} change(s)" . ($dryRun ? ', none written' : '') . ").\n";
    exit(0);
}

$result = wp_update_post(['ID' => $formId, 'post_content' => wpforms_encode($form)], true);
if (is_wp_error($result)) {
    echo 'Error: ' . $result->get_error_message() . "\n";
    exit(1);
}

wp_cache_flush();
echo "Done ({$changes} change(s) written).\n";
