<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;

/**
 * Inject a random suffix into card-image filenames at upload time so
 * concurrent / bulk uploads can't collide on the same on-disk path.
 *
 * The bug we're protecting against: when N parallel uploads of files
 * with the same name (e.g., five different "1_hires.png" coming from
 * five different card-set imports) all call wp_unique_filename in the
 * same window, each PHP worker sees the same on-disk state, each picks
 * the same "unique" name (say `1_hires-4.png`), each writes the file
 * to disk (overwriting each other), and each creates an attachment
 * record pointing at that single path. Result: 5 attachment records
 * share one file; the file on disk is whichever upload finished last;
 * the other 4 cards display the wrong image on the storefront.
 *
 * Surfaced 2026-05-18 — 47 published cards on itzenzo.tv/collection
 * (and /cards) were displaying mismatched images. Audit traced every
 * affected attachment to the 2026-05-09/10 card-image import batch,
 * with file paths like 1_hires-4.png shared across 5 distinct cards.
 *
 * Scope: only filenames matching the known-vulnerable pattern
 * (`{digits}_hires(-{digits})?.ext`) get the unique suffix, plus any
 * upload whose request carries a parent card post_id. Other uploads
 * (page hero images, Yoast OG, etc.) keep their natural filenames so
 * the WP media library stays browsable.
 */
class CardAttachmentUniqueFilename implements Hook
{
    /**
     * Filename pattern that triggered the 2026-05 collision incident.
     * Matches `1_hires.png`, `12_hires-2.png`, `8_hires-4.jpg`, etc.
     * Source images for card art that ship with this naming come from
     * a TCG image-host convention; we have no control over the
     * upstream naming, so we defensively suffix them at upload time.
     */
    private const VULNERABLE_PATTERN = '/^\d+_hires(-\d+)?\.[A-Za-z0-9]+$/';

    public function register(): void
    {
        add_filter('wp_handle_upload_prefilter', [$this, 'suffixVulnerableFilenames']);
    }

    /**
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    public function suffixVulnerableFilenames(array $file): array
    {
        $name = isset($file['name']) && is_string($file['name']) ? $file['name'] : '';
        if ($name === '') {
            return $file;
        }

        if (!$this->isVulnerable($name) && !$this->isCardUpload()) {
            return $file;
        }

        $info = pathinfo($name);
        $base = $info['filename'] ?? $name;
        $ext = isset($info['extension']) && $info['extension'] !== ''
            ? '.' . $info['extension']
            : '';

        // 8 hex chars = 4 bytes of entropy. Collision probability is
        // ~1 in 4 billion per upload — statistically negligible at our
        // volume even during a bulk import.
        try {
            $suffix = bin2hex(random_bytes(4));
        } catch (\Throwable $e) {
            // random_bytes can throw if the OS RNG is unavailable.
            // Microtime fallback gives less entropy but is still
            // collision-resistant within the concurrent-upload window.
            $suffix = substr(str_replace('.', '', (string) microtime(true)), -8);
        }

        $file['name'] = $base . '-' . $suffix . $ext;
        return $file;
    }

    private function isVulnerable(string $name): bool
    {
        return (bool) preg_match(self::VULNERABLE_PATTERN, $name);
    }

    private function isCardUpload(): bool
    {
        // WP Admin's media uploader threads a post_id query/form param
        // through to wp_handle_upload_prefilter. When the parent is a
        // card post, we know this upload is feeding card art and apply
        // the suffix regardless of filename pattern.
        $postId = isset($_REQUEST['post_id']) ? (int) $_REQUEST['post_id'] : 0;
        if ($postId <= 0) {
            return false;
        }
        return get_post_type($postId) === 'card';
    }
}
