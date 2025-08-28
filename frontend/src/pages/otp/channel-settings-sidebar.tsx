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
                            <p className="text-sm text-gray-600">Configure username-based verification settings.</p>
                        </div>
                        <div className="space-y-6">
                            {/* Verification Method */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-3">
                                    Verification Method
                                </label>
                                <div className="space-y-3">
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="username-magic-link"
                                            name="username-verification-method"
                                            value="magic-link"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="username-magic-link" className="ml-2 text-sm text-gray-700">
                                            Magic Link
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="username-otp"
                                            name="username-verification-method"
                                            value="otp"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="username-otp" className="ml-2 text-sm text-gray-700">
                                            OTP
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="username-both"
                                            name="username-verification-method"
                                            value="both"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="username-both" className="ml-2 text-sm text-gray-700">
                                            Both OTP and Magic Link
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {/* Username Requirements */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Username Requirements</h5>
                                
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Minimum Length
                                        </label>
                                        <input
                                            type="number"
                                            min="3"
                                            max="10"
                                            defaultValue="3"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Maximum Length
                                        </label>
                                        <input
                                            type="number"
                                            min="5"
                                            max="30"
                                            defaultValue="20"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        Allowed Characters
                                    </label>
                                    <div className="space-y-2">
                                        <div className="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="username-alphanumeric"
                                                checked
                                                className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                            />
                                            <label htmlFor="username-alphanumeric" className="ml-2 text-sm text-gray-700">
                                                Alphanumeric (a-z, A-Z, 0-9)
                                            </label>
                                        </div>
                                        <div className="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="username-underscores"
                                                className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                            />
                                            <label htmlFor="username-underscores" className="ml-2 text-sm text-gray-700">
                                                Underscores (_)
                                            </label>
                                        </div>
                                        <div className="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="username-hyphens"
                                                className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                            />
                                            <label htmlFor="username-hyphens" className="ml-2 text-sm text-gray-700">
                                                Hyphens (-)
                                            </label>
                                        </div>
                                        <div className="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="username-dots"
                                                className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                            />
                                            <label htmlFor="username-dots" className="ml-2 text-sm text-gray-700">
                                                Dots (.)
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div className="space-y-3">
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="username-unique"
                                            checked
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="username-unique" className="ml-2 text-sm text-gray-700">
                                            Require unique username
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="username-availability-check"
                                            checked
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="username-availability-check" className="ml-2 text-sm text-gray-700">
                                            Real-time availability check
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {/* Security Settings */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Security Settings</h5>
                                
                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="username-case-sensitive"
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="username-case-sensitive" className="ml-2 text-sm text-gray-700">
                                        Case sensitive usernames
                                    </label>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Reserved Words (Blacklist)
                                    </label>
                                    <textarea
                                        rows={3}
                                        placeholder="admin, root, system, test, demo"
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                    />
                                    <p className="text-xs text-gray-500 mt-1">Comma-separated list of reserved usernames</p>
                                </div>
                            </div>

                            {/* Sign Up Settings */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Sign Up Settings</h5>
                                
                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="username-required-signup"
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="username-required-signup" className="ml-2 text-sm text-gray-700">
                                        Required at sign up
                                    </label>
                                </div>

                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="username-verify-signup"
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="username-verify-signup" className="ml-2 text-sm text-gray-700">
                                        Verify at sign up
                                    </label>
                                </div>

                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="username-allow-signin"
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="username-allow-signin" className="ml-2 text-sm text-gray-700">
                                        Allow to sign in
                                    </label>
                                </div>
                            </div>

                            {/* Username Configuration */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Username Configuration</h5>
                                
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
                            </div>
                        </div>
                    </div>
                );

            case 'password':
                return (
                    <div className="space-y-6">
                        <div>
                            <h4 className="text-sm font-medium text-gray-900 mb-2">Password Settings</h4>
                            <p className="text-sm text-gray-600">Configure password-based verification settings.</p>
                        </div>
                        <div className="space-y-6">
                            {/* Verification Method */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-3">
                                    Verification Method
                                </label>
                                <div className="space-y-3">
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="password-magic-link"
                                            name="password-verification-method"
                                            value="magic-link"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="password-magic-link" className="ml-2 text-sm text-gray-700">
                                            Magic Link
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="password-otp"
                                            name="password-verification-method"
                                            value="otp"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="password-otp" className="ml-2 text-sm text-gray-700">
                                            OTP
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="password-both"
                                            name="password-verification-method"
                                            value="both"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="password-both" className="ml-2 text-sm text-gray-700">
                                            Both OTP and Magic Link
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {/* Password Requirements */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Password Requirements</h5>
                                
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Minimum Length
                                        </label>
                                        <input
                                            type="number"
                                            min="8"
                                            max="20"
                                            defaultValue="8"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Maximum Length
                                        </label>
                                        <input
                                            type="number"
                                            min="10"
                                            max="128"
                                            defaultValue="128"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-3">
                                        Complexity Requirements
                                    </label>
                                    <div className="space-y-2">
                                        <div className="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="password-uppercase"
                                                checked
                                                className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                            />
                                            <label htmlFor="password-uppercase" className="ml-2 text-sm text-gray-700">
                                                Require uppercase letters (A-Z)
                                            </label>
                                        </div>
                                        <div className="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="password-lowercase"
                                                checked
                                                className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                            />
                                            <label htmlFor="password-lowercase" className="ml-2 text-sm text-gray-700">
                                                Require lowercase letters (a-z)
                                            </label>
                                        </div>
                                        <div className="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="password-numbers"
                                                checked
                                                className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                            />
                                            <label htmlFor="password-numbers" className="ml-2 text-sm text-gray-700">
                                                Require numbers (0-9)
                                            </label>
                                        </div>
                                        <div className="flex items-center">
                                            <input
                                                type="checkbox"
                                                id="password-special"
                                                className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                            />
                                            <label htmlFor="password-special" className="ml-2 text-sm text-gray-700">
                                                Require special characters (!@#$%^&*)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Password Validation */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Password Validation</h5>
                                
                                <div className="space-y-3">
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="password-common-blacklist"
                                            checked
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="password-common-blacklist" className="ml-2 text-sm text-gray-700">
                                            Block common passwords
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="password-personal-info"
                                            checked
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="password-personal-info" className="ml-2 text-sm text-gray-700">
                                            Prevent personal info (username, email)
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="password-sequential"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="password-sequential" className="ml-2 text-sm text-gray-700">
                                            Block sequential characters (123, abc)
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="password-repeated"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="password-repeated" className="ml-2 text-sm text-gray-700">
                                            Limit repeated characters (aaa, 111)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {/* Security Features */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Security Features</h5>
                                
                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="password-strength-meter"
                                        checked
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="password-strength-meter" className="ml-2 text-sm text-gray-700">
                                        Show password strength meter
                                    </label>
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Password Expiry (days)
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            max="365"
                                            defaultValue="90"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                        />
                                        <p className="text-xs text-gray-500 mt-1">0 = No expiry</p>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Password History
                                        </label>
                                        <input
                                            type="number"
                                            min="0"
                                            max="20"
                                            defaultValue="5"
                                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                        />
                                        <p className="text-xs text-gray-500 mt-1">Prevent reuse of last N passwords</p>
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Account Lockout (failed attempts)
                                    </label>
                                    <input
                                        type="number"
                                        min="3"
                                        max="10"
                                        defaultValue="5"
                                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                    />
                                </div>
                            </div>

                            {/* User Experience */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">User Experience</h5>
                                
                                <div className="space-y-3">
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="password-show-toggle"
                                            checked
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="password-show-toggle" className="ml-2 text-sm text-gray-700">
                                            Show/hide password toggle
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="password-confirmation"
                                            checked
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="password-confirmation" className="ml-2 text-sm text-gray-700">
                                            Require password confirmation
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="password-hints"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="password-hints" className="ml-2 text-sm text-gray-700">
                                            Allow password hints (optional)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {/* Sign Up Settings */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Sign Up Settings</h5>
                                
                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="password-required-signup"
                                        checked
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="password-required-signup" className="ml-2 text-sm text-gray-700">
                                        Required at sign up
                                    </label>
                                </div>

                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="password-verify-signup"
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="password-verify-signup" className="ml-2 text-sm text-gray-700">
                                        Verify at sign up
                                    </label>
                                </div>

                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="password-allow-signin"
                                        checked
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="password-allow-signin" className="ml-2 text-sm text-gray-700">
                                        Allow to sign in
                                    </label>
                                </div>
                            </div>

                            {/* Password Configuration */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Password Configuration</h5>
                                
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
                    </div>
                );

            case 'phone':
                return (
                    <div className="space-y-6">
                        <div>
                            <h4 className="text-sm font-medium text-gray-900 mb-2">Phone Number Settings</h4>
                            <p className="text-sm text-gray-600">Configure phone number-based verification settings.</p>
                        </div>
                        <div className="space-y-6">
                            {/* Smart Auth */}
                            <div>
                                <div className="flex items-center justify-between mb-3">
                                    <label className="block text-sm font-medium text-gray-700">
                                        Smart Auth
                                    </label>
                                    <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Coming Soon
                                    </span>
                                </div>
                                <p className="text-xs text-gray-500 mb-3">Automatically switch between delivery channels on failure</p>
                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="smart-auth"
                                        disabled
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                                    />
                                    <label htmlFor="smart-auth" className="ml-2 text-sm text-gray-500">
                                        Enable Smart Auth (e.g., switch from WhatsApp to SMS on failure)
                                    </label>
                                </div>
                            </div>

                            {/* Verification Method */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-3">
                                    Verification Method
                                </label>
                                <div className="space-y-3">
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="phone-magic-link"
                                            name="phone-verification-method"
                                            value="magic-link"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="phone-magic-link" className="ml-2 text-sm text-gray-700">
                                            Magic Link
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="phone-otp"
                                            name="phone-verification-method"
                                            value="otp"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="phone-otp" className="ml-2 text-sm text-gray-700">
                                            OTP
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="phone-both"
                                            name="phone-verification-method"
                                            value="both"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="phone-both" className="ml-2 text-sm text-gray-700">
                                            Both OTP and Magic Link
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {/* OTP Digits */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-3">
                                    OTP Digits
                                </label>
                                <div className="space-y-3">
                                    <div className="flex items-center">
                                        <input
                                            type="radio"
                                            id="phone-otp-4"
                                            name="phone-otp-digits"
                                            value="4"
                                            className="w-4 h-4 text-primary border-gray-300 focus:ring-primary"
                                        />
                                        <label htmlFor="phone-otp-4" className="ml-2 text-sm text-gray-700">
                                            4 Digits
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="radio"
                                            id="phone-otp-6"
                                            name="phone-otp-digits"
                                            value="6"
                                            className="w-4 h-4 text-primary border-gray-300 focus:ring-primary"
                                        />
                                        <label htmlFor="phone-otp-6" className="ml-2 text-sm text-gray-700">
                                            6 Digits
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {/* Delivery Channel */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-3">
                                    Delivery Channel
                                </label>
                                <div className="space-y-3">
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="delivery-sms"
                                            name="delivery-channel"
                                            value="sms"
                                            checked
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="delivery-sms" className="ml-2 text-sm text-gray-700">
                                            SMS
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="delivery-whatsapp"
                                            name="delivery-channel"
                                            value="whatsapp"
                                            disabled
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                                        />
                                        <label htmlFor="delivery-whatsapp" className="ml-2 text-sm text-gray-500">
                                            WhatsApp
                                        </label>
                                        <span className="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Coming Soon
                                        </span>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="delivery-viber"
                                            name="delivery-channel"
                                            value="viber"
                                            disabled
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                                        />
                                        <label htmlFor="delivery-viber" className="ml-2 text-sm text-gray-500">
                                            Viber
                                        </label>
                                        <span className="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Coming Soon
                                        </span>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="delivery-voice"
                                            name="delivery-channel"
                                            value="voice"
                                            disabled
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
                                        />
                                        <label htmlFor="delivery-voice" className="ml-2 text-sm text-gray-500">
                                            Phone Call
                                        </label>
                                        <span className="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Coming Soon
                                        </span>
                                    </div>
                                </div>
                                <p className="text-xs text-gray-500 mt-2">Note: Magic Link only works with SMS. OTP works with all channels.</p>
                            </div>

                            {/* Expiry Time */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Expiry Time (seconds)
                                </label>
                                <input
                                    type="number"
                                    min="30"
                                    max="3600"
                                    defaultValue="300"
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                    placeholder="300"
                                />
                                <p className="text-xs text-gray-500 mt-1">Set expiry time in seconds (30-3600)</p>
                            </div>

                            {/* Sign Up Settings */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Sign Up Settings</h5>
                                
                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="phone-required-signup"
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="phone-required-signup" className="ml-2 text-sm text-gray-700">
                                        Required at sign up
                                    </label>
                                </div>

                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="phone-verify-signup"
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="phone-verify-signup" className="ml-2 text-sm text-gray-700">
                                        Verify at sign up
                                    </label>
                                </div>

                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="phone-allow-signin"
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="phone-allow-signin" className="ml-2 text-sm text-gray-700">
                                        Allow to sign in
                                    </label>
                                </div>
                            </div>

                            {/* Phone Configuration */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Phone Configuration</h5>
                                
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

                            </div>
                        </div>
                    </div>
                );

            case 'email':
                return (
                    <div className="space-y-6">
                        <div>
                            <h4 className="text-sm font-medium text-gray-900 mb-2">Email Settings</h4>
                            <p className="text-sm text-gray-600">Configure email-based verification settings.</p>
                        </div>
                        <div className="space-y-6">
                            {/* Verification Method */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-3">
                                    Verification Method
                                </label>
                                <div className="space-y-3">
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="magic-link"
                                            name="verification-method"
                                            value="magic-link"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="magic-link" className="ml-2 text-sm text-gray-700">
                                            Magic Link
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="checkbox"
                                            id="otp"
                                            name="verification-method"
                                            value="otp"
                                            className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                        />
                                        <label htmlFor="otp" className="ml-2 text-sm text-gray-700">
                                            OTP
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {/* OTP Digits (only show if OTP is selected) */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-3">
                                    OTP Digits
                                </label>
                                <div className="space-y-3">
                                    <div className="flex items-center">
                                        <input
                                            type="radio"
                                            id="otp-4"
                                            name="otp-digits"
                                            value="4"
                                            className="w-4 h-4 text-primary border-gray-300 focus:ring-primary"
                                        />
                                        <label htmlFor="otp-4" className="ml-2 text-sm text-gray-700">
                                            4 Digits
                                        </label>
                                    </div>
                                    <div className="flex items-center">
                                        <input
                                            type="radio"
                                            id="otp-6"
                                            name="otp-digits"
                                            value="6"
                                            className="w-4 h-4 text-primary border-gray-300 focus:ring-primary"
                                        />
                                        <label htmlFor="otp-6" className="ml-2 text-sm text-gray-700">
                                            6 Digits
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {/* Expiry Time */}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Expiry Time (seconds)
                                </label>
                                <input
                                    type="number"
                                    min="30"
                                    max="3600"
                                    defaultValue="300"
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                                    placeholder="300"
                                />
                                <p className="text-xs text-gray-500 mt-1">Set expiry time in seconds (30-3600)</p>
                            </div>

                            {/* Sign Up Settings */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Sign Up Settings</h5>
                                
                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="required-signup"
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="required-signup" className="ml-2 text-sm text-gray-700">
                                        Required at sign up
                                    </label>
                                </div>

                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="verify-signup"
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="verify-signup" className="ml-2 text-sm text-gray-700">
                                        Verify at sign up
                                    </label>
                                </div>

                                <div className="flex items-center">
                                    <input
                                        type="checkbox"
                                        id="allow-signin"
                                        className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
                                    />
                                    <label htmlFor="allow-signin" className="ml-2 text-sm text-gray-700">
                                        Allow to sign in
                                    </label>
                                </div>
                            </div>

                            {/* Email Configuration */}
                            <div className="space-y-4">
                                <h5 className="text-sm font-medium text-gray-900">Email Configuration</h5>
                                
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
                                        From Email
                                    </label>
                                    <input
                                        type="email"
                                        defaultValue="noreply@example.com"
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
