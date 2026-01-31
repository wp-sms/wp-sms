import { useState, useEffect, useRef } from 'react'
import { settingsApi } from '@/api/settingsApi'

/**
 * Hook to fetch gateway registry data from the REST API
 * Returns gateways, regions, source, loading state, and error
 */
export default function useGatewayRegistry() {
  const [data, setData] = useState({
    gateways: [],
    regions: [],
    source: null,
  })
  // Ensure gateways/regions are always arrays (defensive)
  const gateways = Array.isArray(data.gateways) ? data.gateways : []
  const regions = Array.isArray(data.regions) ? data.regions : []
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState(null)
  const fetchedRef = useRef(false)

  useEffect(() => {
    if (fetchedRef.current) return
    fetchedRef.current = true

    let cancelled = false

    const fetchData = async () => {
      try {
        const result = await settingsApi.getGatewayRegistry()
        if (!cancelled) {
          setData({
            gateways: result.gateways || [],
            regions: result.regions || [],
            source: result.source || 'local',
          })
        }
      } catch (err) {
        if (!cancelled) {
          setError(err.message)
        }
      } finally {
        if (!cancelled) {
          setIsLoading(false)
        }
      }
    }

    fetchData()

    return () => {
      cancelled = true
    }
  }, [])

  return {
    gateways,
    regions,
    source: data.source,
    isLoading,
    error,
  }
}
