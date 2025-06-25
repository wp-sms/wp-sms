"use client"

import { useEffect, useRef, useState } from "react"
import { HelpCircle, Lock } from "lucide-react"
import { Label } from "./label"
import { Badge } from "./badge"
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "./tooltip"

// Import intl-tel-input types and library
declare global {
  interface Window {
    intlTelInput: any
    intlTelInputUtils: any
  }
}

interface PhoneFieldProps {
  label: string
  value?: string
  placeholder?: string
  description?: string
  tooltip?: string
  isPro?: boolean
  isRequired?: boolean
  isLocked?: boolean
  preferredCountries?: string[]
  onChange?: (value: string, isValid: boolean, countryData: any) => void
}

export function PhoneField({
  label,
  value = "",
  placeholder = "Enter phone number",
  description,
  tooltip,
  isPro = false,
  isRequired = false,
  isLocked = false,
  preferredCountries = ["us", "ca", "gb"],
  onChange,
}: PhoneFieldProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const itiRef = useRef<any>(null)
  const [isLoaded, setIsLoaded] = useState(false)

  useEffect(() => {
    // Load intl-tel-input CSS and JS
    const loadIntlTelInput = async () => {
      // Load CSS
      if (!document.querySelector('link[href*="intl-tel-input"]')) {
        const link = document.createElement("link")
        link.rel = "stylesheet"
        link.href = "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css"
        document.head.appendChild(link)
      }

      // Load JS
      if (!window.intlTelInput) {
        const script = document.createElement("script")
        script.src = "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"
        script.onload = () => {
          // Load utils script for validation
          const utilsScript = document.createElement("script")
          utilsScript.src = "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
          utilsScript.onload = () => setIsLoaded(true)
          document.head.appendChild(utilsScript)
        }
        document.head.appendChild(script)
      } else {
        setIsLoaded(true)
      }
    }

    loadIntlTelInput()
  }, [])

  useEffect(() => {
    if (isLoaded && inputRef.current && !itiRef.current) {
      // Initialize intl-tel-input
      itiRef.current = window.intlTelInput(inputRef.current, {
        preferredCountries: preferredCountries,
        separateDialCode: true,
        autoPlaceholder: "aggressive",
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
        customPlaceholder: () => placeholder,
      })

      // Set initial value
      if (value) {
        itiRef.current.setNumber(value)
      }

      // Handle input changes
      const handleChange = () => {
        if (itiRef.current && onChange) {
          const phoneNumber = itiRef.current.getNumber()
          const isValid = itiRef.current.isValidNumber()
          const countryData = itiRef.current.getSelectedCountryData()
          onChange(phoneNumber, isValid, countryData)
        }
      }

      inputRef.current.addEventListener("input", handleChange)
      inputRef.current.addEventListener("countrychange", handleChange)

      return () => {
        if (inputRef.current) {
          inputRef.current.removeEventListener("input", handleChange)
          inputRef.current.removeEventListener("countrychange", handleChange)
        }
      }
    }
  }, [isLoaded, value, onChange, placeholder, preferredCountries])

  // Update value when prop changes
  useEffect(() => {
    if (itiRef.current && value !== itiRef.current.getNumber()) {
      itiRef.current.setNumber(value)
    }
  }, [value])

  return (
    <TooltipProvider>
      <div className={`space-y-2 ${isLocked ? "opacity-60" : ""}`}>
        <div className="flex items-center gap-2">
          <Label className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
            {label}
            {isRequired && <span className="text-destructive ml-1">*</span>}
          </Label>

          {tooltip && (
            <Tooltip>
              <TooltipTrigger asChild>
                <HelpCircle className="h-4 w-4 text-muted-foreground cursor-help" />
              </TooltipTrigger>
              <TooltipContent>
                <p className="max-w-xs">{tooltip}</p>
              </TooltipContent>
            </Tooltip>
          )}

          {isPro && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Badge variant="secondary" className="text-xs bg-orange-100 text-orange-800 hover:bg-orange-200">
                  <Lock className="mr-1 h-3 w-3" />
                  Pro
                </Badge>
              </TooltipTrigger>
              <TooltipContent>
                <p>This feature requires WP SMS Pro</p>
              </TooltipContent>
            </Tooltip>
          )}
        </div>

        <div className={isLocked ? "pointer-events-none" : ""}>
          <input
            ref={inputRef}
            type="tel"
            disabled={isLocked}
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            style={{ paddingLeft: "52px" }} // Space for country flag
          />
        </div>

        {description && <p className="text-sm text-muted-foreground leading-relaxed">{description}</p>}
      </div>
    </TooltipProvider>
  )
}

