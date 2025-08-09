import { Bell, Shield } from 'lucide-react';

export const OtpHeader: React.FC = () => {
    return (
        <header className="border-b border-border bg-white px-6 py-4">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-x-3">
                    <div className="size-8 rounded-lg bg-primary flex items-center justify-center">
                        <Shield className="text-white" size={18} />
                    </div>
                    <div>
                        <h1 className="text-lg font-semibold text-gray-900">OTP Management</h1>
                        <p className="text-sm text-gray-500">Manage your OTP settings and activities</p>
                    </div>
                </div>

                <div className="flex items-center gap-x-3">
                    <button className="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <Bell className="text-gray-600" size={18} />
                    </button>
                </div>
            </div>
        </header>
    );
};
