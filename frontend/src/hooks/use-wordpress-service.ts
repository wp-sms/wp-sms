import { WordPressService } from '@/lib/wordpress-service'

export function useWordPressService() {
  const service = WordPressService.getInstance()

  return {
    globals: service.getGlobalsData(),
    nonce: service.getNonce(),
    restUrl: service.getRestUrl(),
    buildUrl: service.getBuildUrl(),
    layout: service.getLayoutData(),
    getHeaders: () => service.getHeaders(),
  }
}
