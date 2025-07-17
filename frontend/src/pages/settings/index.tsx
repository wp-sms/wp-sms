import React from 'react';
import { createRoot } from 'react-dom/client';
import { HashRouter as Router } from 'react-router-dom';
import SettingsRoutes from '@/routes/settings';
import { SettingsLayout } from '@/layouts/settings-layout';
import Providers from '@/providers';

const Settings = () => {
    return (
        <Providers>
            <Router>
                <SettingsLayout>
                    <SettingsRoutes />
                </SettingsLayout>
            </Router>
        </Providers>
    );
};

// Mount the app
const container = document.getElementById('my-test-plugin-settings-root');
if (container) {
    const root = createRoot(container);
    root.render(
        <React.StrictMode>
            <Settings />
        </React.StrictMode>
    );
}
