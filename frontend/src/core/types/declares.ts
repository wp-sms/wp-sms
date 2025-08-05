declare global {
    interface Window {
        WP_SMS_DATA?: {
            nonce: string;
            restUrl: string;
            frontend_build_url: string;
        };
    }
}
