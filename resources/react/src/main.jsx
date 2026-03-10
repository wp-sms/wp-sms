import { createRoot } from 'react-dom/client';
import App from './App';
import './styles/index.css';

const container = document.getElementById('wpsms-settings-root');

if (container) {
    const root = createRoot(container);
    root.render(<App />);
}
