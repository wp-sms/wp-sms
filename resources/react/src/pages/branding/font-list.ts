export interface FontOption {
  value: string;
  label: string;
  google: boolean;
}

export const SYSTEM_FONTS: FontOption[] = [
  { value: 'system-ui', label: 'System Default', google: false },
  { value: 'Arial', label: 'Arial', google: false },
  { value: 'Georgia', label: 'Georgia', google: false },
  { value: 'Verdana', label: 'Verdana', google: false },
  { value: 'Tahoma', label: 'Tahoma', google: false },
  { value: 'Trebuchet MS', label: 'Trebuchet MS', google: false },
];

export const GOOGLE_FONTS: FontOption[] = [
  { value: 'Inter', label: 'Inter', google: true },
  { value: 'Roboto', label: 'Roboto', google: true },
  { value: 'Open Sans', label: 'Open Sans', google: true },
  { value: 'Lato', label: 'Lato', google: true },
  { value: 'Montserrat', label: 'Montserrat', google: true },
  { value: 'Poppins', label: 'Poppins', google: true },
  { value: 'Nunito', label: 'Nunito', google: true },
  { value: 'Raleway', label: 'Raleway', google: true },
  { value: 'Source Sans 3', label: 'Source Sans 3', google: true },
  { value: 'PT Sans', label: 'PT Sans', google: true },
  { value: 'Merriweather', label: 'Merriweather', google: true },
  { value: 'Playfair Display', label: 'Playfair Display', google: true },
  { value: 'Rubik', label: 'Rubik', google: true },
  { value: 'Work Sans', label: 'Work Sans', google: true },
  { value: 'DM Sans', label: 'DM Sans', google: true },
  { value: 'Manrope', label: 'Manrope', google: true },
  { value: 'Plus Jakarta Sans', label: 'Plus Jakarta Sans', google: true },
  { value: 'Space Grotesk', label: 'Space Grotesk', google: true },
  { value: 'IBM Plex Sans', label: 'IBM Plex Sans', google: true },
  { value: 'Outfit', label: 'Outfit', google: true },
  { value: 'Figtree', label: 'Figtree', google: true },
  { value: 'Geist', label: 'Geist', google: true },
  { value: 'Noto Sans', label: 'Noto Sans', google: true },
  { value: 'Cabin', label: 'Cabin', google: true },
  { value: 'Karla', label: 'Karla', google: true },
];

export const ALL_FONTS: FontOption[] = [...SYSTEM_FONTS, ...GOOGLE_FONTS];
