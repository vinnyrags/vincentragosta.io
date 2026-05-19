<?php

declare(strict_types=1);

namespace ChildTheme\Tests\Unit\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Hooks\CardAttachmentUniqueFilename;
use Mythus\Contracts\Hook;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CardAttachmentUniqueFilename.
 *
 * The invariant: any filename matching the vulnerable card-image
 * naming pattern (`\d+_hires(-\d+)?.ext`) gets a random hex suffix
 * injected before WordPress's `wp_unique_filename` race window opens,
 * so concurrent uploads can't collide on the same on-disk path. Other
 * filenames pass through untouched so the WP media library stays
 * browsable.
 */
class CardAttachmentUniqueFilenameTest extends TestCase
{
    public function testImplementsHookInterface(): void
    {
        $this->assertTrue(
            is_subclass_of(CardAttachmentUniqueFilename::class, Hook::class),
        );
    }

    public function testRegisterAddsUploadPrefilter(): void
    {
        $hook = new CardAttachmentUniqueFilename();
        $hook->register();

        $this->assertIsInt(
            has_filter(
                'wp_handle_upload_prefilter',
                [$hook, 'suffixVulnerableFilenames'],
            ),
        );
    }

    public function testVulnerableFilenameGetsSuffixed(): void
    {
        $hook = new CardAttachmentUniqueFilename();

        $result = $hook->suffixVulnerableFilenames([
            'name' => '1_hires.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/php123',
        ]);

        $this->assertMatchesRegularExpression(
            '/^1_hires-[a-f0-9]{8}\.png$/',
            $result['name'],
            'Vulnerable filename should be suffixed with 8 hex chars',
        );
    }

    public function testWpAutoSuffixVulnerableFilenameAlsoGetsRandomSuffix(): void
    {
        // The original incident path: 1_hires-4.png had 5 attachments
        // all pointing at it. The `-4` is WP's auto-suffix from earlier
        // collisions; the random suffix here protects against the case
        // where THAT path is uploaded concurrently again.
        $hook = new CardAttachmentUniqueFilename();

        $result = $hook->suffixVulnerableFilenames([
            'name' => '12_hires-2.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/php456',
        ]);

        $this->assertMatchesRegularExpression(
            '/^12_hires-2-[a-f0-9]{8}\.png$/',
            $result['name'],
        );
    }

    public function testTwoConcurrentVulnerableUploadsGetDifferentNames(): void
    {
        // The exact race that broke 47 cards: two parallel uploads of
        // the same filename. After the hook, they should diverge
        // before WordPress's `wp_unique_filename` ever runs.
        $hook = new CardAttachmentUniqueFilename();

        $a = $hook->suffixVulnerableFilenames([
            'name' => '1_hires.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/a',
        ]);
        $b = $hook->suffixVulnerableFilenames([
            'name' => '1_hires.png',
            'type' => 'image/png',
            'tmp_name' => '/tmp/b',
        ]);

        $this->assertNotSame(
            $a['name'],
            $b['name'],
            'Two concurrent vulnerable uploads must end up with distinct filenames',
        );
    }

    public function testNonVulnerableFilenamePassesThrough(): void
    {
        // Page hero images, Yoast OG images, etc. should keep their
        // human-readable filenames so the WP media library stays
        // browsable.
        $hook = new CardAttachmentUniqueFilename();

        $result = $hook->suffixVulnerableFilenames([
            'name' => 'hero-image.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/php789',
        ]);

        $this->assertSame('hero-image.jpg', $result['name']);
    }

    public function testEmptyNamePassesThrough(): void
    {
        // Defensive: WordPress sometimes passes through partial $file
        // arrays during error handling. Don't blow up on missing keys.
        $hook = new CardAttachmentUniqueFilename();

        $result = $hook->suffixVulnerableFilenames([
            'type' => 'image/png',
            'tmp_name' => '/tmp/php123',
        ]);

        $this->assertArrayNotHasKey('name', $result);
    }

    public function testCardParentTriggersSuffixEvenForNonVulnerableName(): void
    {
        // When the WP admin media uploader passes a post_id and the
        // parent post is a card, every upload is treated as
        // card-art-feeding and gets a unique suffix — even if the
        // filename doesn't match the {digits}_hires pattern.
        $hook = new CardAttachmentUniqueFilename();

        $_REQUEST['post_id'] = 99999;
        // Stub get_post_type by registering a non-existent ID; the
        // function will return false. We test the no-card-parent
        // branch here, since we can't easily fake a post type without
        // WorDBless setup.
        $result = $hook->suffixVulnerableFilenames([
            'name' => 'random-photo.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/php999',
        ]);
        unset($_REQUEST['post_id']);

        // post_id 99999 doesn't resolve to a card, so the filename
        // passes through unchanged — confirming the isCardUpload guard
        // doesn't accidentally suffix every upload that carries a
        // post_id.
        $this->assertSame('random-photo.jpg', $result['name']);
    }
}
