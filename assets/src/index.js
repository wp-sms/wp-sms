import "./index.scss"
import { TextControl, TextareaControl } from '@wordpress/components';

wp.blocks.registerBlockType("wp-sms-blocks/newsletter", {
  title: "Newsletter",
  icon: "admin-users",
  category: "wp-sms-blocks",
  attributes: {
    title: { type: 'string' },
    description: { type: 'string' },
  },
  edit: EditComponent,
  save: function () {
    return null
  }
})

function EditComponent(props) {
  return (
    <div className="wp-sms-block">
      <h2 className="wp-sms-block__title">Newsletter</h2>
      <div className="wp-sms-block__main">
        <TextControl
          label="Title"
          value={ props.attributes.title }
          onChange={ (e) => {props.setAttributes( {title: e})} }
        />
        <TextareaControl
          label="Description"
          value={ props.attributes.description }
          onChange={ (e) => {props.setAttributes( {description: e})} }
        />
      </div>
    </div>
  )
}
