<?php

declare(strict_types=1);

namespace ParentTheme\Providers;

use DI\Container;
use ParentTheme\Providers\Contracts\Hook;
use ParentTheme\Providers\Contracts\Registrable;
use ParentTheme\Providers\Support\Acf\AcfManager;
use ParentTheme\Providers\Support\Asset\AssetManager;
use ParentTheme\Providers\Support\Block\BlockManager;
use ParentTheme\Providers\Support\AbstractRegistry;
use ParentTheme\Providers\Support\Feature\FeatureManager;
use ParentTheme\Providers\Support\Rest\RestManager;
use ReflectionClass;
use ReflectionMethod;
use Twig\Environment;

/**
 * Base provider class.
 *
 * All providers should extend this class and implement the register method.
 * Provides asset enqueueing via AssetManager and block registration via BlockManager.
 */
abstract class Provider implements Registrable
{
    /**
     * Feature classes to register (toggleable via => false).
     *
     * @var array<class-string<Registrable>>
     */
    protected array $features = [];

    /**
     * Hook classes to register (always-active, additive only).
     *
     * @var array<class-string<Registrable>>
     */
    protected array $hooks = [];

    /**
     * Blocks to register.
     *
     * @var string[]
     */
    protected array $blocks = [];

    /**
     * REST API endpoint classes to register.
     *
     * @var array<class-string<\ParentTheme\Providers\Contracts\Routable>>
     */
    protected array $routes = [];

    /**
     * REST API namespace prefix (defaults to provider slug).
     */
    protected string $routeNamespace = '';

    /**
     * REST API version string.
     */
    protected string $routeVersion = 'v1';

    protected ?AcfManager $acfManager = null;
    protected ?AssetManager $assets = null;
    protected ?BlockManager $blockManager = null;
    protected ?FeatureManager $featureManager = null;
    protected ?RestManager $restManager = null;
    protected string $configPath;
    protected ?string $templatesPath = null;
    protected string $textDomain;

    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * Register the service provider.
     *
     * Child classes should override this method and call parent::register()
     * to ensure features and blocks are registered.
     */
    public function register(): void
    {
        $this->setup();
        $this->registerHooks();
        $this->registerFeatures();
        $this->acfManager->initializeHooks();
        $this->blockManager->initializeHooks($this);
        $this->maybeRegisterTwigFilter();

        if (!empty($this->restManager->getEnabled())) {
            add_action('rest_api_init', [$this, 'registerRoutes']);
        }
    }

    /**
     * Add custom Twig functions.
     *
     * Override this method to register provider-specific Twig functions.
     * Called on 'timber/twig' filter.
     */
    public function addTwigFunctions(Environment $twig): Environment
    {
        return $twig;
    }

    /**
     * Register timber/twig filter only if addTwigFunctions() is overridden.
     *
     * Avoids no-op filter callbacks for providers that don't add Twig functions.
     */
    protected function maybeRegisterTwigFilter(): void
    {
        $reflection = new ReflectionMethod($this, 'addTwigFunctions');
        $declaringClass = $reflection->getDeclaringClass()->getName();

        // Only register if a subclass overrides the method
        if ($declaringClass !== self::class) {
            add_filter('timber/twig', [$this, 'addTwigFunctions']);
        }
    }

