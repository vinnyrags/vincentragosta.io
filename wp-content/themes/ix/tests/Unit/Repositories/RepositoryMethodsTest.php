<?php

namespace IX\Tests\Unit\Repositories;

use IX\Tests\Mocks\MockPost;
use IX\Tests\Mocks\MockRepository;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Repository methods using MockRepository.
 */
class RepositoryMethodsTest extends TestCase
{
    private MockRepository $repository;
    private array $mockPosts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new MockRepository();

        // Create test posts
        $this->mockPosts = [
            MockPost::create([
                'ID' => 1,
                'post_title' => 'First Post',
                'post_name' => 'first-post',
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_date' => '2024-01-01 10:00:00',
                'post_author' => 1,
            ])->setMockMeta(['featured' => '1']),

            MockPost::create([
                'ID' => 2,
                'post_title' => 'Second Post',
                'post_name' => 'second-post',
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_date' => '2024-01-15 10:00:00',
                'post_author' => 1,
            ]),

            MockPost::create([
                'ID' => 3,
                'post_title' => 'Third Post',
                'post_name' => 'third-post',
                'post_status' => 'publish',
                'post_type' => 'post',
                'post_date' => '2024-02-01 10:00:00',
                'post_author' => 2,
            ]),

            MockPost::create([
                'ID' => 4,
                'post_title' => 'Draft Post',
                'post_name' => 'draft-post',
                'post_status' => 'draft',
                'post_type' => 'post',
                'post_date' => '2024-02-15 10:00:00',
                'post_author' => 1,
            ]),

            MockPost::create([
                'ID' => 5,
                'post_title' => 'Page Content',
                'post_name' => 'page-content',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_date' => '2024-01-10 10:00:00',
                'post_author' => 1,
            ]),
        ];

