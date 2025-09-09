import { Shield } from 'lucide-react';
import { useParams } from 'react-router-dom';

import ChannelsPage from './channels-page';

const OtpDynamicPages = () => {
    const { name } = useParams();

    const renderContent = () => {
        switch (name) {
            case 'activities':
                return (
                    <div className="p-6">
                        <div className="mb-6">
                            <div className="flex items-center gap-x-2 border border-border p-4 rounded-lg">
                                <div className="flex items-center gap-3">
                                    <div className="size-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                        <Shield className="text-gray-600" size={20} />
                                    </div>
                                    <div>
                                        <h2 className="text-xl font-bold text-gray-900">OTP Activities</h2>
                                        <p className="text-sm text-gray-500">Monitor and track OTP activities and usage</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                            <p className="text-gray-600">This is the activities page content. Coming soon...</p>
                        </div>
                    </div>
                );
            case 'logs':
                return (
                    <div className="p-6">
                        <div className="mb-6">
                            <div className="flex items-center gap-x-2 border border-border p-4 rounded-lg">
                                <div className="flex items-center gap-3">
                                    <div className="size-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                        <Shield className="text-gray-600" size={20} />
                                    </div>
                                    <div>
                                        <h2 className="text-xl font-bold text-gray-900">OTP Logs</h2>
                                        <p className="text-sm text-gray-500">View detailed logs of OTP operations and events</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                            <p className="text-gray-600">This is the logs page content. Coming soon...</p>
                        </div>
                    </div>
                );
            case 'channels':
                return <ChannelsPage />;
            case 'brandings':
                return (
                    <div className="p-6">
                        <div className="mb-6">
                            <div className="flex items-center gap-x-2 border border-border p-4 rounded-lg">
                                <div className="flex items-center gap-3">
                                    <div className="size-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                        <Shield className="text-gray-600" size={20} />
                                    </div>
                                    <div>
                                        <h2 className="text-xl font-bold text-gray-900">OTP Brandings</h2>
                                        <p className="text-sm text-gray-500">Customize OTP branding and appearance</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                            <p className="text-gray-600">This is the brandings page content. Coming soon...</p>
                        </div>
                    </div>
                );
            case 'settings':
                return (
                    <div className="p-6">
                        <div className="mb-6">
                            <div className="flex items-center gap-x-2 border border-border p-4 rounded-lg">
                                <div className="flex items-center gap-3">
                                    <div className="size-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                        <Shield className="text-gray-600" size={20} />
                                    </div>
                                    <div>
                                        <h2 className="text-xl font-bold text-gray-900">OTP Settings</h2>
                                        <p className="text-sm text-gray-500">Configure OTP system settings and preferences</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                            <p className="text-gray-600">This is the settings page content. Coming soon...</p>
                        </div>
                    </div>
                );
            default:
                return (
                    <div className="p-6">
                        <div className="mb-6">
                            <div className="flex items-center gap-x-2 border border-border p-4 rounded-lg">
                                <div className="flex items-center gap-3">
                                    <div className="size-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                        <Shield className="text-gray-600" size={20} />
                                    </div>
                                    <div>
                                        <h2 className="text-xl font-bold text-gray-900">Page Not Found</h2>
                                        <p className="text-sm text-gray-500">The requested page was not found</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                            <p className="text-gray-600">The requested page "{name}" was not found.</p>
                        </div>
                    </div>
                );
        }
    };

    return renderContent();
};

export default OtpDynamicPages;
