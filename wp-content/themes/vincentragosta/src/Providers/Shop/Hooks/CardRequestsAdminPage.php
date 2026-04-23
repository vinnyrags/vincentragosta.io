<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use Mythus\Contracts\Hook;

/**
 * WordPress admin page for reviewing and managing card view requests.
 *
 * Registered as a submenu under the itzenzo.tv options page. Uses the
 * wp_card_view_requests table directly — avoids making a CPT for
 * request rows since they're lightweight, high-volume, and never need
 * the post editor surface.
 */
class CardRequestsAdminPage implements Hook
{
    private const PAGE_SLUG = 'card-requests';
    private const PARENT_SLUG = 'shop-settings';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [$this, 'handleActions']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            self::PARENT_SLUG,
            __('Card Requests', 'vincentragosta'),
            __('Card Requests', 'vincentragosta'),
            'edit_posts',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function handleActions(): void
    {
        if (!isset($_GET['page'], $_GET['action'], $_GET['request_id'])) {
            return;
        }

        if ($_GET['page'] !== self::PAGE_SLUG) {
            return;
        }

        if (!current_user_can('edit_posts')) {
            return;
        }

        $id = (int) $_GET['request_id'];
        $action = sanitize_text_field((string) $_GET['action']);
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field((string) $_GET['_wpnonce']) : '';

        if (!wp_verify_nonce($nonce, 'card_request_' . $action . '_' . $id)) {
            return;
        }

        global $wpdb;
        $table = CardRequestsMigration::tableName();

        if ($action === 'shown') {
            $wpdb->update(
                $table,
                ['status' => 'shown', 'shown_at' => current_time('mysql')],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            );
        } elseif ($action === 'skip') {
            $wpdb->update(
                $table,
                ['status' => 'skipped'],
                ['id' => $id],
                ['%s'],
                ['%d']
            );
        } elseif ($action === 'reopen') {
            $wpdb->update(
                $table,
                ['status' => 'pending', 'shown_at' => null],
                ['id' => $id],
                ['%s', '%s'],
                ['%d']
            );
        }

        wp_safe_redirect(add_query_arg([
            'page'   => self::PAGE_SLUG,
            'status' => isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : 'pending',
        ], admin_url('admin.php')));
        exit;
    }

    public function render(): void
    {
        global $wpdb;
        $table = CardRequestsMigration::tableName();

        $status = isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : 'pending';
        $validStatus = in_array($status, ['pending', 'shown', 'skipped', 'all'], true) ? $status : 'pending';

        $sql = "SELECT r.*, p.post_title AS card_title, p.post_name AS card_slug FROM {$table} r LEFT JOIN {$wpdb->posts} p ON p.ID = r.card_post_id";
        $args = [];
        if ($validStatus !== 'all') {
            $sql .= ' WHERE r.status = %s';
            $args[] = $validStatus;
        }
        $sql .= ' ORDER BY r.requested_at DESC LIMIT 200';

        $rows = $args
            ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);

        $pendingCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'");
        $shownCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'shown'");
        $skippedCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'skipped'");
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Card View Requests', 'vincentragosta'); ?></h1>

            <ul class="subsubsub">
                <li><a href="<?php echo esc_url(add_query_arg(['page' => self::PAGE_SLUG, 'status' => 'pending'], admin_url('admin.php'))); ?>" class="<?php echo $validStatus === 'pending' ? 'current' : ''; ?>"><?php esc_html_e('Pending', 'vincentragosta'); ?> <span class="count">(<?php echo esc_html((string) $pendingCount); ?>)</span></a> |</li>
                <li><a href="<?php echo esc_url(add_query_arg(['page' => self::PAGE_SLUG, 'status' => 'shown'], admin_url('admin.php'))); ?>" class="<?php echo $validStatus === 'shown' ? 'current' : ''; ?>"><?php esc_html_e('Shown', 'vincentragosta'); ?> <span class="count">(<?php echo esc_html((string) $shownCount); ?>)</span></a> |</li>
                <li><a href="<?php echo esc_url(add_query_arg(['page' => self::PAGE_SLUG, 'status' => 'skipped'], admin_url('admin.php'))); ?>" class="<?php echo $validStatus === 'skipped' ? 'current' : ''; ?>"><?php esc_html_e('Skipped', 'vincentragosta'); ?> <span class="count">(<?php echo esc_html((string) $skippedCount); ?>)</span></a> |</li>
                <li><a href="<?php echo esc_url(add_query_arg(['page' => self::PAGE_SLUG, 'status' => 'all'], admin_url('admin.php'))); ?>" class="<?php echo $validStatus === 'all' ? 'current' : ''; ?>"><?php esc_html_e('All', 'vincentragosta'); ?></a></li>
            </ul>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Card', 'vincentragosta'); ?></th>
                        <th><?php esc_html_e('Email', 'vincentragosta'); ?></th>
                        <th><?php esc_html_e('Discord', 'vincentragosta'); ?></th>
                        <th><?php esc_html_e('Requested', 'vincentragosta'); ?></th>
                        <th><?php esc_html_e('Status', 'vincentragosta'); ?></th>
                        <th><?php esc_html_e('Actions', 'vincentragosta'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6"><?php esc_html_e('No requests.', 'vincentragosta'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $id = (int) $row['id'];
                        $cardId = (int) $row['card_post_id'];
                        $title = $row['card_title'] ?? sprintf('#%d', $cardId);
                        $editLink = $cardId ? get_edit_post_link($cardId) : '';
                        $rowStatus = (string) $row['status'];
                        ?>
                        <tr>
                            <td>
                                <?php if ($editLink): ?>
                                    <a href="<?php echo esc_url($editLink); ?>"><?php echo esc_html($title); ?></a>
                                <?php else: ?>
                                    <?php echo esc_html($title); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html((string) $row['requester_email']); ?></td>
                            <td><?php echo esc_html((string) ($row['discord_username'] ?? '')); ?></td>
                            <td><?php echo esc_html((string) $row['requested_at']); ?></td>
                            <td><?php echo esc_html($rowStatus); ?></td>
                            <td>
                                <?php if ($rowStatus === 'pending'): ?>
                                    <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['page' => self::PAGE_SLUG, 'action' => 'shown', 'request_id' => $id, 'status' => $validStatus], admin_url('admin.php')), 'card_request_shown_' . $id)); ?>"><?php esc_html_e('Mark Shown', 'vincentragosta'); ?></a>
                                    <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['page' => self::PAGE_SLUG, 'action' => 'skip', 'request_id' => $id, 'status' => $validStatus], admin_url('admin.php')), 'card_request_skip_' . $id)); ?>"><?php esc_html_e('Skip', 'vincentragosta'); ?></a>
                                <?php else: ?>
                                    <a class="button button-small" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['page' => self::PAGE_SLUG, 'action' => 'reopen', 'request_id' => $id, 'status' => $validStatus], admin_url('admin.php')), 'card_request_reopen_' . $id)); ?>"><?php esc_html_e('Reopen', 'vincentragosta'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
