<?php

declare(strict_types=1);

namespace ParentTheme\Providers;

use Mythus\Provider as MythusProvider;
use ReflectionClass;
use ReflectionMethod;
use Twig\Environment;

/**
 * Theme-aware provider bridge.
 *
 * Extends the framework-level Mythus Provider with Timber/Twig integration,
 * template path resolution, and WordPress theme text domain detection.
 * All theme providers (parent and child) extend this class.
 */
abstract class Provider extends MythusProvider
{
    protected ?string $templatesPath = null;

    /**
     * Register the service provider.
     *
     * Adds Twig filter registration on top of the base Mythus registration.
     */
    public function register(): void
    {
        parent::register();
        $this->maybeRegisterTwigFilter();
    }

    /**
     * Set up managers and detect the templates directory.
     */
    protected function setup(): void
    {
        if ($this->assets !== null) {
            return;
        }

        parent::setup();

        $reflection = new ReflectionClass($this);
        $providerDir = dirname($reflection->getFileName());

        $templatesDir = $providerDir . '/templates';
        $this->templatesPath = is_dir($templatesDir) ? $templatesDir : null;
    }

    /**
     * Resolve dist path using the active stylesheet directory.
     */
    protected function getDistPath(): string
    {
        return get_stylesheet_directory() . '/dist';
    }

    /**
     * Resolve dist URI using the active stylesheet directory.
     */
    protected function getDistUri(): string
    {
        return get_stylesheet_directory_uri() . '/dist';
    }

    /**
     * Detect text domain based on whether the provider lives in the
     * child theme (stylesheet) or parent theme (template) directory.
     */
    protected function resolveTextDomain(string $providerDir): string
    {
        return str_starts_with($providerDir, get_stylesheet_directory())
            ? (get_stylesheet() ?: 'theme')
            : (get_template() ?: 'theme');
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
     * Get the absolute path to this provider's templates directory.
     *
     * Returns null if the provider has no templates/ directory.
     */
    public function getTemplatePath(): ?string
    {
        $this->setup();
        return $this->templatesPath;
    }

    /**
     * Get template directories from the full class hierarchy.
     *
     * Walks from the concrete class up toward Provider, collecting
     * templates/ directories at each level. Child paths come first
     * so Twig resolves overrides before parent defaults.
     *
     * @return array<string> Absolute paths to templates directories.
     */
    public function getTemplateSearchPaths(): array
    {
        $paths = [];
        $seen = [];
        $class = new ReflectionClass($this);

        while ($class && $class->getName() !== self::class) {
            $dir = dirname($class->getFileName());

            if (!isset($seen[$dir])) {
                $seen[$dir] = true;
                $templatesPath = $dir . '/templates';

                if (is_dir($templatesPath)) {
                    $paths[] = $templatesPath;
                }
            }

            $class = $class->getParentClass();
        }

        return $paths;
    }
}
