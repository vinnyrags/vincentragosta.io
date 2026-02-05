# TwigProvider

Extends the parent theme's TwigProvider to add site-specific Twig functions.

## Custom Functions

### `icon(name)`

Returns an `IconService` instance for rendering SVG icons from the sprite.

**Parameters:**
- `name` (string) - Icon name (without file extension)

**Returns:** `IconService` instance (implements `__toString()`)

**Usage in templates:**

```twig
{# Render an icon #}
{{ icon('arrow-right') }}

{# Icon in a link #}
<a href="/next">
  Next {{ icon('chevron-right') }}
</a>

{# Icon with custom class (use raw filter since IconService returns safe HTML) #}
<span class="icon-wrapper">
  {{ icon('menu') }}
</span>
```

## IconService

The `icon()` function returns an `IconService` instance which:

- Looks up the icon in the SVG sprite (`src/Providers/Theme/assets/images/svg-sprite/`)
- Returns the `<svg><use></use></svg>` markup when cast to string
- Returns empty string if icon doesn't exist

## Adding More Functions

```php
public function addTwigFunctions(\Twig\Environment $twig): \Twig\Environment
{
    $twig = parent::addTwigFunctions($twig);

    // Add another function
    $twig->addFunction(new TwigFunction('my_function', function ($arg) {
        return do_something($arg);
    }));

    return $twig;
}
```
