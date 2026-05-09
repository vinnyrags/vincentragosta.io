<?php

declare(strict_types=1);

namespace ChildTheme\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Support\PullBoxRepository;
use Mythus\Contracts\Hook;

/**
 * Renders a "Reset Pull Box" button on the itzenzo.tv → Settings page,
 * just below the `pb_total_slots` ACF field on the Pull Box & Bundle tab.
 *
 * One click closes the active pull box and opens a fresh one with the
 * configured defaults — same path as the Discord `/pull reset` command,
 * just driven from WP admin instead. Useful when the chase prize hits
 * mid-stream and the operator has admin already open.
 *
 * Form posts to admin-post.php with a nonce; the handler runs the
 * reset and redirects back to the settings page with a success notice.
 */
class PullBoxAdminReset implements Hook
{
    private const ACTION = 'reset_pull_box';
    private const NONCE_NAME = 'reset_pull_box_nonce';

    public function __construct(private readonly PullBoxRepository $repository)
    {
    }

    public function register(): void
    {
        add_action('acf/render_field/key=field_pb_total_slots', [$this, 'renderResetButton']);
        add_action('admin-post_' . self::ACTION, [$this, 'handleResetPost']);
        add_action('admin_notices', [$this, 'renderAdminNotice']);
    }

    /**
     * ACF fires this immediately after rendering the pb_total_slots field.
     * We append a small form below it with a Reset button.
     */
    public function renderResetButton(): void
    {
        $url = admin_url('admin-post.php');
        $nonce = wp_create_nonce(self::ACTION);
        $current = $this->repository->findActiveBox();

        $statusLine = '';
        if ($current) {
            $totalSlots = (int) $current['total_slots'];
            $claimed = count($this->repository->getClaimedSlotNumbers((int) $current['id']));
            $statusLine = sprintf(
                'Active box: <strong>%s</strong> (#%d) — %d/%d slots claimed.',
                esc_html((string) $current['name']),
                (int) $current['id'],
                $claimed,
                $totalSlots,
            );
        } else {
            $statusLine = '<em>No active box. The next visitor to the homepage modal will auto-create one with the defaults above.</em>';
        }

        ?>
        <div style="margin-top: 12px; padding: 12px; background: #f6f7f7; border-left: 4px solid #2271b1;">
            <p style="margin: 0 0 8px 0;"><?php echo $statusLine; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
            <form method="post" action="<?php echo esc_url($url); ?>" style="display: inline;">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                <input type="hidden" name="<?php echo esc_attr(self::NONCE_NAME); ?>" value="<?php echo esc_attr($nonce); ?>">
                <button type="submit" class="button button-secondary"
                        onclick="return confirm('Reset the active pull box? This closes the current box and opens a fresh one with the configured number of slots. Use this when the chase prize hits.');">
                    🎰 Reset Pull Box (chase hit, start new batch)
                </button>
            </form>
        </div>
        <?php
    }

    /**
     * Handler for the reset form post. Runs the same reset path the
     * REST endpoint uses, then redirects back to the settings page
     * with a transient flag so we can render a follow-up admin notice.
     */
    public function handleResetPost(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized.', 'Reset Pull Box', ['response' => 403]);
        }

        $nonce = $_POST[self::NONCE_NAME] ?? '';
        if (!is_string($nonce) || !wp_verify_nonce($nonce, self::ACTION)) {
            wp_die('Invalid nonce.', 'Reset Pull Box', ['response' => 403]);
        }

        $newBox = $this->repository->resetActiveBox();
        if ($newBox) {
            set_transient(
                'pull_box_reset_notice_' . get_current_user_id(),
                sprintf('Pull box reset — new box #%d ready with %d fresh slots.', (int) $newBox['id'], (int) $newBox['total_slots']),
                30
            );
        } else {
            set_transient(
                'pull_box_reset_error_' . get_current_user_id(),
                'Reset failed — pb_price_id is not configured. Set it on the Pull Box & Bundle tab and try again.',
                30
            );
        }

        $referer = wp_get_referer() ?: admin_url();
        wp_safe_redirect($referer);
        exit;
    }

    /**
     * Surfaces the success/failure of a reset action as a one-shot
     * admin notice keyed on the current user's transient.
     */
    public function renderAdminNotice(): void
    {
        $userId = get_current_user_id();
        if (!$userId) {
            return;
        }
        $success = get_transient('pull_box_reset_notice_' . $userId);
        if ($success) {
            delete_transient('pull_box_reset_notice_' . $userId);
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html((string) $success)
            );
            return;
        }
        $error = get_transient('pull_box_reset_error_' . $userId);
        if ($error) {
            delete_transient('pull_box_reset_error_' . $userId);
            printf(
                '<div class="notice notice-error is-dismissible"><p>%s</p></div>',
                esc_html((string) $error)
            );
        }
    }
}
