import {
    InspectorControls,
    useBlockProps,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { CheckboxControl, TextControl } from "@wordpress/components";

/**
 * Edit function.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function edit( { className, attributes, setAttributes } ) {
    const blockProps = useBlockProps();

    const handleRequiredChange = (isRequired) => {
        setAttributes({ isRequired });
    };

    const handleTextChange = (mobile) => {
        setAttributes({ mobile });
    };


    // Function to handle checkbox changes
    const handleCheckboxChange = (isChecked) => {
        setAttributes({ enabled: isChecked });
    };

    return ([
        <InspectorControls title={__('Mobile Field', 'wp-sms')}>
            <div style={{paddingLeft: 20}}>
                <CheckboxControl
                    label={__("Required", "wp-sms")}
                    checked={attributes.isRequired}
                    onChange={handleRequiredChange}
                />
            </div>
        </InspectorControls>,
        <div {...blockProps}>
            <TextControl
                label={__("Mobile", "wp-sms")} // Replace "text-domain" with your plugin/theme domain
                checked={attributes.enabled}
                onChange={handleCheckboxChange}
            />
        </div>
    ]);
}
