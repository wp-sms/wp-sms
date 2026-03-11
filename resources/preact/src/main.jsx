import { render } from 'preact';
import { App } from './App';
import './styles/auth.css';

const root = document.getElementById('wsms-auth');
if (root) {
    render(<App />, root);
}
