<template>
  <div class="join">
    <header class="join__top">
      <div class="join__brand">
        <OlBrand />
      </div>
      <p v-if="peek?.instructor.display_name" class="join__school">
        {{ peek.instructor.display_name }}
      </p>
    </header>

    <main class="join__main">
      <p v-if="peekLoading" class="muted">Opening your form…</p>
      <p v-else-if="peekError" class="error" role="alert">{{ peekError }}</p>

      <template v-else-if="done">
        <section class="block">
          <h1 class="block__title">Thanks{{ doneName ? `, ${doneName}` : '' }}</h1>
          <p class="block__copy">
            {{ doneMessage }}
          </p>
        </section>
      </template>

      <template v-else-if="peek">
        <!-- YOU -->
        <section v-if="section === 'you'" class="block">
          <p class="block__eyebrow">You</p>
          <h1 class="block__title">
            {{ greeting }}
          </h1>
          <p class="block__copy">A few quick details so your instructor can get ready.</p>

          <div class="fields">
            <label class="field">
              <span class="field__label">First name</span>
              <input v-model="firstName" class="field__input" type="text" autocomplete="given-name" required>
            </label>
            <label class="field">
              <span class="field__label">Last name</span>
              <input v-model="lastName" class="field__input" type="text" autocomplete="family-name" required>
            </label>
            <label class="field">
              <span class="field__label">Mobile</span>
              <input
                v-model="mobile"
                class="field__input"
                type="tel"
                inputmode="tel"
                autocomplete="tel"
                placeholder="07…"
                required
              >
            </label>
            <label class="field">
              <span class="field__label">
                Email
                <span class="field__optional">optional</span>
              </span>
              <input v-model="email" class="field__input" type="email" autocomplete="email">
            </label>

            <template v-if="showPickup">
              <label class="field">
                <span class="field__label">
                  Usual pickup
                  <span class="field__optional">optional</span>
                </span>
                <textarea
                  v-model="pickup"
                  class="field__textarea"
                  rows="2"
                  placeholder="Home address or meeting point"
                />
              </label>
            </template>
            <button
              v-else
              class="skip"
              type="button"
              @click="showPickup = true"
            >
              Add pickup address?
            </button>
          </div>

          <p v-if="sectionError" class="error" role="alert">{{ sectionError }}</p>
          <button class="btn" type="button" @click="goDriving">
            Continue
            <span aria-hidden="true">→</span>
          </button>
          <button
            v-if="showPickup"
            class="skip"
            type="button"
            @click="showPickup = false; pickup = ''"
          >
            Skip pickup for now
          </button>
        </section>

        <!-- DRIVING -->
        <section v-else-if="section === 'driving'" class="block">
          <p class="block__eyebrow">Driving</p>

          <template v-if="drivingStep === 'had_lessons'">
            <h1 class="block__title">Have you driven before?</h1>
            <div class="choices">
              <button class="choice" type="button" @click="setDrivenBefore('no')">
                Completely new
              </button>
              <button class="choice" type="button" @click="setDrivenBefore('lessons')">
                Lessons with another instructor
              </button>
              <button class="choice" type="button" @click="setDrivenBefore('private')">
                Private practice only
              </button>
              <button class="choice" type="button" @click="setDrivenBefore('both')">
                Lessons and private practice
              </button>
            </div>
          </template>

          <template v-else-if="drivingStep === 'hours'">
            <h1 class="block__title">Roughly how many lesson hours?</h1>
            <div class="choices">
              <button
                v-for="opt in hoursOptions"
                :key="opt.value"
                class="choice"
                type="button"
                @click="lessonHoursBand = opt.value; drivingStep = 'last_drove'"
              >
                {{ opt.label }}
              </button>
            </div>
            <button class="skip" type="button" @click="drivingStep = 'last_drove'">Skip</button>
          </template>

          <template v-else-if="drivingStep === 'last_drove'">
            <h1 class="block__title">When did you last drive?</h1>
            <div class="choices">
              <button
                v-for="opt in lastDroveOptions"
                :key="opt.value"
                class="choice"
                type="button"
                @click="onLastDrove(opt.value)"
              >
                {{ opt.label }}
              </button>
            </div>
            <button class="skip" type="button" @click="afterLastDrove">
              Skip
            </button>
          </template>

          <template v-else-if="drivingStep === 'skills'">
            <h1 class="block__title">What have you practised?</h1>
            <p class="block__copy">Tap anything that sounds familiar — no pressure.</p>
            <div class="choices choices--wrap">
              <button
                v-for="(label, key) in skillGroups"
                :key="key"
                class="choice choice--chip"
                type="button"
                :data-on="skillsPractised.includes(key) ? 'yes' : 'no'"
                @click="toggleSkill(key)"
              >
                {{ label }}
              </button>
            </div>
            <label class="field">
              <span class="field__label">
                Anything you remember working on?
                <span class="field__optional">optional</span>
              </span>
              <textarea v-model="rememberWorkingOn" class="field__textarea" rows="2" />
            </label>
            <button class="btn" type="button" @click="drivingStep = 'transmission'">
              Continue
              <span aria-hidden="true">→</span>
            </button>
            <button class="skip" type="button" @click="drivingStep = 'transmission'">Skip</button>
          </template>

          <template v-else-if="drivingStep === 'transmission'">
            <h1 class="block__title">Manual or automatic?</h1>
            <div class="choices">
              <button class="choice" type="button" @click="setTransmission('manual')">Manual</button>
              <button class="choice" type="button" @click="setTransmission('automatic')">Automatic</button>
              <button class="choice" type="button" @click="setTransmission('either')">Not sure</button>
            </div>
          </template>
        </section>

        <!-- TESTS -->
        <section v-else-if="section === 'tests'" class="block">
          <p class="block__eyebrow">Tests</p>

          <template v-if="testsStep === 'theory'">
            <h1 class="block__title">Have you passed your theory?</h1>
            <div class="choices">
              <button class="choice" type="button" @click="setTheory('passed')">Yes</button>
              <button class="choice" type="button" @click="setTheory('not_yet')">Not yet</button>
              <button class="choice" type="button" @click="setTheory('booked')">Booked</button>
            </div>
          </template>

          <template v-else-if="testsStep === 'theory_date'">
            <h1 class="block__title">
              {{ theoryStatus === 'booked' ? 'When is it booked for?' : 'When did you pass?' }}
            </h1>
            <label class="field">
              <span class="field__label">Date</span>
              <input v-model="theoryPassDate" class="field__input" type="date">
            </label>
            <button class="btn" type="button" @click="testsStep = 'practical'">
              Continue
              <span aria-hidden="true">→</span>
            </button>
            <button class="skip" type="button" @click="theoryPassDate = ''; testsStep = 'practical'">
              Skip
            </button>
          </template>

          <template v-else-if="testsStep === 'practical'">
            <h1 class="block__title">Is your practical test booked?</h1>
            <div class="choices">
              <button class="choice" type="button" @click="setPractical(true)">Yes</button>
              <button class="choice" type="button" @click="setPractical(false)">No</button>
            </div>
          </template>

          <template v-else-if="testsStep === 'practical_details'">
            <h1 class="block__title">When and where?</h1>
            <div class="fields">
              <label class="field">
                <span class="field__label">Date</span>
                <input v-model="practicalDate" class="field__input" type="date">
              </label>
              <label class="field">
                <span class="field__label">
                  Time
                  <span class="field__optional">optional</span>
                </span>
                <input v-model="practicalTime" class="field__input" type="time">
              </label>
              <label class="field">
                <span class="field__label">
                  Test centre
                  <span class="field__optional">optional</span>
                </span>
                <input v-model="practicalCentre" class="field__input" type="text" placeholder="e.g. Isleworth">
              </label>
            </div>
            <button class="btn" type="button" @click="section = 'availability'">
              Continue
              <span aria-hidden="true">→</span>
            </button>
            <button class="skip" type="button" @click="section = 'availability'">Skip</button>
          </template>
        </section>

        <!-- AVAILABILITY -->
        <section v-else-if="section === 'availability'" class="block">
          <p class="block__eyebrow">Availability</p>
          <h1 class="block__title">When are you usually free?</h1>
          <p class="block__copy">Rough is fine — mornings, afternoons, evenings.</p>

          <div
            v-for="day in weekdays"
            :key="day.value"
            class="day"
          >
            <p class="day__name">{{ day.label }}</p>
            <div class="day__slots">
              <button
                v-for="slot in slots"
                :key="slot.value"
                class="choice choice--chip"
                type="button"
                :data-on="hasSlot(day.value, slot.value) ? 'yes' : 'no'"
                @click="toggleSlot(day.value, slot.value)"
              >
                {{ slot.label }}
              </button>
            </div>
          </div>

          <label class="toggle">
            <input v-model="changesOften" type="checkbox">
            <span>My availability often changes</span>
          </label>

          <button class="btn" type="button" @click="section = 'about'">
            Continue
            <span aria-hidden="true">→</span>
          </button>
          <button class="skip" type="button" @click="clearAvailability(); section = 'about'">
            Skip for now
          </button>
        </section>

        <!-- ABOUT -->
        <section v-else-if="section === 'about'" class="block">
          <p class="block__eyebrow">About</p>
          <h1 class="block__title">Anything else that’s useful?</h1>
          <p class="block__copy">All optional — skip anything you like.</p>

          <div class="fields">
            <div>
              <p class="field__label">What’s your goal?</p>
              <div class="choices choices--wrap">
                <button
                  v-for="opt in goalOptions"
                  :key="opt.value"
                  class="choice choice--chip"
                  type="button"
                  :data-on="goal === opt.value ? 'yes' : 'no'"
                  @click="goal = goal === opt.value ? '' : opt.value"
                >
                  {{ opt.label }}
                </button>
              </div>
            </div>

            <label class="field">
              <span class="field__label">What should your instructor know?</span>
              <textarea
                v-model="instructorShouldKnow"
                class="field__textarea"
                rows="3"
                placeholder="Nerves, medical notes, anything that helps the first lesson…"
              />
            </label>

            <div>
              <p class="field__label">How do you feel about driving?</p>
              <div class="choices choices--wrap">
                <button
                  v-for="opt in confidenceOptions"
                  :key="opt.value"
                  class="choice choice--chip"
                  type="button"
                  :data-on="confidence === opt.value ? 'yes' : 'no'"
                  @click="confidence = opt.value"
                >
                  {{ opt.label }}
                </button>
              </div>
            </div>

            <div>
              <p class="field__label">Any private practice?</p>
              <div class="choices choices--wrap">
                <button
                  v-for="opt in privatePracticeOptions"
                  :key="opt.value"
                  class="choice choice--chip"
                  type="button"
                  :data-on="privatePractice === opt.value ? 'yes' : 'no'"
                  @click="privatePractice = opt.value"
                >
                  {{ opt.label }}
                </button>
              </div>
            </div>

            <div>
              <p class="field__label">Preferred contact</p>
              <div class="choices choices--wrap">
                <button
                  v-for="opt in contactOptions"
                  :key="opt.value"
                  class="choice choice--chip"
                  type="button"
                  :data-on="preferredContact === opt.value ? 'yes' : 'no'"
                  @click="preferredContact = preferredContact === opt.value ? '' : opt.value"
                >
                  {{ opt.label }}
                </button>
              </div>
            </div>

            <template v-if="peek.instructor.has_cancellation_policy">
              <div class="terms">
                <button class="text-link" type="button" @click="showTerms = !showTerms">
                  {{ showTerms ? 'Hide' : 'View' }} cancellation policy
                </button>
                <p v-if="termsLoading" class="muted">Loading…</p>
                <p v-else-if="termsError" class="error">{{ termsError }}</p>
                <div v-else-if="showTerms && terms" class="terms__body">
                  <p class="terms__biz">{{ terms.business_name }}</p>
                  <p class="terms__text">{{ terms.cancellation_policy }}</p>
                </div>
                <label class="toggle">
                  <input v-model="termsAcknowledged" type="checkbox">
                  <span>I’ve read and understand the cancellation policy</span>
                </label>
              </div>
            </template>
          </div>

          <p v-if="sectionError" class="error" role="alert">{{ sectionError }}</p>

          <button class="btn" type="button" :disabled="submitting" @click="onSubmit">
            {{ submitting ? 'Sending…' : 'Send to instructor' }}
            <span aria-hidden="true">→</span>
          </button>
          <button
            class="skip"
            type="button"
            :disabled="submitting"
            @click="skipAboutAndSubmit"
          >
            Skip for now and send
          </button>
        </section>
      </template>
    </main>
  </div>
