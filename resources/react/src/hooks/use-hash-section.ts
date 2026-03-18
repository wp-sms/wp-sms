import { useState, useEffect, useCallback } from 'react';
import { NAV_ITEMS } from '@/components/layout/app-shell';

const VALID_SECTIONS = new Set(
  NAV_ITEMS.flatMap((item) =>
    'children' in item ? item.children.map((c) => c.id) : [item.id]
  )
);

function parseHash(): { section: string; subTab: string } | null {
  const raw = window.location.hash.slice(1);
  const [section, subTab] = raw.split('/');
  if (VALID_SECTIONS.has(section)) {
    return { section, subTab: subTab ?? '' };
  }
  return null;
}

export function useHashSection(defaultSection: string): [string, (s: string) => void, string] {
  const initial = parseHash();
  const [section, setSectionState] = useState(initial?.section ?? defaultSection);
  const [subTab, setSubTab] = useState(initial?.subTab ?? '');

  const setSection = useCallback((s: string) => {
    const [main, sub] = s.split('/');
    setSectionState(main);
    setSubTab(sub ?? '');
    if (window.location.hash !== `#${s}`) {
      window.location.hash = s;
    }
  }, []);

  useEffect(() => {
    const onHashChange = () => {
      const parsed = parseHash();
      if (parsed) {
        setSectionState(parsed.section);
        setSubTab(parsed.subTab);
      }
    };
    window.addEventListener('hashchange', onHashChange);
    return () => window.removeEventListener('hashchange', onHashChange);
  }, []);

  return [section, setSection, subTab];
}
