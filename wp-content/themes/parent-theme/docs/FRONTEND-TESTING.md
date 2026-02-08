# Frontend Testing Plan

This document outlines the plan for implementing frontend JavaScript testing when the codebase warrants it.

## Current State

The frontend JavaScript consists of:
- **Block components** (`blocks/*/edit.js`, `save.js`) - React/Gutenberg components
- **View scripts** (`blocks/*/view.js`) - Frontend interactivity (vanilla JS)
- **Asset scripts** (`src/Providers/*/assets/js/`) - General frontend utilities

## When to Implement

Consider adding frontend tests when you have:
- [ ] Utility functions with complex logic (data transformation, validation)
- [ ] Shared components used across multiple blocks
- [ ] Complex state management in block editors
- [ ] Frontend bugs that tests would have caught
- [ ] Significant frontend interactivity beyond simple DOM toggling

## Testing Strategy

### Tier 1: Unit Tests (Jest)

Best for isolated logic without heavy WordPress dependencies.

**Good candidates:**
- Utility/helper functions
- Data transformation logic
- Pure functions extracted from components
- View scripts with testable logic (like shutter-cards)

**Setup:** Already available via `@wordpress/scripts`

### Tier 2: Component Tests (Jest + React Testing Library)

For testing React components in isolation.

**Good candidates:**
- Reusable components
- Components with significant internal logic
- Custom hooks

**Challenges:**
- Requires mocking WordPress packages
- Block editor components are tightly coupled to WordPress context

### Tier 3: E2E Tests (Playwright)

For testing full user flows in a real browser.

**Good candidates:**
- Critical user journeys
- Block insertion and configuration
- Frontend interactivity

**Challenges:**
- Requires running WordPress instance
- Slower execution
- More infrastructure

## Implementation Plan

### Phase 1: Jest Unit Tests

#### 1. Verify Jest is Available

`@wordpress/scripts` includes Jest. Test with:

```bash
npm run test:unit:js
```

If no tests exist, it will report "No tests found".

#### 2. Create Test Directory Structure

```
src/Providers/Theme/
├── blocks/
│   └── shutter-cards/
│       ├── frontend/
│       │   ├── view.js
│       │   └── view.test.js      # Co-located test
│       └── editor/
│           └── index.js
└── assets/
    └── js/
        ├── frontend.js
        ├── utils/
        │   ├── helpers.js
        │   └── helpers.test.js
        └── __tests__/     # Alternative: dedicated test folder
            └── helpers.test.js
```

#### 3. Configure Jest (if needed)

Create `jest.config.js` in theme root if custom config is needed:

```js
const defaultConfig = require('@wordpress/scripts/config/jest-unit.config');

module.exports = {
    ...defaultConfig,
    testPathIgnorePatterns: ['/node_modules/', '/vendor/'],
    setupFilesAfterEnv: ['<rootDir>/tests/js/setup.js'],
};
```

#### 4. Add Test Scripts to package.json

```json
{
  "scripts": {
    "test:js": "wp-scripts test-unit-js",
    "test:js:watch": "wp-scripts test-unit-js --watch",
    "test:js:coverage": "wp-scripts test-unit-js --coverage"
  }
}
```

#### 5. Example: Testing view.js (Shutter Cards)

```js
// blocks/shutter-cards/view.test.js

/**
 * @jest-environment jsdom
 */

describe('Shutter Cards', () => {
    beforeEach(() => {
        // Set up DOM
        document.body.innerHTML = `
            <div class="shutter-cards shutter-cards--preload">
                <div class="shutter-card" data-index="0"></div>
                <div class="shutter-card" data-index="1"></div>
                <div class="shutter-card" data-index="2"></div>
            </div>
        `;
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('initializes with first card active and others inactive', () => {
        // Import and trigger DOMContentLoaded
        require('./view.js');
        document.dispatchEvent(new Event('DOMContentLoaded'));

        const cards = document.querySelectorAll('.shutter-card');

        expect(cards[0].classList.contains('shutter-card--inactive')).toBe(false);
        expect(cards[1].classList.contains('shutter-card--inactive')).toBe(true);
        expect(cards[2].classList.contains('shutter-card--inactive')).toBe(true);
    });

    test('clicking inactive card makes it active', () => {
        require('./view.js');
        document.dispatchEvent(new Event('DOMContentLoaded'));

        const cards = document.querySelectorAll('.shutter-card');
        cards[1].click();

        expect(cards[0].classList.contains('shutter-card--inactive')).toBe(true);
        expect(cards[1].classList.contains('shutter-card--inactive')).toBe(false);
        expect(cards[2].classList.contains('shutter-card--inactive')).toBe(true);
    });

    test('removes preload class after timeout', () => {
        jest.useFakeTimers();
        require('./view.js');
        document.dispatchEvent(new Event('DOMContentLoaded'));

        const container = document.querySelector('.shutter-cards');
        expect(container.classList.contains('shutter-cards--preload')).toBe(true);

        jest.advanceTimersByTime(500);
        expect(container.classList.contains('shutter-cards--preload')).toBe(false);

        jest.useRealTimers();
    });
});
```

