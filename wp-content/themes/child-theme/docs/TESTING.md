# PHP Testing Plan

This document outlines the plan for implementing PHPUnit testing for both the parent-theme and child-theme, with pre-commit hook integration.

## Overview

Each theme will maintain its own test suite, testing only its own functionality. The child-theme should not test parent-theme code, and vice versa. Tests will run automatically on each commit via a pre-commit hook to prevent regressions.

## Current State

### Existing Infrastructure (Child Theme)
- `phpunit.xml` - PHPUnit configuration
- `tests/bootstrap.php` - Test bootstrap using WorDBless
- `tests/TestTimberStarterTheme.php` - Legacy test file (needs updating)
- `composer.json` includes:
  - `automattic/wordbless` - WordPress testing without database
  - `yoast/wp-test-utils` - Testing utilities

### Parent Theme
- No test infrastructure currently
- Will need similar setup after extraction to separate repo

## Test Structure

### Child Theme (`child-theme/tests/`)

```
tests/
├── bootstrap.php                    # Test bootstrap
├── Unit/                            # Unit tests (no WordPress deps)
│   └── Services/
│       └── IconServiceTest.php
├── Integration/                     # Integration tests (with WordPress)
│   ├── Providers/
│   │   ├── AssetServiceProviderTest.php
│   │   ├── BlockServiceProviderTest.php
│   │   ├── PostTypeServiceProviderTest.php
│   │   ├── ThemeServiceProviderTest.php
│   │   └── TwigServiceProviderTest.php
│   └── ThemeTest.php
└── Fixtures/                        # Test fixtures (mock SVGs, etc.)
    └── svg/
        └── test-icon.svg
```

### Parent Theme (`parent-theme/tests/`)

```
tests/
├── bootstrap.php
├── Unit/
│   ├── Contracts/
│   │   └── RegistrableTest.php
│   └── Traits/
│       └── HasAssetsTest.php
├── Integration/
│   ├── Providers/
│   │   ├── AssetServiceProviderTest.php
│   │   ├── ServiceProviderTest.php
│   │   └── ThemeServiceProviderTest.php
│   └── ThemeTest.php
└── Fixtures/
```

## What to Test

### Child Theme

| Class | Test Coverage |
|-------|---------------|
| `IconService` | Icon resolution, sanitization, attribute handling, directory scanning |
| `Theme` | Provider registration, bootstrap process |
| `AssetServiceProvider` | Asset enqueueing (extends parent) |
| `BlockServiceProvider` | Block registration, render callbacks |
| `PostTypeServiceProvider` | Custom post type registration |
| `TwigServiceProvider` | Twig filters, functions, context |
| `ThemeServiceProvider` | Theme support features |
| `ButtonIconEnhancer` | Block content filtering |

### Parent Theme

| Class | Test Coverage |
|-------|---------------|
| `Theme` | Base bootstrap, provider loading |
| `ServiceProvider` | Abstract registration interface |
| `AssetServiceProvider` | Base asset enqueueing logic |
| `Registrable` contract | Interface compliance |
| `HasAssets` trait | Asset path resolution |
| `DisableComments` | Comment removal functionality |

## PHPUnit Configuration

### Child Theme (`phpunit.xml`)

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

### Parent Theme (`phpunit.xml`)

Same structure, pointing to parent theme's `src/` directory.

## Bootstrap Setup

### Child Theme (`tests/bootstrap.php`)

