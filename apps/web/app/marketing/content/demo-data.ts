/** Consistent marketing demo world — not necessarily identical to seeder spellings. */
export const marketingInstructor = {
  name: 'Sarah Mills',
  school: 'Sarah Mills Driving',
} as const

export const marketingPupils = {
  amina: {
    firstName: 'Amina',
    lastName: 'Yusuf',
    fullName: 'Amina Yusuf',
    nextLesson: 'Tomorrow, 4:00',
    lastLesson: 'Roundabouts',
    nextFocus: 'Independent lane choice',
    testDays: 18,
    creditHours: 4,
    pickup: '12 Oakfield Road',
  },
  jack: {
    firstName: 'Jack',
    lastName: 'Collins',
    fullName: 'Jack Collins',
    time: '11:00',
  },
  sophie: {
    firstName: 'Sophie',
    lastName: 'Ahmed',
    fullName: 'Sophie Ahmed',
    time: '16:00',
  },
} as const

export const marketingDiary = {
  day: 'Thursday',
  date: '11 September',
  lessons: [
    { time: '09:00', pupil: marketingPupils.amina.fullName, duration: '1h 30m' },
    { time: '11:00', pupil: marketingPupils.jack.fullName, duration: '1h' },
    { time: '13:00', gap: true, gapLabel: '13:00–15:00', gapMatches: 3 },
    { time: '16:00', pupil: marketingPupils.sophie.fullName, duration: '1h 30m' },
  ],
} as const

export const marketingMoney = {
  month: 'September',
  earned: '£2,680',
  outstanding: '£420',
  projected: '£3,720',
  goal: '£4,000',
  shortOfGoal: '£280',
  diaryHours: '7.5 hours',
} as const

export const marketingDayBrief = {
  evening: {
    needRebook: 4,
    outstanding: '£186',
    tomorrowLessons: 5,
  },
} as const

export const marketingProgress = {
  skill: 'Roundabouts',
  current: 'Developing independence',
  history: [
    { date: '12 Aug', note: 'Needed prompts for lane choice' },
    { date: '19 Aug', note: 'Less help needed' },
    { date: '26 Aug', note: 'Handled smaller roundabouts independently' },
  ],
  next: 'Larger multi-lane roundabouts',
} as const

export const marketingLearnerHome = {
  greeting: 'Good morning, Amina',
  nextDrive: 'Tomorrow\'s drive',
  nextDriveTime: '4:00–5:30',
  focus: 'Independent lane choice',
  hoursDriven: '26h 30m',
  lessonsDone: 20,
  lastLesson: 'Roundabouts',
  testCountdown: '18 days',
} as const
