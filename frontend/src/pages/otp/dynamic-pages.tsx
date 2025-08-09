import { useParams } from 'react-router-dom';

const OtpDynamicPages = () => {
    const { name } = useParams();

    const renderContent = () => {
        switch (name) {
            case 'activities':
                return (
                    <div className="p-6">
                        <h2 className="text-2xl font-bold mb-4">OTP Activities</h2>
                        <p className="text-gray-600">This is the activities page content. Coming soon...</p>
                    </div>
                );
            case 'logs':
                return (
                    <div className="p-6">
                        <h2 className="text-2xl font-bold mb-4">OTP Logs</h2>
                        <p className="text-gray-600">This is the logs page content. Coming soon...</p>
                    </div>
                );
            case 'channels':
                return (
                    <div className="p-6">
                        <h2 className="text-2xl font-bold mb-4">OTP Channels</h2>
                        <p className="text-gray-600">This is the channels page content. Coming soon...</p>
                    </div>
                );
            case 'brandings':
                return (
                    <div className="p-6">
                        <h2 className="text-2xl font-bold mb-4">OTP Brandings</h2>
                        <p className="text-gray-600">This is the brandings page content. Coming soon...</p>
                    </div>
                );
            case 'settings':
                return (
                    <div className="p-6">
                        <h2 className="text-2xl font-bold mb-4">OTP Settings</h2>
                        <p className="text-gray-600">This is the settings page content. Coming soon...</p>
                    </div>
                );
            default:
                return (
                    <div className="p-6">
                        <h2 className="text-2xl font-bold mb-4">Page Not Found</h2>
                        <p className="text-gray-600">The requested page "{name}" was not found.</p>
                    </div>
                );
        }
    };

    return renderContent();
};

export default OtpDynamicPages;
