import { useEffect, useState } from 'react'

import { WordPressService } from '@/lib/wordpress-service'

type Country = {
  id: number
  name: string
  nativeName: string
  code: string
  dialCode: string
  allDialCodes: string[]
  emoji: string
  unicode: string
  flag: string
}

// Global cache
let cachedCountries: Country[] | null = null
let loadingPromise: Promise<Country[]> | null = null

const loadCountries = async (): Promise<Country[]> => {
  if (cachedCountries) {
    return cachedCountries
  }

  if (loadingPromise) {
    return loadingPromise
  }

  loadingPromise = (async () => {
    try {
      const dataService = WordPressService.getInstance()
      const response = await fetch(`${dataService.getJsonPath()}/countries.json`)
      const data = (await response.json()) as Country[]
      cachedCountries = data
      return data
    } catch {
      return []
    } finally {
      loadingPromise = null
    }
  })()

  return loadingPromise
}

export function useCountries() {
  const [countries, setCountries] = useState<Country[]>(cachedCountries || [])
  const [isLoading, setIsLoading] = useState(!cachedCountries)

  useEffect(() => {
    if (cachedCountries) {
      return
    }

    loadCountries().then((data) => {
      setCountries(data)
      setIsLoading(false)
    })
  }, [])

  const getEmojiByCode = (code: string): string => {
    if (!code || code === 'global') {
      return '🌐'
    }

    const country = countries.find((c) => c.code.toLowerCase() === code.toLowerCase())
    return country?.emoji || '🌐'
  }

  return { countries, isLoading, getEmojiByCode }
}
