/**
 * Homepage marketing copy — DRAFT for founder review.
 * Keep concrete and situation-specific; avoid AI SaaS patterns.
 */
export const homeCopy = {
  hero: {
    eyebrow: 'For independent UK driving instructors',
    title: 'Run your driving school without the usual admin.',
    lead:
      'Diary, pupils, lessons, progress and money in one place. Your pupils get a proper learning app too.',
    note: 'Draft copy — subject to founder review.',
  },
  independence: {
    title: 'Your school. Your pupils. Your way of teaching.',
    body:
      'OwnLane is for ADIs and PDIs who run their own business. Not a franchise app. Not a generic booking widget. Software that fits around a real teaching week.',
  },
  workingDay: {
    title: 'A day that already knows what happened last time',
    intro:
      'Information should move through the day. You should not have to dig through messages to remember where Amina left off.',
  },
  diary: {
    title: 'When a gap opens, you see who could fill it',
    body:
      'Cancel a lesson and OwnLane looks at pupil availability and history. Three names appear before you start scrolling through your contacts.',
  },
  pupil: {
    title: 'The pupil record is the record',
    body:
      'Next lesson, last lesson, next focus, test date, credit balance. Not scattered across WhatsApp, notes apps and a paper diary.',
  },
  continuity: {
    title: 'OwnLane remembers where you left off',
    body:
      'Complete a lesson, set the next focus, book the next slot. Next time you open Today or the pupil page, the context is already there.',
  },
  teaching: {
    title: 'Explain it once. They can look again later.',
    body:
      'Draw on a road board or mark a junction on a real map. Attach it to the lesson. Amina sees it in her portal after she gets home.',
  },
  learner: {
    title: 'Your pupils get more than a booking link',
    body:
      'Upcoming lessons, progress, routes from lessons, interactive practice and lesson playback. Something they might actually open between drives.',
  },
  playback: {
    title: 'Go back through the lesson',
    body:
      'The route on a map, moments you marked, explanations you saved. Useful the night before the next drive.',
  },
  progress: {
    title: 'Progress with evidence',
    body:
      'Skill ratings tied to lessons. A history of what happened. No made-up readiness percentage.',
  },
  money: {
    title: 'Know what you earned and what is still owed',
    body:
      'September totals, outstanding balances, packages and expenses. Business view separate from each pupil\'s credit.',
  },
  connection: {
    title: 'One system, two views',
    body:
      'You set the next focus. They see it before the lesson. You share a junction explanation. It appears in their Learn section.',
  },
  beta: {
    title: 'We are building OwnLane with working instructors',
    body:
      'The product is in beta. Core teaching, diary, pupil and learner workflows work today. Create an account and tell us what would make your week easier.',
  },
} as const

export const dayMoments = [
  {
    id: 'before',
    label: 'Before the first lesson',
    time: '08:45',
    items: [
      { label: '09:00 Amina Yusuf', detail: 'Last time: roundabouts' },
      { label: 'Next focus', detail: 'Lane choice' },
      { label: 'Credit', detail: '4 hours' },
    ],
    note: 'Context on Today before you leave the house.',
  },
  {
    id: 'between',
    label: 'Between lessons',
    time: '11:30',
    items: [
      { label: '11:30–13:30 free', detail: '2 hours' },
      { label: 'Could fit', detail: '3 pupils' },
    ],
    note: 'Gap matching when a slot is empty.',
  },
  {
    id: 'after',
    label: 'After a lesson',
    time: '17:15',
    items: [
      { label: 'Roundabouts', detail: 'Developing' },
      { label: 'Next focus', detail: 'Independent lane choice' },
    ],
    note: 'Complete the lesson once. Progress and next focus saved.',
  },
  {
    id: 'evening',
    label: 'Evening',
    time: '20:00',
    items: [
      { label: 'Need another lesson', detail: '4 pupils' },
      { label: 'Outstanding', detail: '£186' },
      { label: 'Tomorrow', detail: '5 lessons' },
    ],
    note: 'What needs attention before tomorrow.',
  },
] as const
