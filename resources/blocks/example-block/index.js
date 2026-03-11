import { registerBlockType } from '@wordpress/blocks';
import edit from './edit.js';

registerBlockType('wp-sms/example-block', {
    edit,
    save: () => null,
});
