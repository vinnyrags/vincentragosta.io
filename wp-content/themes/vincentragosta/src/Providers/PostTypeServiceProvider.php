<?php

namespace ChildTheme\Providers;

/**
 * Registers custom post types from JSON configuration files.
 */
class PostTypeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        add_action('init', [$this, 'registerPostTypes']);
    }

    /**
     * Register custom post types from /config directory.
     */
    public function registerPostTypes(): void
    {
        $config_dir = get_template_directory() . '/config/';
        if (!is_dir($config_dir)) {
            return;
        }

        $json_files = glob($config_dir . '*.json');
        foreach ($json_files as $file) {
            $this->registerFromConfig($file);
        }
    }

    /**
     * Register a post type from a JSON config file.
     */
    private function registerFromConfig(string $file): void
    {
        $content = file_get_contents($file);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return;
        }

        if (!isset($data['post_type'], $data['args'])) {
            return;
        }

        register_post_type($data['post_type'], $data['args']);
    }
}
