export const marketingSite = {
  name: 'OwnLane',
  tagline: 'Run your driving school. Keep your independence.',
  betaLabel: 'Beta',
  cta: {
    primary: 'Join the beta',
    primaryTo: '/register',
    secondary: 'See how it works',
    secondaryTo: '#how-it-works',
    signIn: 'Sign in',
    signInTo: '/login',
  },
  nav: [
    {
      label: 'Product',
      children: [
        { label: 'Features overview', to: '/features' },
        { label: 'Diary', to: '/features/diary' },
        { label: 'Pupils', to: '/features/pupils' },
        { label: 'Progress', to: '/features/progress' },
        { label: 'Teaching tools', to: '/features/teaching' },
        { label: 'Money', to: '/features/money' },
      ],
    },
    { label: 'For instructors', to: '/instructors' },
    { label: 'For learners', to: '/learners' },
    { label: 'Pricing', to: '/pricing' },
  ],
  footer: {
    product: [
      { label: 'Features', to: '/features' },
      { label: 'For instructors', to: '/instructors' },
      { label: 'For learners', to: '/learners' },
      { label: 'Pricing', to: '/pricing' },
    ],
    company: [
      { label: 'About', to: '/about' },
    ],
    legal: [
      { label: 'Sign in', to: '/login' },
      { label: 'Create account', to: '/register' },
    ],
  },
} as const
