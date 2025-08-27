'use client';

import type { SidebarGroupProps } from './types';

export const SidebarGroup: React.FC<SidebarGroupProps> = ({ title, children, showTitle = true }) => {
  return (
    <section>
      {showTitle && <strong>{title}</strong>}

      <div className="flex flex-col gap-y-1 mt-3">{children}</div>
    </section>
  );
};
