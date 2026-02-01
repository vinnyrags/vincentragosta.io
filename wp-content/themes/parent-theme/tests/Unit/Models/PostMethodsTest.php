<?php

namespace ParentTheme\Tests\Unit\Models;

use DateTime;
use ParentTheme\Tests\Mocks\MockPost;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Post model methods using MockPost.
 */
class PostMethodsTest extends TestCase
{
    /**
     * Test url() returns the post link.
     */
    public function testUrlReturnsLink(): void
    {
        $post = MockPost::create(['post_name' => 'hello-world']);

        $this->assertEquals('https://example.com/hello-world/', $post->url());
        $this->assertEquals($post->link(), $post->url());
    }

    /**
     * Test publishedDate() returns DateTime object.
     */
    public function testPublishedDateReturnsDateTime(): void
    {
        $post = MockPost::create(['post_date' => '2024-03-15 14:30:00']);

        $date = $post->publishedDate();

        $this->assertInstanceOf(DateTime::class, $date);
        $this->assertEquals('2024-03-15', $date->format('Y-m-d'));
        $this->assertEquals('14:30:00', $date->format('H:i:s'));
    }

    /**
     * Test modifiedDate() returns DateTime object.
     */
    public function testModifiedDateReturnsDateTime(): void
    {
        $post = MockPost::create(['post_modified' => '2024-04-20 09:15:00']);

        $date = $post->modifiedDate();

        $this->assertInstanceOf(DateTime::class, $date);
        $this->assertEquals('2024-04-20', $date->format('Y-m-d'));
        $this->assertEquals('09:15:00', $date->format('H:i:s'));
    }

    /**
     * Test isPublished() returns true for published posts.
     */
    public function testIsPublishedReturnsTrueForPublishedPost(): void
    {
        $post = MockPost::create(['post_status' => 'publish']);

        $this->assertTrue($post->isPublished());
    }

    /**
     * Test isPublished() returns false for draft posts.
     */
    public function testIsPublishedReturnsFalseForDraftPost(): void
    {
        $post = MockPost::create(['post_status' => 'draft']);

        $this->assertFalse($post->isPublished());
    }

    /**
     * Test isPublished() returns false for pending posts.
     */
    public function testIsPublishedReturnsFalseForPendingPost(): void
    {
        $post = MockPost::create(['post_status' => 'pending']);

        $this->assertFalse($post->isPublished());
    }

    /**
     * Test isDraft() returns true for draft posts.
     */
    public function testIsDraftReturnsTrueForDraftPost(): void
    {
        $post = MockPost::create(['post_status' => 'draft']);

        $this->assertTrue($post->isDraft());
    }

    /**
     * Test isDraft() returns false for published posts.
     */
    public function testIsDraftReturnsFalseForPublishedPost(): void
    {
        $post = MockPost::create(['post_status' => 'publish']);

        $this->assertFalse($post->isDraft());
    }

    /**
     * Test getMeta() retrieves meta value.
     */
    public function testGetMetaRetrievesValue(): void
    {
        $post = MockPost::create();
        $post->setMockMeta([
            'color' => 'blue',
            'size' => 'large',
        ]);

        $this->assertEquals('blue', $post->getMeta('color'));
        $this->assertEquals('large', $post->getMeta('size'));
    }

    /**
     * Test getMeta() returns empty string for non-existent key.
     */
    public function testGetMetaReturnsEmptyForNonExistentKey(): void
    {
        $post = MockPost::create();

        $this->assertEquals('', $post->getMeta('nonexistent'));
    }

    /**
     * Test getMeta() with single=false returns array.
     */
    public function testGetMetaWithSingleFalseReturnsArray(): void
    {
        $post = MockPost::create();
        $post->setMockMeta(['key' => 'value']);

        $result = $post->getMeta('key', false);

        $this->assertIsArray($result);
        $this->assertEquals(['value'], $result);
    }

    /**
     * Test hasTerm() returns true when post has the term.
     */
    public function testHasTermReturnsTrueWhenPostHasTerm(): void
    {
        $post = MockPost::create();
        $post->setMockTerms('category', ['news', 'featured']);

        $this->assertTrue($post->hasTerm('news', 'category'));
        $this->assertTrue($post->hasTerm('featured', 'category'));
    }

    /**
     * Test hasTerm() returns false when post doesn't have the term.
     */
    public function testHasTermReturnsFalseWhenPostDoesNotHaveTerm(): void
    {
        $post = MockPost::create();
        $post->setMockTerms('category', ['news']);

        $this->assertFalse($post->hasTerm('sports', 'category'));
    }

    /**
     * Test hasTerm() returns false for non-existent taxonomy.
     */
    public function testHasTermReturnsFalseForNonExistentTaxonomy(): void
    {
        $post = MockPost::create();

        $this->assertFalse($post->hasTerm('anything', 'nonexistent'));
    }

    /**
     * Test hasTerm() works with term IDs.
     */
    public function testHasTermWorksWithTermIds(): void
    {
        $post = MockPost::create();
        $post->setMockTerms('category', [1, 5, 10]);

        $this->assertTrue($post->hasTerm(5, 'category'));
        $this->assertFalse($post->hasTerm(99, 'category'));
    }

