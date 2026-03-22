import { useBlockProps, InspectorControls, RichText } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, Placeholder } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const VIEW_OPTIONS = [
    { label: 'Login', value: 'login' },
    { label: 'Register', value: 'register' },
    { label: 'Forgot Password', value: 'forgot-password' },
];

const MODE_OPTIONS = [
    { label: 'Popup', value: 'popup' },
    { label: 'Embed', value: 'embed' },
];

export default function Edit({ attributes, setAttributes }) {
    const { view, formSlug, mode, buttonText } = attributes;
    const blockProps = useBlockProps();

    const [forms, setForms] = useState(null);
    const [formsError, setFormsError] = useState(false);

    useEffect(() => {
        if (view !== 'register') {
            return;
        }

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
    }, [view]);

    const formOptions = [{ label: 'Default registration form', value: '' }];
    if (forms) {
        forms.forEach((form) => {
            formOptions.push({ label: form.name, value: form.slug });
        });
    }

    return (
        <div {...blockProps}>
            <InspectorControls>
                <PanelBody title="Form Settings">
                    <SelectControl
                        label="View"
                        value={view}
                        options={VIEW_OPTIONS}
                        onChange={(val) => setAttributes({ view: val })}
                    />
                    {view === 'register' && (
                        <>
                            <SelectControl
                                label="Registration Form"
                                value={formSlug}
                                options={formOptions}
                                onChange={(val) => setAttributes({ formSlug: val })}
                                help={
                                    formsError
                                        ? 'Could not load forms. You can type a form slug manually below.'
                                        : forms === null
                                          ? 'Loading forms…'
                                          : 'Create forms in WP SMS → Authentication → Registration Forms'
                                }
                            />
                            {formsError && (
                                <TextControl
                                    label="Form Slug"
                                    value={formSlug}
                                    onChange={(val) => setAttributes({ formSlug: val })}
                                    help="Enter the registration form slug manually."
                                />
                            )}
                        </>
                    )}
                    <SelectControl
                        label="Mode"
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
                        placeholder="Sign In"
                        allowedFormats={[]}
                    />
                </div>
            ) : (
                <ServerSideRender
                    block="wsms/auth-form"
                    attributes={attributes}
                    EmptyResponsePlaceholder={() => (
                        <Placeholder icon="lock" label="WSMS Auth Form">
                            <p>Configure this block in the sidebar.</p>
                        </Placeholder>
                    )}
                />
            )}
        </div>
    );
}
