import { useState, useEffect } from 'react'

interface GatewayFieldOption {
  [key: string]: string
}

interface GatewayField {
  id: string
  name: string
  desc: string
  type: string
  className: string
  options?: GatewayFieldOption
}

interface UseGatewayFieldsReturn {
  data: GatewayField[]
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

export function useGatewayFields(gatewayName: string | null): UseGatewayFieldsReturn {
  const [data, setData] = useState<GatewayField[]>([])
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const fetchGatewayFields = async () => {
      if (!gatewayName || gatewayName === 'default') {
        setData([])
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
        
        const response = await fetch(`${wpSmsData.restUrl}gateways/fields/${gatewayName}`, {
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
          throw new Error(result.message || 'Failed to fetch gateway fields')
        }
        
        setData(result.data)
      } catch (err) {
        setError(err instanceof Error ? err.message : 'An error occurred')
        console.error('Failed to fetch gateway fields:', err)
      } finally {
        setLoading(false)
      }
    }

    fetchGatewayFields()
  }, [gatewayName])

  return { data, loading, error }
} 