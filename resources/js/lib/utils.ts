import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export const themeTokens = {
  brand: "--brand",
  brandForeground: "--brand-foreground",
  brandSoft: "--brand-soft",
  brandSoftForeground: "--brand-soft-foreground",
  brandBorder: "--brand-border",
  sidebarAccent: "--sidebar-accent",
  sidebarAccentForeground: "--sidebar-accent-foreground",
  buttonPrimary: "--button-primary",
  buttonCancel: "--button-cancel",
  buttonSecondary: "--button-secondary",
} as const

export const themeClasses = {
  bgBrand: "bg-brand",
  textOnBrand: "text-brand-foreground",
  bgBrandSoft: "bg-brand-soft",
  textBrandSoft: "text-brand-soft-foreground",
  borderBrand: "border-brand-border",
  textBrand: "text-brand",
  buttonPrimary: "bg-button-primary text-white px-4 py-2 rounded-md",
  buttonCancel: "bg-button-cancel text-white px-4 py-2 rounded-md",
  buttonSecondary: "bg-button-secondary text-white px-4 py-2 rounded-md",
} as const

export function getCssVar(name: string, el: HTMLElement = document.documentElement) {
  if (typeof window === "undefined") return ""
  return getComputedStyle(el).getPropertyValue(name).trim()
}

export function setCssVar(name: string, value: string, el: HTMLElement = document.documentElement) {
  if (typeof window === "undefined") return
  el.style.setProperty(name, value)
}

export function getThemeToken(token: keyof typeof themeTokens, el?: HTMLElement) {
  return getCssVar(themeTokens[token], el ?? document.documentElement)
}
