import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl, Placeholder, Spinner } from '@wordpress/components';
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

    const formOptions = [{ label: 'Select a form…', value: '' }];
    if (forms) {
        forms.forEach((form) => {
            formOptions.push({ label: `${form.name} (${form.slug})`, value: form.slug });
        });
    }

    const selectedForm = forms?.find((f) => f.slug === formSlug);

    return (
        <div {...blockProps}>
            <InspectorControls>
                <PanelBody title="Form Settings">
                    <SelectControl
                        label="Subscription Form"
                        value={formSlug}
                        options={formOptions}
                        onChange={(val) => setAttributes({ formSlug: val })}
                        help={
                            formsError
                                ? 'Could not load forms. Enter a slug manually below.'
                                : forms === null
                                  ? 'Loading forms…'
                                  : 'Select which subscription form to display'
                        }
                    />
                    {formsError && (
                        <TextControl
                            label="Form Slug"
                            value={formSlug}
                            onChange={(val) => setAttributes({ formSlug: val })}
                            help="Enter the subscription form slug manually."
                        />
                    )}
                </PanelBody>
            </InspectorControls>

            <Placeholder icon="clipboard" label="Subscription Form">
                {forms === null ? (
                    <Spinner />
                ) : formSlug ? (
                    <p style={{ margin: 0 }}>
                        {selectedForm ? selectedForm.name : formSlug}
                        {' · '}
                        {selectedForm ? `${selectedForm.fields.length} fields` : ''}
                        {selectedForm?.double_optin ? ' · Double opt-in' : ''}
                    </p>
                ) : (
                    <p style={{ margin: 0, color: '#757575' }}>
                        Select a subscription form in the block settings.
                    </p>
                )}
            </Placeholder>
        </div>
    );
}