</template>

<script setup lang="ts">
import type { IntakeAnswers, IntakePeek, IntakeTerms } from '~/composables/useIntake'

definePageMeta({ layout: false })
useHead({ title: 'Join · OwnLane' })

const route = useRoute()
const { peekIntake, fetchIntakeTerms, submitIntake } = useIntake()

const token = computed(() => {
  const raw = route.query.token
  return typeof raw === 'string' ? raw : ''
})

type Section = 'you' | 'driving' | 'tests' | 'availability' | 'about'
type DrivingStep = 'had_lessons' | 'hours' | 'last_drove' | 'skills' | 'transmission'
type TestsStep = 'theory' | 'theory_date' | 'practical' | 'practical_details'

const peek = ref<IntakePeek | null>(null)
const peekLoading = ref(true)
const peekError = ref('')
const section = ref<Section>('you')
const sectionError = ref('')
const submitting = ref(false)
const done = ref(false)
const doneName = ref('')
const doneMessage = ref('')

const firstName = ref('')
const lastName = ref('')
const mobile = ref('')
const email = ref('')
const pickup = ref('')
const showPickup = ref(false)

const hadLessonsBefore = ref<boolean | null>(null)
const drivenBefore = ref<'no' | 'lessons' | 'private' | 'both' | ''>('')
const drivingStep = ref<DrivingStep>('had_lessons')
const lessonHoursBand = ref('')
const lastDrove = ref('')
const skillsPractised = ref<string[]>([])
const rememberWorkingOn = ref('')
const transmission = ref('')

