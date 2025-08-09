import React from 'react';
import { X, Settings } from 'lucide-react';

interface Channel {
    id: string;
    name: string;
    enabled: boolean;
    required: boolean;
}

interface ChannelSettingsSidebarProps {
    channel: Channel;
    onClose: () => void;
}

export const ChannelSettingsSidebar: React.FC<ChannelSettingsSidebarProps> = ({
    channel,
    onClose
}) => {
    const renderChannelSettings = () => {
        switch (channel.id) {
            case 'username':
                return (
                    <div className="space-y-6">
                        <div>
                            <h4 className="text-sm font-medium text-gray-900 mb-2">Username Settings</h4>
                            <p className="text-sm text-gray-600">Configure username-based OTP delivery settings.</p>
                        </div>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Username Field Label
                                </label>
                                <input
                                    type="text"
                                    defaultValue="Username"
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Validation Rules
                                </label>
                                <select className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option>Standard validation</option>
                                    <option>Custom validation</option>
                                </select>
                            </div>
                        </div>
                    </div>
                );

            case 'password':
                return (
                    <div className="space-y-6">
                        <div>
                            <h4 className="text-sm font-medium text-gray-900 mb-2">Password Settings</h4>
                            <p className="text-sm text-gray-600">Configure password-based OTP delivery settings.</p>
                        </div>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Password Field Label
                                </label>
                                <input
                                    type="text"
                                    defaultValue="Password"
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Security Level
                                </label>
                                <select className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option>Standard</option>
                                    <option>Enhanced</option>
                                    <option>Maximum</option>
                                </select>
                            </div>
                        </div>
                    </div>
                );

            case 'phone':
                return (
                    <div className="space-y-6">
                        <div>
                            <h4 className="text-sm font-medium text-gray-900 mb-2">Phone Number Settings</h4>
                            <p className="text-sm text-gray-600">Configure phone number-based OTP delivery settings.</p>
                        </div>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Phone Field Label
                                </label>
                                <input
                                    type="text"
                                    defaultValue="Phone Number"
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Default Country Code
                                </label>
                                <select className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option>+1 (US/Canada)</option>
                                    <option>+44 (UK)</option>
                                    <option>+91 (India)</option>
                                    <option>+86 (China)</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    SMS Provider
                                </label>
                                <select className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option>Twilio</option>
                                    <option>Vonage</option>
                                    <option>Custom Provider</option>
                                </select>
                            </div>
                        </div>
                    </div>
                );

            case 'email':
                return (
                    <div className="space-y-6">
                        <div>
                            <h4 className="text-sm font-medium text-gray-900 mb-2">Email Settings</h4>
                            <p className="text-sm text-gray-600">Configure email-based OTP delivery settings.</p>
                        </div>
                        <div className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Email Field Label
                                </label>
                                <input
                                    type="text"
                                    defaultValue="Email Address"
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Email Template
                                </label>
                                <select className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option>Default Template</option>
                                    <option>Custom Template</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    From Email
                                </label>
                                <input
                                    type="email"
                                    defaultValue="noreply@example.com"
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                />
                            </div>
                        </div>
                    </div>
                );

            default:
                return (
                    <div className="space-y-6">
                        <div>
                            <h4 className="text-sm font-medium text-gray-900 mb-2">Channel Settings</h4>
                            <p className="text-sm text-gray-600">Configure settings for this channel.</p>
                        </div>
                        <div className="text-sm text-gray-500">
                            Settings for this channel will be implemented soon.
                        </div>
                    </div>
                );
        }
    };

    return (
        <>
            {/* Backdrop */}
            <div 
                className="fixed inset-0 backdrop-blur-sm z-40"
                onClick={onClose}
            />
            
            {/* Sidebar */}
            <div className="fixed right-0 top-0 h-full w-96 bg-white shadow-xl z-50 transform translate-x-0 transition-transform duration-300 ease-in-out animate-slide-in">
                <div className="flex flex-col h-full">
                    {/* Header */}
                    <div className="flex items-center justify-between p-6 border-b border-gray-200">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-primary/10 rounded-lg">
                                <Settings className="text-primary" size={20} />
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900">
                                    {channel.name} Settings
                                </h3>
                                <p className="text-sm text-gray-500">Configure channel options</p>
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
                        {renderChannelSettings()}
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
                                className="flex-1 px-4 py-2 bg-primary text-white hover:bg-primary/90 rounded-lg font-medium transition-colors"
                            >
                                Save Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
};
