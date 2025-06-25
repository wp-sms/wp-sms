import '../../index.css'
// import '../../globals.css'
import { createRoot } from 'react-dom/client';
import { SettingsPage } from './SettingsPage';

// Mount the app to the WordPress admin div
const container = document.getElementById('wp-sms-settings-root');


if (container) {
    const root = createRoot(container);
    root.render(<SettingsPage />);
} else {
    console.error('WP SMS Settings: Missing #wp-sms-settings-root element in DOM.');
    console.log('WP SMS Settings: Available elements with similar IDs:', 
        Array.from(document.querySelectorAll('[id*="wp-sms"]')).map(el => el.id)
    );

}
