const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require('path');

module.exports = {
    ...defaultConfig,
    resolve: {
        ...defaultConfig.resolve,
        alias: {
            ...defaultConfig.resolve.alias,
            '@sidebar/components': path.resolve(__dirname, 'src/components/'),
            '@sidebar/hooks': path.resolve(__dirname, 'src/hooks/'),
            '@sidebar/icons': path.resolve(__dirname, 'src/svg/')
        }
    },
};