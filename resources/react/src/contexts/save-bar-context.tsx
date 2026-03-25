import { createContext, useContext, useState, useCallback, useEffect, useMemo } from 'react';
import type { SaveStatus } from '@/hooks/use-resource-settings';

export interface SaveBarState {
  isDirty: boolean;
  saveStatus: SaveStatus;
  onSave: () => void;
}

interface SaveBarContextValue {
  state: SaveBarState;
  claim: (state: SaveBarState) => void;
  release: () => void;
}

const SaveBarContext = createContext<SaveBarContextValue | null>(null);

export function SaveBarProvider({
  defaultState,
  children,
}: {
  defaultState: SaveBarState;
  children: React.ReactNode;
}) {
  const [override, setOverride] = useState<SaveBarState | null>(null);

  const claim = useCallback((s: SaveBarState) => {
    setOverride(prev => {
      if (prev && prev.isDirty === s.isDirty && prev.saveStatus === s.saveStatus && prev.onSave === s.onSave) {
        return prev;
      }
      return s;
    });
  }, []);

  const release = useCallback(() => setOverride(null), []);
  const state = override ?? defaultState;
  const value = useMemo(() => ({ state, claim, release }), [state, claim, release]);

  return (
    <SaveBarContext.Provider value={value}>
      {children}
    </SaveBarContext.Provider>
  );
}

export function useSaveBar(state: SaveBarState) {
  const ctx = useContext(SaveBarContext);
  if (!ctx) throw new Error('useSaveBar must be used within <SaveBarProvider>');
  const { claim, release } = ctx;

  useEffect(() => {
    claim(state);
  }, [state.isDirty, state.saveStatus, state.onSave, claim]);

  useEffect(() => {
    return () => release();
  }, [release]);
}

export function useSaveBarState(): SaveBarState {
  const ctx = useContext(SaveBarContext);
  if (!ctx) throw new Error('useSaveBarState must be used within <SaveBarProvider>');
  return ctx.state;
}
