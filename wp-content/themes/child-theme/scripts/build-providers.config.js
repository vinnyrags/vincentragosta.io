const path = require('path');
const parentScss = path.resolve(__dirname, '..', '..', 'parent-theme', 'src', 'Providers', 'Theme', 'assets', 'scss');

module.exports = {
    sassImports: [
        path.join(parentScss, 'common', '_breakpoints.scss'),
    ],
    sassLoadPaths: [parentScss],
};
