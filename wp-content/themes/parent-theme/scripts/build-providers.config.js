const path = require('path');
const themeScss = path.resolve(__dirname, '..', 'src', 'Providers', 'Theme', 'assets', 'scss');

module.exports = {
    sassLoadPaths: [themeScss],
};
