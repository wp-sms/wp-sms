import { useEffect } from 'preact/hooks';
import { useLocation } from 'preact-iso';

export function useAuthGuard() {
    const { route } = useLocation();

    useEffect(() => {
        if (!window.wsmsAuth?.isLoggedIn) route('/login');
    }, []);

    return window.wsmsAuth?.isLoggedIn;
}
