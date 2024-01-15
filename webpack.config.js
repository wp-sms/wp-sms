const path = require('path');

module.exports = {
    mode: 'development',
    entry: {
        admin: [
            './assets/js/admin.js',
            './assets/js/admin-export.js',
            './assets/js/admin-privacy-data.js',
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
};