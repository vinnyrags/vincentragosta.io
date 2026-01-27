# Parent Theme Testing

This document covers the PHPUnit testing setup for the parent theme.

## Overview

The parent theme uses PHPUnit with WorDBless for testing. Tests are organized into Unit and Integration test suites.

## Test Structure

```
tests/
├── bootstrap.php           # Test bootstrap
├── Unit/                   # Unit tests (no WordPress deps)
│   └── Traits/
│       └── HasAssetsTraitTest.php
├── Integration/            # Integration tests (with WordPress)
│   ├── ThemeTest.php
│   └── Providers/
│       ├── ServiceProviderTest.php
│       └── DisableCommentsTest.php
└── Fixtures/               # Test fixtures
```

## Running Tests

### All Tests

```bash
composer test
```

### Unit Tests Only

```bash
composer test:unit
```

### Integration Tests Only

```bash
composer test:integration
```

## Test Coverage

### Unit Tests

| Class/Trait | Coverage |
|-------------|----------|
| `HasAssets` trait | Slug generation, handle prefixing |

### Integration Tests

| Class | Coverage |
|-------|----------|
| `Theme` | Bootstrap, provider registration |
| `ServiceProvider` | Abstract registration, feature loading |
| `DisableComments` | Comment removal hooks |

## Writing Tests

### Unit Test Example

```php
<?php

namespace ParentTheme\Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use ParentTheme\Traits\HasAssets;

class HasAssetsTraitTest extends TestCase
{
    public function testGetSlugFromClassName(): void
    {
        $mock = new class {
            use HasAssets;

            public function exposeGetSlug(): string
            {
                return $this->getSlug();
            }
        };

        // Anonymous class returns empty string, test with reflection instead
        $this->assertIsString($mock->exposeGetSlug());
    }
}
```

### Integration Test Example

```php
<?php

namespace ParentTheme\Tests\Integration\Providers;

use ParentTheme\Providers\DisableComments;
use Yoast\WPTestUtils\WPIntegration\TestCase;

class DisableCommentsTest extends TestCase
{
    public function testRemovesCommentsFromAdminBar(): void
    {
        $provider = new DisableComments();
        $provider->register();

        // Verify hook is registered
        $this->assertNotFalse(
            has_action('wp_before_admin_bar_render', [$provider, 'removeFromAdminBar'])
        );
    }
}
```

## Configuration

### phpunit.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    colors="true"
    verbose="true"
    stopOnFailure="false"
>
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
    </testsuites>
    <coverage>
        <include>
            <directory suffix=".php">./src</directory>
        </include>
    </coverage>
</phpunit>
```

### Bootstrap (tests/bootstrap.php)

```php
<?php

use WorDBless\Load;

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/vendor/roots/wordpress-no-content/');
}

$wordpress_dir = dirname(__DIR__) . '/wordpress';
if (!file_exists($wordpress_dir . '/wp-content')) {
    mkdir($wordpress_dir . '/wp-content', 0755, true);
}
if (!file_exists($wordpress_dir . '/wp-content/themes')) {
    mkdir($wordpress_dir . '/wp-content/themes', 0755, true);
}

copy(
    dirname(__DIR__) . '/vendor/automattic/wordbless/src/dbless-wpdb.php',
    $wordpress_dir . '/wp-content/db.php'
);

$theme_name = basename(dirname(__DIR__));
$theme_src = dirname(dirname(__DIR__)) . '/' . $theme_name;
$theme_dest = $wordpress_dir . '/wp-content/themes/' . $theme_name;
if (is_dir($theme_src) && !file_exists($theme_dest)) {
    symlink($theme_src, $theme_dest);
}

Load::load();
switch_theme($theme_name);
```

## Dependencies

Required in `composer.json`:

```json
{
    "require-dev": {
        "automattic/wordbless": "^0.4.2",
        "yoast/wp-test-utils": "^1.0"
    }
}
```

## Pre-Commit Integration

When PHP files in the parent-theme are staged for commit, the pre-commit hook in the child-theme will run the parent-theme tests:

```bash
# From .husky/pre-commit
if echo "$STAGED_PHP" | grep -q "wp-content/themes/parent-theme/"; then
    echo "Running parent-theme PHP tests..."
    cd "$REPO_ROOT/wp-content/themes/parent-theme" && composer test
fi
```

## Best Practices

1. **Test behavior, not implementation** - Focus on what the code does, not how
2. **Use descriptive test names** - `testDisablesCommentsOnPosts` not `test1`
3. **One assertion per test** - When practical, keep tests focused
4. **Isolate tests** - Each test should run independently
5. **Mock external dependencies** - Don't rely on external services