const testsStep = ref<TestsStep>('theory')
const theoryStatus = ref('')
const theoryPassDate = ref('')
const practicalBooked = ref<boolean | null>(null)
const practicalDate = ref('')
const practicalTime = ref('')
const practicalCentre = ref('')

const availabilityDays = ref<Record<number, string[]>>({})
const changesOften = ref(false)

const goal = ref('')
const instructorShouldKnow = ref('')
const confidence = ref('')
const privatePractice = ref('')
const preferredContact = ref('')
const termsAcknowledged = ref(false)
const showTerms = ref(false)
const terms = ref<IntakeTerms | null>(null)
const termsLoading = ref(false)
const termsError = ref('')

const skillGroups = computed(() => peek.value?.skill_groups ?? {})

const greeting = computed(() => {
  const name = firstName.value.trim() || peek.value?.prefill.first_name
  return name ? `Hi ${name}` : 'Hi there'
})

const hoursOptions = [
  { value: 'under_5', label: 'Under 5' },
  { value: '5_10', label: '5–10' },
  { value: '10_20', label: '10–20' },
  { value: '20_40', label: '20–40' },
  { value: '40_plus', label: '40+' },
  { value: 'not_sure', label: 'Not sure' },
]

const lastDroveOptions = [
  { value: 'this_week', label: 'This week' },
  { value: 'this_month', label: 'This month' },
  { value: '1_3_months', label: '1–3 months ago' },
  { value: '3_6_months', label: '3–6 months ago' },
  { value: 'over_6_months', label: 'Over 6 months ago' },
  { value: 'over_a_year', label: 'Over a year ago' },
  { value: 'not_sure', label: 'Not sure' },
]

