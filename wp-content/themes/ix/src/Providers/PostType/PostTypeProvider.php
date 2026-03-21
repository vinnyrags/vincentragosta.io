<?php

declare(strict_types=1);

namespace IX\Providers\PostType;

use IX\Providers\Provider;

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

        parent::register();
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
        if ($json_files === false) {
            return;
        }

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

        if ($content === false) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('PostTypeProvider: Could not read config file: %s', $file));
            return;
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf(
                'PostTypeProvider: Invalid JSON in config file %s: %s',
                $file,
                json_last_error_msg()
            ));
            return;
        }

        if (!isset($data['post_type'], $data['args'])) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log(sprintf('PostTypeProvider: Missing post_type or args in config file: %s', $file));
            return;
        }

        $args = $data['args'];

        if (isset($args['labels'])) {
            $args['labels'] = $this->translateLabels($args['labels']);
        }

        register_post_type($data['post_type'], $args);

        if (!empty($data['classic_editor'])) {
            $postType = $data['post_type'];
            add_filter('use_block_editor_for_post_type', static function (bool $use, string $type) use ($postType): bool {
                return $type === $postType ? false : $use;
            }, 10, 2);
        }
    }
}
