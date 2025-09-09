import '../../globals.css';

import React from 'react';
import { createRoot } from 'react-dom/client';
import { HashRouter as Router } from 'react-router-dom';

import { OtpLayout } from '@/layouts/otp-layout';
import Providers from '@/providers';
import OtpRoutes from '@/routes/otp';

const Otp = () => {
    return (
        <Providers>
            <Router>
                <OtpLayout>
                    <OtpRoutes />
                </OtpLayout>
            </Router>
        </Providers>
    );
};

const container = document.getElementById('wp-sms-otp-app');
if (container) {
    const root = createRoot(container);
    root.render(
        <React.StrictMode>
            <Otp />
        </React.StrictMode>
    );
}
