import { useState, useEffect } from 'react'

interface FieldOption {
  [key: string]: string | { [key: string]: string }
}

interface SchemaField {
  key: string
  type: string
  label: string
  description: string
  default: any
  groupLabel: string
  section: string | null
  options: FieldOption
  order: number
  doc: string
  showIf: { [key: string]: string } | null
  hideIf: { [key: string]: string } | null
  repeatable: boolean
  placeholder?: string
  fieldGroups?: any[]
}

interface GroupSchema {
  label: string
  fields: SchemaField[]
}

interface UseGroupSchemaReturn {
  data: GroupSchema | null
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

export function useGroupSchema(groupName: string | null): UseGroupSchemaReturn {
  const [data, setData] = useState<GroupSchema | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const fetchGroupSchema = async () => {
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
        
        const response = await fetch(`${wpSmsData.restUrl}settings/schema/group/${groupName}`, {
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
          throw new Error(result.message || 'Failed to fetch group schema')
        }
        
        setData(result.data)
      } catch (err) {
        setError(err instanceof Error ? err.message : 'An error occurred')
        console.error('Failed to fetch group schema:', err)
      } finally {
        setLoading(false)
      }
    }

    fetchGroupSchema()
  }, [groupName])

  return { data, loading, error }
} 