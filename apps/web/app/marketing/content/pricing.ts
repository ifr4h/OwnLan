/**
 * Pricing copy — draft only. Values sourced from docs/07-business-market-gtm.md.
 * Do not treat as final until founder approval.
 */
export const pricingDraft = {
  status: 'draft' as const,
  disclaimer:
    'Pricing is not final. OwnLane is in beta — join early and help shape what we build.',
  tiers: [
    {
      id: 'core',
      name: 'Core',
      priceLabel: 'Free during beta',
      priceNote: 'Generous core while we validate with independent instructors',
      description:
        'Diary, pupils, progress, learner portal, lesson completion, teaching tools and basic payment records.',
      highlights: [
        'Today brief and diary',
        'Pupil records and CSV import',
        'Complete-lesson workflow',
        'Learner portal for your pupils',
        'Money overview and packages',
      ],
      cta: 'Join the beta',
      ctaTo: '/register',
      featured: true,
    },
    {
      id: 'pro',
      name: 'Pro',
      priceLabel: 'From ~£20/month',
      priceNote: 'Indicative — not final. Likely £19.99–£24.99/month when we launch paid tiers.',
      description:
        'Advanced automation, finance, analytics and growth tools as they ship.',
      highlights: [
        'Everything in Core',
        'Advanced finance and reporting',
        'CRM and growth tools',
        'Priority access to new features',
      ],
      cta: 'Join the beta',
      ctaTo: '/register',
      featured: false,
    },
    {
      id: 'school',
      name: 'Driving school',
      priceLabel: 'Later',
      priceNote: 'Multi-instructor schools — pricing to validate',
      description: 'For ADI teams and small schools when the product is ready.',
      highlights: [
        'Multiple instructors',
        'Shared pupils and diary',
        'Business reporting',
      ],
      cta: 'Get in touch',
      ctaTo: '/about',
      featured: false,
    },
  ],
  foundingNote:
    'We are talking to founding instructors who want a professional setup without franchise overhead. Early partners may receive extended access while we refine the product.',
} as const
