# TwigProvider

Base provider for registering custom Twig functions and filters via Timber.

## How It Works

Hooks into `timber/twig` filter to add custom functions to the Twig environment.

## Extending in Child Themes

The parent provider is intentionally minimal. Child themes should extend it to add site-specific functions:

```php
namespace ChildTheme\Providers\Twig;

use ParentTheme\Providers\Twig\TwigProvider as BaseTwigProvider;
use Twig\TwigFunction;
use Twig\TwigFilter;

class TwigProvider extends BaseTwigProvider
{
    public function addTwigFunctions(\Twig\Environment $twig): \Twig\Environment
    {
        // Always call parent first
        $twig = parent::addTwigFunctions($twig);

        // Add custom functions
        $twig->addFunction(new TwigFunction('icon', function (string $name) {
            return new IconService($name);
        }));

        // Add custom filters
        $twig->addFilter(new TwigFilter('phone_link', function (string $phone) {
            return 'tel:' . preg_replace('/[^0-9+]/', '', $phone);
        }));

        return $twig;
    }
}
```

## Usage in Templates

Once registered, functions are available in all Twig templates:

```twig
{# Using a custom function #}
{{ icon('arrow-right') }}

{# Using a custom filter #}
<a href="{{ phone_number|phone_link }}">{{ phone_number }}</a>
```

## Common Functions to Add

| Function | Purpose | Example |
|----------|---------|---------|
| `icon()` | Render SVG icons | `{{ icon('menu') }}` |
| `image()` | Get image by ID with srcset | `{{ image(123) }}` |
| `svg()` | Inline SVG file | `{{ svg('logo.svg') }}` |

## Common Filters to Add

| Filter | Purpose | Example |
|--------|---------|---------|
| `phone_link` | Format phone for `tel:` href | `{{ phone\|phone_link }}` |
| `excerpt` | Custom excerpt with length | `{{ content\|excerpt(20) }}` |
| `slugify` | Convert to URL-safe slug | `{{ title\|slugify }}` |
