import { cn } from '@/lib/utils'

function Skeleton({ className, ...props }) {
  return (
    <div
      className={cn('wsms-animate-pulse wsms-rounded wsms-bg-muted', className)}
      {...props}
    />
  )
}

function SkeletonCard({ className, ...props }) {
  return (
    <div
      className={cn('wsms-rounded wsms-border wsms-border-border wsms-bg-card wsms-p-4', className)}
      {...props}
    >
      <Skeleton className="wsms-h-4 wsms-w-1/3 wsms-mb-2" />
      <Skeleton className="wsms-h-3 wsms-w-2/3 wsms-mb-4" />
      <div className="wsms-space-y-2">
        <Skeleton className="wsms-h-8 wsms-w-full" />
        <Skeleton className="wsms-h-8 wsms-w-full" />
      </div>
    </div>
  )
}

function SkeletonText({ lines = 3, className, ...props }) {
  return (
    <div className={cn('wsms-space-y-2', className)} {...props}>
      {Array.from({ length: lines }).map((_, i) => (
        <Skeleton
          key={i}
          className={cn('wsms-h-3', i === lines - 1 ? 'wsms-w-4/5' : 'wsms-w-full')}
        />
      ))}
    </div>
  )
}

export { Skeleton, SkeletonCard, SkeletonText }
