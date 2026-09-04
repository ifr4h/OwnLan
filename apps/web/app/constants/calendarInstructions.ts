export type CalendarPlatform = {
  id: string
  name: string
  steps: string[]
}

export const calendarPlatforms: CalendarPlatform[] = [
  {
    id: 'apple',
    name: 'Apple Calendar',
    steps: [
      'Copy your OwnLane calendar link.',
      'Open Calendar on your Mac, iPhone or iPad.',
      'Choose File → New Calendar Subscription (Mac) or Add Calendar → Add Subscription Calendar (iPhone/iPad).',
      'Paste the link and save.',
    ],
  },
  {
    id: 'google',
    name: 'Google Calendar',
    steps: [
      'Copy your OwnLane calendar link.',
      'In Google Calendar, open Settings → Add calendar → From URL.',
      'Paste the link and add the calendar.',
    ],
  },
  {
    id: 'outlook',
    name: 'Outlook',
    steps: [
      'Copy your OwnLane calendar link.',
      'In Outlook, go to Add calendar → Subscribe from web.',
      'Paste the link and import.',
    ],
  },
]
