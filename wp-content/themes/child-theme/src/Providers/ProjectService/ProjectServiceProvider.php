<?php

namespace ChildTheme\Providers\ProjectService;

use ParentTheme\Providers\ServiceProvider;

/**
 * Project Service Provider.
 *
 * Self-contained provider for all project-related functionality.
 * Reads configuration from its own /config directory.
 */
class ProjectServiceProvider extends ServiceProvider
{
    private string $configPath;
    private string $textDomain = 'child-theme';

    public function __construct()
    {
        $this->configPath = __DIR__ . '/config';
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
    }

    /**
     * Register the project post type.
     */
    public function registerPostType(): void
    {
        $config = $this->loadConfig('post-type.json');

        if (!$config || !isset($config['post_type'], $config['args'])) {
            return;
        }

        $args = $config['args'];

        if (isset($args['labels'])) {
            $args['labels'] = $this->translateLabels($args['labels']);
        }

        register_post_type($config['post_type'], $args);
    }

    /**
     * Translate an array of label strings.
     *
     * @param array<string, string> $labels
     * @return array<string, string>
     */
    protected function translateLabels(array $labels): array
    {
        $translated = [];

        foreach ($labels as $key => $label) {
            $translated[$key] = __($label, $this->textDomain);
        }

        return $translated;
    }

    /**
     * Load a JSON configuration file.
     *
     * @param string $filename The config file name
     * @return array|null The config array or null if not found/invalid
     */
    protected function loadConfig(string $filename): ?array
    {
        $filepath = $this->configPath . '/' . $filename;

        if (!file_exists($filepath)) {
            return null;
        }

        $content = file_get_contents($filepath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }
}
