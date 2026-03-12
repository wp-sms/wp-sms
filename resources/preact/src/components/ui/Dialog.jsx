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
        // Prevent body scroll
        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        // Focus trap: focus the content on open
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
            className="fixed inset-0 z-[99999] flex items-center justify-center bg-black/80 animate-fade-in"
            onClick={handleOverlayClick}
        >
            <div
                ref={contentRef}
                tabIndex={-1}
                className={cn(
                    'relative w-full max-w-md max-h-[90vh] overflow-y-auto rounded-xl bg-background shadow-lg outline-none animate-fade-in',
                    className,
                )}
            >
                <button
                    type="button"
                    onClick={onClose}
                    className="absolute top-3 right-3 flex size-7 items-center justify-center rounded-md bg-transparent border-none text-muted-foreground hover:text-foreground cursor-pointer transition-colors"
                    aria-label="Close"
                >
                    <X className="size-4" />
                </button>
                {children}
            </div>
        </div>
    );
}
