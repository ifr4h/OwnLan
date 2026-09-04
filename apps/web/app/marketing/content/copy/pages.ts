export const instructorsCopy = {
  meta: {
    title: 'For instructors',
    description:
      'OwnLane helps independent UK driving instructors run diary, pupils, progress and money without franchise overhead.',
  },
  hero: {
    title: 'Built for instructors who run their own school',
    lead:
      'You teach, drive, manage bookings, chase payments and remember where each pupil is. OwnLane keeps that in one place.',
  },
  problems: [
    {
      title: 'Bookings and gaps',
      body: 'Diary with travel warnings. Recurring lessons. When a slot opens, see pupils who could fit.',
    },
    {
      title: 'Pupil context',
      body: 'Last lesson, next focus, test date, credit balance. On Today before you pick them up.',
    },
    {
      title: 'Late cancellations',
      body: 'Empty-seat suggestions when someone cancels. Less time messaging around.',
    },
    {
      title: 'Payments',
      body: 'Packages, balances, what is owed. Record cash and bank transfer. Business overview for the month.',
    },
    {
      title: 'Lesson records',
      body: 'Notes, learner-visible summary, skill tags. Complete once at the end of the lesson.',
    },
    {
      title: 'Pupil experience',
      body: 'Give them a portal with lessons, progress, routes and resources, instead of another reminder text.',
    },
  ],
  switching: {
    title: 'Switching does not need a big migration project',
    body:
      'Import pupils from CSV. Add lessons as you go. Invite pupils to the portal when you are ready. Start with Today and the diary.',
  },
} as const

export const learnersCopy = {
  meta: {
    title: 'For learners',
    description:
      'What you get when your driving instructor uses OwnLane — lessons, progress, routes, resources and lesson playback.',
  },
  hero: {
    title: 'Your driving lessons in one place',
    lead:
      'If your instructor uses OwnLane, you get a proper app for your lessons, progress and learning between drives.',
  },
  includes: [
    { title: 'Upcoming lessons', body: 'Time, pickup and what you are working on next.' },
    { title: 'Lesson history', body: 'Recaps from completed lessons with skills practised.' },
    { title: 'Progress', body: 'Skills by area with history from your lessons.' },
    { title: 'Routes', body: 'Maps from lessons your instructor shares.' },
    { title: 'Learn', body: 'Interactive scenarios linked to what you are practising.' },
    { title: 'Lesson playback', body: 'Revisit the route, marked moments and explanations.' },
    { title: 'Purchase history', body: 'Lesson credit and activity on packages.' },
    { title: 'Test journey', body: 'Practical test countdown and theory status when recorded.' },
  ],
  note:
    'You need an invite from your instructor. OwnLane is not a way to find a driving instructor — it works with the instructor you already have.',
} as const

export const aboutCopy = {
  meta: {
    title: 'About OwnLane',
    description:
      'OwnLane is building the operating platform for independent UK driving instructors.',
  },
  hero: {
    title: 'Independence for instructors who teach on their own',
    lead:
      'Large franchises bundle diary, pupils and marketing. Independent ADIs often patch together apps, spreadsheets and messages. OwnLane is the other option: professional tools without giving up control.',
  },
  body: [
    'We are a UK team building for the MVP stage: diary, pupils, teaching workflow, progress, money and a learner portal that pupils might actually use.',
    'OwnLane is in beta. We work with design partners who teach every week and tell us what matters.',
    'We do not claim thousands of instructors or fake testimonials. The product is the proof for now.',
  ],
} as const
