<?php

namespace ChildTheme\Tests\Unit\Providers\Project;

use ChildTheme\Providers\Project\ProjectPost;
use ChildTheme\Tests\Mocks\MockProjectPost;
use IX\Providers\Project\ProjectPost as BaseProjectPost;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the child ProjectPost model.
 */
class ProjectPostTest extends TestCase
{
    /**
     * Test that POST_TYPE constant is set to 'project'.
     */
    public function testPostTypeConstant(): void
    {
        $this->assertEquals('project', ProjectPost::POST_TYPE);
    }

    /**
     * Test that ProjectPost extends the parent ProjectPost.
     */
    public function testExtendsParentProjectPost(): void
    {
        $this->assertTrue(is_subclass_of(ProjectPost::class, BaseProjectPost::class));
    }

    /**
     * Test that sortYear method exists and has correct signature.
     */
    public function testSortYearMethodSignature(): void
    {
        $reflection = new \ReflectionClass(ProjectPost::class);
        $method = $reflection->getMethod('sortYear');

        $this->assertTrue($method->isPublic());
        $this->assertEquals('string', (string) $method->getReturnType());
    }

    private function project(array $fields): MockProjectPost
    {
        return MockProjectPost::create()->setMockMeta($fields);
    }

    public function testScalarAccessorsTrimValues(): void
    {
        $post = $this->project([
            ProjectPost::FIELD_CLIENT       => '  ARTHOUSE Interactive ',
            ProjectPost::FIELD_ROLE         => 'Design + full-stack',
            ProjectPost::FIELD_LIVE_URL     => 'https://celebrityautobiography.com ',
            ProjectPost::FIELD_STATUS_LABEL => ' Live · Off-Broadway',
        ]);

        $this->assertSame('ARTHOUSE Interactive', $post->client());
        $this->assertSame('Design + full-stack', $post->role());
        $this->assertSame('https://celebrityautobiography.com', $post->liveUrl());
        $this->assertSame('Live · Off-Broadway', $post->statusLabel());
    }

    public function testAccessorsReturnEmptyDefaultsWhenUnset(): void
    {
        $post = $this->project([]);

        $this->assertSame('', $post->client());
        $this->assertSame('', $post->role());
        $this->assertSame('', $post->liveUrl());
        $this->assertSame('', $post->statusLabel());
        $this->assertSame([], $post->technologies());
        $this->assertSame('', $post->stackSummary());
        $this->assertNull($post->year());
        $this->assertFalse($post->isCaseStudy());
    }

    /**
     * @dataProvider yearProvider
     */
    public function testYearAcceptsOnlyPlausibleYears(mixed $raw, ?int $expected): void
    {
        $this->assertSame($expected, $this->project([ProjectPost::FIELD_YEAR => $raw])->year());
    }

    /**
     * @return array<string, array{mixed, ?int}>
     */
    public static function yearProvider(): array
    {
        return [
            'int'          => [2026, 2026],
            'numeric text' => ['2018', 2018],
            'zero'         => [0, null],
            'empty'        => ['', null],
            'too old'      => ['1066', null],
            'garbage'      => ['twenty', null],
        ];
    }

    public function testSortYearPrefersProjectYear(): void
    {
        $this->assertSame('2021', $this->project([ProjectPost::FIELD_YEAR => '2021'])->sortYear());
    }

    public function testTechnologiesSplitsTrimsAndDeduplicates(): void
    {
        $post = $this->project([
            ProjectPost::FIELD_TECHNOLOGIES => 'WordPress,  Mythus , IX,, wordpress, Timber/Twig ,PHP   8.4',
        ]);

        $this->assertSame(['WordPress', 'Mythus', 'IX', 'Timber/Twig', 'PHP 8.4'], $post->technologies());
    }

    public function testStackSummaryPrefersExplicitValue(): void
    {
        $post = $this->project([
            ProjectPost::FIELD_STACK_SUMMARY => 'Mythus · IX · PHP 8.4',
            ProjectPost::FIELD_TECHNOLOGIES  => 'A, B, C, D',
        ]);

        $this->assertSame('Mythus · IX · PHP 8.4', $post->stackSummary());
    }

