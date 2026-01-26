<?php

namespace ParentTheme;

use ParentTheme\Contracts\Registrable;
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

    public function __construct()
    {
        Timber::$dirname = $this->templateDirectories;

        $this->registerAll($this->providers);

        parent::__construct();
    }

    /**
     * Register all classes that implement Registrable.
     *
     * @param array<class-string<Registrable>> $classes
     */
    protected function registerAll(array $classes): void
    {
        foreach ($classes as $class) {
            (new $class())->register();
        }
    }
}
