<?php

namespace ParentTheme;

use DI\Container;
use DI\ContainerBuilder;
use ParentTheme\Providers\Contracts\Registrable;
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
     */
    public function __construct()
    {
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
     * Child themes override this to add their own definitions.
     *
     * @return string[]
     */
    protected function getContainerDefinitions(): array
    {
        return [
            get_template_directory() . '/src/config/container.php',
        ];
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
     * @param array<class-string<Registrable>> $classes
     */
    protected function registerAll(array $classes): void
    {
        foreach ($classes as $class) {
            $provider = $this->container->get($class);
            $provider->register();
        }
    }
}
