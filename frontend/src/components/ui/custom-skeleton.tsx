import clsx from 'clsx'
import type { PropsWithChildren } from 'react'

export type CustomSkeletonProps = PropsWithChildren<{
  isLoading?: boolean
  className?: string
  wrapperClassName?: string
}>

export const CustomSkeleton = ({ isLoading, children, className, wrapperClassName }: CustomSkeletonProps) => {
  return (
    <div className={wrapperClassName}>
      <div
        className={clsx(
          'group rounded-sm relative overflow-hidden bg-gray-200 dark:bg-gray-200 pointer-events-none',
          'before:opacity-100 before:absolute before:inset-0 before:-translate-x-full before:animate-shimmer',
          'before:border-t before:border-gray-200 before:bg-gradient-to-r before:from-transparent',
          'before:via-gray-200 dark:before:via-gray-200 before:to-transparent',
          'after:opacity-100 after:absolute after:inset-0 after:-z-10 after:bg-gray-200 dark:after:bg-gray-200',
          !isLoading && [
            'data-[loaded=true]:pointer-events-auto',
            'data-[loaded=true]:overflow-visible',
            'data-[loaded=true]:!bg-transparent',
            'data-[loaded=true]:before:opacity-0',
            'data-[loaded=true]:before:-z-10',
            'data-[loaded=true]:before:animate-none',
            'data-[loaded=true]:after:opacity-0',
            'transition-background !duration-200',
          ]
        )}
        data-loaded={isLoading ? 'false' : 'true'}
      >
        <div
          className={clsx(
            'transition-opacity motion-reduce:transition-none !duration-200',
            isLoading ? 'opacity-0 group-data-[loaded=true]:opacity-100' : 'opacity-100',
            className
          )}
        >
          {children}
        </div>
      </div>
    </div>
  )
}
