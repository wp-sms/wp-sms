import "./index.scss"
import { CheckboxControl } from '@wordpress/components';

wp.blocks.registerBlockType("wp-statistics-widgets/newsletter", {
  title: "Newsletter",
  icon: "admin-users",
  category: "wp-statistics-widgets",
  attributes: {
    showLoggedUsers: { type: "boolean" },
  },
  edit: EditComponent,
  save: function () {
    return null
  }
})

function EditComponent(props) {
  return (
    <div className="wp-statistics-widget">
      <h2 className="wp-statistics-widget__title">Visitors</h2>
      <div className="wp-statistics-widget__main">
        <CheckboxControl
          label="Show Logged Users"
          help="Show all visitors include logged users."
          checked={ props.attributes.showLoggedUsers }
          onChange={ (e) => {props.setAttributes( {showLoggedUsers: e})} }
        />
      </div>
    </div>
  )
}
