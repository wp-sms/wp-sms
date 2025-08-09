import OtpDynamicPages from '@/pages/otp/dynamic-pages';
import { Routes, Route, Navigate } from 'react-router-dom';

const OtpRoutes = () => {
    return (
        <Routes>
            <Route path="/" element={<Navigate to="activities" replace />} />
            <Route path=":name" element={<OtpDynamicPages />} />
        </Routes>
    );
};

export default OtpRoutes;
