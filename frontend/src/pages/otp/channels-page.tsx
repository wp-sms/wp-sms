import { AlertCircle, Save, Settings, Shield } from 'lucide-react';
import React, { useState } from 'react';

import { ChannelSettingsSidebar } from './channel-settings-sidebar';

interface Channel {
    id: string;
    name: string;
    enabled: boolean;
    required: boolean;
}

const ChannelsPage: React.FC = () => {
    const [channels, setChannels] = useState<Channel[]>([
        { id: 'username', name: 'Username', enabled: false, required: false },
        { id: 'password', name: 'Password', enabled: false, required: false },
        { id: 'phone', name: 'Phone Number', enabled: false, required: true },
        { id: 'email', name: 'Email', enabled: false, required: true },
    ]);

    const [selectedChannel, setSelectedChannel] = useState<Channel | null>(null);
    const [showSettings, setShowSettings] = useState(false);
    const [hasChanges, setHasChanges] = useState(false);

    const handleChannelToggle = (channelId: string) => {
        const updatedChannels = channels.map(channel => 
            channel.id === channelId 
                ? { ...channel, enabled: !channel.enabled }
                : channel
        );
        setChannels(updatedChannels);
        setHasChanges(true);
    };

    const handleSettingsClick = (channel: Channel) => {
        setSelectedChannel(channel);
        setShowSettings(true);
    };

    const closeSettings = () => {
        setShowSettings(false);
        setSelectedChannel(null);
    };

    const handleSave = () => {
        // Validate that at least one required channel (phone or email) is enabled
        const requiredChannels = channels.filter(channel => channel.required);
        const hasRequiredChannel = requiredChannels.some(channel => channel.enabled);

        if (!hasRequiredChannel) {
            alert('At least one of Phone Number or Email must be selected.');
            return;
        }

        // TODO: Save to backend
        console.log('Saving channels:', channels);
        setHasChanges(false);
        alert('Channels saved successfully!');
    };

    const requiredChannels = channels.filter(channel => channel.required);
    const hasRequiredChannel = requiredChannels.some(channel => channel.enabled);

    return (
        <div className="p-6 relative">
            {/* Page Header Card */}
            <div className="mb-6">
                <div className="flex items-center gap-x-2 border border-border p-4 rounded-lg">
                    <div className="flex items-center gap-3">
                        <div className="size-10 rounded-lg bg-gray-100 flex items-center justify-center">
                            <Shield className="text-gray-600" size={20} />
                        </div>
                        <div>
                            <h2 className="text-xl font-bold text-gray-900">OTP Channels</h2>
                            <p className="text-sm text-gray-500">Configure which channels are available for OTP delivery</p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Save Button */}
            <div className="flex justify-end mb-6">
                <button
                    onClick={handleSave}
                    disabled={!hasChanges || !hasRequiredChannel}
                    className={`flex items-center gap-2 px-6 py-3 rounded-lg font-medium transition-colors ${
                        hasChanges && hasRequiredChannel
                            ? 'bg-primary text-white hover:bg-primary/90 shadow-sm'
                            : 'bg-gray-200 text-gray-500 cursor-not-allowed'
                    }`}
                >
                    <Save size={16} />
                    Save Changes
                </button>
            </div>

            {/* Validation Warning */}
            {!hasRequiredChannel && (
                <div className="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div className="flex items-center gap-2 text-red-700">
                        <AlertCircle size={16} />
                        <span className="font-medium">Required Channel Missing</span>
                    </div>
                    <p className="text-red-600 mt-1 text-sm">
                        At least one of Phone Number or Email must be selected for OTP delivery.
                    </p>
                </div>
            )}

            {/* Channels Configuration Card */}
            <div className="bg-white border border-gray-200 rounded-lg shadow-sm">
                <div className="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 className="text-lg font-semibold text-gray-900">Available Channels</h3>
                    <p className="text-sm text-gray-600 mt-1">Select which channels should be available for OTP delivery</p>
                </div>
                <div className="divide-y divide-gray-200">
                    {channels.map((channel) => (
                        <div key={channel.id} className="px-6 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors">
                            <div className="flex items-center gap-3">
                                <input
                                    type="checkbox"
                                    id={channel.id}
                                    checked={channel.enabled}
                                    onChange={() => handleChannelToggle(channel.id)}
                                    className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary focus:ring-2"
                                />
                                <label 
                                    htmlFor={channel.id} 
                                    className="text-sm font-medium text-gray-900 cursor-pointer"
                                >
                                    {channel.name}
                                    {channel.required && (
                                        <span className="ml-1 text-red-500">*</span>
                                    )}
                                </label>
                            </div>
                            <button
                                onClick={() => handleSettingsClick(channel)}
                                className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                title={`Configure ${channel.name} settings`}
                            >
                                <Settings size={16} />
                            </button>
                        </div>
                    ))}
                </div>
            </div>

            {/* Settings Sidebar */}
            {showSettings && selectedChannel && (
                <ChannelSettingsSidebar
                    channel={selectedChannel}
                    onClose={closeSettings}
                />
            )}
        </div>
    );
};

export default ChannelsPage;
