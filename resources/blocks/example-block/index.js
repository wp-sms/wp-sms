import { registerBlockType } from '@wordpress/blocks';
import edit from './edit';

registerBlockType('wp-sms/example-block', {
    edit,
    save: () => null,
});
