import { useRef, useCallback } from 'react'

export function useFormChanges<T extends Record<string, any>>(initialValues: T | null) {
  const originalValuesRef = useRef<T | null>(null)
  const currentValuesRef = useRef<T | null>(null)

  // Update original values when they change
  if (initialValues !== originalValuesRef.current) {
    originalValuesRef.current = initialValues
    currentValuesRef.current = initialValues
  }

  const updateValue = useCallback((key: keyof T, value: any) => {
    if (currentValuesRef.current) {
      currentValuesRef.current = {
        ...currentValuesRef.current,
        [key]: value
      }
    }
  }, [])

  const getChangedFields = useCallback((): Partial<T> => {
    if (!originalValuesRef.current || !currentValuesRef.current) {
      return {}
    }

    const changedFields: Partial<T> = {}
    
    Object.keys(currentValuesRef.current).forEach(key => {
      const originalValue = originalValuesRef.current![key as keyof T]
      const currentValue = currentValuesRef.current![key as keyof T]
      
      // Deep comparison for arrays and objects
      if (Array.isArray(currentValue) && Array.isArray(originalValue)) {
        if (JSON.stringify(currentValue) !== JSON.stringify(originalValue)) {
          changedFields[key as keyof T] = currentValue
        }
      } else if (currentValue !== originalValue) {
        changedFields[key as keyof T] = currentValue
      }
    })

    return changedFields
  }, [])

  const hasChanges = useCallback((): boolean => {
    return Object.keys(getChangedFields()).length > 0
  }, [getChangedFields])

  const resetChanges = useCallback(() => {
    currentValuesRef.current = originalValuesRef.current
  }, [])

  return {
    updateValue,
    getChangedFields,
    hasChanges,
    resetChanges
  }
} 