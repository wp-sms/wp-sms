import { cn } from '@/utils/cn';

export function AuthLink({ className, children, ...props }) {
    return <a className={cn('wsms-auth-link', className)} {...props}>{children}</a>;
}
