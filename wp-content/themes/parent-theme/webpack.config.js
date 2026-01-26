const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

// Path to breakpoints file
const breakpointsPath = path.resolve(__dirname, 'assets/src/scss/common/_breakpoints.scss');

// Clone the default config
const config = {
  ...defaultConfig,
  module: {
    ...defaultConfig.module,
    rules: defaultConfig.module.rules.map((rule) => {
      // Check if this is the SCSS rule
      if (rule.test && rule.test.toString().includes('(sc|sa)ss')) {
        return {
          ...rule,
          use: rule.use.map((loader) => {
            // Find sass-loader and add additionalData
            if (
              typeof loader === 'object' &&
              loader.loader &&
              loader.loader.includes('sass-loader')
            ) {
              return {
                ...loader,
                options: {
                  ...loader.options,
                  additionalData: `@use "${breakpointsPath.replace(/\\/g, '/')}" as *;\n`,
                },
              };
            }
            return loader;
          }),
        };
      }
      return rule;
    }),
  },
};

module.exports = config;
