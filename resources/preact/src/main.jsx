import { render } from 'preact';
import { App } from './App';
import { BrandingProvider } from './components/BrandingProvider';
import { applyBrandingVars } from './utils/apply-branding';
import './styles/auth.css';

// Apply branding CSS variables synchronously BEFORE render to prevent FOUC
applyBrandingVars(window.wsmsAuth?.branding);

const el = document.getElementById('wsms-auth');
if (el) {
    render(
        <BrandingProvider>
            <App />
        </BrandingProvider>,
        el
    );
}