const weekdays = [
  { value: 1, label: 'Mon' },
  { value: 2, label: 'Tue' },
  { value: 3, label: 'Wed' },
  { value: 4, label: 'Thu' },
  { value: 5, label: 'Fri' },
  { value: 6, label: 'Sat' },
  { value: 7, label: 'Sun' },
]

const slots = [
  { value: 'morning', label: 'Morning' },
  { value: 'afternoon', label: 'Afternoon' },
  { value: 'evening', label: 'Evening' },
]

const goalOptions = [
  { value: 'from_scratch', label: 'Starting from scratch' },
  { value: 'gain_confidence', label: 'Building confidence' },
  { value: 'pass_test', label: 'Preparing for a test' },
  { value: 'returning', label: 'Returning after a break' },
  { value: 'switching', label: 'Switching instructors' },
  { value: 'particular_area', label: 'Improving a particular area' },
]

const confidenceOptions = [
  { value: 'very_nervous', label: 'Very nervous' },
  { value: 'a_little', label: 'A little' },
  { value: 'okay', label: 'Okay' },
  { value: 'confident', label: 'Confident' },
]

const privatePracticeOptions = [
  { value: 'yes', label: 'Yes' },
  { value: 'no', label: 'No' },
  { value: 'sometimes', label: 'Sometimes' },
]

