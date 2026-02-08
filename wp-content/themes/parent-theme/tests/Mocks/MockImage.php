<?php

namespace ParentTheme\Tests\Mocks;

use ParentTheme\Models\Image;

/**
 * Mock Image class for testing.
 *
 * Allows creating Image instances without WordPress/Timber dependencies.
 */
class MockImage extends Image
{
    private string $mockSrc = 'https://example.com/image.jpg';
    private ?string $mockAlt = 'Test image';
    private ?int $mockWidth = 1200;
    private ?int $mockHeight = 800;

    /**
     * Create a mock image with the given data.
     */
    public static function create(array $data = []): self
    {
        $image = new self();

        $defaults = [
            'ID' => 1,
            'post_title' => 'Test Image',
            'post_type' => 'attachment',
            'post_mime_type' => 'image/jpeg',
        ];

        $data = array_merge($defaults, $data);

        foreach ($data as $key => $value) {
            $image->$key = $value;
        }

        return $image;
    }

    /**
     * Override src() for testing.
     */
    public function src($size = 'full'): string
    {
        return $this->mockSrc;
    }

    /**
     * Override alt() for testing.
     */
    public function alt(): ?string
    {
        return $this->mockAlt;
    }

    /**
     * Override originalWidth() to return mock dimensions.
     */
    protected function originalWidth(): int|null
    {
        return $this->mockWidth;
    }

    /**
     * Override originalHeight() to return mock dimensions.
     */
    protected function originalHeight(): int|null
    {
        return $this->mockHeight;
    }

    /**
     * Set the mock source URL.
     */
    public function setMockSrc(string $src): self
    {
        $this->mockSrc = $src;
        return $this;
    }

    /**
     * Set the mock alt text.
     */
    public function setMockAlt(?string $alt): self
    {
        $this->mockAlt = $alt;
        return $this;
    }

    /**
     * Set the mock original width.
     */
    public function setMockWidth(?int $width): self
    {
        $this->mockWidth = $width;
        return $this;
    }

    /**
     * Set the mock original height.
     */
    public function setMockHeight(?int $height): self
    {
        $this->mockHeight = $height;
        return $this;
    }
}
