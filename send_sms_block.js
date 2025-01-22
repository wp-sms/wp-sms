
(() => {
    "use strict";
    const { registerBlockType } = window.wp.blocks;
    const { __ } = window.wp.i18n;
    const { InspectorControls, useBlockProps } = window.wp.blockEditor;
    const { useState, Fragment } = window.wp.element;
    const { PanelBody, ToggleControl, RadioControl, TextControl, SelectControl, TextareaControl } = window.wp.components;

    const blockMetadata = JSON.parse(`
        {
            "$schema": "https://schemas.wp.org/trunk/block.json",
            "apiVersion": 2,
            "name": "wp-sms-blocks/send-sms",
            "title": "Send SMS",
            "category": "wp-sms-blocks",
            "editorScript": "file:./index.js",
            "editorStyle": "file:./index.css",
            "attributes": {
                "title": { "type": "string" },
                "description": { "type": "string" },
                "onlyLoggedUsers": { "type": "boolean", "default": false },
                "userRole": { "type": "string", "default": "subscriber" },
                "maxCharacters": { "type": "number", "default": 160 },
                "receiver": { "type": "string", "default": "numbers" },
                "subscriberGroup": { "type": "string" }
            },
            "example": {
                "attributes": {
                    "title": "Send SMS",
                    "description": "Please use this form for sending SMS"
                }
            }
        }
    `);

    registerBlockType(blockMetadata, {
        icon: (
            <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
            >
                <path
                    d="M6.74 17.58C6.36 17.58 6.05 17.9 6.05 18.29C6.05 18.68 6.36 19 6.74 19V17.58Z..."
                    fill="#F88E40"
                />
            </svg>
        ),

        edit({ attributes, setAttributes }) {
            const {
                title,
                description,
                onlyLoggedUsers,
                userRole,
                maxCharacters,
                receiver,
                subscriberGroup,
            } = attributes;

            const [isSubscriber, setIsSubscriber] = useState(receiver === "subscribers");
            const [loggedInOnly, setLoggedInOnly] = useState(onlyLoggedUsers);

            const controls = (
                <InspectorControls>
                    <PanelBody title={__("Settings", "wp-sms")} initialOpen={true}>
                        <ToggleControl
                            label={__("Only for logged in users", "wp-sms")}
                            checked={loggedInOnly}
                            onChange={(value) => {
                                setLoggedInOnly(value);
                                setAttributes({ onlyLoggedUsers: value });
                            }}
                        />
                        {loggedInOnly && (
                            <RadioControl
                                label={__("Select Role", "wp-sms")}
                                selected={userRole}
                                options={wpSmsSendSmsBlockData.userRoleOptions}
                                onChange={(value) => setAttributes({ userRole: value })}
                            />
                        )}
                        <TextControl
                            label={__("Max Characters", "wp-sms")}
                            value={maxCharacters}
                            type="number"
                            onChange={(value) => setAttributes({ maxCharacters: value })}
                        />
                        <SelectControl
                            label={__("Receiver", "wp-sms")}
                            value={receiver}
                            options={[
                                { label: "Custom Number", value: "numbers" },
                                { label: "Admin", value: "admin" },
                                { label: "Subscribers", value: "subscribers" },
                            ]}
                            onChange={(value) => {
                                setIsSubscriber(value === "subscribers");
                                setAttributes({ receiver: value });
                            }}
                        />
                        {isSubscriber && (
                            <SelectControl
                                label={__("Subscriber Group", "wp-sms")}
                                value={subscriberGroup}
                                options={wpSmsSendSmsBlockData.subscriberGroups}
                                onChange={(value) => setAttributes({ subscriberGroup: value })}
                            />
                        )}
                    </PanelBody>
                </InspectorControls>
            );

            return (
                <Fragment>
                    {controls}
                    <div {...useBlockProps()}>
                        <div className="wp-sms-block wp-sms-block--sendSms">
                            <h2 className="wp-sms-block__title">Send SMS</h2>
                            <div className="wp-sms-block__main">
                                <TextControl
                                    label={__("Title", "wp-sms")}
                                    value={title}
                                    onChange={(value) => setAttributes({ title: value })}
                                />
                                <TextareaControl
                                    label={__("Description", "wp-sms")}
                                    value={description}
                                    onChange={(value) => setAttributes({ description: value })}
                                />
                            </div>
                        </div>
                    </div>
                </Fragment>
            );
        },
    });
})();