    /**
     * Test post properties are accessible.
     */
    public function testPostPropertiesAreAccessible(): void
    {
        $post = MockPost::create([
            'ID' => 42,
            'post_title' => 'My Test Post',
            'post_content' => 'This is the content.',
            'post_excerpt' => 'Short excerpt.',
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_name' => 'my-test-post',
            'post_author' => 5,
        ]);

        $this->assertEquals(42, $post->ID);
        $this->assertEquals('My Test Post', $post->post_title);
        $this->assertEquals('This is the content.', $post->post_content);
        $this->assertEquals('Short excerpt.', $post->post_excerpt);
        $this->assertEquals('publish', $post->post_status);
        $this->assertEquals('post', $post->post_type);
        $this->assertEquals('my-test-post', $post->post_name);
        $this->assertEquals(5, $post->post_author);
    }

    /**
     * Test title() method returns post_title.
     */
    public function testTitleReturnsPostTitle(): void
    {
        $post = MockPost::create(['post_title' => 'Amazing Title']);

        $this->assertEquals('Amazing Title', $post->title());
    }

    /**
     * Test multiple posts can be created with different data.
     */
    public function testMultiplePostsCanBeCreated(): void
    {
        $post1 = MockPost::create([
            'ID' => 1,
            'post_title' => 'First Post',
            'post_status' => 'publish',
        ]);

        $post2 = MockPost::create([
            'ID' => 2,
            'post_title' => 'Second Post',
            'post_status' => 'draft',
        ]);

        $this->assertEquals(1, $post1->ID);
        $this->assertEquals('First Post', $post1->post_title);
        $this->assertTrue($post1->isPublished());

        $this->assertEquals(2, $post2->ID);
        $this->assertEquals('Second Post', $post2->post_title);
        $this->assertTrue($post2->isDraft());
    }

    // ===================
    // setMeta() tests
    // ===================

    /**
     * Test setMeta() saves a value.
     */
    public function testSetMetaSavesValue(): void
    {
        $post = MockPost::create();

        $result = $post->setMeta('color', 'red');

        $this->assertTrue($result);
        $this->assertEquals('red', $post->getMeta('color'));
    }

    /**
     * Test setMeta() tracks the operation.
     */
    public function testSetMetaTracksOperation(): void
    {
        $post = MockPost::create();

        $post->setMeta('size', 'large');

        $this->assertTrue($post->wasMetaUpdated());
        $this->assertEquals(['key' => 'size', 'value' => 'large'], $post->getLastSetMeta());
    }

    /**
     * Test setMeta() overwrites existing value.
     */
    public function testSetMetaOverwritesExistingValue(): void
    {
        $post = MockPost::create();
        $post->setMockMeta(['color' => 'blue']);

        $post->setMeta('color', 'green');

        $this->assertEquals('green', $post->getMeta('color'));
    }

    /**
     * Test setMeta() with various value types.
     */
    public function testSetMetaWithVariousTypes(): void
    {
        $post = MockPost::create();

        $post->setMeta('string', 'hello');
        $post->setMeta('number', 42);
        $post->setMeta('array', ['a', 'b', 'c']);

        $this->assertEquals('hello', $post->getMeta('string'));
        $this->assertEquals(42, $post->getMeta('number'));
        $this->assertEquals(['a', 'b', 'c'], $post->getMeta('array'));
    }

    // ===================
    // deleteMeta() tests
    // ===================

    /**
     * Test deleteMeta() removes a value.
     */
    public function testDeleteMetaRemovesValue(): void
    {
        $post = MockPost::create();
        $post->setMockMeta(['color' => 'blue', 'size' => 'large']);

        $result = $post->deleteMeta('color');

        $this->assertTrue($result);
        $this->assertEquals('', $post->getMeta('color'));
        $this->assertEquals('large', $post->getMeta('size'));
    }

    /**
     * Test deleteMeta() tracks the operation.
     */
    public function testDeleteMetaTracksOperation(): void
    {
        $post = MockPost::create();
        $post->setMockMeta(['key' => 'value']);

        $post->deleteMeta('key');

        $this->assertTrue($post->wasMetaDeleted());
        $this->assertEquals('key', $post->getLastDeletedMetaKey());
    }

    /**
     * Test deleteMeta() returns true even for non-existent key.
     */
    public function testDeleteMetaReturnsTrue(): void
    {
        $post = MockPost::create();

        $result = $post->deleteMeta('nonexistent');

        $this->assertTrue($result);
    }

    // ===================
    // refresh() tests
    // ===================

    /**
     * Test refresh() is called.
     */
    public function testRefreshIsCalled(): void
    {
        $post = MockPost::create();

        $post->refresh();

        $this->assertTrue($post->wasRefreshed());
    }

    /**
     * Test refresh() can be called multiple times.
     */
    public function testRefreshCanBeCalledMultipleTimes(): void
    {
        $post = MockPost::create();

        $post->refresh();
        $post->refresh();
        $post->refresh();

        $this->assertTrue($post->wasRefreshed());
    }

    // ===================
    // resetTracking() tests
    // ===================

    /**
     * Test resetTracking() clears all flags.
     */
    public function testResetTrackingClearsAllFlags(): void
    {
        $post = MockPost::create();
        $post->setMeta('key', 'value');
        $post->deleteMeta('other');
        $post->refresh();

        $this->assertTrue($post->wasMetaUpdated());
        $this->assertTrue($post->wasMetaDeleted());
        $this->assertTrue($post->wasRefreshed());

        $post->resetTracking();

        $this->assertFalse($post->wasMetaUpdated());
        $this->assertFalse($post->wasMetaDeleted());
        $this->assertFalse($post->wasRefreshed());
        $this->assertNull($post->getLastDeletedMetaKey());
        $this->assertEmpty($post->getLastSetMeta());
    }
}
