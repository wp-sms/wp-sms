/**
 * Manages sub-tab state for pages with in-page tabs routed via hash subtabs.
 *
 * Convention: when the active tab is the default (first) tab, the URL is just
 * the parent section (e.g. `#security`). For other tabs it's `#security/captcha`.
 */
export function useSubTabs(
  parentId: string,
  validTabs: readonly string[],
  subTab?: string,
  onNavigate?: (s: string) => void,
) {
  const defaultTab = validTabs[0];
  const activeTab = subTab && validTabs.includes(subTab) ? subTab : defaultTab;

  function handleTabChange(tab: string) {
    onNavigate?.(tab === defaultTab ? parentId : `${parentId}/${tab}`);
  }

  return [activeTab, handleTabChange] as const;
}
