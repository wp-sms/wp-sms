import {__} from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
} from '@wordpress/block-editor';

import {TextControl, TextareaControl} from '@wordpress/components';

const SubscribeIcon = () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="hsl(24, 95%, 38%)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
        <circle cx="9" cy="7" r="4" stroke="hsl(24, 95%, 38%)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
        <line x1="19" y1="8" x2="19" y2="14" stroke="hsl(24, 95%, 38%)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
        <line x1="22" y1="11" x2="16" y2="11" stroke="hsl(24, 95%, 38%)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
    </svg>
);

/**
 * Edit function.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function edit( { attributes, setAttributes } ) {

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

    return (
        <>
            <InspectorControls>
            </InspectorControls>
            <div {...useBlockProps()}>
                <div className="wp-sms-block wp-sms-block--subscribe">
                    <div className="wp-sms-block__header">
                        <SubscribeIcon />
                        <span>{__('Subscribe', 'wp-sms')}</span>
                    </div>
                    <div className="wp-sms-block__main">
                        <TextControl
                            __next40pxDefaultSize
                            __nextHasNoMarginBottom
                            label={__('Title', 'wp-sms')}
                            value={title}
                            onChange={onChangeTitle}
                        />
                        <TextareaControl
                            __nextHasNoMarginBottom
                            label={__('Description', 'wp-sms')}
                            value={description}
                            onChange={onChangeDescription}
                        />
                    </div>
                    <div className="wp-sms-block__preview">
                        <div className="wp-sms-block__preview-label">{__('Your Name', 'wp-sms')}</div>
                        <div className="wp-sms-block__preview-input"></div>
                        <div className="wp-sms-block__preview-label">{__('Phone Number', 'wp-sms')}</div>
                        <div className="wp-sms-block__preview-input"></div>
                        <div className="wp-sms-block__preview-button">{__('Subscribe', 'wp-sms')}</div>
                    </div>
                </div>
            </div>
        </>
    );
}
