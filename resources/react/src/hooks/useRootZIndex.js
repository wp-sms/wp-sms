import { useEffect } from 'react'

/**
 * Ref-counted z-index boost for #wpsms-settings-root.
 *
 * Multiple modals (SetupWizard, AllInOneModal, etc.) need the root container
 * at a high z-index so their fixed overlays sit above the WP admin bar/sidebar.
 * Each modal calls useRootZIndex(isOpen). The z-index is set when the first
 * modal opens and only cleared when the last one closes.
 */
let openCount = 0

function update() {
  const root = document.getElementById('wpsms-settings-root')
  if (!root) return
  root.style.zIndex = openCount > 0 ? '100000' : ''
}

export function useRootZIndex(isOpen) {
  useEffect(() => {
    if (!isOpen) return
    openCount++
    update()
    return () => {
      openCount--
      update()
    }
  }, [isOpen])
}
