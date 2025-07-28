import { CustomSkeleton } from '@/components/ui/custom-skeleton';
import type { FieldWrapperProps } from './types';
import { FieldLabel } from '../label';
import { FieldDescription } from '../description';
import { FieldMessage } from '../message';
import clsx from 'clsx';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Badge } from '@/components/ui/badge';
import { HelpCircle, Lock } from 'lucide-react';

export const FieldWrapper: React.FC<FieldWrapperProps> = ({
    label,
    description,
    tooltip,
    isPro = false,
    isRequired = false,
    isLocked = false,
    isLoading = false,
    children,
    error,
}) => {
    return (
        <TooltipProvider>
            <div className={clsx('flex flex-col gap-y-2', isLocked && 'opacity-50')}>
                <div className="flex items-center gap-2">
                    <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
                        <FieldLabel text={label} />
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

                    {isPro && !isLocked && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Badge
                                    variant="secondary"
                                    className="text-xs bg-orange-100 text-orange-800 hover:bg-orange-200"
                                >
                                    <Lock className="mr-1 h-3 w-3" />
                                    Pro
                                </Badge>
                            </TooltipTrigger>

                            <TooltipContent>
                                <p>This feature requires WP SMS Pro</p>
                            </TooltipContent>
                        </Tooltip>
                    )}
                </div>

                <CustomSkeleton isLoading={isLoading} className={clsx(isLocked && 'pointer-events-none')}>
                    {children}
                </CustomSkeleton>

                <CustomSkeleton isLoading={isLoading} wrapperClassName="flex">
                    <FieldDescription text={description} />
                </CustomSkeleton>

                <FieldMessage text={error} />
            </div>
        </TooltipProvider>
    );
};
