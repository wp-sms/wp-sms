import { useState, useEffect, useCallback } from 'react';

function parseHash(validSections: Set<string>): { section: string; subTab: string } | null {
  const raw = window.location.hash.slice(1);
  const [section, subTab] = raw.split('/');
  if (validSections.has(section)) {
    return { section, subTab: subTab ?? '' };
  }
  return null;
}

export function useHashSection(defaultSection: string, validSections: Set<string>): [string, (s: string) => void, string] {
  const initial = parseHash(validSections);
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
      const parsed = parseHash(validSections);
      if (parsed) {
        setSectionState(parsed.section);
        setSubTab(parsed.subTab);
      }
    };
    window.addEventListener('hashchange', onHashChange);
    return () => window.removeEventListener('hashchange', onHashChange);
  }, [validSections]);

  return [section, setSection, subTab];
}
