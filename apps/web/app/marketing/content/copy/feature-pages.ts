export type FeaturePageCopy = {
  slug: string
  meta: { title: string; description: string }
  hero: { title: string; lead: string }
  problem: string
  instructorSees: string[]
  pupilSees?: string[]
  connects: string
}

export const featurePageCopy: Record<string, FeaturePageCopy> = {
  diary: {
    slug: 'diary',
    meta: {
      title: 'Diary',
      description: 'Day, week and month diary with travel warnings, recurring lessons and gap suggestions.',
    },
    hero: {
      title: 'A diary that fits a driving instructor\'s week',
      lead: 'Lessons on a timeline, travel time between pickups, and a clear view when something opens up.',
    },
    problem: 'Paper diaries and generic calendars do not know your pupils live in different postcodes or that a cancelled slot could become two hours of teaching.',
    instructorSees: [
      'Day, week and month views',
      'Travel warnings when gaps are tight',
      'Recurring weekly bookings with preview',
      'Overlap detection',
      'Gap suggestions when a lesson is cancelled',
    ],
    connects: 'Links to pupils, Today brief, booking suggestions from each pupil\'s history.',
  },
  pupils: {
    slug: 'pupils',
    meta: {
      title: 'Pupils',
      description: 'Pupil records with test dates, packages, progress, availability and portal invites.',
    },
    hero: {
      title: 'One record per pupil',
      lead: 'Contact details, lessons, money, progress and portal access without opening five apps.',
    },
    problem: 'Important details end up in WhatsApp threads, notes apps and memory. That breaks down with fifteen active pupils.',
    instructorSees: [
      'Contact, pickup and private notes',
      'Test date, centre and theory status',
      'Package credit and amount due',
      'Availability windows',
      'CSV import and intake from enquiry links',
      'Portal invite (copy link)',
    ],
    pupilSees: ['Invite to create their own learner account'],
    connects: 'Every lesson, payment and progress record links back to the pupil.',
  },
  progress: {
    slug: 'progress',
    meta: {
      title: 'Progress',
      description: 'DVSA-aligned skills with lesson evidence and history, for instructors and learners.',
    },
    hero: {
      title: 'Progress tied to real lessons',
      lead: 'Skill ratings when you complete a lesson. History over time. No gamified readiness score.',
    },
    problem: 'A single percentage does not tell a pupil what to work on next or show an instructor what changed over three weeks of roundabouts.',
    instructorSees: [
      'DVSA skill catalogue on complete-lesson',
      'Ratings with lesson link',
      'Next focus saved for the next drive',
      'Per-pupil progress on pupil page',
    ],
    pupilSees: [
      'Skills by area in the portal',
      'History and evidence per skill',
      'Current focus before the next lesson',
    ],
    connects: 'Complete lesson → progress updates → pupil portal and Today brief.',
  },
  teaching: {
    slug: 'teaching',
    meta: {
      title: 'Teaching tools',
      description: 'Road boards, real-road overlays and lesson resources shared with learners.',
    },
    hero: {
      title: 'Explain junctions on a board they can reopen',
      lead: 'Teaching Studio for road layouts and real-road maps. Attach resources to lessons.',
    },
    problem: 'A verbal explanation in the car is gone when the engine stops. Diagrams on paper get lost.',
    instructorSees: [
      'Road board canvas with templates',
      'Real-road map with draw overlay',
      'Resource library',
      'Route recording with marked moments',
      'Share resources to the learner portal',
    ],
    pupilSees: [
      'Resources from lessons in Learn',
      'Teaching Studio scenes in lesson playback',
    ],
    connects: 'Resources flow from Teaching Studio → lesson → learner portal and playback.',
  },
  money: {
    slug: 'money',
    meta: {
      title: 'Money',
      description: 'Business overview, packages, payments and expenses for driving instructors.',
    },
    hero: {
      title: 'What you earned and what is still owed',
      lead: 'Month view for the business. Per-pupil packages and balances on each pupil record.',
    },
    problem: 'Spreadsheets for income, mental notes for who owes what, and no link between diary gaps and money.',
    instructorSees: [
      'Money received, outstanding and spending',
      'Packages and manual payment recording',
      'Expenses by category',
      'CSV export',
      'Per-pupil credit and amount due',
    ],
    pupilSees: ['Purchase history and lesson credit in the portal'],
    connects: 'Completing a lesson can use package credit or create a charge automatically.',
  },
}
