<?php

namespace ParentTheme\Providers;

use ParentTheme\Providers\Contracts\Registrable;
use ParentTheme\Providers\Support\Asset\AssetManager;
use ParentTheme\Providers\Support\Block\BlockManager;
use ParentTheme\Providers\Support\Feature\FeatureManager;
use ReflectionClass;

/**
 * Base service provider class.
 *
 * All service providers should extend this class and implement the register method.
 * Provides asset enqueueing via AssetManager and block registration via BlockManager.
 */
abstract class ServiceProvider implements Registrable
{
    /**
     * Feature classes to register.
     *
     * @var array<class-string<Registrable>>
     */
    protected array $features = [];

    /**
     * Blocks to register.
     *
     * @var string[]
     */
    protected array $blocks = [];

    protected ?AssetManager $assets = null;
    protected ?BlockManager $blockManager = null;
    protected ?FeatureManager $featureManager = null;

    /**
     * Register the service provider.
     *
     * Child classes should override this method and call parent::register()
     * to ensure features and blocks are registered.
     */
    public function register(): void
    {
        $this->boot();
        $this->registerFeatures();
        $this->blockManager->initializeHooks($this);
    }

    /**
     * Initialize the asset, block, and feature managers.
     *
     * Idempotent — safe to call multiple times.
     */
    protected function boot(): void
    {
        if ($this->assets !== null) {
            return;
        }

        $reflection = new ReflectionClass($this);
        $slug = AssetManager::slugify($reflection->getShortName());
        $providerDir = dirname($reflection->getFileName());

        $distPath = get_stylesheet_directory() . '/dist';
        $distUri = get_stylesheet_directory_uri() . '/dist';

        $this->assets = new AssetManager($slug, $distPath, $distUri);

        $blocksPath = $providerDir . '/blocks';
        $themePath = get_stylesheet_directory();
        $themeUri = get_stylesheet_directory_uri();
        $relativePath = str_replace($themePath, '', $blocksPath);
        $blocksUri = $themeUri . $relativePath;

        $this->blockManager = new BlockManager($blocksPath, $blocksUri, $distPath, $distUri, $this->blocks);
        $this->featureManager = new FeatureManager($this->collectFeatures());
    }

    /**
     * Register all feature classes via the FeatureManager.
     */
    protected function registerFeatures(): void
    {
        $this->featureManager->registerAll();
    }

    /**
     * Collect and merge features from the class hierarchy.
     *
     * Walks from the concrete class up toward ServiceProvider, normalizing
     * each level's $features into [class => bool]. Child entries override
     * parent entries, allowing opt-out via `ClassName::class => false`.
     *
     * @return array<class-string, bool>
     */
    protected function collectFeatures(): array
    {
        $merged = [];
        $class = new ReflectionClass($this);

        while ($class && $class->getName() !== self::class) {
            $defaults = $class->getDefaultProperties();
            if (isset($defaults['features'])) {
                $normalized = FeatureManager::normalize($defaults['features']);
                $merged = array_merge($normalized, $merged);
            }
            $class = $class->getParentClass();
        }

        return $merged;
    }

    /**
     * Enqueue a stylesheet from the dist/css directory.
     */
    public function enqueueStyle(string $handle, string $filename, array $deps = []): void
    {
        $this->boot();
        $this->assets->enqueueStyle($handle, $filename, $deps);
    }

    /**
     * Enqueue a script from the dist/js/{provider-slug}/ directory.
     */
    public function enqueueScript(string $handle, string $filename, array $deps = [], bool $inFooter = true): void
    {
        $this->boot();
        $this->assets->enqueueScript($handle, $filename, $deps, $inFooter);
    }

    /**
     * Enqueue a stylesheet from any path relative to dist/.
     */
    protected function enqueueDistStyle(string $handle, string $path, array $deps = []): void
    {
        $this->boot();
        $this->assets->enqueueDistStyle($handle, $path, $deps);
    }

    /**
     * Enqueue a script from any path relative to dist/.
     */
    protected function enqueueDistScript(string $handle, string $path, array $deps = [], bool $inFooter = true): void
    {
        $this->boot();
        $this->assets->enqueueDistScript($handle, $path, $deps, $inFooter);
    }

    /**
     * Enqueue a script using an .asset.php manifest for dependencies and version.
     */
    protected function enqueueManifestScript(string $handle, string $path, array $extraDeps = [], bool $inFooter = true): void
    {
        $this->boot();
        $this->assets->enqueueManifestScript($handle, $path, $extraDeps, $inFooter);
    }

    /**
     * Enqueue a block editor script from dist/js/.
     */
    protected function enqueueEditorScript(string $handle, string $filename, array $deps = []): void
    {
        $this->boot();
        $this->blockManager->enqueueEditorScript($handle, $filename, $deps);
    }

    /**
     * Enqueue block assets for both frontend and editor.
     *
     * Override this method to enqueue styles/scripts for your blocks.
     * Called on 'enqueue_block_assets' hook.
     */
    public function enqueueBlockAssets(): void
    {
        // Override in provider to enqueue block assets
    }

    /**
     * Enqueue block editor assets only.
     *
     * Override this method to enqueue editor-only scripts for your blocks.
     * Called on 'enqueue_block_editor_assets' hook.
     */
    public function enqueueBlockEditorAssets(): void
    {
        // Override in provider to enqueue editor assets
    }

    /**
     * Get the blocks to register.
     *
     * @return string[]
     */
    public function getBlocks(): array
    {
        $this->boot();
        return $this->blockManager->getBlocks();
    }

    /**
     * Get the base path for blocks.
     */
    public function getBlocksPath(): string
    {
        $this->boot();
        return $this->blockManager->getBlocksPath();
    }

    /**
     * Get the URI for the blocks directory.
     */
    public function getBlocksUri(): string
    {
        $this->boot();
        return $this->blockManager->getBlocksUri();
    }

    /**
     * Register all blocks.
     */
    public function registerBlocks(): void
    {
        $this->boot();
        $this->blockManager->registerBlocks();
    }
}
