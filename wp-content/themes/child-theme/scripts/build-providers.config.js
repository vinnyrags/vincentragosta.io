const path = require('path');
const parentScss = path.resolve(__dirname, '..', '..', 'ix', 'src', 'Providers', 'Theme', 'assets', 'scss');
const childScss = path.resolve(__dirname, '..', 'src', 'Providers', 'Theme', 'assets', 'scss');

module.exports = {
    sassLoadPaths: [parentScss, childScss],
};
