import { cn } from '@/lib/utils'

/**
 * Base skeleton element - subtle pulse animation
 */
function Skeleton({ className, ...props }) {
  return (
    <div
      className={cn('wsms-skeleton wsms-rounded', className)}
      {...props}
    />
  )
}

function SkeletonCard({ className, ...props }) {
  return (
    <div
      className={cn('wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card wsms-p-4', className)}
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

/**
 * Page loading skeleton
 * Clean, minimal design matching the dashboard aesthetic
 */
function PageLoadingSkeleton({ className, ...props }) {
  return (
    <div
      className={cn('wsms-space-y-4', className)}
      {...props}
      role="status"
      aria-label="Loading"
    >
      {/* Card 1 - Header/stats area */}
      <div className="wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card wsms-p-5">
        <div className="wsms-flex wsms-items-center wsms-gap-4">
          <Skeleton className="wsms-h-10 wsms-w-10 wsms-rounded-lg" />
          <div className="wsms-space-y-2 wsms-flex-1">
            <Skeleton className="wsms-h-4 wsms-w-28" />
            <Skeleton className="wsms-h-3 wsms-w-40" />
          </div>
        </div>
      </div>

      {/* Card 2 - Main content area */}
      <div className="wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card">
        {/* Toolbar area */}
        <div className="wsms-p-4 wsms-border-b wsms-border-border">
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <Skeleton className="wsms-h-9 wsms-w-48 wsms-rounded-md" />
            <Skeleton className="wsms-h-9 wsms-w-24 wsms-rounded-md" />
          </div>
        </div>

        {/* Content rows */}
        <div className="wsms-p-4 wsms-space-y-4">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="wsms-flex wsms-items-center wsms-gap-4">
              <Skeleton className="wsms-h-4 wsms-w-4 wsms-rounded" />
              <Skeleton className="wsms-h-4 wsms-flex-1 wsms-max-w-[200px]" />
              <Skeleton className="wsms-h-4 wsms-w-20 wsms-ml-auto" />
            </div>
          ))}
        </div>
      </div>

      <span className="wsms-sr-only">Loading...</span>
    </div>
  )
}

export { Skeleton, SkeletonCard, SkeletonText, PageLoadingSkeleton }