// Column layout version
interface ColumnPhoneFieldProps extends PhoneFieldProps {}

export function ColumnPhoneField({
  label,
  value = "",
  placeholder = "Enter phone number",
  description,
  tooltip,
  isPro = false,
  isRequired = false,
  isLocked = false,
  preferredCountries = ["us", "ca", "gb"],
  onChange,
}: ColumnPhoneFieldProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const itiRef = useRef<any>(null)
  const [isLoaded, setIsLoaded] = useState(false)

  useEffect(() => {
    // Load intl-tel-input CSS and JS (same as above)
    const loadIntlTelInput = async () => {
      if (!document.querySelector('link[href*="intl-tel-input"]')) {
        const link = document.createElement("link")
        link.rel = "stylesheet"
        link.href = "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css"
        document.head.appendChild(link)
      }

      if (!window.intlTelInput) {
        const script = document.createElement("script")
        script.src = "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"
        script.onload = () => {
          const utilsScript = document.createElement("script")
          utilsScript.src = "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js"
          utilsScript.onload = () => setIsLoaded(true)
          document.head.appendChild(utilsScript)
        }
        document.head.appendChild(script)
      } else {
        setIsLoaded(true)
      }
    }

    loadIntlTelInput()
  }, [])

  useEffect(() => {
    if (isLoaded && inputRef.current && !itiRef.current) {
      itiRef.current = window.intlTelInput(inputRef.current, {
        preferredCountries: preferredCountries,
        separateDialCode: true,
        autoPlaceholder: "aggressive",
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js",
        customPlaceholder: () => placeholder,
      })

      if (value) {
        itiRef.current.setNumber(value)
      }

      const handleChange = () => {
        if (itiRef.current && onChange) {
          const phoneNumber = itiRef.current.getNumber()
          const isValid = itiRef.current.isValidNumber()
          const countryData = itiRef.current.getSelectedCountryData()
          onChange(phoneNumber, isValid, countryData)
        }
      }

      inputRef.current.addEventListener("input", handleChange)
      inputRef.current.addEventListener("countrychange", handleChange)

      return () => {
        if (inputRef.current) {
          inputRef.current.removeEventListener("input", handleChange)
          inputRef.current.removeEventListener("countrychange", handleChange)
        }
      }
    }
  }, [isLoaded, value, onChange, placeholder, preferredCountries])

  useEffect(() => {
    if (itiRef.current && value !== itiRef.current.getNumber()) {
      itiRef.current.setNumber(value)
    }
  }, [value])

  return (
    <TooltipProvider>
      <div
        className={`grid grid-cols-1 lg:grid-cols-2 gap-6 py-6 border-b border-border last:border-b-0 ${isLocked ? "opacity-60" : ""}`}
      >
        {/* Left Column - Label and Description */}
        <div className="space-y-2">
          <div className="flex items-center gap-2">
            <Label className="text-sm font-medium leading-none">
              {label}
              {isRequired && <span className="text-destructive ml-1">*</span>}
            </Label>

            {tooltip && (
              <Tooltip>
                <TooltipTrigger asChild>
                  <HelpCircle className="h-4 w-4 text-muted-foreground cursor-help" />
                </TooltipTrigger>
                <TooltipContent>
                  <p className="max-w-xs">{tooltip}</p>
                </TooltipContent>
              </Tooltip>
            )}

            {isPro && (
              <Tooltip>
                <TooltipTrigger asChild>
                  <Badge variant="secondary" className="text-xs bg-orange-100 text-orange-800 hover:bg-orange-200">
                    <Lock className="mr-1 h-3 w-3" />
                    Pro
                  </Badge>
                </TooltipTrigger>
                <TooltipContent>
                  <p>This feature requires WP SMS Pro</p>
                </TooltipContent>
              </Tooltip>
            )}
          </div>

          {description && <p className="text-sm text-muted-foreground leading-relaxed pr-4">{description}</p>}
        </div>

        {/* Right Column - Field */}
        <div className={isLocked ? "pointer-events-none" : ""}>
          <input
            ref={inputRef}
            type="tel"
            disabled={isLocked}
            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            style={{ paddingLeft: "52px" }}
          />
        </div>
      </div>
    </TooltipProvider>
  )
}
