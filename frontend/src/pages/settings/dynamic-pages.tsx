import { ControlledFieldRenderer } from '@/components/form/controlled-field-renderer';
import { FieldLabel } from '@/components/form/label';
import { DynamicFieldsSkeleton } from '@/components/settings';
import { Button } from '@/components/ui/button';
import { CustomSkeleton } from '@/components/ui/custom-skeleton';
import { Form } from '@/components/ui/form';
import { useGetGroupSchema, useGetGroupValues } from '@/models/settings';
import { useCallback, useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { useParams } from 'react-router-dom';

const SettingsDynamicPages = () => {
    const { name } = useParams();

    const {
        data: groupSchema,
        promise: groupSchemaPromise,
        isLoading: isGroupSchemaLoading,
        isRefetching: isGroupSchemaRefetching,
    } = useGetGroupSchema({
        params: {
            groupName: name ?? 'general',
        },
    });

    const {
        promise: groupValuesPromise,
        isLoading: isGroupValuesLoading,
        isRefetching: isGroupValuesRefetching,
    } = useGetGroupValues({
        params: {
            groupName: name ?? 'general',
        },
    });

    const form = useForm({
        defaultValues: async () => {
            try {
                const [groupSchemaRes, groupValuesRes] = await Promise.all([groupSchemaPromise, groupValuesPromise]);

                return groupValuesRes?.data ?? {};
            } catch (error) {
                return {};
            }
        },
    });

    const formValues = form.watch();

    const isFormDirty = useMemo(() => {
        const defaultValues = form.formState.defaultValues ?? {};

        for (const key in defaultValues) {
            const defaultValue = defaultValues?.[key];
            const currentValue = formValues[key];

            if ((defaultValue === null && currentValue === '') || (defaultValue === '' && currentValue === null)) {
                continue;
            }

            if (defaultValue !== currentValue) {
                return true;
            }
        }
        return false;
    }, [form.formState.defaultValues, formValues]);

    const handleReset = useCallback(() => {
        form.reset(form.formState.defaultValues);
    }, [form]);

    return (
        <div className="p-6">
            <Form {...form}>
                <div className="flex flex-col gap-y-4">
                    {isGroupSchemaLoading || isGroupValuesLoading ? (
                        <DynamicFieldsSkeleton />
                    ) : (
                        groupSchema?.data?.sections?.map((section, idx) => {
                            return (
                                <section key={`${section?.id}-${idx}`} className="border border-border p-4 rounded-lg">
                                    <h3>{section?.title}</h3>

                                    <div className="flex flex-col gap-y-8 max-w-2xl">
                                        {section?.fields?.map((field) => {
                                            const shouldShow = Object.entries(field?.showIf ?? {}).every(
                                                ([key, expectedValue]) => {
                                                    return formValues[key] === expectedValue;
                                                }
                                            );

                                            const shouldHide = Object.entries(field?.hideIf ?? {}).some(
                                                ([key, expectedValue]) => {
                                                    return formValues[key] === expectedValue;
                                                }
                                            );

                                            if (!shouldShow || shouldHide || Boolean(field?.hidden)) {
                                                return null;
                                            }

                                            return (
                                                <ControlledFieldRenderer
                                                    isLoading={isGroupSchemaRefetching || isGroupValuesRefetching}
                                                    key={`section-${section?.id}-field-${field?.key}`}
                                                    schema={field}
                                                />
                                            );
                                        })}
                                    </div>
                                </section>
                            );
                        })
                    )}

                    <div className="flex items-center gap-x-3">
                        <Button disabled={!isFormDirty} type="submit">
                            Save Changes
                        </Button>

                        <Button disabled={!isFormDirty} type="button" variant="secondary" onClick={handleReset}>
                            Reset
                        </Button>
                    </div>
                </div>
            </Form>
        </div>
    );
};

export default SettingsDynamicPages;
