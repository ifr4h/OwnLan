/**
 * UK outward code (district) from free-text address, e.g. MK1 from "… MK1 1AA".
 */
export function ukOutwardCode(address: string | null | undefined): string | null {
  if (!address?.trim()) return null

  const full = address.match(/\b([A-Z]{1,2}\d[A-Z\d]?)\s*\d[A-Z]{2}\b/i)
  if (full?.[1]) return full[1].toUpperCase()

  // Instructors sometimes store only the outward code.
  const tokens = address.toUpperCase().split(/[\s,]+/).filter(Boolean)
  for (let i = tokens.length - 1; i >= 0; i -= 1) {
    if (/^[A-Z]{1,2}\d[A-Z\d]?$/.test(tokens[i])) return tokens[i]
  }

  return null
}
