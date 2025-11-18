export class WordPressService {
  private static instance: WordPressService
  private readonly data: NonNullable<typeof window.WP_SMS_DATA>

  private constructor() {
    if (!window.WP_SMS_DATA) {
      throw new Error('WP_SMS_DATA not available. Make sure the plugin is properly initialized.')
    }
    this.data = window.WP_SMS_DATA
  }

  public static getInstance(): WordPressService {
    if (!WordPressService.instance) {
      WordPressService.instance = new WordPressService()
    }
    return WordPressService.instance
  }

  public getNonce(): string {
    return this.data.globals.nonce
  }

  public getRestUrl(): string {
    return this.data.globals.restUrl
  }

  public getBuildUrl(): string {
    return this.data.globals.frontend_build_url
  }

  public getJsonPath(): string {
    return this.data.globals.jsonPath
  }

  public getGlobalsData() {
    return this.data.globals
  }

  public getLayoutData() {
    return this.data.layout
  }

  public getHeaders(): HeadersInit {
    return {
      'Content-Type': 'application/json',
      'X-WP-Nonce': this.getNonce(),
    }
  }
}
