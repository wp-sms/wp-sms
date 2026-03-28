import { useState } from 'preact/hooks';
import { forwardRef } from 'preact/compat';
import { __ } from '@wordpress/i18n';
import { Eye, EyeOff } from 'lucide-react';
import { cn } from '@/utils/cn';

export const PasswordInput = forwardRef(function PasswordInput({ className, ...props }, ref) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="wsms-auth-password-wrapper">
            <input
                ref={ref}
                type={visible ? 'text' : 'password'}
                data-slot="input"
                className={cn('wsms-auth-input wsms-auth-password-wrapper__input', className)}
                {...props}
            />
            <button
                type="button"
                className="wsms-auth-password-toggle"
                onClick={(e) => { e.preventDefault(); setVisible((v) => !v); }}
                tabIndex={-1}
                aria-label={visible ? __('Hide password', 'wp-sms') : __('Show password', 'wp-sms')}
            >
                {visible ? <EyeOff /> : <Eye />}
            </button>
        </div>
    );
});
