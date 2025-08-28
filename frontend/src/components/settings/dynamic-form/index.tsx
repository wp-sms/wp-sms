import { useWatch } from 'react-hook-form';
import { useState } from 'react';
import type { SettingsDynamicFormProps } from './types';
import { ControlledFieldRenderer } from '@/components/form/controlled-field-renderer';
import { DynamicFieldsSkeleton } from '../dynamic-fields-skeleton';
import { SettingsGroupTitle } from '../group-title';
import { SubFieldsSidebar } from '../subfields-sidebar';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle } from 'lucide-react';
import type { SchemaField } from '@/models/settings/types/getGroupSchema';

export const SettingsDynamicForm: React.FC<SettingsDynamicFormProps> = ({
    groupSchema,
    isInitialLoading,
    isRefreshing,
}) => {
    const formValues = useWatch();
    
    // State for subFields sidebar
    const [subFieldsSidebar, setSubFieldsSidebar] = useState<{
        isOpen: boolean;
        fieldName: string;
        fieldLabel: string;
        subFields: SchemaField[];
    }>({
        isOpen: false,
        fieldName: '',
        fieldLabel: '',
        subFields: []
    });

    const handleSubFieldsClick = (fieldName: string, subFields: SchemaField[]) => {
        const field = groupSchema?.sections
            ?.flatMap(section => section.fields)
            ?.find(field => field.key === fieldName);
            
        setSubFieldsSidebar({
            isOpen: true,
            fieldName,
            fieldLabel: field?.label || fieldName,
            subFields
        });
    };

    const closeSubFieldsSidebar = () => {
        setSubFieldsSidebar({
            isOpen: false,
            fieldName: '',
            fieldLabel: '',
            subFields: []
        });
    };

    const handleSubFieldsSave = (values: Record<string, any>) => {
        // Handle saving subFields values
        console.log('Saving subFields for', subFieldsSidebar.fieldName, ':', values);
        // Here you can implement the actual saving logic
        closeSubFieldsSidebar();
    };

    if (isInitialLoading) {
        return <DynamicFieldsSkeleton />;
    }

    if (!groupSchema) {
        return (
            <Alert>
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>No settings schema available.</AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="flex flex-col gap-y-4">
            <SettingsGroupTitle label={groupSchema?.label} icon={groupSchema?.icon} />

            {groupSchema?.sections?.map((section, idx) => {
                return (
                    <Card key={`${section?.id}-${idx}`} className="flex flex-col gap-y-4">
                        <CardHeader>
                            <CardHeader>
                                <CardTitle>{section?.title}</CardTitle>

                                <CardDescription>{section?.subtitle}</CardDescription>
                            </CardHeader>
                        </CardHeader>

                        <CardContent className="flex flex-col gap-y-8 max-w-3xl">
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
                                        onSubFieldsClick={handleSubFieldsClick}
                                    />
                                );
                            })}
                        </CardContent>
                    </Card>
                );
            })}

            {/* SubFields Sidebar */}
            {subFieldsSidebar.isOpen && (
                <SubFieldsSidebar
                    fieldName={subFieldsSidebar.fieldName}
                    fieldLabel={subFieldsSidebar.fieldLabel}
                    subFields={subFieldsSidebar.subFields}
                    isOpen={subFieldsSidebar.isOpen}
                    onClose={closeSubFieldsSidebar}
                    onSave={handleSubFieldsSave}
                />
            )}
        </div>
    );
};
