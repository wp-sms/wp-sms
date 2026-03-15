const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    resolve: {
        ...defaultConfig.resolve,
    },
    externals: {
        ...defaultConfig.externals,
        // WooCommerce packages → window globals.
        '@woocommerce/blocks-checkout': ['wc', 'blocksCheckout'],
        '@woocommerce/block-data': ['wc', 'wcBlocksData'],
        '@woocommerce/settings': ['wc', 'wcSettings'],
        '@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
    },
};
