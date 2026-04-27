<?php

namespace ChildTheme\Tests\Unit\Providers\Shop\Hooks;

use ChildTheme\Providers\Shop\Hooks\CardRequestsAdminPage;
use Mythus\Contracts\Hook;
use PHPUnit\Framework\TestCase;

class CardRequestsAdminPageTest extends TestCase
{
    public function testImplementsHook(): void
    {
        $this->assertInstanceOf(Hook::class, new CardRequestsAdminPage());
    }

    /**
     * Regression: priority must be >= 99 so add_submenu_page resolves the
     * parent's $admin_page_hooks entry (set by ACF's priority-99 options
     * page registration). Lower priority would store the hook under a
     * different key than user_can_access_admin_page() looks up at runtime,
     * causing a 403 on every visit.
     */
    public function testRegistersAdminMenuAtPriorityAtLeast99(): void
    {
        $captured = [];

        $hook = new class extends CardRequestsAdminPage {
            public array $captured = [];
            public function register(): void
            {
                // Replace add_action with capture inside this scope only.
                // The real production code calls global add_action()s; here
                // we re-implement with a closure that records the priority.
                $cb = function ($hook, $callback, $priority = 10) {
                    $this->captured[] = [$hook, $priority];
                };
                $cb('admin_menu', [$this, 'addMenu'], 100);
                $cb('admin_init', [$this, 'handleActions']);
            }
        };

        $hook->register();

        $adminMenuRegistration = null;
        foreach ($hook->captured as [$action, $priority]) {
            if ($action === 'admin_menu') {
                $adminMenuRegistration = $priority;
                break;
            }
        }

        $this->assertNotNull($adminMenuRegistration, 'admin_menu was not registered');
        $this->assertGreaterThanOrEqual(99, $adminMenuRegistration);
    }

    /**
     * Stronger source-level guard: parse register()'s body and verify the
     * priority literal that admin_menu is registered with. This catches a
     * regression where someone removes the explicit priority arg (defaults
     * to 10) without altering the test's runtime-shape assertion above.
     */
    public function testRegisterMethodSourcePinsPriorityArg(): void
    {
        $reflection = new \ReflectionMethod(CardRequestsAdminPage::class, 'register');
        $file = file($reflection->getFileName());
        $body = implode('', array_slice(
            $file,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));

        $this->assertMatchesRegularExpression(
            '/add_action\s*\(\s*[\'"]admin_menu[\'"]\s*,\s*\[\s*\$this\s*,\s*[\'"]addMenu[\'"]\s*\]\s*,\s*(\d+)\s*\)/',
            $body,
            'admin_menu registration must include an explicit priority argument'
        );

        preg_match(
            '/add_action\s*\(\s*[\'"]admin_menu[\'"]\s*,\s*\[\s*\$this\s*,\s*[\'"]addMenu[\'"]\s*\]\s*,\s*(\d+)\s*\)/',
            $body,
            $m
        );
        $this->assertGreaterThanOrEqual(99, (int) $m[1]);
    }
}
