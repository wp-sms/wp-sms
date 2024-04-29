const path = require('path');
const fs = require('fs');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

// Specify the base directory for blocks
const blocksBaseDir = 'assets/src/blocks/woocommerce';

// Automatically find all block folders
const includeFolders = fs.readdirSync(path.resolve(process.cwd(), blocksBaseDir))
    .map(dir => path.join(blocksBaseDir, dir));

// Remove SASS rule from the default config so we can define our own
const defaultRules = defaultConfig.module.rules.filter((rule) => {
    return String(rule.test) !== String(/\.(sc|sa)ss$/);
});

// Dynamically generate entry points from specified folders
const entryPoints = {};
const patterns = [];

includeFolders.forEach(folder => {
    const fullPath = path.resolve(process.cwd(), folder);
    const folderName = path.basename(folder); // Extract the last part of the folder path
    // Find all JS files and add to entry points, add block.json to patterns for copying
    fs.readdirSync(fullPath).forEach(file => {
        if (file.endsWith('.js')) {
            entryPoints[`${folderName}/${path.basename(file, '.js')}`] = path.resolve(fullPath, file);
        } else if (file === 'block.json') {
            patterns.push({
                from: path.resolve(fullPath, 'block.json'),
                to: path.resolve(process.cwd(), 'assets', 'blocks', 'woocommerce', folderName, 'block.json')
            });
        }
    });
});

module.exports = {
    ...defaultConfig,
    entry: entryPoints,
    output: {
        path: path.resolve(process.cwd(), 'assets', 'blocks', 'woocommerce'),
        filename: '[name].js', // Adjusted to use folderName for filename
    },
    module: {
        ...defaultConfig.module,
        rules: [
            ...defaultRules,
            {
                test: /\.(sc|sa)ss$/,
                exclude: /node_modules/,
                use: [
                    MiniCssExtractPlugin.loader,
                    { loader: 'css-loader', options: { importLoaders: 1 } },
                    {
                        loader: 'sass-loader',
                        options: {
                            sassOptions: {
                                includePaths: ['src/css'],
                            },
                            additionalData: (content, loaderContext) => {
                                const { resourcePath, rootContext } = loaderContext;
                                const relativePath = path.relative(rootContext, resourcePath);
                                if (relativePath.startsWith('src/css/')) {
                                    return content;
                                }
                                // Optionally prepend styles or variables to all .scss/.sass files
                                // return '@import "_colors"; ' + content;
                            },
                        },
                    },
                ],
            },
        ],
    },
    plugins: [
        ...defaultConfig.plugins.filter(
            plugin => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
        ),
        new WooCommerceDependencyExtractionWebpackPlugin(),
        new MiniCssExtractPlugin({
            filename: '[name].css',  // Adjusted to use folderName for CSS files
        }),
        new CopyWebpackPlugin({
            patterns: patterns
        })
    ],
};
