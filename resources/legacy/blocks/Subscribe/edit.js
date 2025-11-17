import {__} from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
} from '@wordpress/block-editor';

import {TextControl, TextareaControl} from '@wordpress/components';

/**
 * Edit function.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function edit( { className, attributes, setAttributes } ) {

    const { title, description } = attributes;

    const onChangeTitle = ( val ) => {
        setAttributes({
            title: val,
        })
    };

    const onChangeDescription = ( val ) => {
        setAttributes({
            description: val,
        })
    };

    const TitleTextInput = () => {
        return (
            <TextControl
                label={__('Title', 'wp-sms')}
                value={title}
                onChange={ onChangeTitle }
            />
        );
    };

    const DescriptionTextareaInput = () => {
        return (
            <TextareaControl
                label={__('Description', 'wp-sms')}
                value={description}
                onChange={ onChangeDescription }
            />
        );
    };

    return ( [
        <InspectorControls>
        </InspectorControls>
        ,
        <div {...useBlockProps()}>
            <div className="wp-sms-block wp-sms-block--subscribe">
                <h2 className="wp-sms-block__title">Subscribe</h2>
                <div className="wp-sms-block__main">
                    {TitleTextInput()}
                    {DescriptionTextareaInput()}
                </div>
            </div>
        </div>
    ] );
}