const contactOptions = [
  { value: 'sms', label: 'Text' },
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'email', label: 'Email' },
  { value: 'call', label: 'Call' },
]

async function loadPeek() {
  peekLoading.value = true
  peekError.value = ''
  peek.value = null
  if (!token.value) {
    peekError.value = 'This link is missing its code. Ask your instructor for a new one.'
    peekLoading.value = false
    return
  }
  try {
    peek.value = await peekIntake(token.value)
    const p = peek.value.prefill
    if (p.first_name) firstName.value = p.first_name
    if (p.last_name) lastName.value = p.last_name
    if (p.mobile) mobile.value = p.mobile
    if (p.email) email.value = p.email
  } catch (e) {
    peekError.value = extractApiError(e, 'This link is invalid or has expired.')
  } finally {
    peekLoading.value = false
  }
}

function goDriving() {
  sectionError.value = ''
  if (!firstName.value.trim() || !lastName.value.trim() || !mobile.value.trim()) {
    sectionError.value = 'Please add your name and mobile number.'
    return
  }
  section.value = 'driving'
  drivingStep.value = 'had_lessons'
}

function setDrivenBefore(value: 'no' | 'lessons' | 'private' | 'both') {
  drivenBefore.value = value
  const hadLessons = value === 'lessons' || value === 'both'
  hadLessonsBefore.value = hadLessons
  if (hadLessons) {
    drivingStep.value = 'hours'
  } else {
    // Completely new or private-only — skip lesson-hours / skills.
    skillsPractised.value = []
    lessonHoursBand.value = ''
    lastDrove.value = value === 'private' ? lastDrove.value : ''
    drivingStep.value = value === 'private' ? 'last_drove' : 'transmission'
  }
}

function onLastDrove(value: string) {
  lastDrove.value = value
  afterLastDrove()
}

function afterLastDrove() {
  drivingStep.value = hadLessonsBefore.value ? 'skills' : 'transmission'
}

function setTransmission(value: string) {
  transmission.value = value
  section.value = 'tests'
  testsStep.value = 'theory'
}

function toggleSkill(key: string) {
  const i = skillsPractised.value.indexOf(key)
  if (i >= 0) skillsPractised.value.splice(i, 1)
  else skillsPractised.value.push(key)
}

function setTheory(status: string) {
  theoryStatus.value = status
  if (status === 'passed' || status === 'booked') {
    testsStep.value = 'theory_date'
  } else {
    testsStep.value = 'practical'
  }
}

function setPractical(yes: boolean) {
  practicalBooked.value = yes
  if (yes) {
    testsStep.value = 'practical_details'
  } else {
    section.value = 'availability'
  }
}

