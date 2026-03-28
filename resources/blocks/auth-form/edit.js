import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const VIEW_OPTIONS = [
    { label: __('Login', 'wp-sms'), value: 'login' },
    { label: __('Register', 'wp-sms'), value: 'register' },
    { label: __('Forgot Password', 'wp-sms'), value: 'forgot-password' },
];

const MODE_OPTIONS = [
    { label: __('Popup', 'wp-sms'), value: 'popup' },
    { label: __('Embed', 'wp-sms'), value: 'embed' },
];

export default function Edit({ attributes, setAttributes }) {
    const { view, formSlug, mode, buttonText } = attributes;
    const blockProps = useBlockProps();

    const [forms, setForms] = useState(null);
    const [formsError, setFormsError] = useState(false);

    useEffect(() => {
        setForms(null);
        setFormsError(false);

        const controller = new AbortController();

        apiFetch({ path: '/wsms/v1/auth/admin/registration-forms', signal: controller.signal })
            .then((response) => {
                const list = response?.items ?? [];
                setForms(Array.isArray(list) ? list : []);
                setFormsError(false);
            })
            .catch((err) => {
                if (err.name === 'AbortError') return;
                setForms([]);
                setFormsError(true);
            });

        return () => controller.abort();
    }, []);

    const formOptions = [{ label: __('Default form', 'wp-sms'), value: '' }];
    if (forms) {
        forms.forEach((form) => {
            formOptions.push({ label: form.name, value: form.slug });
        });
    }

    return (
        <div {...blockProps}>
            <InspectorControls>
                <PanelBody title={__('Form Settings', 'wp-sms')}>
                    <SelectControl
                        label={__('View', 'wp-sms')}
                        value={view}
                        options={VIEW_OPTIONS}
                        onChange={(val) => setAttributes({ view: val, formSlug: '' })}
                    />
                    <SelectControl
                        label={__('Form', 'wp-sms')}
                        value={formSlug}
                        options={formOptions}
                        onChange={(val) => setAttributes({ formSlug: val })}
                        help={
                            formsError
                                ? __('Could not load forms. You can type a form slug manually below.', 'wp-sms')
                                : forms === null
                                  ? __('Loading forms…', 'wp-sms')
                                  : __('Apply custom branding and redirect from a form', 'wp-sms')
                        }
                    />
                    {formsError && (
                        <TextControl
                            label={__('Form Slug', 'wp-sms')}
                            value={formSlug}
                            onChange={(val) => setAttributes({ formSlug: val })}
                            help={__('Enter the form slug manually.', 'wp-sms')}
                        />
                    )}
                    <SelectControl
                        label={__('Mode', 'wp-sms')}
                        value={mode}
                        options={MODE_OPTIONS}
                        onChange={(val) => setAttributes({ mode: val })}
                    />
                </PanelBody>
            </InspectorControls>

            {mode === 'popup' ? (
                <div className="wp-block-button">
                    <RichText
                        tagName="div"
                        className="wsms-auth-trigger wp-block-button__link wp-element-button"
                        value={buttonText}
                        onChange={(val) => setAttributes({ buttonText: val })}
                        placeholder={__('Sign In', 'wp-sms')}
                        allowedFormats={[]}
                    />
                </div>
            ) : (
                <Placeholder icon="lock" label={`${__('Auth Form', 'wp-sms')} — ${VIEW_OPTIONS.find(o => o.value === view)?.label ?? view}`}>
                    <p style={{ margin: 0 }}>
                        {formSlug ? `${__('Form', 'wp-sms')}: ${formSlug}` : __('Default form', 'wp-sms')}
                        {' · '}{__('Embedded', 'wp-sms')}
                    </p>
                </Placeholder>
            )}
        </div>
    );
}
