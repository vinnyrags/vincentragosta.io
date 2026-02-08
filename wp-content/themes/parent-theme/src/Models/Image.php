<?php

declare(strict_types=1);

namespace ParentTheme\Models;

use Timber\Image as TimberImage;
use Timber\ImageHelper;
use Timber\Timber;

/**
 * Custom Image model with fluent resize/crop API.
 *
 * Extends Timber\Image to provide chainable methods for
 * resizing, cropping, and rendering images in templates.
 *
 * @example
 * // In Twig: {{ post.thumbnail.resize(800, 600) }}
 * // In Twig: {{ post.thumbnail.resize(400).crop }}
 * // In PHP:  $image->resize(800, 600)->crop(CropDirection::CENTER)->render()
 */
class Image extends TimberImage
{
    private ?string $imgSize = null;
    private ?int $resizeWidth = null;
    private ?int $resizeHeight = null;
    private CropDirection $cropDirection = CropDirection::NONE;
    private bool $lazy = true;
    private array $attributes = [];

    /**
     * Set resize dimensions.
     */
    public function resize(?int $width = null, ?int $height = null): static
    {
        $this->resizeWidth = $width;
        $this->resizeHeight = $height;
        $this->imgSize = null;

        return $this;
    }

    /**
     * Set the resize width.
     */
    public function setWidth(int $width): static
    {
        $this->resizeWidth = $width;
        $this->imgSize = null;

        return $this;
    }

    /**
     * Set the resize height.
     */
    public function setHeight(int $height): static
    {
        $this->resizeHeight = $height;
        $this->imgSize = null;

        return $this;
    }

    /**
     * Use a registered WordPress image size.
     *
     * Clears any manually set resize dimensions.
     */
    public function setSize(string $size): static
    {
        $this->imgSize = $size;
        $this->resizeWidth = null;
        $this->resizeHeight = null;

        return $this;
    }

    /**
     * Set the crop direction.
     */
    public function crop(CropDirection $direction = CropDirection::CENTER): static
    {
        $this->cropDirection = $direction;

        return $this;
    }

    /**
     * Set the lazy loading behavior.
     */
    public function setLazy(bool $lazy): static
    {
        $this->lazy = $lazy;

        return $this;
    }

    /**
     * Set a custom HTML attribute.
     */
    public function setAttr(string $key, string $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Get the original image width from Timber.
     *
     * Protected so test mocks can override without affecting
     * the public width() API.
     */
    protected function originalWidth(): int|null
    {
        return parent::width();
    }

    /**
     * Get the original image height from Timber.
     *
     * Protected so test mocks can override without affecting
     * the public height() API.
     */
    protected function originalHeight(): int|null
    {
        return parent::height();
    }

    /**
     * Get the image width.
     *
     * Returns resize width when set, otherwise falls back to original.
     */
    public function width(): int|null
    {
        return $this->resizeWidth ?? $this->originalWidth();
    }

    /**
     * Get the image height.
     *
     * Returns resize height when set, otherwise falls back to original.
     */
    public function height(): int|null
    {
        return $this->resizeHeight ?? $this->originalHeight();
    }

    /**
     * Get the resized image source URL.
     *
     * Orchestrates resize logic:
     * 1. If a WP image size is set, uses wp_get_attachment_image_src()
     * 2. Otherwise calculates proportional dimensions, checks if resize is needed,
     *    and calls ImageHelper::resize()
     * 3. Falls back to the original src()
     */
    public function resizedSrc(): string
    {
        if ($this->imgSize !== null) {
            $src = wp_get_attachment_image_src($this->ID, $this->imgSize);

            return $src ? $src[0] : $this->src();
        }

        $this->fillMissingDimension();

        if (!$this->shouldResize()) {
            return $this->src();
        }

        $crop = $this->cropDirection !== CropDirection::NONE
            ? $this->cropDirection->value
            : 'default';

        $resized = ImageHelper::resize(
            $this->src(),
            $this->resizeWidth ?? 0,
            $this->resizeHeight ?? 0,
            $crop
        );

        return $resized ?: $this->src();
    }

    /**
     * Calculate missing width or height from aspect ratio.
     *
     * Uses wp_constrain_dimensions() to prevent upscaling.
     */
    public function fillMissingDimension(): void
    {
        if ($this->resizeWidth !== null && $this->resizeHeight !== null) {
            return;
        }

        if ($this->resizeWidth === null && $this->resizeHeight === null) {
            return;
        }

        $originalWidth = $this->originalWidth();
        $originalHeight = $this->originalHeight();

        if (!$originalWidth || !$originalHeight) {
            return;
        }

        $targetWidth = $this->resizeWidth ?? 0;
        $targetHeight = $this->resizeHeight ?? 0;

        [$constrainedWidth, $constrainedHeight] = wp_constrain_dimensions(
            $originalWidth,
            $originalHeight,
            $targetWidth,
            $targetHeight
        );

        if ($this->resizeWidth === null) {
            $this->resizeWidth = $constrainedWidth;
        }

        if ($this->resizeHeight === null) {
            $this->resizeHeight = $constrainedHeight;
        }
    }

    /**
     * Determine if a resize is actually needed.
     *
     * Returns false if no resize dimensions are set, if dimensions match
     * the original, or if upscaling would be required without a crop.
     */
    public function shouldResize(): bool
    {
        if ($this->resizeWidth === null && $this->resizeHeight === null) {
            return false;
        }

        $originalWidth = $this->originalWidth();
        $originalHeight = $this->originalHeight();

        if ($this->resizeWidth === $originalWidth && $this->resizeHeight === $originalHeight) {
            return false;
        }

        $wouldUpscale = ($this->resizeWidth !== null && $originalWidth && $this->resizeWidth > $originalWidth)
            || ($this->resizeHeight !== null && $originalHeight && $this->resizeHeight > $originalHeight);

        if ($wouldUpscale && $this->cropDirection === CropDirection::NONE) {
            return false;
        }

        return true;
    }

    /**
     * Build the HTML attributes array for the img tag.
     *
     * @return array<string, string>
     */
    public function buildAttributes(): array
    {
        $attrs = [
            'src' => $this->resizedSrc(),
            'alt' => $this->alt() ?? '',
            'width' => (string) ($this->width() ?? ''),
            'height' => (string) ($this->height() ?? ''),
        ];

        if ($this->lazy) {
            $attrs['loading'] = 'lazy';
        }

        return array_merge($attrs, $this->attributes);
    }

    /**
     * Convert an attributes array to an HTML attribute string.
     *
     * @param array<string, string> $attributes
     */
    public function attributesToString(array $attributes): string
    {
        $parts = [];

        foreach ($attributes as $key => $value) {
            $parts[] = esc_attr($key) . '="' . esc_attr($value) . '"';
        }

        return implode(' ', $parts);
    }

    /**
     * Render the image using the Twig template.
     */
    public function render(): string
    {
        return Timber::compile('partial/image.twig', ['image' => $this]);
    }

    /**
     * Convert the image to an HTML string.
     */
    public function __toString(): string
    {
        try {
            return $this->render();
        } catch (\Throwable) {
            return '';
        }
    }
}
