const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
    mode:'production',
    entry: {
        admin: [
            './assets/src/scripts/quick-reply.js',
            './assets/src/scripts/import-subscriber.js',
            './assets/src/scripts/admin.js',
            './assets/src/scripts/admin-export.js',
            './assets/src/scripts/admin-send-sms.js',
            './assets/src/scripts/edit-subscriber.js',
            './assets/src/scripts/edit-group.js',
            './assets/src/scripts/admin-privacy-data.js',
            './assets/src/scripts/admin-order-view.js',
            './assets/src/scripts/admin-dashboard-stats-widget.js',
            './assets/src/scripts/chart.min.js',
        ],
        frontend: [
            './assets/src/scripts/blocks.js',
        ],
    },
    output: {
        filename: '[name].min.js',
        path: path.resolve(__dirname, 'assets/js'),
        publicPath: '/assets/js/',
    },
    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env'],
                    },
                },
            },
        ],
    },
    devServer: {
        static: {
            directory: path.join(__dirname, 'assets/js'),
        },
    },
    optimization: {
        minimizer: [new TerserPlugin()],
    }
};