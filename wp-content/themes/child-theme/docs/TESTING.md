# PHP Testing

This document covers the PHPUnit testing setup for the child-theme.

For parent-theme testing documentation, see [parent-theme Testing](../../parent-theme/docs/TESTING.md).

## Overview

The child-theme uses PHPUnit with WorDBless for testing. Tests run automatically on commit via a pre-commit hook.

## Test Structure

```
tests/
├── bootstrap.php                    # Test bootstrap
├── Unit/                            # Unit tests (no WordPress deps)
│   └── Services/
│       └── IconServiceTest.php
├── Integration/                     # Integration tests (with WordPress)
│   ├── Providers/
│   │   └── AssetServiceProviderTest.php
│   └── ThemeTest.php
└── Fixtures/                        # Test fixtures
    └── svg/
        └── test-icon.svg
```

## Test Coverage

| Class | Coverage |
|-------|----------|
| `IconService` | Sanitization, fluent interface, attribute handling |
| `Theme` | Provider registration, bootstrap process |
| `AssetServiceProvider` | WordPress hook registration |

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

### Run All Tests (Both Themes)

```bash
npm run test:php:all
```

### With Coverage

```bash
composer test -- --coverage-html coverage/
```

## Pre-Commit Hook

Tests run automatically when PHP files are staged for commit. The hook (`.husky/pre-commit`) detects which theme has changes and runs the appropriate tests.

To bypass in emergencies:

```bash
git commit --no-verify
```

## Writing Tests

### Unit Test Example

```php
<?php

namespace ChildTheme\Tests\Unit\Services;

use ChildTheme\Services\IconService;
use PHPUnit\Framework\TestCase;

class IconServiceTest extends TestCase
{
    public function testWithClassAddsClass(): void
    {
        $service = IconService::get('test')->withClass('icon-lg');
        $reflection = new \ReflectionClass($service);
        $prop = $reflection->getProperty('attributes');
        $prop->setAccessible(true);

        $attrs = $prop->getValue($service);
        $this->assertEquals('icon-lg', $attrs['class']);
    }
}
```

### Integration Test Example

```php
<?php

namespace ChildTheme\Tests\Integration;

use ChildTheme\Theme;
use Yoast\WPTestUtils\WPIntegration\TestCase;

class ThemeTest extends TestCase
{
    public function testProvidersAreRegistered(): void
    {
        $theme = new Theme();
        $reflection = new \ReflectionClass($theme);
        $prop = $reflection->getProperty('providers');
        $prop->setAccessible(true);

        $this->assertNotEmpty($prop->getValue($theme));
    }
}
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

- **WorDBless** provides WordPress without a database for fast, isolated tests
- Tests should run in under 10 seconds
- Each theme tests only its own code
