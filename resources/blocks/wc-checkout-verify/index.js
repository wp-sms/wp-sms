import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';

registerBlockType(metadata.name, {
    edit: () => {
        return wp.element.createElement(
            'div',
            { className: 'wsms-checkout-verify-editor' },
            'WSMS: Email/Phone verification will appear here on the checkout page.',
        );
    },
    save: () => null,
});