```php
<?php

use WorDBless\Load;

// Setup WordPress test environment
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Create required directories for WorDBless
$wordpress_dir = dirname(__DIR__) . '/wordpress';
if (!file_exists($wordpress_dir . '/wp-content')) {
    mkdir($wordpress_dir . '/wp-content', 0755, true);
}
if (!file_exists($wordpress_dir . '/wp-content/themes')) {
    mkdir($wordpress_dir . '/wp-content/themes', 0755, true);
}

// Copy WorDBless database mock
copy(
    dirname(__DIR__) . '/vendor/automattic/wordbless/src/dbless-wpdb.php',
    $wordpress_dir . '/wp-content/db.php'
);

// Symlink theme for WordPress to find it
$theme_name = basename(dirname(__DIR__));
$theme_src = dirname(dirname(__DIR__)) . '/' . $theme_name;
$theme_dest = $wordpress_dir . '/wp-content/themes/' . $theme_name;
if (is_dir($theme_src) && !file_exists($theme_dest)) {
    symlink($theme_src, $theme_dest);
}

// Load WordPress
Load::load();

// Switch to our theme
switch_theme($theme_name);
```

## Pre-Commit Hook Setup

### Option 1: Husky + lint-staged (Recommended)

Uses the existing npm infrastructure for consistency.

**Installation:**
```bash
npm install --save-dev husky lint-staged
```

**package.json additions:**
```json
{
  "scripts": {
    "prepare": "husky",
    "test:php": "composer test"
  },
  "lint-staged": {
    "**/*.php": [
      "composer test"
    ]
  }
}
```

**.husky/pre-commit:**
```bash
npx lint-staged
```

### Option 2: Composer Scripts + Git Hooks

For PHP-only projects without npm.

**composer.json:**
```json
{
  "scripts": {
    "test": "phpunit",
    "test:unit": "phpunit --testsuite Unit",
    "test:integration": "phpunit --testsuite Integration"
  }
}
```

**.git/hooks/pre-commit:**
```bash
#!/bin/bash

# Run child-theme tests
cd wp-content/themes/child-theme
composer test
if [ $? -ne 0 ]; then
    echo "Child theme tests failed!"
    exit 1
fi

# Run parent-theme tests (when in separate repo, this becomes its own hook)
cd ../parent-theme
composer test
if [ $? -ne 0 ]; then
    echo "Parent theme tests failed!"
    exit 1
fi

exit 0
```

### Option 3: Husky with Direct PHP (Hybrid)

**.husky/pre-commit:**
```bash
#!/bin/bash

# Get list of staged PHP files
STAGED_PHP=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$')

if [ -n "$STAGED_PHP" ]; then
    # Check if child-theme files are staged
    if echo "$STAGED_PHP" | grep -q "child-theme/"; then
        echo "Running child-theme tests..."
        cd wp-content/themes/child-theme && composer test
        if [ $? -ne 0 ]; then
            exit 1
        fi
    fi

    # Check if parent-theme files are staged
    if echo "$STAGED_PHP" | grep -q "parent-theme/"; then
        echo "Running parent-theme tests..."
        cd wp-content/themes/parent-theme && composer test
        if [ $? -ne 0 ]; then
            exit 1
        fi
    fi
fi

exit 0
```

## Implementation Steps

### Phase 1: Update Child Theme Test Infrastructure

1. [ ] Update `phpunit.xml` with new configuration
2. [ ] Update `tests/bootstrap.php`
3. [ ] Remove legacy `TestTimberStarterTheme.php`
4. [ ] Create directory structure (`Unit/`, `Integration/`, `Fixtures/`)
5. [ ] Create base test case classes

### Phase 2: Write Child Theme Tests

1. [ ] `IconServiceTest.php` - Unit tests for icon resolution and rendering
2. [ ] `ThemeTest.php` - Integration test for theme bootstrap
3. [ ] `AssetServiceProviderTest.php` - Asset enqueueing
4. [ ] `BlockServiceProviderTest.php` - Block registration
5. [ ] `PostTypeServiceProviderTest.php` - CPT registration
6. [ ] `TwigServiceProviderTest.php` - Twig extensions
7. [ ] `ButtonIconEnhancerTest.php` - Block content filtering

### Phase 3: Setup Pre-Commit Hook

