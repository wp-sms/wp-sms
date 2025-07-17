import { useGetGroupSchema } from '@/models/groups';

const GeneralSettings = () => {
    const { data } = useGetGroupSchema({
        params: {
            groupName: 'general',
        },
    });

    console.log(data);

    return (
        <div className="p-6">
            <h1 className="text-2xl font-bold mb-4">General Settings</h1>
            <p className="mb-4">Manage your application settings here.</p>
        </div>
    );
};

export default GeneralSettings;
