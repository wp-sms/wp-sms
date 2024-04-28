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

function checkForFrontendJS(srcPath) {
    const blockFolders = getFolders(srcPath);
    const entries = {};
    blockFolders.forEach(folder => {
        const indexJSPath = path.join(srcPath, folder, 'index.js');
        const frontendJSPath = path.join(srcPath, "./" + folder, 'frontend.js');
        if (fs.existsSync(indexJSPath)) {
            entries[folder] = "./" + indexJSPath;
        }
        if (fs.existsSync(frontendJSPath)) {
            entries[folder + 'Frontend'] = "./" + frontendJSPath;
        }
    });
    return entries;
}

const blockEntries = checkForFrontendJS(blocksDir);
console.log(blockEntries)
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
console.log(entry)
module.exports = {
    mode: 'production',
    entry: entry,
    output: {
        path: path.resolve(__dirname, 'assets/src/blocks'),
        filename: (pathData) => {
            const blockName = pathData.chunk.name;
            if (blockName.endsWith('Frontend')) {
                // Correct the folder name by removing 'Frontend' and specify the frontend.js file directly
                return `${blockName.replace('Frontend', '')}/frontend.js`;
            } else {
                // Keep other files output as before
                return `${blockName}/index.js`;
            }
        },
        publicPath: '/assets/src/blocks/',
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
                test: /\.css$/,
                use: [MiniCssExtractPlugin.loader, 'css-loader']
            },
            {
                test: /\.svg$/,
                use: 'svg-inline-loader'
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
            outputFilename: '[name]/index.asset.php'
        }),
        new CopyPlugin({
            patterns: Object.keys(blockEntries)
                .filter(blockName => !blockName.endsWith('Frontend')) // Filter out the 'Frontend' entries
                .map(blockName => ({
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
