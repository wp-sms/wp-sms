import { registerBlockType } from '@wordpress/blocks';
import edit from './edit.js';

registerBlockType('wsms/subscription-form', {
    edit,
    save: () => null,

    transforms: {
        from: [
            {
                type: 'shortcode',
                tag: 'wsms_subscribe',
                attributes: {
                    formSlug: {
                        type: 'string',
                        shortcode: ({ named: { id } }) => id || '',
                    },
                },
            },
        ],
    },
});