    /**
     * Set up the asset, block, feature, and REST managers.
     *
     * Called once by register(). Idempotent as a safety net.
     */
    protected function setup(): void
    {
        if ($this->assets !== null) {
            return;
        }

        $reflection = new ReflectionClass($this);
        $slug = AssetManager::slugify($reflection->getShortName());
        $providerDir = dirname($reflection->getFileName());

        $distPath = get_stylesheet_directory() . '/dist';
        $distUri = get_stylesheet_directory_uri() . '/dist';

        $this->configPath = $providerDir . '/config';

        $templatesDir = $providerDir . '/templates';
        $this->templatesPath = is_dir($templatesDir) ? $templatesDir : null;

        $this->textDomain = str_starts_with($providerDir, get_stylesheet_directory())
            ? (get_stylesheet() ?: 'theme')
            : (get_template() ?: 'theme');
        $this->assets = new AssetManager($slug, $distPath, $distUri);

        $blocksPath = $providerDir . '/blocks';
        $themePath = get_stylesheet_directory();
        $themeUri = get_stylesheet_directory_uri();
        $relativePath = str_replace($themePath, '', $blocksPath);
        $blocksUri = $themeUri . $relativePath;

        $this->blockManager = new BlockManager($blocksPath, $blocksUri, $distPath, $distUri, $this->blocks);
        $this->featureManager = new FeatureManager($this->collectFeatures(), $this->container);
        $this->acfManager = new AcfManager($providerDir, $this->textDomain);

        $routeNamespace = $this->routeNamespace ?: $slug;
        $this->restManager = new RestManager(
            $this->collectRoutes(),
            $this->container,
            $routeNamespace . '/' . $this->routeVersion,
        );
    }

