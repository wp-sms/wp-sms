const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = {
    mode:'production',
    entry: {
        admin: [
            './public/src/scripts/quick-reply.js',
            './public/src/scripts/admin-order-view.js',
            './public/src/scripts/postbox.js',
            './public/src/scripts/chart.min.js',
            './public/src/scripts/admin-dashboard-stats-widget.js',
        ],
        frontend: [
            './public/src/scripts/blocks.js',
        ],
    },
    output: {
        filename: '[name].min.js',
        path: path.resolve(__dirname, 'public/js'),
        publicPath: '/public/js/',
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
            directory: path.join(__dirname, 'public/js'),
        },
    },
    optimization: {
        minimizer: [
            new TerserPlugin({
                extractComments: false,
                terserOptions: {
                    format: {
                        comments: false,
                    },
                },
            }),
        ],
    }
};