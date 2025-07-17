import { Link, useLocation } from 'react-router-dom';

export const SettingsSidebar: React.FC = () => {
    const location = useLocation();

    return (
        <div className="w-64  p-6 border-r">
            <h2 className="text-lg font-semibold mb-4">Settings</h2>
            <nav className="space-y-2">
                <Link
                    to="/"
                    className={`block p-2 rounded ${location.pathname === '/' ? 'bg-blue-500 !text-white' : 'hover:bg-gray-200'}`}
                >
                    General Settings
                </Link>
                <Link
                    to="/permissions"
                    className={`block p-2 rounded ${location.pathname === '/permissions' ? 'bg-blue-500 !text-white' : 'hover:bg-gray-200'}`}
                >
                    Permissions
                </Link>
            </nav>
        </div>
    );
};
