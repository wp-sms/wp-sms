import { useEffect, useRef, useCallback } from 'preact/hooks';
import { X } from 'lucide-react';
import { cn } from '@/utils/cn';

export function Dialog({ open, onClose, children, className }) {
    const overlayRef = useRef(null);
    const contentRef = useRef(null);

    const handleKeyDown = useCallback((e) => {
        if (e.key === 'Escape' && onClose) onClose();
    }, [onClose]);

    const handleOverlayClick = useCallback((e) => {
        if (e.target === overlayRef.current && onClose) onClose();
    }, [onClose]);

    useEffect(() => {
        if (!open) return;
        document.addEventListener('keydown', handleKeyDown);
        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        contentRef.current?.focus();

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            document.body.style.overflow = prevOverflow;
        };
    }, [open, handleKeyDown]);

    if (!open) return null;

    return (
        <div
            ref={overlayRef}
            className="wsms-auth-dialog-overlay"
            onClick={handleOverlayClick}
        >
            <div
                ref={contentRef}
                tabIndex={-1}
                className={cn('wsms-auth-dialog', className)}
            >
                <button
                    type="button"
                    onClick={onClose}
                    className="wsms-auth-dialog__close"
                    aria-label="Close"
                >
                    <X />
                </button>
                {children}
            </div>
        </div>
    );
}