        $this->repository->setMockPosts($this->mockPosts);
    }

    // ===================
    // find() tests
    // ===================

    /**
     * Test find() returns post by ID.
     */
    public function testFindReturnsPostById(): void
    {
        $post = $this->repository->find(1);

        $this->assertNotNull($post);
        $this->assertEquals(1, $post->ID);
        $this->assertEquals('First Post', $post->post_title);
    }

    /**
     * Test find() returns null for non-existent ID.
     */
    public function testFindReturnsNullForNonExistentId(): void
    {
        $post = $this->repository->find(999);

        $this->assertNull($post);
    }

    /**
     * Test find() returns null for wrong post type.
     */
    public function testFindReturnsNullForWrongPostType(): void
    {
        // Post ID 5 is a 'page', not a 'post'
        $post = $this->repository->find(5);

        $this->assertNull($post);
    }

    // ===================
    // findBySlug() tests
    // ===================

    /**
     * Test findBySlug() returns post by slug.
     */
    public function testFindBySlugReturnsPost(): void
    {
        $post = $this->repository->findBySlug('second-post');

        $this->assertNotNull($post);
        $this->assertEquals(2, $post->ID);
        $this->assertEquals('Second Post', $post->post_title);
    }

    /**
     * Test findBySlug() returns null for non-existent slug.
     */
    public function testFindBySlugReturnsNullForNonExistentSlug(): void
    {
        $post = $this->repository->findBySlug('does-not-exist');

        $this->assertNull($post);
    }

    // ===================
    // findOne() tests
    // ===================

    /**
     * Test findOne() returns single post.
     */
    public function testFindOneReturnsSinglePost(): void
    {
        $post = $this->repository->findOne(['orderby' => 'date', 'order' => 'ASC']);

        $this->assertNotNull($post);
        $this->assertEquals('First Post', $post->post_title);
    }

    /**
     * Test findOne() returns null when no match.
     */
    public function testFindOneReturnsNullWhenNoMatch(): void
    {
        $post = $this->repository->findOne(['name' => 'nonexistent']);

        $this->assertNull($post);
    }

    // ===================
    // all() tests
    // ===================

    /**
     * Test all() returns all published posts of correct type.
     */
    public function testAllReturnsPublishedPosts(): void
    {
        $posts = $this->repository->all();

        // Should return 3 published posts (not the draft, not the page)
        $this->assertCount(3, $posts);
        foreach ($posts as $post) {
            $this->assertEquals('publish', $post->post_status);
            $this->assertEquals('post', $post->post_type);
        }
    }

    /**
     * Test all() respects limit.
     */
    public function testAllRespectsLimit(): void
    {
        $posts = $this->repository->all(2);

        $this->assertCount(2, $posts);
    }

    /**
     * Test all() with limit of 1.
     */
    public function testAllWithLimitOfOne(): void
    {
        $posts = $this->repository->all(1);

        $this->assertCount(1, $posts);
    }

    // ===================
    // latest() tests
    // ===================

    /**
     * Test latest() returns posts ordered by date descending.
     */
    public function testLatestReturnsPostsOrderedByDateDesc(): void
    {
        $posts = $this->repository->latest(3);

        $this->assertCount(3, $posts);
        $this->assertEquals('Third Post', $posts[0]->post_title);
        $this->assertEquals('Second Post', $posts[1]->post_title);
        $this->assertEquals('First Post', $posts[2]->post_title);
    }

    /**
     * Test latest() respects limit.
     */
    public function testLatestRespectsLimit(): void
    {
        $posts = $this->repository->latest(2);

        $this->assertCount(2, $posts);
        $this->assertEquals('Third Post', $posts[0]->post_title);
        $this->assertEquals('Second Post', $posts[1]->post_title);
    }

    // ===================
    // findMany() tests
    // ===================

    /**
     * Test findMany() returns posts by IDs.
     */
    public function testFindManyReturnsPostsByIds(): void
    {
        $posts = $this->repository->findMany([1, 3]);

        $this->assertCount(2, $posts);
    }

    /**
     * Test findMany() preserves order.
     */
    public function testFindManyPreservesOrder(): void
    {
        $posts = $this->repository->findMany([3, 1, 2]);

        $this->assertCount(3, $posts);
        $this->assertEquals(3, $posts[0]->ID);
        $this->assertEquals(1, $posts[1]->ID);
        $this->assertEquals(2, $posts[2]->ID);
    }

    /**
     * Test findMany() returns empty array for empty IDs.
     */
    public function testFindManyReturnsEmptyArrayForEmptyIds(): void
    {
        $posts = $this->repository->findMany([]);

        $this->assertIsArray($posts);
        $this->assertEmpty($posts);
    }

    /**
     * Test findMany() ignores non-existent IDs.
     */
    public function testFindManyIgnoresNonExistentIds(): void
    {
        $posts = $this->repository->findMany([1, 999, 2]);

        $this->assertCount(2, $posts);
    }

    // ===================
    // byAuthor() tests
    // ===================

    /**
     * Test byAuthor() returns posts by author ID.
     */
    public function testByAuthorReturnsPostsByAuthorId(): void
    {
        $posts = $this->repository->byAuthor(1);

        // Author 1 has posts 1, 2 (published) - post 4 is draft
        $this->assertCount(2, $posts);
        foreach ($posts as $post) {
            $this->assertEquals(1, $post->post_author);
        }
    }

    /**
     * Test byAuthor() respects limit.
     */
    public function testByAuthorRespectsLimit(): void
    {
        $posts = $this->repository->byAuthor(1, 1);

        $this->assertCount(1, $posts);
    }

    /**
     * Test byAuthor() returns empty for author with no posts.
     */
    public function testByAuthorReturnsEmptyForAuthorWithNoPosts(): void
    {
        $posts = $this->repository->byAuthor(999);

        $this->assertEmpty($posts);
    }

    // ===================
    // drafts() tests
    // ===================

    /**
     * Test drafts() returns draft posts.
     */
    public function testDraftsReturnsDraftPosts(): void
    {
        $posts = $this->repository->drafts();

        $this->assertCount(1, $posts);
        $this->assertEquals('Draft Post', $posts[0]->post_title);
        $this->assertEquals('draft', $posts[0]->post_status);
    }

    /**
     * Test drafts() respects limit.
     */
    public function testDraftsRespectsLimit(): void
    {
        // Add another draft
        $this->mockPosts[] = MockPost::create([
            'ID' => 6,
            'post_title' => 'Another Draft',
            'post_status' => 'draft',
            'post_type' => 'post',
        ]);
        $this->repository->setMockPosts($this->mockPosts);

        $posts = $this->repository->drafts(1);

        $this->assertCount(1, $posts);
    }

    // ===================
    // whereMetaEquals() tests
    // ===================

    /**
     * Test whereMetaEquals() filters by meta value.
     */
    public function testWhereMetaEqualsFiltersByMetaValue(): void
    {
        $posts = $this->repository->whereMetaEquals('featured', '1');

        $this->assertCount(1, $posts);
        $this->assertEquals('First Post', $posts[0]->post_title);
    }

    /**
     * Test whereMetaEquals() returns empty when no match.
     */
    public function testWhereMetaEqualsReturnsEmptyWhenNoMatch(): void
    {
        $posts = $this->repository->whereMetaEquals('nonexistent', 'value');

        $this->assertEmpty($posts);
    }

    /**
     * Test whereMetaEquals() respects limit.
     */
    public function testWhereMetaEqualsRespectsLimit(): void
    {
        // Add another featured post
        $this->mockPosts[] = MockPost::create([
            'ID' => 6,
            'post_title' => 'Another Featured',
            'post_status' => 'publish',
            'post_type' => 'post',
        ])->setMockMeta(['featured' => '1']);
        $this->repository->setMockPosts($this->mockPosts);

        $posts = $this->repository->whereMetaEquals('featured', '1', 1);

        $this->assertCount(1, $posts);
    }

    // ===================
    // count() tests
    // ===================

    /**
     * Test count() returns correct count.
     */
    public function testCountReturnsCorrectCount(): void
    {
        $count = $this->repository->count();

        $this->assertEquals(3, $count); // 3 published posts
    }

    /**
     * Test count() with custom args.
     */
    public function testCountWithCustomArgs(): void
    {
        $count = $this->repository->count(['post_status' => 'draft']);

        $this->assertEquals(1, $count);
    }

    // ===================
    // exists() tests
    // ===================

    /**
     * Test exists() returns true when posts exist.
     */
    public function testExistsReturnsTrueWhenPostsExist(): void
    {
        $exists = $this->repository->exists();

        $this->assertTrue($exists);
    }

    /**
     * Test exists() returns false when no posts match.
     */
    public function testExistsReturnsFalseWhenNoPostsMatch(): void
    {
        $exists = $this->repository->exists(['name' => 'does-not-exist']);

        $this->assertFalse($exists);
    }

    /**
     * Test exists() with specific criteria.
     */
    public function testExistsWithSpecificCriteria(): void
    {
        $exists = $this->repository->exists(['name' => 'first-post']);

        $this->assertTrue($exists);
    }

    // ===================
    // query() tests
    // ===================

    /**
     * Test query() with custom args.
     */
    public function testQueryWithCustomArgs(): void
    {
        $posts = $this->repository->query([
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $this->assertCount(3, $posts);
        $this->assertEquals('First Post', $posts[0]->post_title);
        $this->assertEquals('Second Post', $posts[1]->post_title);
        $this->assertEquals('Third Post', $posts[2]->post_title);
    }

    /**
     * Test query() with descending title order.
     */
    public function testQueryWithDescendingTitleOrder(): void
    {
        $posts = $this->repository->query([
            'orderby' => 'title',
            'order' => 'DESC',
        ]);

        $this->assertCount(3, $posts);
        $this->assertEquals('Third Post', $posts[0]->post_title);
        $this->assertEquals('Second Post', $posts[1]->post_title);
        $this->assertEquals('First Post', $posts[2]->post_title);
    }

    /**
     * Test query() stores last query args.
     */
    public function testQueryStoresLastQueryArgs(): void
    {
        $this->repository->query(['posts_per_page' => 5]);

        $args = $this->repository->getLastQueryArgs();

        $this->assertEquals(5, $args['posts_per_page']);
        $this->assertEquals('publish', $args['post_status']);
        $this->assertEquals('post', $args['post_type']);
    }

    // ===================
    // buildArgs() tests
    // ===================

    /**
     * Test buildArgs() includes defaults.
     */
    public function testBuildArgsIncludesDefaults(): void
    {
        $args = $this->repository->exposeBuildArgs([]);

        $this->assertEquals('publish', $args['post_status']);
        $this->assertEquals('post', $args['post_type']);
    }

    /**
     * Test buildArgs() merges custom args.
     */
    public function testBuildArgsMergesCustomArgs(): void
    {
        $args = $this->repository->exposeBuildArgs([
            'posts_per_page' => 10,
            'orderby' => 'title',
        ]);

        $this->assertEquals(10, $args['posts_per_page']);
        $this->assertEquals('title', $args['orderby']);
        $this->assertEquals('publish', $args['post_status']);
    }

    /**
     * Test buildArgs() allows overriding defaults.
     */
    public function testBuildArgsAllowsOverridingDefaults(): void
    {
        $args = $this->repository->exposeBuildArgs([
            'post_status' => 'draft',
        ]);

        $this->assertEquals('draft', $args['post_status']);
    }

    // ===================
    // postType() tests
    // ===================

    /**
     * Test postType() returns model's POST_TYPE.
     */
    public function testPostTypeReturnsModelPostType(): void
    {
        $postType = $this->repository->exposePostType();

        $this->assertEquals('post', $postType);
    }

    // ===================
    // whereTerm() tests
    // ===================

    /**
     * Test whereTerm() filters by taxonomy term slug.
     */
    public function testWhereTermFiltersByTermSlug(): void
    {
        // Set up posts with terms
        $this->mockPosts[0]->setMockTerms('category', ['news', 'featured']);
        $this->mockPosts[1]->setMockTerms('category', ['sports']);
        $this->mockPosts[2]->setMockTerms('category', ['news']);
        $this->repository->setMockPosts($this->mockPosts);

        $posts = $this->repository->whereTerm('category', 'news');

        $this->assertCount(2, $posts);
    }

    /**
     * Test whereTerm() filters by term ID.
     */
    public function testWhereTermFiltersByTermId(): void
    {
        $this->mockPosts[0]->setMockTerms('category', [1, 5]);
        $this->mockPosts[1]->setMockTerms('category', [2]);
        $this->mockPosts[2]->setMockTerms('category', [1]);
        $this->repository->setMockPosts($this->mockPosts);

        $posts = $this->repository->whereTerm('category', 1);

        $this->assertCount(2, $posts);
    }

    /**
     * Test whereTerm() respects limit.
     */
    public function testWhereTermRespectsLimit(): void
    {
        $this->mockPosts[0]->setMockTerms('category', ['news']);
        $this->mockPosts[1]->setMockTerms('category', ['news']);
        $this->mockPosts[2]->setMockTerms('category', ['news']);
        $this->repository->setMockPosts($this->mockPosts);

        $posts = $this->repository->whereTerm('category', 'news', 2);

        $this->assertCount(2, $posts);
    }

    /**
     * Test whereTerm() returns empty when no match.
     */
    public function testWhereTermReturnsEmptyWhenNoMatch(): void
    {
        $this->mockPosts[0]->setMockTerms('category', ['news']);
        $this->repository->setMockPosts($this->mockPosts);

        $posts = $this->repository->whereTerm('category', 'nonexistent');

        $this->assertEmpty($posts);
    }

    // ===================
    // whereTermIds() tests
    // ===================

    /**
     * Test whereTermIds() filters by multiple term IDs.
     */
    public function testWhereTermIdsFiltersByMultipleIds(): void
    {
        $this->mockPosts[0]->setMockTerms('category', [1, 2]);
        $this->mockPosts[1]->setMockTerms('category', [3]);
        $this->mockPosts[2]->setMockTerms('category', [2, 4]);
        $this->repository->setMockPosts($this->mockPosts);

        $posts = $this->repository->whereTermIds([1, 2], 'category');

        $this->assertCount(2, $posts);
    }

    /**
     * Test whereTermIds() returns empty for empty term IDs.
     */
    public function testWhereTermIdsReturnsEmptyForEmptyIds(): void
    {
        $posts = $this->repository->whereTermIds([], 'category');

        $this->assertEmpty($posts);
    }

    /**
     * Test whereTermIds() respects limit.
     */
    public function testWhereTermIdsRespectsLimit(): void
    {
        $this->mockPosts[0]->setMockTerms('category', [1]);
        $this->mockPosts[1]->setMockTerms('category', [1]);
        $this->mockPosts[2]->setMockTerms('category', [1]);
        $this->repository->setMockPosts($this->mockPosts);

        $posts = $this->repository->whereTermIds([1], 'category', 2);

        $this->assertCount(2, $posts);
    }

    /**
     * Test whereTermIds() excludes specified IDs.
     */
    public function testWhereTermIdsExcludesSpecifiedIds(): void
    {
        $this->mockPosts[0]->setMockTerms('category', [1]);
        $this->mockPosts[1]->setMockTerms('category', [1]);
        $this->mockPosts[2]->setMockTerms('category', [1]);
        $this->repository->setMockPosts($this->mockPosts);

        $posts = $this->repository->whereTermIds([1], 'category', 10, [1]);

        $this->assertCount(2, $posts);
        foreach ($posts as $post) {
            $this->assertNotEquals(1, $post->ID);
        }
    }

    // ===================
    // save() tests
    // ===================

    /**
     * Test save() updates existing post.
     */
    public function testSaveUpdatesExistingPost(): void
    {
        $post = $this->mockPosts[0];
        $post->post_title = 'Updated Title';

        $result = $this->repository->save($post);

        $this->assertEquals($post->ID, $result);
        $this->assertCount(1, $this->repository->getSavedPosts());
    }

    /**
     * Test save() assigns ID to new post.
     */
    public function testSaveAssignsIdToNewPost(): void
    {
        $post = MockPost::create([
            'ID' => 0,
            'post_title' => 'New Post',
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);

        $result = $this->repository->save($post);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        $this->assertEquals($result, $post->ID);
    }

    /**
     * Test save() adds post to mock posts.
     */
    public function testSaveAddsPostToMockPosts(): void
    {
        $post = MockPost::create([
            'ID' => 0,
            'post_title' => 'Brand New Post',
            'post_status' => 'publish',
            'post_type' => 'post',
        ]);

        $this->repository->save($post);

        // Should be able to find the new post
        $found = $this->repository->find($post->ID);
        $this->assertNotNull($found);
        $this->assertEquals('Brand New Post', $found->post_title);
    }

    /**
     * Test save() tracks all saved posts.
     */
    public function testSaveTracksAllSavedPosts(): void
    {
        $post1 = MockPost::create(['ID' => 0, 'post_title' => 'Post 1', 'post_type' => 'post', 'post_status' => 'publish']);
        $post2 = MockPost::create(['ID' => 0, 'post_title' => 'Post 2', 'post_type' => 'post', 'post_status' => 'publish']);

        $this->repository->save($post1);
        $this->repository->save($post2);

        $savedPosts = $this->repository->getSavedPosts();
        $this->assertCount(2, $savedPosts);
    }

    // ===================
    // delete() tests
    // ===================

    /**
     * Test delete() removes post from mock posts.
     */
    public function testDeleteRemovesPost(): void
    {
        $post = $this->mockPosts[0];
        $postId = $post->ID;

        $result = $this->repository->delete($post);

        $this->assertTrue($result);
        $this->assertNull($this->repository->find($postId));
    }

    /**
     * Test delete() with force delete flag.
     */
    public function testDeleteWithForceDeleteFlag(): void
    {
        $post = $this->mockPosts[0];

        $this->repository->delete($post, true);

        $deletedPosts = $this->repository->getDeletedPosts();
        $this->assertCount(1, $deletedPosts);
        $this->assertTrue($deletedPosts[0]['force']);
    }

    /**
     * Test delete() without force delete flag.
     */
    public function testDeleteWithoutForceDeleteFlag(): void
    {
        $post = $this->mockPosts[0];

        $this->repository->delete($post, false);

        $deletedPosts = $this->repository->getDeletedPosts();
        $this->assertCount(1, $deletedPosts);
        $this->assertFalse($deletedPosts[0]['force']);
    }

    /**
     * Test delete() returns false for non-existent post.
     */
    public function testDeleteReturnsFalseForNonExistentPost(): void
    {
        $post = MockPost::create(['ID' => 9999, 'post_type' => 'post', 'post_status' => 'publish']);

        $result = $this->repository->delete($post);

        $this->assertFalse($result);
    }

    /**
     * Test delete() tracks all deleted posts.
     */
    public function testDeleteTracksAllDeletedPosts(): void
    {
        $this->repository->delete($this->mockPosts[0]);
        $this->repository->delete($this->mockPosts[1]);

        $deletedPosts = $this->repository->getDeletedPosts();
        $this->assertCount(2, $deletedPosts);
    }

    // ===================
    // maybeExcludeCurrentPost() tests
    // ===================

    /**
     * Test maybeExcludeCurrentPost excludes current post on singular.
     */
    public function testMaybeExcludeCurrentPostExcludesOnSingular(): void
    {
        $this->repository->setIsSingular(true);
        $this->repository->setCurrentPostId(1);

        $posts = $this->repository->all();

        // Post with ID 1 should be excluded
        foreach ($posts as $post) {
            $this->assertNotEquals(1, $post->ID);
        }
    }

    /**
     * Test maybeExcludeCurrentPost does not exclude when not singular.
     */
    public function testMaybeExcludeCurrentPostDoesNotExcludeWhenNotSingular(): void
    {
        $this->repository->setIsSingular(false);
        $this->repository->setCurrentPostId(1);

        $posts = $this->repository->all();

        // Post with ID 1 should be included
        $ids = array_map(fn($p) => $p->ID, $posts);
        $this->assertContains(1, $ids);
    }

    /**
     * Test maybeExcludeCurrentPost does not exclude when no current post.
     */
    public function testMaybeExcludeCurrentPostDoesNotExcludeWhenNoCurrentPost(): void
    {
        $this->repository->setIsSingular(true);
        $this->repository->setCurrentPostId(null);

        $posts = $this->repository->all();

        // All posts should be included
        $this->assertCount(3, $posts);
    }

    /**
     * Test excludeCurrentPost property defaults to true.
     */
    public function testExcludeCurrentPostPropertyDefaultsToTrue(): void
    {
        $this->assertTrue($this->repository->getExcludeCurrentPost());
    }
}
