import { registerBlockType } from '@wordpress/blocks';
import edit from './edit.js';
import './editor.css';

registerBlockType('wsms/auth-form', {
    edit,
    save: () => null,

    transforms: {
        from: [
            {
                type: 'shortcode',
                tag: 'wsms_auth',
                attributes: {
                    view: {
                        type: 'string',
                        shortcode: ({ named: { view } }) => view || 'login',
                    },
                    formSlug: {
                        type: 'string',
                        shortcode: ({ named: { id } }) => id || '',
                    },
                    mode: {
                        type: 'string',
                        shortcode: ({ named: { mode } }) => mode || 'popup',
                    },
                    buttonText: {
                        type: 'string',
                        shortcode: ({ named: { text } }) => text || 'Sign In',
                    },
                },
            },
        ],
    },
});
