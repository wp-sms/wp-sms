import React, { createContext, useContext, useReducer, useCallback, useEffect, useMemo } from 'react'
import isEqual from 'fast-deep-equal'
import { getWpSettings, deepMerge } from '../lib/utils'
import { settingsApi } from '../api/settingsApi'

// Valid page IDs for URL validation
const VALID_PAGES = [
  // Messaging
  'send-sms',
  'outbox',
  // Subscribers
  'subscribers',
  'groups',
  // Settings
  'overview',
  'gateway',
  'phone',
  'message-button',
  'notifications',
  'newsletter',
  'integrations',
  'advanced',
  // Privacy
  'privacy',
  // Add-ons - WooCommerce Pro
  'woocommerce-pro',
  'cart-abandonment',
  'sms-campaigns',
  // Add-ons - Two-Way SMS
  'two-way-inbox',
  'two-way-commands',
  'two-way-settings',
]

// Get initial page from URL query params
function getInitialPageFromUrl() {
  const params = new URLSearchParams(window.location.search)
  const tab = params.get('tab')
  return tab && VALID_PAGES.includes(tab) ? tab : 'send-sms'
}

// Update URL with current tab
function updateUrlTab(page) {
  const url = new URL(window.location.href)
  if (page === 'send-sms') {
    url.searchParams.delete('tab')
  } else {
    url.searchParams.set('tab', page)
  }
  window.history.pushState({ tab: page }, '', url)
}

// Initial state
const initialState = {
  settings: {},
  proSettings: {},
  addonValues: {},
  originalSettings: {},
  originalProSettings: {},
  originalAddonValues: {},
  isLoading: true,
  isSaving: false,
  hasChanges: false,
  error: null,
  currentPage: getInitialPageFromUrl(),
  gateways: {},
  addons: {},
}

// Action types
const ACTIONS = {
  SET_LOADING: 'SET_LOADING',
  SET_SAVING: 'SET_SAVING',
  SET_ERROR: 'SET_ERROR',
  LOAD_SETTINGS: 'LOAD_SETTINGS',
  UPDATE_SETTING: 'UPDATE_SETTING',
  UPDATE_PRO_SETTING: 'UPDATE_PRO_SETTING',
  UPDATE_ADDON_SETTING: 'UPDATE_ADDON_SETTING',
  UPDATE_SETTINGS_BATCH: 'UPDATE_SETTINGS_BATCH',
  SAVE_SUCCESS: 'SAVE_SUCCESS',
  RESET_CHANGES: 'RESET_CHANGES',
  SET_PAGE: 'SET_PAGE',
}

// Reducer
function settingsReducer(state, action) {
  switch (action.type) {
    case ACTIONS.SET_LOADING:
      return { ...state, isLoading: action.payload }

    case ACTIONS.SET_SAVING:
      return { ...state, isSaving: action.payload }

    case ACTIONS.SET_ERROR:
      return { ...state, error: action.payload, isLoading: false, isSaving: false }

    case ACTIONS.LOAD_SETTINGS:
      return {
        ...state,
        settings: action.payload.settings,
        proSettings: action.payload.proSettings,
        addonValues: action.payload.addonValues || {},
        originalSettings: { ...action.payload.settings },
        originalProSettings: { ...action.payload.proSettings },
        originalAddonValues: JSON.parse(JSON.stringify(action.payload.addonValues || {})),
        gateways: action.payload.gateways || state.gateways,
        addons: action.payload.addons || state.addons,
        isLoading: false,
        hasChanges: false,
        error: null,
      }

    case ACTIONS.UPDATE_SETTING:
      const newSettings = { ...state.settings, [action.payload.key]: action.payload.value }
      return {
        ...state,
        settings: newSettings,
        hasChanges: !isEqual(newSettings, state.originalSettings) ||
                    !isEqual(state.proSettings, state.originalProSettings) ||
                    !isEqual(state.addonValues, state.originalAddonValues),
      }

    case ACTIONS.UPDATE_PRO_SETTING:
      const newProSettings = { ...state.proSettings, [action.payload.key]: action.payload.value }
      return {
        ...state,
        proSettings: newProSettings,
        hasChanges: !isEqual(state.settings, state.originalSettings) ||
                    !isEqual(newProSettings, state.originalProSettings) ||
                    !isEqual(state.addonValues, state.originalAddonValues),
      }

    case ACTIONS.UPDATE_ADDON_SETTING:
      const { addonSlug: slug, key: fieldKey, value: fieldValue } = action.payload
      const currentAddonValues = state.addonValues || {}
      const newAddonValues = {
        ...currentAddonValues,
        [slug]: {
          ...(currentAddonValues[slug] || {}),
          [fieldKey]: fieldValue,
        },
      }
      return {
        ...state,
        addonValues: newAddonValues,
        hasChanges: !isEqual(state.settings, state.originalSettings) ||
                    !isEqual(state.proSettings, state.originalProSettings) ||
                    !isEqual(newAddonValues, state.originalAddonValues || {}),
      }

    case ACTIONS.UPDATE_SETTINGS_BATCH:
      const batchSettings = { ...state.settings, ...action.payload.settings }
      const batchProSettings = { ...state.proSettings, ...action.payload.proSettings }
      return {
        ...state,
        settings: batchSettings,
        proSettings: batchProSettings,
        hasChanges: !isEqual(batchSettings, state.originalSettings) ||
                    !isEqual(batchProSettings, state.originalProSettings) ||
                    !isEqual(state.addonValues, state.originalAddonValues),
      }

    case ACTIONS.SAVE_SUCCESS:
      return {
        ...state,
        originalSettings: { ...state.settings },
        originalProSettings: { ...state.proSettings },
        originalAddonValues: JSON.parse(JSON.stringify(state.addonValues)),
        isSaving: false,
        hasChanges: false,
        error: null,
      }

    case ACTIONS.RESET_CHANGES:
      return {
        ...state,
        settings: { ...state.originalSettings },
        proSettings: { ...state.originalProSettings },
        addonValues: JSON.parse(JSON.stringify(state.originalAddonValues)),
        hasChanges: false,
      }

    case ACTIONS.SET_PAGE:
      return { ...state, currentPage: action.payload }

    default:
      return state
  }
}

