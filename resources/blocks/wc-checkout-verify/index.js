import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

registerBlockType(metadata.name, {
    edit: () => {
        return wp.element.createElement(
            'div',
            { className: 'wsms-checkout-verify-editor' },
            __('WSMS: Email/Phone verification will appear here on the checkout page.', 'wp-sms'),
        );
    },
    save: () => null,
});
