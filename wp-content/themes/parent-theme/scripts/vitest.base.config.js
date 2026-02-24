/**
 * Base Vitest Configuration
 *
 * Shared test configuration for WordPress themes built on this parent.
 * Provides jsdom environment, global test utilities, and a shared setup file
 * that mocks browser APIs not available in jsdom (matchMedia, IntersectionObserver)
 * and handles DOM cleanup between tests.
 *
 * Usage from a theme's vitest.config.js:
 *
 *   import { defineConfig } from 'vitest/config';
 *   import { baseTestConfig, parentThemeDir } from '../parent-theme/scripts/vitest.base.config.js';
 *
 *   export default defineConfig({
 *       server: { fs: { allow: [parentThemeDir] } },
 *       test: {
 *           ...baseTestConfig,
 *           include: ['tests/js/**\/*.test.js'],
 *       },
 *   });
 */

import { fileURLToPath } from 'url';
import path from 'path';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

/** Absolute path to the parent theme root (for Vite fs.allow in child themes) */
export const parentThemeDir = path.resolve(__dirname, '..');

export const baseTestConfig = {
    environment: 'jsdom',
    globals: true,
    passWithNoTests: true,
    setupFiles: [path.resolve(__dirname, 'test-setup.js')],
};
