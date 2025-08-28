import React from 'react';
import { X, Settings } from 'lucide-react';
import type { SchemaField } from '@/models/settings/types/getGroupSchema';
import { ControlledFieldRenderer } from '@/components/form/controlled-field-renderer';
import { useFormContext } from 'react-hook-form';
import { clientRequest } from '@/core/config';

interface SubFieldsSidebarProps {
    fieldName: string;
    fieldLabel: string;
    subFields: SchemaField[];
    isOpen: boolean;
    onClose: () => void;
    onSave: (values: Record<string, any>) => void;
}

export const SubFieldsSidebar: React.FC<SubFieldsSidebarProps> = ({
    fieldName,
    fieldLabel,
    subFields,
    isOpen,
    onClose,
    onSave
}) => {
    const { getValues, formState } = useFormContext();

    const handleSave = async () => {
        try {
            // Get all form values and filter for subFields
            const allValues = getValues();
            const subFieldValues: Record<string, any> = {};
            
            // Collect all subfield values
            subFields.forEach(field => {
                if (allValues[field.key] !== undefined) {
                    subFieldValues[field.key] = allValues[field.key];
                }
            });

            console.log('Saving subfields as individual fields:', subFieldValues);

            // Send individual subfield values to the save settings API
            await clientRequest.put('/settings/save', subFieldValues);

            // Call the onSave callback with the collected values
            onSave(subFieldValues);
            
            // Close the sidebar
            onClose();
        } catch (error) {
            console.error('Error saving subfields:', error);
            // You might want to show an error toast here
        }
    };

    if (!isOpen) return null;

    return (
        <>
            {/* Backdrop */}
            <div 
                className="fixed inset-0 backdrop-blur-sm z-40"
                onClick={onClose}
            />
            
            {/* Sidebar */}
            <div className="fixed right-0 top-0 h-full w-96 bg-white shadow-xl z-50 transform translate-x-0 transition-transform duration-300 ease-in-out">
                <div className="flex flex-col h-full">
                    {/* Header */}
                    <div className="flex items-center justify-between p-6 border-b border-gray-200">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-primary/10 rounded-lg">
                                <Settings className="text-primary" size={20} />
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900">
                                    {fieldLabel} Settings
                                </h3>
                                <p className="text-sm text-gray-500">Configure additional options</p>
                            </div>
                        </div>
                        <button
                            onClick={onClose}
                            className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                        >
                            <X size={20} />
                        </button>
                    </div>

                    {/* Content */}
                    <div className="flex-1 overflow-y-auto p-6">
                        <div className="space-y-6">
                            {subFields.map((field) => (
                                <div key={field.key} className="space-y-3">
                                    <ControlledFieldRenderer
                                        schema={field}
                                        isLoading={false}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="p-6 border-t border-gray-200">
                        <div className="flex gap-3">
                            <button
                                onClick={onClose}
                                className="flex-1 px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg font-medium transition-colors"
                            >
                                Cancel
                            </button>
                            <button
                                onClick={handleSave}
                                disabled={formState.isSubmitting}
                                className="flex-1 px-4 py-2 bg-primary text-white hover:bg-primary/90 rounded-lg font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {formState.isSubmitting ? 'Saving...' : 'Save Settings'}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};
