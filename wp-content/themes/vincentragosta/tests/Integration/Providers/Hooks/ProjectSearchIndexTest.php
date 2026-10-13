<?php

namespace ChildTheme\Tests\Integration\Providers\Hooks;

use ChildTheme\Providers\Project\Hooks\ProjectSearchIndex;
use WorDBless\BaseTestCase;

/**
 * Integration tests for the ProjectSearchIndex hook.
 */
class ProjectSearchIndexTest extends BaseTestCase
{
    private ProjectSearchIndex $hook;

    public function set_up(): void
    {
        parent::set_up();
        $this->hook = new ProjectSearchIndex();
    }

    public function testRegisterAddsRelevanssiFilter(): void
    {
        $this->hook->register();

        $this->assertSame(10, has_filter('relevanssi_index_custom_fields', [$this->hook, 'addProjectFields']));
    }

    public function testMetaKeysCoverTextFieldsAndEachSectionRow(): void
    {
        $keys = ProjectSearchIndex::metaKeys(2);

        $this->assertContains('project_lead', $keys);
        $this->assertContains('project_technologies', $keys);
        $this->assertContains('project_sections_0_heading', $keys);
        $this->assertContains('project_sections_1_note', $keys);
        $this->assertNotContains('project_sections_2_body', $keys);
        $this->assertNotContains('project_live_url', $keys);
        $this->assertNotContains('project_year', $keys);
    }

    public function testMetaKeysWithoutSectionsAreJustTextFields(): void
    {
        $this->assertSame(ProjectSearchIndex::TEXT_FIELDS, ProjectSearchIndex::metaKeys(0));
    }

    public function testAddsProjectFieldsForProjects(): void
    {
        $postId = wp_insert_post(['post_type' => 'project', 'post_status' => 'publish', 'post_title' => 'Indexed']);
        update_post_meta($postId, 'project_sections', 1);

        $fields = $this->hook->addProjectFields(['existing_field'], $postId);

        $this->assertContains('existing_field', $fields);
        $this->assertContains('project_lead', $fields);
        $this->assertContains('project_sections_0_body', $fields);
        $this->assertNotContains('project_live_url', $fields);
    }

    public function testLeavesOtherPostTypesUnchanged(): void
    {
        $postId = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Page']);
        update_post_meta($postId, 'project_lead', 'Not a project');

        $this->assertSame(['existing_field'], $this->hook->addProjectFields(['existing_field'], $postId));
    }
}
