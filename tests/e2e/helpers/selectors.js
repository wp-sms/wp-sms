/**
 * Common CSS Selectors for WP-SMS React Dashboard
 *
 * These selectors are used across page objects and tests
 * for consistent element targeting.
 */

export const selectors = {
  // App shell and layout
  app: {
    root: '#wpsms-settings-root',
    sidebar: '[data-testid="sidebar"], .wsms-sidebar',
    mainContent: '[data-testid="main-content"], main',
    header: '[data-testid="header"], header',
  },

  // Navigation
  nav: {
    item: (name) => `[data-testid="nav-${name}"], a[href*="tab=${name}"]`,
    activeItem: '[data-testid="nav-item-active"], [aria-current="page"]',
  },

  // Common UI components
  button: {
    primary: 'button[class*="primary"], button[data-variant="default"]',
    secondary: 'button[class*="secondary"], button[data-variant="outline"]',
    destructive: 'button[class*="destructive"], button[data-variant="destructive"]',
    loading: 'button[disabled]:has([class*="animate-spin"])',
  },

  // Form elements
  form: {
    input: 'input[type="text"], input[type="tel"], input[type="email"], input[type="number"]',
    textarea: 'textarea',
    select: '[role="combobox"], select',
    selectTrigger: '[data-testid="select-trigger"], button[role="combobox"]',
    selectOption: '[role="option"]',
    selectContent: '[role="listbox"]',
    checkbox: 'input[type="checkbox"], [role="checkbox"]',
    switch: '[role="switch"]',
    label: 'label',
    error: '[data-testid="form-error"], .text-destructive',
  },

  // Data table
  table: {
    container: '[data-testid="data-table"], table',
    header: 'thead',
    body: 'tbody',
    row: 'tbody tr',
    cell: 'td',
    headerCell: 'th',
    checkbox: 'input[type="checkbox"]',
    selectAll: 'thead input[type="checkbox"]',
    sortButton: 'th button[class*="sort"]',
    emptyState: '[data-testid="empty-state"]',
  },

  // Pagination
  pagination: {
    container: '[data-testid="pagination"]',
    prev: 'button:has-text("Previous"), button[aria-label="Previous"]',
    next: 'button:has-text("Next"), button[aria-label="Next"]',
    pageButton: '[data-testid="page-button"]',
    pageInfo: '[data-testid="page-info"]',
    perPage: '[data-testid="per-page"]',
  },

  // Dialog/Modal
  dialog: {
    container: '[role="dialog"]',
    overlay: '[data-testid="dialog-overlay"]',
    title: '[data-testid="dialog-title"], [role="dialog"] h2',
    description: '[data-testid="dialog-description"]',
    close: '[data-testid="dialog-close"], button[aria-label="Close"]',
    content: '[data-testid="dialog-content"]',
  },

  // Notifications/Toasts
  notification: {
    container: '[data-testid="toast"], [role="alert"]',
    success: '[data-testid="toast-success"], [class*="success"]',
    error: '[data-testid="toast-error"], [class*="destructive"]',
    close: '[data-testid="toast-close"]',
  },

  // Loading states
  loading: {
    spinner: '[class*="animate-spin"]',
    skeleton: '[class*="skeleton"], [class*="animate-pulse"]',
  },

  // Subscribers page
  subscribers: {
    quickAddName: 'input[placeholder*="Name"], input[name="name"]',
    quickAddPhone: 'input[placeholder*="phone"], input[placeholder*="Enter phone"], input[type="tel"]',
    addButton: 'button:has-text("Add Subscriber"), button:has-text("Add")',
    searchInput: 'input[placeholder*="Search"]',
    groupFilter: '[data-testid="group-filter"]',
    statusFilter: '[data-testid="status-filter"]',
    importButton: 'button:has-text("Import")',
    exportButton: 'button:has-text("Export")',
    bulkActions: '[data-testid="bulk-actions"]',
  },

  // Groups page
  groups: {
    quickAddInput: 'input[placeholder*="group"], input[placeholder*="Group"]',
    createButton: 'button:has-text("Create"), button:has-text("Add")',
    viewToggle: {
      list: 'button[aria-label*="list"], button:has([class*="List"])',
      grid: 'button[aria-label*="grid"], button:has([class*="Grid"])',
    },
    gridCard: '[data-testid="group-card"]',
    inlineEditInput: 'input[data-testid="inline-edit"]',
  },

  // Send SMS page
  sendSms: {
    messageTextarea: 'textarea[placeholder*="message"], textarea[name="message"]',
    charCount: '[data-testid="char-count"]',
    segmentCount: '[data-testid="segment-count"]',
    recipientSelector: '[data-testid="recipient-selector"]',
    tabGroups: 'button:has-text("Groups")',
    tabRoles: 'button:has-text("Roles")',
    tabNumbers: 'button:has-text("Numbers")',
    phoneInput: 'input[placeholder*="+"]',
    addNumberButton: 'button:has-text("Add")',
    numberChip: '[data-testid="number-chip"]',
    removeNumber: '[data-testid="remove-number"]',
    flashToggle: '[data-testid="flash-toggle"], label:has-text("Flash")',
    mediaUrlInput: 'input[placeholder*="Media"], input[name="media_url"]',
    previewButton: 'button:has-text("Preview"), button:has-text("Review")',
    sendButton: 'button:has-text("Send")',
    recipientCount: '[data-testid="recipient-count"]',
    creditBalance: '[data-testid="credit-balance"]',
  },

  // Outbox page
  outbox: {
    searchInput: 'input[placeholder*="Search"]',
    statusFilter: '[data-testid="status-filter"]',
    dateFrom: 'input[type="date"]:first-of-type, input[aria-label*="From"]',
    dateTo: 'input[type="date"]:last-of-type, input[aria-label*="To"]',
    refreshButton: 'button:has([class*="Refresh"]), button[aria-label="Refresh"]',
    exportButton: 'button:has-text("Export")',
    messageRow: 'tbody tr',
    viewButton: 'button:has-text("View")',
    resendButton: 'button:has-text("Resend")',
    deleteButton: 'button:has-text("Delete")',
  },

  // Status badges
  status: {
    badge: '[data-testid="status-badge"], [class*="badge"]',
    active: '[class*="success"], [class*="green"]',
    inactive: '[class*="muted"], [class*="gray"]',
    sent: '[class*="success"]',
    failed: '[class*="destructive"], [class*="red"]',
    pending: '[class*="warning"], [class*="yellow"]',
  },

  // Action menu (dropdown)
  actionMenu: {
    trigger: 'button:has([class*="MoreHorizontal"]), button:has([class*="Ellipsis"])',
    content: '[role="menu"]',
    item: '[role="menuitem"]',
    edit: '[role="menuitem"]:has-text("Edit")',
    delete: '[role="menuitem"]:has-text("Delete")',
    view: '[role="menuitem"]:has-text("View")',
  },
};

/**
 * Get a selector by path (e.g., "subscribers.quickAddPhone")
 * @param {string} path - Dot-separated path to selector
 * @returns {string} The selector string
 */
export function getSelector(path) {
  const parts = path.split('.');
  let current = selectors;

  for (const part of parts) {
    if (current[part] === undefined) {
      throw new Error(`Selector not found: ${path}`);
    }
    current = current[part];
  }

  if (typeof current === 'function') {
    throw new Error(`Selector is a function, call it with arguments: ${path}`);
  }

  return current;
}
