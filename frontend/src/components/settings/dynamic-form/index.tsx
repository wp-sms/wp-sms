import { useWatch } from 'react-hook-form';
import type { SettingsDynamicFormProps } from './types';
import { ControlledFieldRenderer } from '@/components/form/controlled-field-renderer';
import { DynamicFieldsSkeleton } from '../dynamic-fields-skeleton';
import { SettingsGroupTitle } from '../group-title';
import { SettingsSectionTitle } from '../section-title';

export const SettingsDynamicForm: React.FC<SettingsDynamicFormProps> = ({
    groupSchema,
    isInitialLoading,
    isRefreshing,
}) => {
    const formValues = useWatch();

    if (isInitialLoading) {
        return <DynamicFieldsSkeleton />;
    }

    if (!groupSchema) {
        return null;
    }

    return (
        <div className="flex flex-col gap-y-4">
            <SettingsGroupTitle label={groupSchema?.label} icon={groupSchema?.icon} />

            {groupSchema?.sections?.map((section, idx) => {
                return (
                    <section
                        key={`${section?.id}-${idx}`}
                        className="flex flex-col gap-y-4 border border-border p-4 rounded-lg"
                    >
                        <SettingsSectionTitle title={section?.title} subtitle={section?.subtitle} />

                        <div className="flex flex-col gap-y-8 max-w-2xl">
                            {section?.fields?.map((field) => {
                                const shouldShow = Object.entries(field?.showIf ?? {}).every(([key, expectedValue]) => {
                                    return formValues[key] === expectedValue;
                                });

                                const shouldHide = Object.entries(field?.hideIf ?? {}).some(([key, expectedValue]) => {
                                    return formValues[key] === expectedValue;
                                });

                                if (!shouldShow || shouldHide || Boolean(field?.hidden)) {
                                    return null;
                                }

                                return (
                                    <ControlledFieldRenderer
                                        isLoading={isRefreshing}
                                        key={`section-${section?.id}-field-${field?.key}`}
                                        schema={field}
                                    />
                                );
                            })}
                        </div>
                    </section>
                );
            })}
        </div>
    );
};
