import { CustomSkeleton } from '@/components/ui/custom-skeleton'
import { FieldLabel } from './label'
import { FieldDescription } from './description'
import { FieldMessage } from './message'
import clsx from 'clsx'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { Badge } from '@/components/ui/badge'
import { HelpCircle } from 'lucide-react'
import { TagBadge } from '@/components/ui/tag-badge'
import type { PropsWithChildren } from 'react'

export type ControlledFieldProps = {
  label?: string
  description?: string
  tooltip?: string
  isLocked?: boolean
  isLoading?: boolean
  error?: string
  readonly?: boolean
  tag?: string
}

export type FieldWrapperProps = PropsWithChildren<ControlledFieldProps & { direction?: 'row' | 'column' }>

export const FieldWrapper: React.FC<FieldWrapperProps> = ({
  label,
  description,
  tooltip,
  isLocked = false,
  isLoading = false,
  readonly = false,
  tag,
  direction = 'column',
  children,
  error,
}) => {
  return (
    <TooltipProvider>
      <div className={clsx('flex flex-col gap-1.5', isLocked && 'opacity-70')}>
        <div className="flex items-center gap-2">
          <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
            <FieldLabel text={label} isInvalid={!!error} />
          </CustomSkeleton>

          {isLocked && (
            <Badge variant="secondary" className="text-xs bg-gray-100 text-gray-600">
              Read Only
            </Badge>
          )}

          {tooltip && !isLocked && (
            <Tooltip>
              <TooltipTrigger asChild>
                <HelpCircle className="h-4 w-4 text-muted-foreground cursor-help" />
              </TooltipTrigger>

              <TooltipContent>
                <p className="max-w-xs">{tooltip}</p>
              </TooltipContent>
            </Tooltip>
          )}

          {readonly && (
            <Badge variant="secondary" className="text-xs bg-gray-100 text-gray-600">
              Read Only
            </Badge>
          )}

          {tag && <TagBadge tag={tag} />}
        </div>

        <div
          className={clsx(
            'flex gap-2',

            direction === 'row' ? 'flex-row items-center' : 'flex-col'
          )}
        >
          <CustomSkeleton isLoading={isLoading} className={clsx(isLocked && 'pointer-events-none')}>
            {children}
          </CustomSkeleton>

          <FieldMessage text={error} />

          <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
            <FieldDescription text={description} />
          </CustomSkeleton>
        </div>
      </div>
    </TooltipProvider>
  )
}
