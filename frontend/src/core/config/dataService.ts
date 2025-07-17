export class WordPressDataService {
  private static instance: WordPressDataService;
  private readonly data: NonNullable<typeof window.WP_SMS_DATA>;

  private constructor() {
    if (!window.WP_SMS_DATA) {
      throw new Error(
        "WP_SMS_DATA not available. Make sure the plugin is properly initialized."
      );
    }
    this.data = window.WP_SMS_DATA;
  }

  public static getInstance(): WordPressDataService {
    if (!WordPressDataService.instance) {
      WordPressDataService.instance = new WordPressDataService();
    }
    return WordPressDataService.instance;
  }

  public getNonce(): string {
    return this.data.nonce;
  }

  public getRestUrl(): string {
    return this.data.restUrl;
  }

  public getHeaders(): HeadersInit {
    return {
      "Content-Type": "application/json",
      "X-WP-Nonce": this.getNonce(),
    };
  }
}
