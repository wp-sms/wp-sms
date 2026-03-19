import { useEffect } from 'preact/hooks';
import { useLocation } from 'preact-iso';
import { authUrl } from '../utils/urls';

export function useAuthGuard() {
    const { route } = useLocation();

    useEffect(() => {
        if (!window.wsmsAuth?.isLoggedIn) route(authUrl('/login'));
    }, []); // eslint-disable-line react-hooks/exhaustive-deps -- mount-only redirect guard

    return window.wsmsAuth?.isLoggedIn;
}
