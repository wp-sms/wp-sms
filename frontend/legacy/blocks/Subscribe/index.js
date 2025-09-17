/**
 * Subscribe
 */
import { registerBlockType } from '@wordpress/blocks';

import "./index.css"

/**
 * Internal dependencies
 */
import edit from './edit';

import metadata from './block.json';

registerBlockType( metadata,
    {
     
        /**
         * @see ./edit.js
         */
        edit,
    } );
