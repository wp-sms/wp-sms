import "./index.scss"
import { TextControl, TextareaControl } from '@wordpress/components';

wp.blocks.registerBlockType("wp-statistics-widgets/newsletter", {
  title: "Newsletter",
  icon: "admin-users",
  category: "wp-statistics-widgets",
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
    <div className="wp-statistics-widget">
      <h2 className="wp-statistics-widget__title">Newsletter</h2>
      <div className="wp-statistics-widget__main">
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
