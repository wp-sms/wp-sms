import { cn } from '@/lib/utils'

function Skeleton({ className, ...props }: React.ComponentProps<'div'>) {
  return <div data-slot="skeleton" className={cn('bg-accent animate-pulse rounded-md', className)} {...props} />
}

function SettingsSchemaSkeleton() {
  return (
    <div className="w-full flex flex-col gap-y-4">
      {Array.from({ length: 2 }, (_, gIdx) => (
        <section key={`skeleton-group-${gIdx}`} className=" flex flex-col gap-y-6 border border-border rounded-lg p-4">
          {Array.from({ length: 2 }, (_, idx) => (
            <div key={`skeleton-group-item-${idx}`} className="max-w-2xl flex flex-col gap-y-1.5">
              <Skeleton className="h-4 w-40 rounded-sm" />
              <Skeleton className="h-8 w-full rounded-md" />
              <Skeleton className="h-3.5 w-72 rounded-sm" />
            </div>
          ))}
        </section>
      ))}
    </div>
  )
}

export { SettingsSchemaSkeleton, Skeleton }
