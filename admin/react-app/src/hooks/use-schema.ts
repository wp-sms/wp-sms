import { useState, useEffect } from 'react'

interface SchemaItem {
  name: string
  label: string
}

interface SchemaGroup {
  label: string
  children?: Record<string, SchemaGroup | SchemaItem>
}

interface SchemaData {
  core: Record<string, SchemaItem>
  addons: Record<string, SchemaItem>
  integrations: SchemaGroup
}

interface UseSchemaReturn {
  data: SchemaData | null
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

export function useSchema(): UseSchemaReturn {
  const [data, setData] = useState<SchemaData | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const fetchSchema = async () => {
      try {
        setLoading(true)
        setError(null)
        
        // Use localized WordPress variables
        const wpSmsData = window.WP_SMS_DATA
        if (!wpSmsData) {
          throw new Error('WP_SMS_DATA not available')
        }
        
        const response = await fetch(`${wpSmsData.restUrl}settings/schema/list`, {
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
          throw new Error(result.message || 'Failed to fetch schema')
        }
        
        setData(result.data)
      } catch (err) {
        setError(err instanceof Error ? err.message : 'An error occurred')
        console.error('Failed to fetch schema:', err)
      } finally {
        setLoading(false)
      }
    }

    fetchSchema()
  }, [])

  return { data, loading, error }
} 