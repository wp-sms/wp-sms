import { useEffect, useRef, useCallback } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { X } from 'lucide-react';
import { cn } from '@/utils/cn';

const FOCUSABLE = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

export function Dialog({ open, onClose, children, className }) {
    const overlayRef = useRef(null);
    const contentRef = useRef(null);
    const previouslyFocusedRef = useRef(null);

    const handleKeyDown = useCallback((e) => {
        if (e.key === 'Escape' && onClose) {
            onClose();
            return;
        }

        // Focus trap
        if (e.key === 'Tab' && contentRef.current) {
            const focusable = contentRef.current.querySelectorAll(FOCUSABLE);
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last?.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first?.focus();
            }
        }
    }, [onClose]);

    const handleOverlayClick = useCallback((e) => {
        if (e.target === overlayRef.current && onClose) onClose();
    }, [onClose]);

    useEffect(() => {
        if (!open) return;
        // Store the element that had focus before the dialog opened
        previouslyFocusedRef.current = document.activeElement;
        document.addEventListener('keydown', handleKeyDown);
        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        contentRef.current?.focus();

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = prevOverflow;
            // Return focus to the previously focused element
            previouslyFocusedRef.current?.focus();
        };
    }, [open, handleKeyDown]);

    if (!open) return null;

    return (
        // eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions -- overlay backdrop; keyboard handled by Escape key listener
        <div
            ref={overlayRef}
            className="wsms-auth-dialog-overlay"
            onClick={handleOverlayClick}
        >
            <div
                ref={contentRef}
                tabIndex={-1}
                role="dialog"
                aria-modal="true"
                className={cn('wsms-auth-dialog', className)}
            >
                <button
                    type="button"
                    onClick={onClose}
                    className="wsms-auth-dialog__close"
                    aria-label={__('Close', 'wp-sms')}
                >
                    <X />
                </button>
                {children}
            </div>
        </div>
    );
}