function hasSlot(weekday: number, slot: string): boolean {
  return (availabilityDays.value[weekday] ?? []).includes(slot)
}

function toggleSlot(weekday: number, slot: string) {
  const current = [...(availabilityDays.value[weekday] ?? [])]
  const i = current.indexOf(slot)
  if (i >= 0) current.splice(i, 1)
  else current.push(slot)
  availabilityDays.value = { ...availabilityDays.value, [weekday]: current }
}

function clearAvailability() {
  availabilityDays.value = {}
  changesOften.value = false
}

watch(showTerms, async (open) => {
  if (!open || terms.value || !token.value) return
  termsLoading.value = true
  termsError.value = ''
  try {
    terms.value = await fetchIntakeTerms(token.value)
  } catch (e) {
    termsError.value = extractApiError(e, 'Could not load terms.')
  } finally {
    termsLoading.value = false
  }
})

function buildAnswers(): IntakeAnswers {
  const days = Object.entries(availabilityDays.value)
    .map(([weekday, daySlots]) => ({
      weekday: Number(weekday),
      slots: daySlots,
    }))
    .filter((d) => d.slots.length > 0)

  const driving: IntakeAnswers['driving'] = {
    had_lessons_before: hadLessonsBefore.value === true,
    driven_before: drivenBefore.value || (hadLessonsBefore.value ? 'lessons' : 'no'),
    transmission: transmission.value || null,
  }
  if (hadLessonsBefore.value === true) {
    if (lessonHoursBand.value) driving.lesson_hours_band = lessonHoursBand.value
    if (lastDrove.value) driving.last_drove = lastDrove.value
    if (skillsPractised.value.length) driving.skills_practised = [...skillsPractised.value]
    if (rememberWorkingOn.value.trim()) driving.remember_working_on = rememberWorkingOn.value.trim()
  } else if (drivenBefore.value === 'private') {
    if (lastDrove.value) driving.last_drove = lastDrove.value
  }

  return {
    identity: {
      first_name: firstName.value.trim(),
      last_name: lastName.value.trim(),
      mobile: mobile.value.trim(),
      email: email.value.trim() || null,
      default_pickup_address: pickup.value.trim() || null,
    },
    driving,
    tests: {
      theory_status: theoryStatus.value || 'not_yet',
      theory_pass_date: theoryPassDate.value || null,
      practical_booked: practicalBooked.value === true,
      practical_date: practicalDate.value || null,
      practical_time: practicalTime.value || null,
      practical_centre: practicalCentre.value.trim() || null,
    },
    availability: {
      days,
      changes_often: changesOften.value,
    },
    about: {
      goal: goal.value || null,
      instructor_should_know: instructorShouldKnow.value.trim() || null,
      confidence: confidence.value || null,
      private_practice: privatePractice.value || null,
      preferred_contact: preferredContact.value || null,
    },
    terms_acknowledged: termsAcknowledged.value,
  }
}

async function onSubmit() {
  sectionError.value = ''
  if (peek.value?.instructor.has_cancellation_policy && !termsAcknowledged.value) {
    sectionError.value = 'Please confirm you’ve read the cancellation policy.'
    return
  }
  await sendAnswers()
}

async function skipAboutAndSubmit() {
  goal.value = ''
  instructorShouldKnow.value = ''
  confidence.value = ''
  privatePractice.value = ''
  preferredContact.value = ''
  if (peek.value?.instructor.has_cancellation_policy && !termsAcknowledged.value) {
    sectionError.value = 'Please confirm you’ve read the cancellation policy.'
    return
  }
  await sendAnswers()
}

async function sendAnswers() {
  submitting.value = true
  sectionError.value = ''
  try {
    const result = await submitIntake(token.value, buildAnswers())
    done.value = true
    doneName.value = result.first_name || firstName.value.trim()
    doneMessage.value = result.message
  } catch (e) {
    sectionError.value = extractApiError(e, 'Could not send your details.')
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  void loadPeek()
})

