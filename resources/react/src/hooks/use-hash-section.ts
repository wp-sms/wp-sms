import { useState, useEffect, useCallback } from 'react';
import { NAV_ITEMS } from '@/components/layout/app-shell';

const VALID_SECTIONS = new Set(
  NAV_ITEMS.flatMap((item) =>
    'children' in item ? item.children.map((c) => c.id) : [item.id]
  )
);

function parseHash(): string | null {
  const hash = window.location.hash.slice(1);
  return VALID_SECTIONS.has(hash) ? hash : null;
}

export function useHashSection(defaultSection: string): [string, (s: string) => void] {
  const [section, setSectionState] = useState(() => parseHash() ?? defaultSection);

  const setSection = useCallback((s: string) => {
    setSectionState(s);
    if (window.location.hash !== `#${s}`) {
      window.location.hash = s;
    }
  }, []);

  useEffect(() => {
    const onHashChange = () => {
      const parsed = parseHash();
      if (parsed) setSectionState(parsed);
    };
    window.addEventListener('hashchange', onHashChange);
    return () => window.removeEventListener('hashchange', onHashChange);
  }, []);

  return [section, setSection];
}
