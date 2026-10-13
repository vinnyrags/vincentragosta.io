<?php

namespace ChildTheme\Tests\Integration\Providers\Hooks;

use ChildTheme\Providers\Project\Hooks\ProjectSectionDefaults;
use Mythus\Contracts\Hook;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the ProjectSectionDefaults hook.
 */
class ProjectSectionDefaultsTest extends BaseTestCase
{
    private ProjectSectionDefaults $hook;

    public function set_up(): void
    {
        parent::set_up();
        $this->hook = new ProjectSectionDefaults();
    }

    public function testImplementsHook(): void
    {
        $this->assertInstanceOf(Hook::class, $this->hook);
    }

    public function testRegisterAddsFieldScopedLoadValueFilter(): void
    {
        $this->hook->register();

        $this->assertSame(
            10,
            has_filter('acf/load_value/key=field_project_sections', [$this->hook, 'prefillNewProject'])
        );
    }

    public function testPrefillsLabelsOnANewProject(): void
    {
        $postId = wp_insert_post(['post_type' => 'project', 'post_status' => 'auto-draft', 'post_title' => '']);

        $rows = $this->hook->prefillNewProject(null, $postId);

        $this->assertSame(
            ProjectSectionDefaults::DEFAULT_LABELS,
            array_column($rows, 'field_project_section_label')
        );
        $this->assertSame('', $rows[0]['field_project_section_body']);
    }

    public function testLeavesSavedProjectsAlone(): void
    {
        $postId = wp_insert_post(['post_type' => 'project', 'post_status' => 'publish', 'post_title' => 'Saved']);

        $this->assertNull($this->hook->prefillNewProject(null, $postId));
    }

    public function testNeverOverridesAnExistingValue(): void
    {
        $postId = wp_insert_post(['post_type' => 'project', 'post_status' => 'auto-draft', 'post_title' => '']);
        $existing = [['field_project_section_label' => 'Custom']];

        $this->assertSame($existing, $this->hook->prefillNewProject($existing, $postId));
    }

    public function testIgnoresNonPostAcfIds(): void
    {
        $this->assertSame('', $this->hook->prefillNewProject('', 'options'));
    }
}
