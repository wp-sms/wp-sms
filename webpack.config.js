import defaultConfig from '@wordpress/scripts/config/webpack.config.js';
import CopyWebpackPlugin from 'copy-webpack-plugin';
import { fileURLToPath } from 'url';
import { dirname, resolve } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

export default {
    ...defaultConfig,
    entry: {
        'Subscribe/index': resolve(__dirname, 'resources/legacy/blocks/Subscribe', 'index.js'),
        'SendSms/index': resolve(__dirname, 'resources/legacy/blocks/SendSms', 'index.js'),
    },
    output: {
        ...defaultConfig.output,
        path: resolve(__dirname, 'public/blocks'),
    },
    plugins: [
        ...defaultConfig.plugins,
        new CopyWebpackPlugin({
            patterns: [
                {
                    from: resolve(__dirname, 'resources/legacy/blocks/Subscribe/block.json'),
                    to: resolve(__dirname, 'public/blocks/Subscribe/block.json'),
                },
                {
                    from: resolve(__dirname, 'resources/legacy/blocks/SendSms/block.json'),
                    to: resolve(__dirname, 'public/blocks/SendSms/block.json'),
                },
            ],
        }),
    ],
};
