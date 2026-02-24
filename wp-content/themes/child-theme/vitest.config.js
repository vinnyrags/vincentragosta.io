import { defineConfig } from 'vitest/config';
import { baseTestConfig, parentThemeDir } from '../parent-theme/scripts/vitest.base.config.js';

export default defineConfig({
    server: { fs: { allow: [parentThemeDir] } },
    test: {
        ...baseTestConfig,
        include: ['tests/js/**/*.test.js'],
    },
});
