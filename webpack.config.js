const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
    mode:'production',
    entry: {
        admin: [
            './assets/js/quick-reply.js',
            './assets/js/import-subscriber.js',
            './assets/js/admin.js',
            './assets/js/admin-export.js',
            './assets/js/admin-send-sms.js',
            './assets/js/edit-subscriber.js',
            './assets/js/edit-group.js',
            './assets/js/admin-privacy-data.js',
            './assets/js/admin-order-view.js',
            './assets/js/admin-dashboard-stats-widget.js',
        ],
        frontend: [
            './assets/js/blocks.js',
        ],
    },
    output: {
        filename: '[name].min.js',
        path: path.resolve(__dirname, 'assets/src/scripts'),
        publicPath: '/assets/src/scripts/',
    },
    optimization: {
        minimizer: [new TerserPlugin()],
    }
};