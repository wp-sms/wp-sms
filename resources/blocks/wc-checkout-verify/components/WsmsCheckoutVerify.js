import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { getSetting } from '@woocommerce/settings';
import { VerifyBridge } from './VerifyBridge.js';

const NAMESPACE = 'wsms-checkout-verify';

/**
 * Main block checkout verification component.
 *
 * Skips verification when the billing value matches the user's verified
 * account value (passed via scriptData.skipEmail / skipPhone).
 */
export function WsmsCheckoutVerify() {
    const scriptData = getSetting('wsms-checkout-verify_data', {});
    const emailEnabled = scriptData.emailEnabled ?? false;
    const phoneEnabled = scriptData.phoneEnabled ?? false;
    const skipEmail = scriptData.skipEmail ?? null;
    const skipPhone = scriptData.skipPhone ?? null;

    const [emailToken, setEmailToken] = useState('');
    const [phoneToken, setPhoneToken] = useState('');
    const [debouncedEmail, setDebouncedEmail] = useState('');
    const [debouncedPhone, setDebouncedPhone] = useState('');

    const { setExtensionData } = useDispatch('wc/store/checkout');

    // Read billing data from the WC cart store reactively.
    const { billingEmail, billingPhone } = useSelect((select) => {
        const store = select('wc/store/cart');
        const customer = store.getCustomerData();
        return {
            billingEmail: customer?.billingAddress?.email || '',
            billingPhone: customer?.billingAddress?.phone || '',
        };
    }, []);

    // Debounce email.
    const emailTimerRef = useRef(null);
    useEffect(() => {
        if (!emailEnabled) return;
        clearTimeout(emailTimerRef.current);
        emailTimerRef.current = setTimeout(() => setDebouncedEmail(billingEmail), 800);
        return () => clearTimeout(emailTimerRef.current);
    }, [billingEmail, emailEnabled]);

    // Debounce phone.
    const phoneTimerRef = useRef(null);
    useEffect(() => {
        if (!phoneEnabled) return;
        clearTimeout(phoneTimerRef.current);
        phoneTimerRef.current = setTimeout(() => setDebouncedPhone(billingPhone), 800);
        return () => clearTimeout(phoneTimerRef.current);
    }, [billingPhone, phoneEnabled]);

    // Push both tokens to WC checkout store extension data.
    useEffect(() => {
        setExtensionData(NAMESPACE, {
            email_session_token: emailToken,
            phone_session_token: phoneToken,
        });
    }, [emailToken, phoneToken, setExtensionData]);

    // Invalidate tokens when identifier changes.
    const prevEmailRef = useRef(debouncedEmail);
    useEffect(() => {
        if (prevEmailRef.current !== debouncedEmail) {
            setEmailToken('');
            prevEmailRef.current = debouncedEmail;
        }
    }, [debouncedEmail]);

    const prevPhoneRef = useRef(debouncedPhone);
    useEffect(() => {
        if (prevPhoneRef.current !== debouncedPhone) {
            setPhoneToken('');
            prevPhoneRef.current = debouncedPhone;
        }
    }, [debouncedPhone]);

    const handleEmailVerified = useCallback((token) => setEmailToken(token), []);
    const handlePhoneVerified = useCallback((token) => setPhoneToken(token), []);

    // Skip when billing value matches verified account value.
    const shouldSkipEmail = skipEmail && debouncedEmail && debouncedEmail.toLowerCase() === skipEmail.toLowerCase();
    const shouldSkipPhone = skipPhone && debouncedPhone && debouncedPhone === skipPhone;

    const showEmail = emailEnabled && debouncedEmail && !shouldSkipEmail;
    const showPhone = phoneEnabled && debouncedPhone && !shouldSkipPhone;

    if (!emailEnabled && !phoneEnabled) {
        return null;
    }

    return wp.element.createElement(
        'div',
        { className: 'wsms-block-checkout-verify' },
        showEmail &&
            wp.element.createElement(VerifyBridge, {
                channel: 'email',
                identifier: debouncedEmail,
                onVerified: handleEmailVerified,
            }),
        showPhone &&
            wp.element.createElement(VerifyBridge, {
                channel: 'phone',
                identifier: debouncedPhone,
                onVerified: handlePhoneVerified,
            }),
    );
}
