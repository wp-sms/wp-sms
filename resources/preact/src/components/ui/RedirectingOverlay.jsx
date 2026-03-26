import { Spinner } from './Spinner';

export function RedirectingOverlay() {
    return (
        <div className="flex flex-col items-center gap-3 py-8 animate-fade-in">
            <Spinner className="size-8" />
            <p className="text-sm text-muted-foreground">Redirecting…</p>
        </div>
    );
}
