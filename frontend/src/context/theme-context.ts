import { createContext } from 'react'

const initialState: ThemeProviderState = {
  theme: 'system',
  setTheme: () => null,
}

export const ThemeContext = createContext<ThemeProviderState>(initialState)
