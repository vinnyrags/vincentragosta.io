<?php

namespace ChildTheme\Tests\Unit\Providers\Project;

use ChildTheme\Providers\Project\ProjectArchive;
use ChildTheme\Providers\Project\ProjectPost;
use ChildTheme\Tests\Mocks\MockProjectPost;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the projects archive view model.
 */
class ProjectArchiveTest extends TestCase
{
    private function project(int $id, string $year, string $client, array $categories, bool $caseStudy = false): MockProjectPost
    {
        $terms = array_map(
            static fn(array $c): object => (object) ['slug' => $c[0], 'name' => $c[1]],
            $categories
        );

        return MockProjectPost::create(['ID' => $id, 'post_name' => "p{$id}"])
            ->setMockMeta([
                ProjectPost::FIELD_YEAR       => $year,
                ProjectPost::FIELD_CLIENT     => $client,
                ProjectPost::FIELD_CASE_STUDY => $caseStudy ? '1' : '0',
            ])
            ->setMockTerms('category', $terms);
    }

    /**
     * @return MockProjectPost[]
     */
    private function fixture(): array
    {
        return [
            $this->project(1, '2026', 'ARTHOUSE Interactive', [['broadway', 'Broadway'], ['entertainment', 'Entertainment']], true),
            $this->project(2, '2026', 'arthouse interactive', [['arts-culture', 'Arts &amp; Culture']], true),
            $this->project(3, '2024', 'Situation Interactive', [['broadway', 'Broadway'], ['ticketing', 'Ticketing']]),
            $this->project(4, '2018', 'Situation Interactive', [['broadway', 'Broadway'], ['ticketing', 'Ticketing'], ['pokemon', 'Pokemon']]),
            $this->project(5, '2018', '', [['arts-culture', 'Arts &amp; Culture']]),
        ];
    }

    private function archive(): ProjectArchive
    {
        $projects = $this->fixture();

        return new ProjectArchive($projects, array_filter($projects, static fn($p) => $p->isCaseStudy()));
    }

    public function testStatsReportTotalsClientsAndEarliestYear(): void
    {
        $stats = array_column($this->archive()->stats(), 'value', 'label');

        $this->assertSame('5', $stats['Projects']);
        $this->assertSame('2', $stats['Case studies']);
        $this->assertSame('2', $stats['Clients'], 'Clients are counted case-insensitively and blanks are ignored');
        $this->assertSame('2018', $stats['Since']);
    }

    public function testCategoriesNeedTwoProjectsAndSortBySize(): void
    {
        $categories = $this->archive()->categories();

        $this->assertSame(['broadway', 'arts-culture', 'ticketing'], array_column($categories, 'slug'));
        $this->assertSame([3, 2, 2], array_column($categories, 'count'));
        $this->assertSame('Arts & Culture', $categories[1]['name'], 'Term names are entity-decoded');
    }

    public function testGroupsAreNewestYearFirst(): void
    {
        $groups = $this->archive()->groups();

        $this->assertSame(['2026', '2024', '2018'], array_column($groups, 'year'));
        $this->assertCount(2, $groups[0]['projects']);
    }

    public function testToArrayExposesEverythingTheTemplateUses(): void
    {
        $data = $this->archive()->toArray();

        $this->assertSame(['stats', 'categories', 'case_studies', 'personal', 'groups', 'total'], array_keys($data));
        $this->assertSame([], $data['personal']);
        $this->assertSame(5, $data['total']);
        $this->assertCount(2, $data['case_studies']);
    }

    public function testEmptyArchiveHasNoSinceStat(): void
    {
        $archive = new ProjectArchive([], []);

        $this->assertNotContains('Since', array_column($archive->stats(), 'label'));
        $this->assertSame([], $archive->categories());
        $this->assertSame([], $archive->groups());
    }

    public function testPersonalProjectsGetTheirOwnSection(): void
    {
        $projects   = $this->fixture();
        $projects[] = $this->project(6, '2026', 'itzenzoTTV (Independent)', [['personal', 'Personal'], ['brand', 'Brand']], true);
        $projects[] = $this->project(7, '2025', '', [['personal', 'Personal']]);
        $archive    = new ProjectArchive($projects, array_filter($projects, static fn($p) => $p->isCaseStudy()));

        $this->assertSame([6, 7], array_map(static fn($p) => $p->ID, $archive->personal()));
        $this->assertSame([1, 2], array_map(static fn($p) => $p->ID, $archive->caseStudies()), 'Personal case studies leave the featured section');

        $stats = array_column($archive->stats(), 'value', 'label');
        $this->assertSame('7', $stats['Projects'], 'Personal projects stay in the full index');
        $this->assertSame('2', $stats['Case studies']);
        $this->assertSame('2', $stats['Clients'], 'Personal projects are not clients');
        $this->assertContains('personal', array_column($archive->categories(), 'slug'));
    }

    public function testPersonalProjectsAreOrderedByMenuOrderThenArchiveOrder(): void
    {
        $first  = $this->project(8, '2026', '', [['personal', 'Personal']]);
        $second = $this->project(9, '2026', '', [['personal', 'Personal']]);
        $third  = $this->project(10, '2024', '', [['personal', 'Personal']]);
        $first->menu_order  = 2;
        $second->menu_order = 2;
        $third->menu_order  = 1;

        $archive = new ProjectArchive([$first, $second, $third], []);

        $this->assertSame([10, 8, 9], array_map(static fn($p) => $p->ID, $archive->personal()));
    }
}