// Context
const SettingsContext = createContext(null)

// Provider
export function SettingsProvider({ children }) {
  const [state, dispatch] = useReducer(settingsReducer, initialState)

  // Load initial settings from WordPress localized data
  useEffect(() => {
    const wpSettings = getWpSettings()

    dispatch({
      type: ACTIONS.LOAD_SETTINGS,
      payload: {
        settings: wpSettings.settings || {},
        proSettings: wpSettings.proSettings || {},
        addonValues: wpSettings.addonValues || {},
        gateways: wpSettings.gateways || {},
        addons: wpSettings.addons || {},
      },
    })
  }, [])

  // Update a single setting
  const updateSetting = useCallback((key, value) => {
    dispatch({ type: ACTIONS.UPDATE_SETTING, payload: { key, value } })
  }, [])

  // Update a single pro setting
  const updateProSetting = useCallback((key, value) => {
    dispatch({ type: ACTIONS.UPDATE_PRO_SETTING, payload: { key, value } })
  }, [])

  // Update a single addon setting
  const updateAddonSetting = useCallback((addonSlug, key, value) => {
    if (!addonSlug || !key) return
    dispatch({ type: ACTIONS.UPDATE_ADDON_SETTING, payload: { addonSlug, key, value } })
  }, [])

  // Update multiple settings at once
  const updateSettingsBatch = useCallback((settings = {}, proSettings = {}) => {
    dispatch({ type: ACTIONS.UPDATE_SETTINGS_BATCH, payload: { settings, proSettings } })
  }, [])

  // Save settings to server
  const saveSettings = useCallback(async () => {
    dispatch({ type: ACTIONS.SET_SAVING, payload: true })

    try {
      await settingsApi.updateSettings({
        settings: state.settings,
        proSettings: state.proSettings,
        addonValues: state.addonValues,
      })

      dispatch({ type: ACTIONS.SAVE_SUCCESS })
      return { success: true }
    } catch (error) {
      dispatch({ type: ACTIONS.SET_ERROR, payload: error.message })
      return { success: false, error: error.message }
    }
  }, [state.settings, state.proSettings, state.addonValues])

  // Reset changes to original
  const resetChanges = useCallback(() => {
    dispatch({ type: ACTIONS.RESET_CHANGES })
  }, [])

  // Set current page and update URL
  const setCurrentPage = useCallback((page, updateUrl = true) => {
    dispatch({ type: ACTIONS.SET_PAGE, payload: page })
    if (updateUrl) {
      updateUrlTab(page)
    }
  }, [])

  // Listen for browser back/forward navigation
  useEffect(() => {
    const handlePopState = (event) => {
      const tab = event.state?.tab || getInitialPageFromUrl()
      setCurrentPage(tab, false) // Don't update URL on popstate
    }
    window.addEventListener('popstate', handlePopState)
    return () => window.removeEventListener('popstate', handlePopState)
  }, [setCurrentPage])

  // Get a setting value
  const getSetting = useCallback((key, defaultValue = '') => {
    return state.settings[key] ?? defaultValue
  }, [state.settings])

  // Get a pro setting value
  const getProSetting = useCallback((key, defaultValue = '') => {
    return state.proSettings[key] ?? defaultValue
  }, [state.proSettings])

  // Get an addon setting value
  const getAddonSetting = useCallback((addonSlug, key, defaultValue = '') => {
    if (!state.addonValues || !addonSlug) return defaultValue
    return state.addonValues[addonSlug]?.[key] ?? defaultValue
  }, [state.addonValues])

  // Check if an add-on is active
  const isAddonActive = useCallback((addon) => {
    return state.addons[addon] === true
  }, [state.addons])

  // Test gateway connection
  const testGatewayConnection = useCallback(async () => {
    try {
      const result = await settingsApi.testGateway()
      return result
    } catch (error) {
      return { success: false, error: error.message }
    }
  }, [])

  // Memoize context value to prevent unnecessary re-renders
  const value = useMemo(
    () => ({
      ...state,
      updateSetting,
      updateProSetting,
      updateAddonSetting,
      updateSettingsBatch,
      saveSettings,
      resetChanges,
      setCurrentPage,
      getSetting,
      getProSetting,
      getAddonSetting,
      isAddonActive,
      testGatewayConnection,
    }),
    [
      state,
      updateSetting,
      updateProSetting,
      updateAddonSetting,
      updateSettingsBatch,
      saveSettings,
      resetChanges,
      setCurrentPage,
      getSetting,
      getProSetting,
      getAddonSetting,
      isAddonActive,
      testGatewayConnection,
    ]
  )

  return (
    <SettingsContext.Provider value={value}>
      {children}
    </SettingsContext.Provider>
  )
}

