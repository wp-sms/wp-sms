import { useState, useEffect } from 'react'

interface UseGroupValuesReturn {
  data: Record<string, any> | null
  loading: boolean
  error: string | null
}

// Declare global WordPress variables
declare global {
  interface Window {
    WP_SMS_DATA?: {
      nonce: string
      restUrl: string
    }
  }
}

export function useGroupValues(groupName: string | null): UseGroupValuesReturn {
  const [data, setData] = useState<Record<string, any> | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const fetchGroupValues = async () => {
      if (!groupName) {
        setData(null)
        return
      }

      try {
        setLoading(true)
        setError(null)
        
        // Use localized WordPress variables
        const wpSmsData = window.WP_SMS_DATA
        if (!wpSmsData) {
          throw new Error('WP_SMS_DATA not available')
        }
        
        const response = await fetch(`${wpSmsData.restUrl}settings/values/group/${groupName}`, {
          headers: {
            'X-WP-Nonce': wpSmsData.nonce,
            'Content-Type': 'application/json',
          },
        })
        
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`)
        }
        
        const result = await response.json()
        
        if (!result.success) {
          throw new Error(result.message || 'Failed to fetch group values')
        }
        
        setData(result.data)
      } catch (err) {
        setError(err instanceof Error ? err.message : 'An error occurred')
        console.error('Failed to fetch group values:', err)
      } finally {
        setLoading(false)
      }
    }

    fetchGroupValues()
  }, [groupName])

  return { data, loading, error }
} 