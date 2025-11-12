import { WordPressDataService } from '@/lib/data-service'
import type { LayoutData } from '@/types/global'

/**
 * Hook to access layout data (header and sidebar) from WordPress
 *
 * @returns {LayoutData} Layout data containing header navigation items and sidebar configuration
 */
export function useLayoutData(): LayoutData {
  const dataService = WordPressDataService.getInstance()
  const data = window.WP_SMS_DATA?.layout

  // Return the data if available, otherwise return default empty structure
  // This prevents errors during initial render before WordPress data is fully loaded
  return data || { header: [], sidebar: {} }
}
