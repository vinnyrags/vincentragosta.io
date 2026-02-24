import { defineConfig } from 'vitest/config';
import { baseTestConfig } from './scripts/vitest.base.config.js';

export default defineConfig({
    test: {
        ...baseTestConfig,
        include: ['tests/js/**/*.test.js'],
    },
});