    /**
     * Register all hook classes (always-active behavioral wiring).
     *
     * Hooks are resolved from the DI container and registered unconditionally.
     * Errors are isolated per-hook so a single failure doesn't prevent others.
     */
    protected function registerHooks(): void
    {
        foreach ($this->collectHooks() as $hook) {
            try {
                $instance = $this->container->get($hook);

                if (!$instance instanceof Hook) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log(sprintf(
                        'Provider: %s does not implement Hook. '
                        . 'Move it to $features for toggleable behavior, '
                        . 'or implement Hook for always-active hooks.',
                        $hook
                    ));
                    continue;
                }

                $instance->register();
            } catch (\Throwable $e) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log(sprintf(
                    'Provider: Failed to register hook %s: %s',
                    $hook,
                    $e->getMessage()
                ));
            }
        }
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
     * @return array<class-string, bool>
     */
    protected function collectFeatures(): array
    {
        return $this->collectItems('features');
    }

    /**
     * Collect and merge hooks from the class hierarchy.
     *
     * Unlike features, hooks are additive only — no opt-out via => false.
     *
     * @return array<class-string>
     */
    protected function collectHooks(): array
    {
        return $this->collectAdditive('hooks');
    }

    /**
     * Collect and merge routes from the class hierarchy.
     *
     * @return array<class-string, bool>
     */
    protected function collectRoutes(): array
    {
        return $this->collectItems('routes');
    }

    /**
     * Collect and merge a property from the class hierarchy.
     *
     * Walks from the concrete class up toward Provider, normalizing
     * each level's array into [class => bool]. Child entries override
     * parent entries, allowing opt-out via `ClassName::class => false`.
     *
     * @return array<class-string, bool>
     */
    private function collectItems(string $property): array
    {
        $merged = [];
        $class = new ReflectionClass($this);

        while ($class && $class->getName() !== self::class) {
            $defaults = $class->getDefaultProperties();
            if (isset($defaults[$property])) {
                $normalized = AbstractRegistry::normalize($defaults[$property]);
                $merged = array_merge($normalized, $merged);
            }
            $class = $class->getParentClass();
        }

        return $merged;
    }

    /**
     * Collect and merge an additive property from the class hierarchy.
     *
     * Walks from the concrete class up toward Provider, producing a flat,
     * deduplicated array. No => false support — purely additive.
     *
     * @return array<class-string>
     */
    private function collectAdditive(string $property): array
    {
        $merged = [];
        $class = new ReflectionClass($this);

        while ($class && $class->getName() !== self::class) {
            $defaults = $class->getDefaultProperties();
            if (isset($defaults[$property]) && is_array($defaults[$property])) {
                $merged = array_merge($merged, $defaults[$property]);
            }
            $class = $class->getParentClass();
        }

        return array_values(array_unique($merged));
    }

    /**
     * Register all REST API routes via the RestManager.
     */
    public function registerRoutes(): void
    {
        $this->restManager->registerAll();
    }

    /**
     * Register a post type from a JSON config file.
     *
     * Reads the config via loadConfig(), registers the post type, and
     * handles custom flags like "classic_editor": true.
     *
     * @return string|null The registered post type slug, or null on failure.
     */
    protected function registerPostTypeFromConfig(string $filename): ?string
    {
        $config = $this->loadConfig($filename);

        if (!$config || !isset($config['post_type'], $config['args'])) {
            return null;
        }

        $postType = $config['post_type'];
        $args = $config['args'];

        if (isset($args['labels'])) {
            $args['labels'] = $this->translateLabels($args['labels']);
        }

        register_post_type($postType, $args);

        if (!empty($config['classic_editor'])) {
            add_filter('use_block_editor_for_post_type', static function (bool $use, string $type) use ($postType): bool {
                return $type === $postType ? false : $use;
            }, 10, 2);
        }

        return $postType;
    }

    /**
     * Load a JSON configuration file from the provider's config directory.
     */
    protected function loadConfig(string $filename): ?array
    {
        $this->setup();

        $filepath = $this->configPath . '/' . $filename;

        if (!file_exists($filepath)) {
            return null;
        }

        $content = file_get_contents($filepath);

        if ($content === false) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('Provider::loadConfig: Could not read file: %s', $filepath));
            return null;
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf(
                'Provider::loadConfig: Invalid JSON in %s: %s',
                $filepath,
                json_last_error_msg()
            ));
            return null;
        }

        return $data;
    }

    /**
     * Translate an array of label strings using the provider's text domain.
     *
     * @param array<string, string> $labels
     * @return array<string, string>
     */
    protected function translateLabels(array $labels): array
    {
        $this->setup();

        $translated = [];

        foreach ($labels as $key => $label) {
            $translated[$key] = __($label, $this->textDomain);
        }

        return $translated;
    }

    /**
     * Enqueue a stylesheet from the dist/css directory.
     */
    public function enqueueStyle(string $handle, string $filename, array $deps = []): void
    {
        $this->assets->enqueueStyle($handle, $filename, $deps);
    }

    /**
     * Enqueue a script from the dist/js/{provider-slug}/ directory.
     */
    public function enqueueScript(string $handle, string $filename, array $deps = [], bool $inFooter = true): void
    {
        $this->assets->enqueueScript($handle, $filename, $deps, $inFooter);
    }

    /**
     * Enqueue a stylesheet from any path relative to dist/.
     */
    protected function enqueueDistStyle(string $handle, string $path, array $deps = []): void
    {
        $this->assets->enqueueDistStyle($handle, $path, $deps);
    }

    /**
     * Enqueue a script from any path relative to dist/.
     */
    protected function enqueueDistScript(string $handle, string $path, array $deps = [], bool $inFooter = true): void
    {
        $this->assets->enqueueDistScript($handle, $path, $deps, $inFooter);
    }

    /**
     * Enqueue a script using an .asset.php manifest for dependencies and version.
     */
    protected function enqueueManifestScript(string $handle, string $path, array $extraDeps = [], bool $inFooter = true): void
    {
        $this->assets->enqueueManifestScript($handle, $path, $extraDeps, $inFooter);
    }

    /**
     * Enqueue a block editor script from dist/js/.
     */
    protected function enqueueEditorScript(string $handle, string $filename, array $deps = []): void
    {
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
        $this->setup();
        return $this->blockManager->getBlocks();
    }

    /**
     * Get the base path for blocks.
     */
    public function getBlocksPath(): string
    {
        $this->setup();
        return $this->blockManager->getBlocksPath();
    }

    /**
     * Get the URI for the blocks directory.
     */
    public function getBlocksUri(): string
    {
        $this->setup();
        return $this->blockManager->getBlocksUri();
    }

    /**
     * Get the absolute path to this provider's templates directory.
     *
     * Returns null if the provider has no templates/ directory.
     */
    public function getTemplatePath(): ?string
    {
        $this->setup();
        return $this->templatesPath;
    }

}