    public function testStackSummaryFallsBackToFirstThreeTechnologies(): void
    {
        $post = $this->project([ProjectPost::FIELD_TECHNOLOGIES => 'Next.js, React, TypeScript, Tailwind CSS']);

        $this->assertSame('Next.js · React · TypeScript', $post->stackSummary());
    }

    /**
     * @dataProvider caseStudyProvider
     */
    public function testIsCaseStudyCastsAcfTrueFalse(mixed $raw, bool $expected): void
    {
        $this->assertSame($expected, $this->project([ProjectPost::FIELD_CASE_STUDY => $raw])->isCaseStudy());
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function caseStudyProvider(): array
    {
        return [
            'acf true'   => [true, true],
            'meta "1"'   => ['1', true],
            'meta "0"'   => ['0', false],
            'unset'      => ['', false],
        ];
    }

    public function testLeadAndNoteAreTrimmed(): void
    {
        $post = $this->project([
            ProjectPost::FIELD_LEAD      => "<p>A single long-scroll landing page.</p>\n",
            ProjectPost::FIELD_LEAD_NOTE => ' Design and full-stack development. ',
        ]);

        $this->assertSame('<p>A single long-scroll landing page.</p>', $post->lead());
        $this->assertSame('Design and full-stack development.', $post->leadNote());
    }

    public function testSectionsAssignIdsAndKeepOrder(): void
    {
        $post = $this->project([ProjectPost::FIELD_SECTIONS => [
            ['label' => 'Background', 'heading' => 'Why', 'body' => '<p>One</p>', 'note' => 'Takeaway'],
            ['label' => 'Implementation', 'heading' => 'How', 'body' => '<p>Two</p>'],
            ['label' => 'Results', 'heading' => 'What', 'body' => '<p>Three</p>', 'note' => ''],
        ]]);

        $sections = $post->sections();

        $this->assertSame(['background', 'implementation', 'results'], array_column($sections, 'id'));
        $this->assertSame('Takeaway', $sections[0]['note']);
        $this->assertSame('', $sections[1]['note']);
    }

    public function testPlainTextFieldsGetSmartQuotes(): void
    {
        $post = $this->project([
            ProjectPost::FIELD_LEAD_NOTE => "The show's lead",
            ProjectPost::FIELD_SECTIONS  => [
                ['label' => 'Background', 'heading' => "Broadway's biggest", 'body' => '<p>One</p>', 'note' => "The brand's launch"],
            ],
        ]);

        $section = $post->sections()[0];

        $this->assertSame('The show&#8217;s lead', $post->leadNote());
        $this->assertSame('Broadway&#8217;s biggest', $section['heading']);
        $this->assertSame('The brand&#8217;s launch', $section['note']);
    }

    public function testSectionsDeduplicateIdsAndSkipIncompleteRows(): void
    {
        $post = $this->project([ProjectPost::FIELD_SECTIONS => [
            ['label' => 'Results', 'heading' => 'A', 'body' => '<p>a</p>'],
            ['label' => 'Results', 'heading' => 'B', 'body' => '<p>b</p>'],
            ['label' => 'Draft', 'heading' => '', 'body' => '<p>c</p>'],
            ['label' => '', 'heading' => 'D', 'body' => '<p>d</p>'],
            'not-a-row',
            ['label' => '!!!', 'heading' => 'E', 'body' => '<p>e</p>'],
        ]]);

        $this->assertSame(['results', 'results-2', 'section'], array_column($post->sections(), 'id'));
    }

    public function testSectionsEmptyWhenUnset(): void
    {
        $this->assertSame([], $this->project([])->sections());
    }

    public function testLiveHostStripsSchemeAndWww(): void
    {
        $this->assertSame('wickedthemusical.com', $this->project([ProjectPost::FIELD_LIVE_URL => 'https://www.wickedthemusical.com/tickets'])->liveHost());
        $this->assertSame('', $this->project([])->liveHost());
    }
}
