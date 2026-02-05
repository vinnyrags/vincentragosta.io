<?php

namespace ParentTheme\Providers\PostType;

use ParentTheme\Providers\Provider;

/**
 * Registers custom post types from JSON configuration files.
 *
 * Reads JSON files from the child theme's /config directory and registers
 * post types based on their configuration.
 */
class PostTypeProvider extends Provider
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
        $config_dir = get_stylesheet_directory() . '/config/';
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
    protected function registerFromConfig(string $file): void
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
