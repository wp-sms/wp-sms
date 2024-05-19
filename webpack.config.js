const path = require('path');
const fs = require('fs');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const CopyPlugin = require('copy-webpack-plugin');

// Helper function to get folders in a directory
function getFolders(srcPath) {
    return fs.readdirSync(srcPath).filter(file => fs.statSync(path.join(srcPath, file)).isDirectory());
}

const blocksDir = './assets/src/blocks';
const blockEntries = {
    'SendSms': './assets/src/blocks/SendSms/index.js',
    'Subscribe': './assets/src/blocks/Subscribe/index.js',
    // Add more blocks as needed
};

// Static entries for admin and frontend
const staticAssets = {
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
    ]
};

const entry = { ...staticAssets, ...blockEntries };

module.exports = {
    mode:'production',
    entry: entry,
    output: {
        path: path.resolve(__dirname, 'assets/blocks'),
        filename: (pathData) => {
            // Do not create separate folders for admin and frontend
            return Object.keys(blockEntries).includes(pathData.chunk.name) ? `${pathData.chunk.name}/index.js` : `${pathData.chunk.name}.min.js`;
        },
        publicPath: '/assets/blocks/',
    },

    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env', '@babel/preset-react'],
                    },
                },
            },

            {
                test: /\.css$/, // Add this rule for CSS files
                use: [MiniCssExtractPlugin.loader, 'css-loader']
            },
            {
                test: /\.svg$/,
                use: 'svg-inline-loader'  // This loader transforms SVGs into inline SVGs
            }
        ],
    },
    plugins: [
        new MiniCssExtractPlugin({
            filename: '[name]/index.css'
        }),
        new DependencyExtractionWebpackPlugin({
            injectPolyfill: true,
            outputFormat: 'php',
            combineAssets: false,
            outputFilename: (entryName) => {
                const isStatic = entryName.chunk.name !== "admin" || entryName.chunk.name !== "frontend";
                return isStatic ? 'dev/null/[name].asset.php' : '[name]/index.asset.php';
            }
        }),
        new CopyPlugin({
            patterns: Object.keys(blockEntries).map(blockName => ({
                from: path.resolve(__dirname, blocksDir, blockName, 'block.json'),
                to: path.resolve(__dirname, 'assets/blocks', blockName)
            }))
        })
    ],
    devServer: {
        static: {
            directory: path.join(__dirname, 'assets/js'),
        },
    },
    optimization: {
        minimizer: [new TerserPlugin()],
    }
};
