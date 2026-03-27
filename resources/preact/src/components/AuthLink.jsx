import { cn } from '@/utils/cn';

export function AuthLink({ className, ...props }) {
    return <a className={cn('wsms-auth-link', className)} {...props} />;
}
