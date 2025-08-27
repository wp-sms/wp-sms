import type { PropsWithChildren } from 'react';

export type CustomSkeletonProps = PropsWithChildren<{
  isLoading?: boolean;
  className?: string;
  wrapperClassName?: string;
}>;
