type SeoOptions = {
  title: string
  description: string
  path?: string
}

export function useMarketingSeo(options: SeoOptions) {
  const title = options.title.includes('OwnLane')
    ? options.title
    : `${options.title} · OwnLane`

  useSeoMeta({
    title,
    description: options.description,
    ogTitle: title,
    ogDescription: options.description,
    ogType: 'website',
    twitterCard: 'summary_large_image',
  })

  if (options.path) {
    useHead({
      link: [{ rel: 'canonical', href: `https://ownlane.co.uk${options.path}` }],
    })
  }
}
