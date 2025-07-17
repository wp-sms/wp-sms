import React from 'react';
import { createRoot } from 'react-dom/client';

const Dashboard = () => {
    return (
        <div className="p-6">
            <h1 className="text-2xl font-bold mb-4 text-blue-500">Dashboard</h1>
            <p className="mb-4">Welcome to your dashboard!</p>
            <div className="space-y-2"></div>
        </div>
    );
};

export default Dashboard;

const container = document.getElementById('my-test-plugin-admin-root');
if (container) {
    const root = createRoot(container);
    root.render(
        <React.StrictMode>
            <Dashboard />
        </React.StrictMode>
    );
}