1. [ ] Install husky
2. [ ] Configure pre-commit hook
3. [ ] Test hook with failing and passing tests
4. [ ] Document bypass for emergencies (`--no-verify`)

### Phase 4: Parent Theme Test Infrastructure (Post-Extraction)

After parent-theme is extracted to its own repository:

1. [ ] Copy test infrastructure pattern from child-theme
2. [ ] Create `composer.json` with test dependencies
3. [ ] Create `phpunit.xml`
4. [ ] Create `tests/bootstrap.php`
5. [ ] Write tests for parent-theme specific classes

## Example Test Cases

### IconService Unit Test

```php
<?php

namespace ChildTheme\Tests\Unit\Services;

use ChildTheme\Services\IconService;
use PHPUnit\Framework\TestCase;

class IconServiceTest extends TestCase
{
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = dirname(__DIR__, 2) . '/Fixtures';
    }

    public function testSanitizeNameRemovesDirectoryTraversal(): void
    {
        // Use reflection to test private method
        $service = new IconService('test');
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('sanitizeName');
        $method->setAccessible(true);

        $this->assertEquals('icon', $method->invoke($service, '../../../icon'));
        $this->assertEquals('icon', $method->invoke($service, 'icon.svg'));
    }

    public function testSanitizeContentRemovesScriptTags(): void
    {
        $service = new IconService('test');
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('sanitizeContent');
        $method->setAccessible(true);

        $dirty = '<svg><script>alert("xss")</script><path d="M0 0"/></svg>';
        $clean = $method->invoke($service, $dirty);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringContainsString('<path', $clean);
    }

    public function testWithClassAddsClass(): void
    {
        $service = IconService::get('test')->withClass('icon-lg');
        $reflection = new \ReflectionClass($service);
        $prop = $reflection->getProperty('attributes');
        $prop->setAccessible(true);

        $attrs = $prop->getValue($service);
        $this->assertEquals('icon-lg', $attrs['class']);
    }

    public function testWithAttributesMergesAttributes(): void
    {
        $service = IconService::get('test')
            ->withClass('icon-lg')
            ->withAttributes(['aria-hidden' => 'true', 'role' => 'img']);

        $reflection = new \ReflectionClass($service);
        $prop = $reflection->getProperty('attributes');
        $prop->setAccessible(true);

        $attrs = $prop->getValue($service);
        $this->assertArrayHasKey('class', $attrs);
        $this->assertArrayHasKey('aria-hidden', $attrs);
        $this->assertArrayHasKey('role', $attrs);
    }
}
```

### Theme Integration Test

```php
<?php

namespace ChildTheme\Tests\Integration;

use ChildTheme\Theme;
use WorDBless\BaseTestCase;

class ThemeTest extends BaseTestCase
{
    private Theme $theme;

    public function set_up(): void
    {
        parent::set_up();
        $this->theme = new Theme();
    }

    public function testThemeBootstrapsWithoutErrors(): void
    {
        $this->theme->boot();
        $this->assertTrue(true); // If we get here, no exceptions were thrown
    }

    public function testProvidersAreRegistered(): void
    {
        $reflection = new \ReflectionClass($this->theme);
        $prop = $reflection->getProperty('providers');
        $prop->setAccessible(true);

        $providers = $prop->getValue($this->theme);
        $this->assertNotEmpty($providers);
        $this->assertContains(\ChildTheme\Providers\AssetServiceProvider::class, $providers);
    }
}
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

### With Coverage
```bash
composer test -- --coverage-html coverage/
```

## CI Integration (Future)

When ready for CI (GitHub Actions):

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: composer test
```

## Notes

- **WorDBless** provides a WordPress environment without needing a database, making tests fast and isolated
- **Brain Monkey** (included via wp-test-utils) allows mocking WordPress functions in unit tests
- Tests should be fast - aim for the full suite to run in under 10 seconds
- Use `--no-verify` flag to bypass pre-commit hook in emergencies: `git commit --no-verify`
