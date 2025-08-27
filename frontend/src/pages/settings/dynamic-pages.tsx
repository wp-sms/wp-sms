import { SettingsDynamicForm } from '@/components/settings';
import { SettingsFormActions } from '@/components/settings/form-actions';
import { Form } from '@/components/ui/form';
import { useStableCallback } from '@/core/hooks';
import { useGetGroupSchema, useGetGroupValues } from '@/models/settings';
import { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { useParams } from 'react-router-dom';

const SettingsDynamicPages = () => {
  const { name } = useParams();

  const {
    data: groupSchema,
    isLoading: isGroupSchemaLoading,
    isRefetching: isGroupSchemaRefetching,
  } = useGetGroupSchema({
    params: {
      groupName: name ?? 'general',
    },
  });

  const {
    data: groupValues,
    isLoading: isGroupValuesLoading,
    isRefetching: isGroupValuesRefetching,
  } = useGetGroupValues({
    params: {
      groupName: name ?? 'general',
    },
  });

  const form = useForm({
    defaultValues: {},
  });

  const initForm = useStableCallback(async () => {
    if (groupValues?.data && groupSchema?.data) {
      form.reset(groupValues?.data ?? {});
    }
  }, [groupValues?.data, groupSchema?.data, form]);

  useEffect(() => {
    initForm();
  }, [groupValues?.data, groupSchema?.data]);

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
