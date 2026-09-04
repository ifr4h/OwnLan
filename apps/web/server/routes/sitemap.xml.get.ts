import { MARKETING_SITEMAP_PATHS } from '~/marketing/utils/routes'

export default defineEventHandler((event) => {
  const host = getRequestURL(event).origin
  const urls = MARKETING_SITEMAP_PATHS.map(path =>
    `  <url><loc>${host}${path}</loc><changefreq>weekly</changefreq></url>`,
  ).join('\n')

  const xml = `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls}
</urlset>`

  setHeader(event, 'Content-Type', 'application/xml')
  return xml
})
