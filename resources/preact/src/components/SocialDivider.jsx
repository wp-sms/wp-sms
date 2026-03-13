import { Separator } from './ui/Separator';

export function SocialDivider() {
    return (
        <div className="relative my-4">
            <Separator />
            <span className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-background px-2 text-xs text-muted-foreground">
                or
            </span>
        </div>
    );
}
