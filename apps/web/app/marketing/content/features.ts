export type FeatureStatus = 'available' | 'coming-soon' | 'future'

export type MarketingFeature = {
  slug: string
  title: string
  shortDescription: string
  status: FeatureStatus
  /** Public route when available */
  route?: string
  audience: 'instructor' | 'learner' | 'both'
}

/** Single source of truth for what we claim on the marketing site. */
export const marketingFeatures: MarketingFeature[] = [
  {
    slug: 'diary',
    title: 'Diary',
    shortDescription: 'Day, week and month views with travel warnings, recurring bookings and gap suggestions when a slot opens up.',
    status: 'available',
    route: '/features/diary',
    audience: 'instructor',
  },
  {
    slug: 'pupils',
    title: 'Pupils',
    shortDescription: 'Contact details, test dates, packages, balances, availability and a record that stays with each pupil.',
    status: 'available',
    route: '/features/pupils',
    audience: 'instructor',
  },
  {
    slug: 'progress',
    title: 'Progress',
    shortDescription: 'DVSA-aligned skills with lesson history and evidence, not a single readiness percentage.',
    status: 'available',
    route: '/features/progress',
    audience: 'both',
  },
  {
    slug: 'teaching',
    title: 'Teaching tools',
    shortDescription: 'Road boards, real-road overlays and resources you can share with pupils after the lesson.',
    status: 'available',
    route: '/features/teaching',
    audience: 'both',
  },
  {
    slug: 'money',
    title: 'Money',
    shortDescription: 'What you earned, what is still owed, packages and expenses in one place.',
    status: 'available',
    route: '/features/money',
    audience: 'instructor',
  },
  {
    slug: 'today',
    title: 'Today',
    shortDescription: 'Morning brief with the next lesson, pickup, credit line, next focus and what needs attention.',
    status: 'available',
    audience: 'instructor',
  },
  {
    slug: 'learner-portal',
    title: 'Learner portal',
    shortDescription: 'Lessons, progress, routes, resources and purchase history in a proper app for your pupils.',
    status: 'available',
    audience: 'learner',
  },
  {
    slug: 'lesson-playback',
    title: 'Lesson playback',
    shortDescription: 'Route map, timeline, marked moments and instructor explanations pupils can revisit.',
    status: 'available',
    audience: 'learner',
  },
  {
    slug: 'routes',
    title: 'Routes',
    shortDescription: 'Shared lesson routes on a map with focus and summary from each drive.',
    status: 'available',
    audience: 'learner',
  },
  {
    slug: 'learn',
    title: 'Learn',
    shortDescription: 'Interactive road scenarios personalised from lesson focus and instructor resources.',
    status: 'available',
    audience: 'learner',
  },
  {
    slug: 'private-practice',
    title: 'Private practice',
    shortDescription: 'Learners log practice drives; you see a summary since the last lesson.',
    status: 'available',
    audience: 'both',
  },
  {
    slug: 'enquiries',
    title: 'Enquiries',
    shortDescription: 'Share an intake link, review submissions and add pupils to your list or waiting list.',
    status: 'available',
    audience: 'instructor',
  },
  {
    slug: 'calendar-sync',
    title: 'Calendar sync',
    shortDescription: 'Sync with Google Calendar or Apple Calendar.',
    status: 'coming-soon',
    audience: 'instructor',
  },
  {
    slug: 'payment-links',
    title: 'Card payments',
    shortDescription: 'Payment links and card processing through OwnLane.',
    status: 'future',
    audience: 'instructor',
  },
  {
    slug: 'marketplace',
    title: 'Marketplace',
    shortDescription: 'Public booking and instructor discovery.',
    status: 'future',
    audience: 'both',
  },
]

export const availableFeatures = marketingFeatures.filter(f => f.status === 'available')
export const featurePages = marketingFeatures.filter(f => f.route)

export function getFeature(slug: string): MarketingFeature | undefined {
  return marketingFeatures.find(f => f.slug === slug)
}
