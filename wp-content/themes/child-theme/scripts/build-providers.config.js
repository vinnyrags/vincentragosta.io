const path = require('path');
const parentScss = path.resolve(__dirname, '..', '..', 'parent-theme', 'src', 'Providers', 'Theme', 'assets', 'scss');

module.exports = {
    sassLoadPaths: [parentScss],
};