// Hook
export function useSettings() {
  const context = useContext(SettingsContext)
  if (!context) {
    throw new Error('useSettings must be used within a SettingsProvider')
  }
  return context
}

// Convenience hook for a specific setting
export function useSetting(key, defaultValue = '') {
  const { getSetting, updateSetting } = useSettings()
  const value = getSetting(key, defaultValue)
  const setValue = useCallback((newValue) => updateSetting(key, newValue), [key, updateSetting])
  return [value, setValue]
}

// Convenience hook for a specific pro setting
export function useProSetting(key, defaultValue = '') {
  const { getProSetting, updateProSetting } = useSettings()
  const value = getProSetting(key, defaultValue)
  const setValue = useCallback((newValue) => updateProSetting(key, newValue), [key, updateProSetting])
  return [value, setValue]
}

// Convenience hook for a specific addon setting
export function useAddonSetting(addonSlug, key, defaultValue = '') {
  const { getAddonSetting, updateAddonSetting } = useSettings()
  const value = getAddonSetting(addonSlug, key, defaultValue)
  const setValue = useCallback((newValue) => updateAddonSetting(addonSlug, key, newValue), [addonSlug, key, updateAddonSetting])
  return [value, setValue]
}

/**
 * Convenience hook for multiple related settings
 * Reduces boilerplate when dealing with groups of settings
 *
 * @param {string[]} keys - Array of setting keys
 * @param {object} defaults - Default values for each key
 * @returns {[object, function]} Values object and batch setter
 *
 * @example
 * const [gateway, setGateway] = useSettingsGroup(
 *   ['gateway_name', 'gateway_username', 'gateway_password'],
 *   { gateway_name: '', gateway_username: '', gateway_password: '' }
 * )
 * // gateway = { gateway_name: 'twilio', gateway_username: 'user', ... }
 * // setGateway({ gateway_name: 'nexmo' }) updates just that key
 */
export function useSettingsGroup(keys, defaults = {}) {
  const { getSetting, updateSettingsBatch } = useSettings()

  // Build values object from keys
  const values = useMemo(() => {
    const result = {}
    keys.forEach(key => {
      result[key] = getSetting(key, defaults[key] ?? '')
    })
    return result
  }, [keys, getSetting, defaults])

  // Batch update function
  const setValues = useCallback((updates) => {
    updateSettingsBatch(updates, {})
  }, [updateSettingsBatch])

  return [values, setValues]
}

/**
 * Convenience hook for multiple related pro settings
 *
 * @param {string[]} keys - Array of pro setting keys
 * @param {object} defaults - Default values for each key
 * @returns {[object, function]} Values object and batch setter
 */
export function useProSettingsGroup(keys, defaults = {}) {
  const { getProSetting, updateSettingsBatch } = useSettings()

  const values = useMemo(() => {
    const result = {}
    keys.forEach(key => {
      result[key] = getProSetting(key, defaults[key] ?? '')
    })
    return result
  }, [keys, getProSetting, defaults])

  const setValues = useCallback((updates) => {
    updateSettingsBatch({}, updates)
  }, [updateSettingsBatch])

  return [values, setValues]
}
