<?php

declare(strict_types=1);

namespace ParentTheme;

use DI\Container;
use DI\ContainerBuilder;
use Mythus\Contracts\Registrable;
use Timber\Site;
use Timber\Timber;

/**
 * Base theme class.
 *
 * Bootstraps the theme by registering service providers.
 * Child themes should extend this class and define their own providers.
 */
class Theme extends Site
{
    /**
     * Service providers to register.
     *
     * @var array<class-string<Registrable>>
     */
    protected array $providers = [];

    /**
     * Timber template directories.
     *
     * @var array<string>
     */
    protected array $templateDirectories = ['templates', 'views', 'blocks'];

    private static ?Container $containerInstance = null;
    protected Container $container;

    /**
     * Constructor — DI only.
     * Builds the container. All other setup happens in bootstrap().
     *
     * @throws \RuntimeException If Theme is instantiated more than once.
     */
    public function __construct()
    {
        if (self::$containerInstance !== null) {
            throw new \RuntimeException('Theme has already been initialized. Only one instance is allowed.');
        }

        $this->container = $this->buildContainer();
        self::$containerInstance = $this->container;
    }

    /**
     * Initialize the theme.
     * Called from functions.php after construction.
     */
    public function bootstrap(): void
    {
        $this->initializeTimber();

        Timber::$dirname = $this->templateDirectories;

        $this->registerAll($this->providers);

        parent::__construct();
    }

    protected function buildContainer(): Container
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        foreach ($this->getContainerDefinitions() as $definitionFile) {
            if (file_exists($definitionFile)) {
                $builder->addDefinitions($definitionFile);
            }
        }

        return $builder->build();
    }

    /**
     * Get container definition file paths.
     *
     * PHP-DI autowiring handles most classes automatically. Override this method
     * to add explicit definitions when autowiring can't resolve a dependency.
     *
     * Example: Create src/Config/container.php returning an array:
     *   return [
     *       SomeInterface::class => \DI\autowire(ConcreteClass::class),
     *       'config.value' => 'some-value',
     *   ];
     *
     * Then add it here:
     *   return [get_template_directory() . '/src/Config/container.php'];
     *
     * @return string[]
     */
    protected function getContainerDefinitions(): array
    {
        return [];
    }

    /**
     * Static accessor for edge cases (Twig closures, static contexts).
     * Prefer constructor injection everywhere else.
     */
    public static function container(): Container
    {
        if (self::$containerInstance === null) {
            throw new \RuntimeException('Container not initialized.');
        }
        return self::$containerInstance;
    }

    /**
     * Reset the singleton instance for testing purposes.
     *
     * @internal For testing only. Do not use in production code.
     */
    public static function resetInstance(): void
    {
        self::$containerInstance = null;
    }

    /**
     * Initialize Timber framework.
     */
    protected function initializeTimber(): void
    {
        if (class_exists('Timber\Timber')) {
            Timber::init();
        }
    }

    /**
     * Register all classes that implement Registrable.
     *
     * After registration, appends any provider-level templates/ directories
     * to Timber::$dirname so Twig includes resolve automatically.
     *
     * @param array<class-string<Registrable>> $classes
     */
    protected function registerAll(array $classes): void
    {
        $templatePaths = [];
        $childRoot = get_stylesheet_directory() . '/';
        $parentRoot = get_template_directory() . '/';

        foreach ($classes as $class) {
            $provider = $this->container->get($class);
            $provider->register();

            foreach ($provider->getTemplateSearchPaths() as $path) {
                $relative = str_starts_with($path, $childRoot)
                    ? str_replace($childRoot, '', $path)
                    : str_replace($parentRoot, '', $path);
                if (!in_array($relative, $templatePaths, true)) {
                    $templatePaths[] = $relative;
                }
            }
        }

        if (!empty($templatePaths)) {
            Timber::$dirname = array_merge(Timber::$dirname, $templatePaths);
        }
    }
}
