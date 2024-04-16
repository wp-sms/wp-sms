import {
    InspectorControls,
    useBlockProps,
} from '@wordpress/block-editor';


import { __ } from '@wordpress/i18n';

/**
 * Edit function.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function edit( { className, attributes, setAttributes } ) {
    const blockProps = useBlockProps();

    return ([
        <InspectorControls>
        </InspectorControls>
        ,
        <div {...blockProps}>
            <div className={'example-fields'}>
                <p>Hello World</p>
            </div>
        </div>
    ]);
}