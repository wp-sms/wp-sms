import { useState, useCallback, useMemo } from 'react'
import { useToast } from '@/components/ui/toaster'
import { __ } from '@/lib/utils'

/**
 * Custom hook for managing form dialog state (create/edit dialogs)
 * Handles: open/close state, form data, saving state, toast notifications
 *
 * @param {Object} options - Hook options
 * @param {Function} options.saveFn - Async function to save. Receives (id, formData) => Promise. id is null for create.
 * @param {Object} options.initialData - Initial/default form data
 * @param {Function} options.onSuccess - Callback after successful save. Receives (result, item)
 * @param {Function} options.onError - Callback on save error. Receives (error, item)
 * @param {Function} options.validate - Validation function. Receives (formData) => { valid: boolean, errors: {} }
 * @param {string} options.successMessage - Success toast message (default: 'Saved successfully')
 * @param {string} options.createSuccessMessage - Success message for create (default: successMessage)
 * @param {string} options.updateSuccessMessage - Success message for update (default: successMessage)
 * @returns {Object} Dialog state and methods
 *
 * @example
 * const editDialog = useFormDialog({
 *   saveFn: (id, data) => id
 *     ? subscribersApi.updateSubscriber(id, data)
 *     : subscribersApi.createSubscriber(data),
 *   initialData: { name: '', mobile: '', status: '1' },
 *   onSuccess: () => table.refresh(),
 * })
 *
 * // Open for edit:
 * editDialog.open(existingItem)
 *
 * // Open for create:
 * editDialog.open()
 *
 * // In JSX:
 * <Dialog open={editDialog.isOpen} onOpenChange={(open) => !open && editDialog.close()}>
 *   <Input
 *     value={editDialog.formData.name}
 *     onChange={(e) => editDialog.updateField('name', e.target.value)}
 *   />
 *   <Button onClick={editDialog.save} disabled={editDialog.isSaving}>
 *     {editDialog.isSaving ? 'Saving...' : 'Save'}
 *   </Button>
 * </Dialog>
 */
export function useFormDialog({
  saveFn,
  initialData = {},
  onSuccess,
  onError,
  validate,
  successMessage,
  createSuccessMessage,
  updateSuccessMessage,
} = {}) {
  const { toast } = useToast()

  // Dialog state
  const [item, setItem] = useState(null) // null = closed, {} = create, {id: ...} = edit
  const [formData, setFormData] = useState(initialData)
  const [isSaving, setIsSaving] = useState(false)
  const [errors, setErrors] = useState({})

  // Derived state
  const isOpen = item !== null
  const isNew = item !== null && !item.id
  const isEdit = item !== null && !!item.id

  /**
   * Open the dialog
   * @param {Object} itemToEdit - Item to edit (omit or pass {} for create mode)
   */
  const open = useCallback(
    (itemToEdit = {}) => {
      // Merge initial data with item data for editing
      const data = { ...initialData }
      Object.keys(initialData).forEach((key) => {
        if (itemToEdit[key] !== undefined) {
          data[key] = itemToEdit[key]
        }
      })
      setFormData(data)
      setErrors({})
      setItem(itemToEdit)
    },
    [initialData]
  )

  /**
   * Close the dialog and reset state
   */
  const close = useCallback(() => {
    setItem(null)
    setFormData(initialData)
    setErrors({})
    setIsSaving(false)
  }, [initialData])

  /**
   * Update a single form field
   * @param {string} field - Field name
   * @param {any} value - New value
   */
  const updateField = useCallback((field, value) => {
    setFormData((prev) => ({ ...prev, [field]: value }))
    // Clear error for this field when user types
    setErrors((prev) => {
      if (prev[field]) {
        const next = { ...prev }
        delete next[field]
        return next
      }
      return prev
    })
  }, [])

  /**
   * Update multiple form fields at once
   * @param {Object} updates - Object with field:value pairs
   */
  const updateFields = useCallback((updates) => {
    setFormData((prev) => ({ ...prev, ...updates }))
  }, [])

  /**
   * Reset form to initial data (or current item data if editing)
   */
  const reset = useCallback(() => {
    if (item && item.id) {
      // Reset to item data for edit mode
      const data = { ...initialData }
      Object.keys(initialData).forEach((key) => {
        if (item[key] !== undefined) {
          data[key] = item[key]
        }
      })
      setFormData(data)
    } else {
      // Reset to initial data for create mode
      setFormData(initialData)
    }
    setErrors({})
  }, [item, initialData])

  /**
   * Save the form data
   * @returns {Promise<any>} Result from saveFn
   */
  const save = useCallback(async () => {
    if (!saveFn) {
      console.warn('useFormDialog: saveFn not provided')
      return
    }

    // Run validation if provided
    if (validate) {
      const validation = validate(formData)
      if (!validation.valid) {
        setErrors(validation.errors || {})
        return
      }
    }

    setIsSaving(true)
    setErrors({})

    try {
      const id = item?.id || null
      const result = await saveFn(id, formData)

      // Determine success message
      let message = successMessage
      if (!message) {
        message = id
          ? updateSuccessMessage || __('Updated successfully')
          : createSuccessMessage || __('Created successfully')
      }

      toast({
        title: message,
        variant: 'success',
      })

      // Call success callback
      if (onSuccess) {
        onSuccess(result, item)
      }

      // Close dialog on success
      close()

      return result
    } catch (error) {
      // Handle API errors
      const errorMessage = error.message || __('Failed to save')

      toast({
        title: errorMessage,
        variant: 'destructive',
      })

      // Call error callback
      if (onError) {
        onError(error, item)
      }

      throw error
    } finally {
      setIsSaving(false)
    }
  }, [
    saveFn,
    validate,
    formData,
    item,
    toast,
    successMessage,
    createSuccessMessage,
    updateSuccessMessage,
    onSuccess,
    onError,
    close,
  ])

  /**
   * Check if form has been modified from original data
   */
  const isDirty = useMemo(() => {
    const original = item?.id ? item : initialData
    return Object.keys(initialData).some((key) => {
      return formData[key] !== (original[key] ?? initialData[key])
    })
  }, [formData, item, initialData])

  /**
   * Check if a specific field has an error
   * @param {string} field - Field name
   * @returns {string|undefined} Error message if any
   */
  const getError = useCallback(
    (field) => errors[field],
    [errors]
  )

  /**
   * Check if a specific field has an error
   * @param {string} field - Field name
   * @returns {boolean}
   */
  const hasError = useCallback(
    (field) => !!errors[field],
    [errors]
  )

  return {
    // State
    isOpen,
    isNew,
    isEdit,
    isSaving,
    isDirty,
    item,
    formData,
    errors,

    // Actions
    open,
    close,
    save,
    reset,

    // Form helpers
    updateField,
    updateFields,
    setFormData,
    setErrors,
    getError,
    hasError,
  }
}

export default useFormDialog
