import { renderHook, act, waitFor } from '@testing-library/react'
import { useFormDialog } from '../hooks/useFormDialog'

// Mock the useToast hook
const mockToast = jest.fn()
jest.mock('../components/ui/toaster', () => ({
  useToast: () => ({ toast: mockToast }),
}))

describe('useFormDialog', () => {
  const mockSaveFn = jest.fn()
  const mockOnSuccess = jest.fn()
  const mockOnError = jest.fn()

  const defaultOptions = {
    saveFn: mockSaveFn,
    initialData: { name: '', email: '', status: '1' },
    onSuccess: mockOnSuccess,
    onError: mockOnError,
  }

  beforeEach(() => {
    jest.clearAllMocks()
  })

  describe('initialization', () => {
    test('initializes in closed state', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      expect(result.current.isOpen).toBe(false)
      expect(result.current.item).toBe(null)
      expect(result.current.formData).toEqual({ name: '', email: '', status: '1' })
    })

    test('isSaving is false initially', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      expect(result.current.isSaving).toBe(false)
    })

    test('errors is empty initially', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      expect(result.current.errors).toEqual({})
    })
  })

  describe('open', () => {
    test('opens dialog in create mode when called without arguments', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
      })

      expect(result.current.isOpen).toBe(true)
      expect(result.current.isNew).toBe(true)
      expect(result.current.isEdit).toBe(false)
      expect(result.current.formData).toEqual({ name: '', email: '', status: '1' })
    })

    test('opens dialog in edit mode when called with item', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))
      const existingItem = { id: 1, name: 'John', email: 'john@example.com', status: '0' }

      act(() => {
        result.current.open(existingItem)
      })

      expect(result.current.isOpen).toBe(true)
      expect(result.current.isNew).toBe(false)
      expect(result.current.isEdit).toBe(true)
      expect(result.current.item).toEqual(existingItem)
      expect(result.current.formData).toEqual({
        name: 'John',
        email: 'john@example.com',
        status: '0',
      })
    })

    test('clears previous errors when opening', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      // Set some errors
      act(() => {
        result.current.setErrors({ name: 'Required' })
      })

      expect(result.current.errors).toEqual({ name: 'Required' })

      // Open dialog
      act(() => {
        result.current.open()
      })

      expect(result.current.errors).toEqual({})
    })
  })

  describe('close', () => {
    test('closes dialog and resets state', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      // Open and modify
      act(() => {
        result.current.open({ id: 1, name: 'John', email: '', status: '1' })
        result.current.updateField('name', 'Modified')
      })

      expect(result.current.isOpen).toBe(true)

      // Close
      act(() => {
        result.current.close()
      })

      expect(result.current.isOpen).toBe(false)
      expect(result.current.item).toBe(null)
      expect(result.current.formData).toEqual({ name: '', email: '', status: '1' })
    })
  })

  describe('updateField', () => {
    test('updates a single field', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
      })

      act(() => {
        result.current.updateField('name', 'John')
      })

      expect(result.current.formData.name).toBe('John')
      expect(result.current.formData.email).toBe('')
    })

    test('clears error for field when updating', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
        result.current.setErrors({ name: 'Required', email: 'Invalid' })
      })

      expect(result.current.errors.name).toBe('Required')

      act(() => {
        result.current.updateField('name', 'John')
      })

      expect(result.current.errors.name).toBeUndefined()
      expect(result.current.errors.email).toBe('Invalid')
    })
  })

  describe('updateFields', () => {
    test('updates multiple fields at once', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
      })

      act(() => {
        result.current.updateFields({ name: 'John', email: 'john@example.com' })
      })

      expect(result.current.formData.name).toBe('John')
      expect(result.current.formData.email).toBe('john@example.com')
    })
  })

  describe('reset', () => {
    test('resets to initial data in create mode', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
        result.current.updateField('name', 'Modified')
      })

      expect(result.current.formData.name).toBe('Modified')

      act(() => {
        result.current.reset()
      })

      expect(result.current.formData.name).toBe('')
    })

    test('resets to item data in edit mode', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))
      const existingItem = { id: 1, name: 'Original', email: 'original@example.com', status: '1' }

      act(() => {
        result.current.open(existingItem)
        result.current.updateField('name', 'Modified')
      })

      expect(result.current.formData.name).toBe('Modified')

      act(() => {
        result.current.reset()
      })

      expect(result.current.formData.name).toBe('Original')
    })
  })

  describe('save', () => {
    test('calls saveFn with null id for create', async () => {
      mockSaveFn.mockResolvedValue({ id: 1, name: 'Created' })

      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
        result.current.updateField('name', 'New Item')
      })

      await act(async () => {
        await result.current.save()
      })

      expect(mockSaveFn).toHaveBeenCalledWith(null, {
        name: 'New Item',
        email: '',
        status: '1',
      })
    })

    test('calls saveFn with id for edit', async () => {
      mockSaveFn.mockResolvedValue({ id: 1, name: 'Updated' })

      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open({ id: 1, name: 'Original', email: '', status: '1' })
        result.current.updateField('name', 'Updated')
      })

      await act(async () => {
        await result.current.save()
      })

      expect(mockSaveFn).toHaveBeenCalledWith(1, {
        name: 'Updated',
        email: '',
        status: '1',
      })
    })

    test('shows success toast and closes on success', async () => {
      mockSaveFn.mockResolvedValue({ success: true })

      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
      })

      await act(async () => {
        await result.current.save()
      })

      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({
          variant: 'success',
        })
      )
      expect(result.current.isOpen).toBe(false)
    })

    test('calls onSuccess callback', async () => {
      const saveResult = { id: 1, name: 'Created' }
      mockSaveFn.mockResolvedValue(saveResult)

      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
      })

      await act(async () => {
        await result.current.save()
      })

      expect(mockOnSuccess).toHaveBeenCalledWith(saveResult, {})
    })

    test('shows error toast on failure', async () => {
      mockSaveFn.mockRejectedValue(new Error('Save failed'))

      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
      })

      await expect(
        act(async () => {
          await result.current.save()
        })
      ).rejects.toThrow('Save failed')

      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Save failed',
          variant: 'destructive',
        })
      )
    })

    test('calls onError callback on failure', async () => {
      const error = new Error('Save failed')
      mockSaveFn.mockRejectedValue(error)

      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
      })

      await expect(
        act(async () => {
          await result.current.save()
        })
      ).rejects.toThrow()

      expect(mockOnError).toHaveBeenCalledWith(error, {})
    })

    test('sets isSaving during save', async () => {
      let resolveSave
      mockSaveFn.mockImplementation(
        () => new Promise((resolve) => { resolveSave = resolve })
      )

      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
      })

      // Start save
      let savePromise
      act(() => {
        savePromise = result.current.save()
      })

      expect(result.current.isSaving).toBe(true)

      // Complete save
      await act(async () => {
        resolveSave({ success: true })
        await savePromise
      })

      expect(result.current.isSaving).toBe(false)
    })

    test('does not close on failure', async () => {
      mockSaveFn.mockRejectedValue(new Error('Failed'))

      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
      })

      await expect(
        act(async () => {
          await result.current.save()
        })
      ).rejects.toThrow()

      expect(result.current.isOpen).toBe(true)
    })
  })

  describe('validation', () => {
    test('runs validation before save', async () => {
      const mockValidate = jest.fn().mockReturnValue({
        valid: false,
        errors: { name: 'Name is required' },
      })

      const { result } = renderHook(() =>
        useFormDialog({ ...defaultOptions, validate: mockValidate })
      )

      act(() => {
        result.current.open()
      })

      await act(async () => {
        await result.current.save()
      })

      expect(mockValidate).toHaveBeenCalledWith({ name: '', email: '', status: '1' })
      expect(mockSaveFn).not.toHaveBeenCalled()
      expect(result.current.errors).toEqual({ name: 'Name is required' })
    })

    test('proceeds with save when validation passes', async () => {
      const mockValidate = jest.fn().mockReturnValue({ valid: true })
      mockSaveFn.mockResolvedValue({ success: true })

      const { result } = renderHook(() =>
        useFormDialog({ ...defaultOptions, validate: mockValidate })
      )

      act(() => {
        result.current.open()
      })

      await act(async () => {
        await result.current.save()
      })

      expect(mockSaveFn).toHaveBeenCalled()
    })
  })

  describe('isDirty', () => {
    test('isDirty is false when form matches initial data', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
      })

      expect(result.current.isDirty).toBe(false)
    })

    test('isDirty is true when form is modified', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
        result.current.updateField('name', 'Changed')
      })

      expect(result.current.isDirty).toBe(true)
    })

    test('isDirty is false when form matches item data in edit mode', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open({ id: 1, name: 'Original', email: '', status: '1' })
      })

      expect(result.current.isDirty).toBe(false)
    })
  })

  describe('error helpers', () => {
    test('getError returns error for field', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
        result.current.setErrors({ name: 'Required' })
      })

      expect(result.current.getError('name')).toBe('Required')
      expect(result.current.getError('email')).toBeUndefined()
    })

    test('hasError returns boolean for field', () => {
      const { result } = renderHook(() => useFormDialog(defaultOptions))

      act(() => {
        result.current.open()
        result.current.setErrors({ name: 'Required' })
      })

      expect(result.current.hasError('name')).toBe(true)
      expect(result.current.hasError('email')).toBe(false)
    })
  })

  describe('custom messages', () => {
    test('uses custom success message', async () => {
      mockSaveFn.mockResolvedValue({ success: true })

      const { result } = renderHook(() =>
        useFormDialog({
          ...defaultOptions,
          successMessage: 'Item saved!',
        })
      )

      act(() => {
        result.current.open()
      })

      await act(async () => {
        await result.current.save()
      })

      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Item saved!',
        })
      )
    })

    test('uses different messages for create and update', async () => {
      mockSaveFn.mockResolvedValue({ success: true })

      const { result } = renderHook(() =>
        useFormDialog({
          ...defaultOptions,
          createSuccessMessage: 'Created!',
          updateSuccessMessage: 'Updated!',
        })
      )

      // Test create
      act(() => {
        result.current.open()
      })

      await act(async () => {
        await result.current.save()
      })

      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Created!',
        })
      )

      mockToast.mockClear()

      // Test update
      act(() => {
        result.current.open({ id: 1, name: '', email: '', status: '1' })
      })

      await act(async () => {
        await result.current.save()
      })

      expect(mockToast).toHaveBeenCalledWith(
        expect.objectContaining({
          title: 'Updated!',
        })
      )
    })
  })
})
