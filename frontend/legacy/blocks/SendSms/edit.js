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
                    <h2 className="wp-sms-block__title">Send SMS</h2>
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
                        {/* Add more inputs and controls as needed */}
                    </div>
                </div>
            </div>
        </>
    );
}
