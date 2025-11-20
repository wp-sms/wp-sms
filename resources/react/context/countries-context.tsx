import { createContext, useContext, useEffect, useState } from 'react'

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

type CountriesContextType = {
  countries: Country[]
  isLoading: boolean
  getEmojiByCode: (code: string) => string
}

const CountriesContext = createContext<CountriesContextType | undefined>(undefined)

export function CountriesProvider({ children }: { children: React.ReactNode }) {
  const [countries, setCountries] = useState<Country[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const dataService = WordPressService.getInstance()

  useEffect(() => {
    const loadData = async () => {
      try {
        const response = await fetch(`${dataService.getJsonPath()}/countries.json`)
        const importedData = (await response.json()) as Country[]
        setCountries(importedData)
      } catch {
        // Handle error silently
      } finally {
        setIsLoading(false)
      }
    }

    loadData()
  }, [dataService])

  const getEmojiByCode = (code: string): string => {
    if (!code || code === 'global') {
      return '🌐'
    }

    const country = countries.find((c) => c.code.toLowerCase() === code.toLowerCase())
    return country?.emoji || '🌐'
  }

  return (
    <CountriesContext.Provider value={{ countries, isLoading, getEmojiByCode }}>
      {children}
    </CountriesContext.Provider>
  )
}

export function useCountries() {
  const context = useContext(CountriesContext)
  if (context === undefined) {
    throw new Error('useCountries must be used within a CountriesProvider')
  }
  return context
}
