import { registerBlockType } from '@wordpress/blocks'

import './index.css'

import metadata from './block.json'
import edit from './edit'

registerBlockType(metadata, {
  /**
   * @see ./edit.js
   */
  edit,
})
