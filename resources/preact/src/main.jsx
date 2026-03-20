import { render } from 'preact';
import { App } from './App';
import { BrandingProvider } from './components/BrandingProvider';
import { applyBrandingVars } from './utils/apply-branding';
import { brandingConfig } from './signals/branding';
import './styles/auth.css';

// Apply branding CSS variables synchronously BEFORE render to prevent FOUC
applyBrandingVars(brandingConfig.value);

const el = document.getElementById('wsms-auth');
if (el) {
    render(
        <BrandingProvider>
            <App />
        </BrandingProvider>,
        el
    );
}
