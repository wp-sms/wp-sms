import { SettingsDynamicForm } from '@/components/settings';
import { SettingsFormActions } from '@/components/settings/form-actions';
import { Form } from '@/components/ui/form';
import { useGetGroupSchema, useGetGroupValues } from '@/models/settings';
import { useForm } from 'react-hook-form';
import { useParams } from 'react-router-dom';

const SettingsDynamicPages = () => {
    const { name } = useParams();

    // get data for render ui of dynamic form
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

    // get data to fill default values of form
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
                // wait for both promises to resolve so we can make sure that the form is filled with the correct values
                const [groupSchemaRes, groupValuesRes] = await Promise.all([groupSchemaPromise, groupValuesPromise]);

                return groupValuesRes?.data ?? {};
            } catch (error) {
                return {};
            }
        },
    });

    console.log(form.watch());

    return (
        <div className="p-6">
            <Form {...form}>
                <div className="flex flex-col gap-y-4">
                    <SettingsDynamicForm
                        groupSchema={groupSchema?.data}
                        isInitialLoading={isGroupSchemaLoading || isGroupValuesLoading}
                        isRefreshing={isGroupSchemaRefetching || isGroupValuesRefetching}
                    />

                    <SettingsFormActions />
                </div>
            </Form>
        </div>
    );
};

export default SettingsDynamicPages;