### Phase 2: React Component Tests

#### 1. Install React Testing Library

```bash
npm install --save-dev @testing-library/react @testing-library/jest-dom
```

#### 2. Create Test Setup File

```js
// tests/js/setup.js
import '@testing-library/jest-dom';

// Mock WordPress packages
jest.mock('@wordpress/block-editor', () => ({
    useBlockProps: () => ({}),
    InnerBlocks: () => null,
    RichText: ({ value, onChange, ...props }) => (
        <div {...props}>{value}</div>
    ),
}));

jest.mock('@wordpress/components', () => ({
    PanelBody: ({ children }) => <div>{children}</div>,
    TextControl: ({ label, value, onChange }) => (
        <input aria-label={label} value={value} onChange={e => onChange(e.target.value)} />
    ),
    // Add more mocks as needed
}));

jest.mock('@wordpress/i18n', () => ({
    __: (str) => str,
    _x: (str) => str,
}));
```

#### 3. Example: Testing Block Edit Component

```js
// blocks/hero/edit.test.js
import { render, screen } from '@testing-library/react';
import Edit from './edit';

const defaultAttributes = {
    title: 'Test Title',
    subtitle: '',
};

const defaultProps = {
    attributes: defaultAttributes,
    setAttributes: jest.fn(),
};

describe('Hero Block Edit', () => {
    test('renders title', () => {
        render(<Edit {...defaultProps} />);
        expect(screen.getByText('Test Title')).toBeInTheDocument();
    });

    test('calls setAttributes when title changes', () => {
        // Test implementation
    });
});
```

### Phase 3: E2E Tests (Playwright)

#### 1. Install Playwright

```bash
npm install --save-dev @playwright/test @wordpress/e2e-test-utils-playwright
```

#### 2. Configure Playwright

```js
// playwright.config.js
import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    use: {
        baseURL: 'http://localhost:8888', // Local WordPress URL
    },
    webServer: {
        command: 'wp-env start',
        url: 'http://localhost:8888',
        reuseExistingServer: true,
    },
});
```

#### 3. Add Scripts

```json
{
  "scripts": {
    "test:e2e": "playwright test",
    "test:e2e:ui": "playwright test --ui"
  }
}
```

#### 4. Example E2E Test

```js
// tests/e2e/hero-block.spec.js
import { test, expect } from '@playwright/test';
import { Admin, Editor } from '@wordpress/e2e-test-utils-playwright';

test.describe('Hero Block', () => {
    test('can be inserted and configured', async ({ page, admin, editor }) => {
        await admin.createNewPost();
        await editor.insertBlock({ name: 'theme/hero' });

        // Verify block was inserted
        await expect(page.locator('.wp-block-theme-hero')).toBeVisible();
    });
});
```

## Pre-commit Hook Integration

When ready, update `.husky/pre-commit` to include JS tests:

```bash
#!/bin/bash

REPO_ROOT=$(git rev-parse --show-toplevel)
STAGED_FILES=$(git diff --cached --name-only --diff-filter=ACM)

# Check for staged JS files in child-theme
if echo "$STAGED_FILES" | grep -q "wp-content/themes/child-theme/.*\.js$"; then
    echo "Running child-theme JS tests..."
    cd "$REPO_ROOT/wp-content/themes/child-theme" && npm run test:js
    if [ $? -ne 0 ]; then
        echo "JS tests failed. Commit aborted."
        exit 1
    fi
fi

# Existing PHP tests...
```

## Package.json Scripts Summary

When fully implemented:

```json
{
  "scripts": {
    "test:js": "wp-scripts test-unit-js",
    "test:js:watch": "wp-scripts test-unit-js --watch",
    "test:js:coverage": "wp-scripts test-unit-js --coverage",
    "test:e2e": "playwright test",
    "test:e2e:ui": "playwright test --ui",
    "test:all": "npm run test:php:all && npm run test:js"
  }
}
```

## Dependencies to Install

When ready to implement:

```bash
# For component testing
npm install --save-dev @testing-library/react @testing-library/jest-dom

# For E2E testing
npm install --save-dev @playwright/test @wordpress/e2e-test-utils-playwright
```

## Recommended Reading

- [Jest Documentation](https://jestjs.io/docs/getting-started)
- [Testing Library](https://testing-library.com/docs/react-testing-library/intro/)
- [WordPress Scripts Testing](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/#test-unit-js)
- [WordPress E2E Test Utils](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-e2e-test-utils-playwright/)
- [Playwright Documentation](https://playwright.dev/docs/intro)
