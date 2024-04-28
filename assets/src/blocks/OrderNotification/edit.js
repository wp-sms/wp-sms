import {
    InspectorControls,
    useBlockProps,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { CheckboxControl } from "@wordpress/components";

/**
 * Edit function.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function edit( { className, attributes, setAttributes } ) {
    const blockProps = useBlockProps();

    // Function to handle checkbox changes
    const handleCheckboxChange = (isChecked) => {
        setAttributes({ enabled: isChecked });
    };

    return ([
        <InspectorControls>
            {/* Place any additional controls here */}
        </InspectorControls>,
        <div {...blockProps}>
            <CheckboxControl
                label={__("I would like to get notification about any change in my order via SMS.", "wp-sms")} // Replace "text-domain" with your plugin/theme domain
                checked={attributes.enabled}
                onChange={handleCheckboxChange}
            />
        </div>
    ]);
}
