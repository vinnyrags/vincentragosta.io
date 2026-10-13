<?php

namespace ChildTheme\Tests\Unit\Providers\Project;

use ChildTheme\Providers\Project\ProjectPost;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Structural test for the "Project Details" ACF field group — pins the JSON
 * to the ProjectPost::FIELD_* constants so a rename on either side fails CI
 * instead of silently rendering empty project metadata.
 */
class ProjectFieldsAcfTest extends TestCase
{
    private array $group;

    protected function setUp(): void
    {
        $path = __DIR__ . '/../../../../src/Providers/Project/acf-json/group_project_fields.json';
        $this->assertFileExists($path);
        $this->group = json_decode(file_get_contents($path), true);
        $this->assertIsArray($this->group);
    }

    public function testGroupTargetsProjectPostType(): void
    {
        $this->assertSame('group_project_fields', $this->group['key']);
        $this->assertSame(
            [[['param' => 'post_type', 'operator' => '==', 'value' => ProjectPost::POST_TYPE]]],
            $this->group['location']
        );
        $this->assertSame('projectFields', $this->group['graphql_field_name']);
    }

    public function testEveryModelFieldConstantHasAField(): void
    {
        $constants = array_filter(
            (new ReflectionClass(ProjectPost::class))->getConstants(),
            static fn(string $name): bool => str_starts_with($name, 'FIELD_'),
            ARRAY_FILTER_USE_KEY
        );

        $this->assertNotEmpty($constants);

        foreach ($constants as $constant => $name) {
            $this->assertNotNull($this->findField($name), "ProjectPost::{$constant} ('{$name}') has no field in the group");
        }

        $dataFields = array_filter($this->group['fields'], static fn(array $field): bool => $field['type'] !== 'tab');
        $this->assertCount(count($constants), $dataFields, 'Field group has fields with no ProjectPost accessor');
    }

    public function testSectionsRepeaterHasTheSubFieldsTheModelReads(): void
    {
        $field = $this->findField(ProjectPost::FIELD_SECTIONS);

        $this->assertSame('repeater', $field['type']);
        $this->assertSame(1, $field['min']);
        $this->assertSame(
            ['label', 'heading', 'body', 'note'],
            array_column($field['sub_fields'], 'name')
        );

        $body = $field['sub_fields'][2];
        $this->assertSame('wysiwyg', $body['type']);
        $this->assertSame(0, $body['media_upload'], 'Section bodies are text — no media in the case study body');
    }

    public function testBlockEditorIsHiddenForProjects(): void
    {
        $this->assertContains('the_content', $this->group['hide_on_screen']);
    }

    public function testYearIsARequiredNumberStoredUnderTheSortMetaKey(): void
    {
        $field = $this->findField(ProjectPost::FIELD_YEAR);

        $this->assertSame('project_year', $field['name'], 'ProjectRepository orders by this meta key');
        $this->assertSame('number', $field['type']);
        $this->assertSame(1, $field['required']);
    }

    public function testCaseStudyIsAToggleDefaultingOff(): void
    {
        $field = $this->findField(ProjectPost::FIELD_CASE_STUDY);

        $this->assertSame('true_false', $field['type']);
        $this->assertSame(0, $field['default_value']);
    }

    public function testFieldKeysAreUnique(): void
    {
        $keys = array_column($this->group['fields'], 'key');

        $this->assertSame($keys, array_unique($keys));
    }

    private function findField(string $name): ?array
    {
        foreach ($this->group['fields'] as $field) {
            if ($field['type'] !== 'tab' && $field['name'] === $name) {
                return $field;
            }
        }

        return null;
    }
}