watch(token, () => {
  void loadPeek()
})
</script>

<style scoped>
.join {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: var(--color-chalk-green);
  color: var(--color-ink-black);
}

.join__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-12);
  padding: var(--spacing-16) var(--spacing-20);
}

.join__brand {
  display: inline-flex;
  align-items: center;
}

.join__school {
  font-size: var(--text-body-sm);
  opacity: 0.7;
  text-align: right;
}

.join__main {
  flex: 1;
  width: 100%;
  max-width: 520px;
  margin: 0 auto;
  padding: 0 var(--spacing-20) var(--spacing-32);
}

.block {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  background: var(--color-paper-white);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--spacing-20);
}

.block__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.55;
}

.block__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  line-height: var(--leading-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.block__copy {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
  opacity: 0.75;
  margin-top: calc(var(--spacing-8) * -1);
}

.fields {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.field__label {
  font-size: var(--text-body-sm);
  display: flex;
  align-items: baseline;
  gap: var(--spacing-8);
}

.field__optional {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.55;
}

.field__input,
.field__textarea {
  width: 100%;
  min-height: 48px;
  padding: 12px 20px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-inputs);
  background: var(--color-chalk-green);
  font: inherit;
}

.field__textarea {
  border-radius: var(--radius-small);
  resize: vertical;
  min-height: 72px;
}

.field__input:focus,
.field__textarea:focus {
  outline: 2px solid var(--color-ownlane-green);
  outline-offset: 2px;
}

.choices {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.choices--wrap {
  flex-direction: row;
  flex-wrap: wrap;
}

.choice {
  min-height: 52px;
  padding: 14px 20px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-buttons);
  background: var(--color-chalk-green);
  font: inherit;
  font-size: var(--text-body);
  text-align: left;
  cursor: pointer;
  color: inherit;
}

.choice:hover,
.choice:focus-visible {
  border-color: var(--color-ownlane-green);
  outline: none;
}

.choice--chip {
  min-height: 44px;
  padding: 10px 16px;
  font-size: var(--text-body-sm);
  border-radius: 999px;
  width: auto;
}

.choice[data-on='yes'] {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  border-color: var(--color-ownlane-green);
}

.day {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
  padding-bottom: var(--spacing-12);
  border-bottom: 1px solid var(--color-frost-green);
}

.day:last-of-type {
  border-bottom: none;
}

.day__name {
  font-size: var(--text-body-sm);
  font-weight: 500;
}

.day__slots {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.toggle {
  display: flex;
  align-items: flex-start;
  gap: var(--spacing-12);
  font-size: var(--text-body-sm);
  min-height: 44px;
}

.toggle input {
  margin-top: 4px;
  width: 18px;
  height: 18px;
  accent-color: var(--color-ownlane-green);
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-8);
  min-height: 52px;
  padding: 14px 24px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  box-shadow: var(--shadow-button);
  font: inherit;
  cursor: pointer;
  align-self: stretch;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.skip {
  border: none;
  background: transparent;
  font: inherit;
  font-size: var(--text-body-sm);
  text-decoration: underline;
  cursor: pointer;
  min-height: 44px;
  opacity: 0.7;
  align-self: flex-start;
  padding: 0;
  color: inherit;
}

.text-link {
  border: none;
  background: transparent;
  font: inherit;
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  cursor: pointer;
  padding: 0;
  text-decoration: underline;
}

.terms {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  padding-top: var(--spacing-8);
  border-top: 1px solid var(--color-frost-green);
}

.terms__body {
  background: var(--color-chalk-green);
  border-radius: var(--radius-small);
  padding: var(--spacing-16);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.terms__biz {
  font-size: var(--text-body-sm);
  opacity: 0.7;
}

.terms__text {
  font-size: var(--text-body-sm);
  white-space: pre-wrap;
  line-height: var(--leading-body-sm);
}

.error {
  color: var(--color-marker-red);
  font-size: var(--text-body-sm);
}

.muted {
  font-size: var(--text-body-sm);
  opacity: 0.7;
}
</style>
