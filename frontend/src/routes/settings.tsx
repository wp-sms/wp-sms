import SettingsDynamicPages from '@/pages/settings/dynamic-pages';
import { Routes, Route, Navigate } from 'react-router-dom';

const SettingsRoutes = () => {
  return (
    <Routes>
      <Route path="/" element={<Navigate to="general" replace />} />
      <Route path=":name" element={<SettingsDynamicPages />} />
    </Routes>
  );
};

export default SettingsRoutes;
