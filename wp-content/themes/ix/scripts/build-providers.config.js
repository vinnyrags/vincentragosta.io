const path = require('path');
const themeScss = path.resolve(__dirname, '..', 'src', 'Providers', 'Theme', 'assets', 'scss');
const nodeModules = path.resolve(__dirname, '..', 'node_modules');

module.exports = {
    sassLoadPaths: [themeScss, nodeModules],
};
