import { cn } from '@/lib/utils'
import { useCountries } from '@/context/countries-context'

type CountryFlagProps = {
  countryCode: string
  className?: string
}

/**
 * Displays a country flag emoji using the country code slug
 * Fetches emoji from countries data via context
 */
export function CountryFlag({ countryCode, className }: CountryFlagProps) {
  const { getEmojiByCode } = useCountries()
  const emoji = getEmojiByCode(countryCode)

  return <span className={cn('inline-block', className)}>{emoji}</span>
}
