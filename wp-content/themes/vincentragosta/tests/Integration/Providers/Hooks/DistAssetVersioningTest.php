<?php

namespace ChildTheme\Tests\Integration\Providers\Hooks;

use ChildTheme\Providers\Theme\Hooks\DistAssetVersioning;
use Mythus\Contracts\Hook;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the DistAssetVersioning hook.
 */
class DistAssetVersioningTest extends BaseTestCase
{
    private string $dir;
    private DistAssetVersioning $hook;

    public function set_up(): void
    {
        parent::set_up();

        $this->dir = sys_get_temp_dir() . '/dist-versioning-' . uniqid() . '/';
        mkdir($this->dir . 'js', 0777, true);
        file_put_contents($this->dir . 'js/projects-view.js', '/* built */');
        touch($this->dir . 'js/projects-view.js', 1791930627);

        $dir = $this->dir;
        $this->hook = new class ($dir) extends DistAssetVersioning {
            public function __construct(private string $path)
            {
            }

            protected function distUri(): string
            {
                return 'https://example.test/wp-content/themes/vr/dist/';
            }

            protected function distPath(): string
            {
                return $this->path;
            }

            protected function coreVersion(): string
            {
                return '6.9.7';
            }
        };
    }

    public function tear_down(): void
    {
        @unlink($this->dir . 'js/projects-view.js');
        @rmdir($this->dir . 'js');
        @rmdir($this->dir);
        parent::tear_down();
    }

    public function testImplementsHookAndRegistersBothFilters(): void
    {
        $this->assertInstanceOf(Hook::class, $this->hook);

        $this->hook->register();

        $this->assertSame(10, has_filter('script_loader_src', [$this->hook, 'versionSrc']));
        $this->assertSame(10, has_filter('style_loader_src', [$this->hook, 'versionSrc']));
    }

    public function testReplacesCoreVersionWithFileMtime(): void
    {
        $src = 'https://example.test/wp-content/themes/vr/dist/js/projects-view.js?ver=6.9.7';

        $this->assertSame(
            'https://example.test/wp-content/themes/vr/dist/js/projects-view.js?ver=1791930627',
            $this->hook->versionSrc($src)
        );
    }

    public function testAddsVersionWhenMissing(): void
    {
        $src = 'https://example.test/wp-content/themes/vr/dist/js/projects-view.js';

        $this->assertStringContainsString('ver=1791930627', $this->hook->versionSrc($src));
    }

    public function testLeavesExplicitVersionsAlone(): void
    {
        $src = 'https://example.test/wp-content/themes/vr/dist/js/projects-view.js?ver=1700000000';

        $this->assertSame($src, $this->hook->versionSrc($src));
    }

    public function testLeavesAssetsOutsideDistAlone(): void
    {
        $src = 'https://example.test/wp-includes/js/jquery.js?ver=6.9.7';

        $this->assertSame($src, $this->hook->versionSrc($src));
    }

    public function testLeavesMissingFilesAndTraversalAlone(): void
    {
        $missing   = 'https://example.test/wp-content/themes/vr/dist/js/missing.js?ver=6.9.7';
        $traversal = 'https://example.test/wp-content/themes/vr/dist/../functions.php?ver=6.9.7';

        $this->assertSame($missing, $this->hook->versionSrc($missing));
        $this->assertSame($traversal, $this->hook->versionSrc($traversal));
    }

    public function testPassesThroughFalse(): void
    {
        $this->assertFalse($this->hook->versionSrc(false));
    }
}
