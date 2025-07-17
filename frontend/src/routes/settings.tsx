import GeneralSettings from '@/pages/settings/general';
import PermissionsSettings from '@/pages/settings/permissions';
import { Routes, Route } from 'react-router-dom';

const SettingsRoutes = () => {
    return (
        <Routes>
            <Route path="/" element={<GeneralSettings />} />
            <Route path="/permissions" element={<PermissionsSettings />} />
        </Routes>
    );
};

export default SettingsRoutes;
