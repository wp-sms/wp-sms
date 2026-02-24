import {__} from '@wordpress/i18n';
import {
    useBlockProps,
    InspectorControls,
} from '@wordpress/block-editor';

import {useState, useEffect} from '@wordpress/element';

import {
    TextControl,
    TextareaControl,
    ToggleControl,
    RadioControl,
    SelectControl,
    PanelBody,
} from '@wordpress/components';

const SmsIcon = () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" stroke="hsl(24, 95%, 38%)" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
    </svg>
);

/**
 * Edit function.
 *
 * @see https://developer.wordpress.org/block-editor/developers/block-api/block-edit-save/#edit
 *
 * @return {WPElement} Element to render.
 */
export default function edit({attributes, setAttributes}) {
    // Destructure your attributes
    const {title, description, onlyLoggedUsers, userRole, maxCharacters, receiver, subscriberGroup} = attributes;

    // Define states
    const [showSubscriberGroup, setShowSubscriberGroup] = useState(receiver == 'subscribers');
    const [showUserRoles, setShowUserRoles] = useState(onlyLoggedUsers);

    // Handlers to update attributes
    const onChangeTitle = (val) => setAttributes({title: val});
    const onChangeDescription = (val) => setAttributes({description: val});
    const onChangeUserRole = (val) => setAttributes({userRole: val});
    const onChangeMaxCharacters = (val) => setAttributes({maxCharacters: val});
    const onChangeSubscriberGroup = (val) => setAttributes({subscriberGroup: val});
    const onChangeReceiver = function (val) {
        val == 'subscribers' ? setShowSubscriberGroup(true) : setShowSubscriberGroup(false);
        setAttributes({receiver: val});
    }
    const toggleLoggedUsers = function (val) {
        val ? setShowUserRoles(true) : setShowUserRoles(false);
        setAttributes({onlyLoggedUsers: !onlyLoggedUsers})
    }

    // Define the controls for block settings
    const blockSettings = (
        <InspectorControls>
            <PanelBody title={__('Settings', 'wp-sms')} initialOpen={true}>
                <ToggleControl
                    label={__('Only for logged in users', 'wp-sms')}
                    checked={onlyLoggedUsers}
                    onChange={toggleLoggedUsers}
                />
                {showUserRoles && (
                    <RadioControl
                        label={__('Select Role', 'wp-sms')}
                        selected={userRole}
                        options={wpSmsSendSmsBlockData.userRoleOptions}
                        onChange={onChangeUserRole}
                    />
                )}
                <TextControl
                    label={__('Max Characters', 'wp-sms')}
                    value={maxCharacters}
                    onChange={onChangeMaxCharacters}
                    type="number"
                />
                <SelectControl
                    label={__('Receiver', 'wp-sms')}
                    value={receiver}
                    options={[
                        {label: 'Custom Number', value: 'numbers'},
                        {label: 'Admin', value: 'admin'},
                        {label: 'Subscribers', value: 'subscribers'},
                    ]}
                    onChange={onChangeReceiver}
                />

                {showSubscriberGroup && (
                    <SelectControl
                        label={__('Subscriber Group', 'wp-sms')}
                        value={subscriberGroup}
                        options={wpSmsSendSmsBlockData.subscriberGroups}
                        onChange={onChangeSubscriberGroup}
                    />
                )}
            </PanelBody>
        </InspectorControls>
    );

    return (
        <>
            {blockSettings}
            <div {...useBlockProps()}>
                <div className="wp-sms-block wp-sms-block--sendSms">
                    <div className="wp-sms-block__header">
                        <SmsIcon />
                        <span>{__('Send SMS', 'wp-sms')}</span>
                    </div>
                    <div className="wp-sms-block__main">
                        <TextControl
                            label={__('Title', 'wp-sms')}
                            value={title}
                            onChange={onChangeTitle}
                        />
                        <TextareaControl
                            label={__('Description', 'wp-sms')}
                            value={description}
                            onChange={onChangeDescription}
                        />
                    </div>
                    <div className="wp-sms-block__preview">
                        <div className="wp-sms-block__preview-label">{__('Message', 'wp-sms')}</div>
                        <div className="wp-sms-block__preview-textarea"></div>
                        {receiver === 'numbers' && (
                            <>
                                <div className="wp-sms-block__preview-label">{__('Receiver', 'wp-sms')}</div>
                                <div className="wp-sms-block__preview-input"></div>
                            </>
                        )}
                        <div className="wp-sms-block__preview-button">{__('Send Message', 'wp-sms')}</div>
                    </div>
                </div>
            </div>
        </>
    );
}
