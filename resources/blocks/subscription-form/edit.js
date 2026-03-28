import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function Edit({ attributes, setAttributes }) {
    const { formSlug } = attributes;
    const blockProps = useBlockProps();

    const [forms, setForms] = useState(null);
    const [formsError, setFormsError] = useState(false);

    useEffect(() => {
        setForms(null);
        setFormsError(false);

        const controller = new AbortController();

        apiFetch({ path: '/wsms/v1/subscription-forms', signal: controller.signal })
            .then((response) => {
                const list = response?.items ?? [];
                setForms(Array.isArray(list) ? list : []);
            })
            .catch((err) => {
                if (err.name === 'AbortError') return;
                setForms([]);
                setFormsError(true);
            });

        return () => controller.abort();
    }, []);

    const formOptions = [{ label: __('Select a form…', 'wp-sms'), value: '' }];
    if (forms) {
        forms.forEach((form) => {
            formOptions.push({ label: `${form.name} (${form.slug})`, value: form.slug });
        });
    }

    const selectedForm = forms?.find((f) => f.slug === formSlug);

    return (
        <div {...blockProps}>
            <InspectorControls>
                <PanelBody title={__('Form Settings', 'wp-sms')}>
                    <SelectControl
                        label={__('Subscription Form', 'wp-sms')}
                        value={formSlug}
                        options={formOptions}
                        onChange={(val) => setAttributes({ formSlug: val })}
                        help={
                            formsError
                                ? __('Could not load forms. Enter a slug manually below.', 'wp-sms')
                                : forms === null
                                  ? __('Loading forms…', 'wp-sms')
                                  : __('Select which subscription form to display', 'wp-sms')
                        }
                    />
                    {formsError && (
                        <TextControl
                            label={__('Form Slug', 'wp-sms')}
                            value={formSlug}
                            onChange={(val) => setAttributes({ formSlug: val })}
                            help={__('Enter the subscription form slug manually.', 'wp-sms')}
                        />
                    )}
                </PanelBody>
            </InspectorControls>

            <Placeholder icon="clipboard" label={__('Subscription Form', 'wp-sms')}>
                {forms === null ? (
                    <Spinner />
                ) : formSlug ? (
                    <p style={{ margin: 0 }}>
                        {selectedForm ? selectedForm.name : formSlug}
                        {' · '}
                        {selectedForm ? `${selectedForm.fields.length} ${__('fields', 'wp-sms')}` : ''}
                        {selectedForm?.double_optin ? ` · ${__('Double opt-in', 'wp-sms')}` : ''}
                    </p>
                ) : (
                    <p style={{ margin: 0, color: '#757575' }}>
                        {__('Select a subscription form in the block settings.', 'wp-sms')}
                    </p>
                )}
            </Placeholder>
        </div>
    );
}
