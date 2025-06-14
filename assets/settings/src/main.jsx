import './index.css';     // ✅ global styles
import './App.css';       // ✅ optional, if used globally
import React from 'react';
import {createRoot} from 'react-dom/client';
import App from './App.jsx';

const container = document.getElementById('wpsms-settings-root');
if (container) {
    const root = createRoot(container);
    root.render(<App/>);
}
