import { createRoot } from 'react-dom/client';
import App from './app';

const root = createRoot(document.getElementById('my-test-plugin-root')!);
root.render(<App />);
