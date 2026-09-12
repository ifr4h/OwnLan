<template>
  <section class="page">
    <NuxtLink :to="pupil ? `/pupils/${pupil.id}` : '/pupils'" class="back">← Back</NuxtLink>

    <p v-if="loading" class="muted">Loading…</p>
    <p v-else-if="loadError" class="error" role="alert">{{ loadError }}</p>

    <template v-else-if="pupil">
      <header class="page__header">
        <p class="page__eyebrow">Edit pupil</p>
        <h1 class="page__title">{{ pupil.full_name }}</h1>
      </header>

      <form class="form" @submit.prevent="onSubmit">
        <div class="form__row">
          <label class="field">
            <span class="field__label">First name</span>
            <input v-model="firstName" class="field__input" type="text" required>
          </label>
          <label class="field">
            <span class="field__label">
              Middle name
              <span class="field__optional">optional</span>
            </span>
            <input v-model="middleName" class="field__input" type="text" autocomplete="additional-name">
          </label>
          <label class="field">
            <span class="field__label">Last name</span>
            <input v-model="lastName" class="field__input" type="text" required>
          </label>
        </div>

        <label class="field">
          <span class="field__label">Mobile</span>
          <input
            v-model="mobile"
            class="field__input"
            type="tel"
            inputmode="tel"
            required
          >
        </label>

        <label class="field">
          <span class="field__label">
            Email
            <span class="field__optional">optional</span>
          </span>
          <input v-model="email" class="field__input" type="email">
        </label>

        <div class="form__section">
          <p class="form__section-title">About</p>
          <div class="form__row">
            <label class="field">
              <span class="field__label">
                Gender
                <span class="field__optional">optional</span>
              </span>
              <select v-model="gender" class="field__input">
                <option value="">Not set</option>
                <option value="female">Female</option>
                <option value="male">Male</option>
                <option value="non_binary">Non-binary</option>
                <option value="prefer_not_to_say">Prefer not to say</option>
              </select>
            </label>
            <label class="field">
              <span class="field__label">
                Date of birth
                <span class="field__optional">optional</span>
              </span>
              <input v-model="dateOfBirth" class="field__input" type="date">
            </label>
          </div>
          <div class="form__row">
            <label class="field">
              <span class="field__label">Transmission</span>
              <select v-model="transmission" class="field__input">
                <option value="">Not set</option>
                <option value="manual">Manual</option>
                <option value="automatic">Automatic</option>
                <option value="either">Either</option>
              </select>
            </label>
            <label class="field">
              <span class="field__label">Best way to reach them</span>
              <select v-model="preferredContact" class="field__input">
                <option value="">Not set</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="sms">Text</option>
                <option value="call">Call</option>
                <option value="email">Email</option>
              </select>
            </label>
          </div>
          <label class="field">
            <span class="field__label">
              Referred by
              <span class="field__optional">optional</span>
            </span>
            <input v-model="referredBy" class="field__input" type="text" placeholder="Another pupil, Google, leaflet…">
          </label>
        </div>

        <div class="form__section">
          <p class="form__section-title">Contacts</p>
          <p class="form__section-hint">
            Parent or guardian for under-18s, plus emergency contacts in order (1st, 2nd…).
            This is not parent login to OwnLane.
          </p>
          <div v-for="(c, index) in contacts" :key="index" class="contact-edit">
            <div class="form__row">
              <label class="field">
                <span class="field__label">Type</span>
                <select v-model="c.kind" class="field__input">
                  <option value="parent">Parent</option>
                  <option value="guardian">Guardian</option>
                  <option value="emergency">Emergency</option>
                  <option value="other">Other</option>
                </select>
              </label>
              <label class="field">
                <span class="field__label">Relationship</span>
                <input v-model="c.relationship" class="field__input" type="text" placeholder="Mum, Dad, partner…">
              </label>
            </div>
            <label class="field">
              <span class="field__label">Name</span>
              <input v-model="c.name" class="field__input" type="text">
            </label>
            <div class="form__row">
              <label class="field">
                <span class="field__label">Phone</span>
                <input v-model="c.phone" class="field__input" type="tel" inputmode="tel">
              </label>
              <label class="field">
                <span class="field__label">Email</span>
                <input v-model="c.email" class="field__input" type="email">
              </label>
            </div>
            <label class="field">
              <span class="field__label">
                Note
                <span class="field__optional">optional</span>
              </span>
              <input v-model="c.notes" class="field__input" type="text" placeholder="Pays for lessons, collect from school…">
            </label>
            <button class="ghost" type="button" @click="contacts.splice(index, 1)">Remove contact</button>
          </div>
          <button class="btn btn--ghost" type="button" @click="addContact">Add contact</button>
        </div>

        <div class="form__section">
          <p class="form__section-title">Eyesight &amp; health</p>
          <div class="form__row">
            <label class="field">
              <span class="field__label">Eyesight check</span>
              <select v-model="eyesightStatus" class="field__input">
                <option value="">Not recorded</option>
                <option value="not_checked">Not checked yet</option>
                <option value="checked_ok">Checked · fine</option>
                <option value="glasses">Needs glasses / lenses</option>
                <option value="failed">Failed / recheck needed</option>
              </select>
            </label>
            <label class="field">
              <span class="field__label">Checked on</span>
              <input v-model="eyesightCheckedOn" class="field__input" type="date">
            </label>
          </div>
          <label class="ol-check" style="display:flex;gap:8px;align-items:center">
            <input v-model="wearsGlasses" type="checkbox">
            Wears glasses or contact lenses for driving
          </label>
          <label class="field">
            <span class="field__label">
              Health / teaching notes
              <span class="field__optional">optional</span>
            </span>
            <textarea
              v-model="medicalNotes"
              class="field__textarea"
              rows="3"
              placeholder="Glasses for driving, ADHD, anxiety, anything that changes how you teach"
            />
          </label>
        </div>

        <div class="form__section">
          <p class="form__section-title">Payment notes</p>
          <p class="form__section-hint">
            e.g. pays cash after each lesson, on the old £32 rate, parent pays.
            Special lesson prices are managed in Services → Pricing.
          </p>
          <label class="field">
            <span class="field__label">
              Notes
              <span class="field__optional">optional</span>
            </span>
            <textarea v-model="paymentNotes" class="field__textarea" rows="2" />
          </label>
          <NuxtLink to="/services/pricing#pupil-rates" class="ol-link-action">Set a special rate →</NuxtLink>
        </div>

        <div class="form__section">
          <p class="form__section-title">Pickup places</p>
          <p class="form__section-hint">Visible to this pupil’s instructor</p>
          <p v-if="placesError" class="error" role="alert">{{ placesError }}</p>
          <PlacesManager
            :locations="places"
            audience="instructor"
            :busy="placesBusy"
            @create="onPlaceCreate"
            @update="onPlaceUpdate"
            @remove="onPlaceRemove"
            @set-default="onPlaceDefault"
          />
        </div>

        <div class="form__section">
          <p class="form__section-title">Licence</p>
          <label class="field">
            <span class="field__label">
              Licence number
              <span class="field__optional">optional</span>
            </span>
            <input v-model="licenceNumber" class="field__input" type="text">
          </label>
          <label class="field">
            <span class="field__label">
              Licence expiry
              <span class="field__optional">optional</span>
            </span>
            <input v-model="licenceExpiry" class="field__input" type="date">
          </label>
        </div>

        <div class="form__section">
          <p class="form__section-title">Waitlist</p>
          <label class="field">
            <span class="field__label">
              Can start from
              <span class="field__optional">optional</span>
            </span>
            <input v-model="availableFrom" class="field__input" type="date">
          </label>
        </div>

        <div class="form__section">
          <p class="form__section-title">Practical test</p>
          <label class="field">
            <span class="field__label">
              Test date
              <span class="field__optional">optional</span>
            </span>
            <input v-model="testDate" class="field__input" type="date">
          </label>
          <label class="field">
            <span class="field__label">
              Test time
              <span class="field__optional">optional</span>
            </span>
            <input v-model="testTime" class="field__input" type="time">
          </label>
          <label class="field">
            <span class="field__label">
              Test centre
              <span class="field__optional">optional</span>
            </span>
            <input v-model="testCentre" class="field__input" type="text">
          </label>
          <label class="field">
            <span class="field__label">
              Booking reference
              <span class="field__optional">optional</span>
            </span>
            <input v-model="bookingRef" class="field__input" type="text">
          </label>
          <label class="field">
            <span class="field__label">
              Last cancellation date
              <span class="field__optional">optional</span>
            </span>
            <input v-model="cancelBy" class="field__input" type="date">
          </label>
          <fieldset class="reminders">
            <legend class="field__label">Remind me</legend>
            <label v-for="opt in reminderOptions" :key="opt.value" class="reminders__item">
              <input v-model="reminderOffsets" type="checkbox" :value="opt.value">
              {{ opt.label }}
            </label>
          </fieldset>
        </div>

        <div class="form__section">
          <p class="form__section-title">Theory</p>
          <label class="field">
            <span class="field__label">Status</span>
            <select v-model="theoryStatus" class="field__input">
              <option value="">Not recorded</option>
              <option value="not_yet">Not passed yet</option>
              <option value="booked">Booked</option>
              <option value="passed">Passed</option>
            </select>
          </label>
          <label v-if="theoryStatus === 'booked'" class="field">
            <span class="field__label">Theory test date</span>
            <input v-model="theoryTestDate" class="field__input" type="date" required>
          </label>
          <label v-if="theoryStatus === 'passed'" class="field">
            <span class="field__label">
              Pass date
              <span class="field__optional">certificate runs 2 years from this</span>
            </span>
            <input v-model="theoryPassDate" class="field__input" type="date" required>
          </label>
          <p v-if="theoryStatus === 'not_yet'" class="form__section-hint">
            They still need to book and pass the theory test.
          </p>
          <p v-else-if="theoryStatus === 'booked'" class="form__section-hint">
            Booked means they’ve got a date. Add when it is.
          </p>
          <p v-else-if="theoryStatus === 'passed' && theoryPassDate" class="form__section-hint">
            Certificate is valid for 2 years from the pass date. OwnLane tracks how long is left to take the practical.
          </p>
        </div>

        <label class="field">
          <span class="field__label">
            Private notes
            <span class="field__optional">optional</span>
          </span>
          <textarea
            v-model="notes"
            class="field__textarea"
            rows="4"
            placeholder="Things only you need to remember"
          />
        </label>

        <p v-if="error" class="error" role="alert">{{ error }}</p>

        <button class="btn" type="submit" :disabled="pending">
          {{ pending ? 'Saving…' : 'Save changes' }}
        </button>
      </form>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { Pupil } from '~/composables/usePupils'
import type { LearnerLocation, LocationIconName } from '~/composables/useLearnerLocations'
import PlacesManager from '~/components/locations/PlacesManager.vue'

const route = useRoute()
const id = computed(() => Number(route.params.id))
const { getPupil, updatePupil } = usePupils()
const {
  listForLearner,
  createForLearner,
  updateForLearner,
  deleteForLearner,
  setDefaultForLearner,
} = useLearnerLocations()

const pupil = ref<Pupil | null>(null)
const loading = ref(true)
const loadError = ref('')
const pending = ref(false)
const error = ref('')

const firstName = ref('')
const middleName = ref('')
const lastName = ref('')
const mobile = ref('')
const email = ref('')
const gender = ref('')
const dateOfBirth = ref('')
const transmission = ref('')
const preferredContact = ref('')
const referredBy = ref('')
const contacts = ref<Array<{
  kind: string
  name: string
  phone: string
  email: string
  relationship: string
  notes: string
}>>([])
const eyesightStatus = ref('')
const eyesightCheckedOn = ref('')
const wearsGlasses = ref(false)
const medicalNotes = ref('')
const paymentNotes = ref('')
const testDate = ref('')
const testTime = ref('')
const testCentre = ref('')
const bookingRef = ref('')
const cancelBy = ref('')
const reminderOffsets = ref<number[]>([7, 1, 0])
const theoryStatus = ref('')
const theoryTestDate = ref('')
const theoryPassDate = ref('')
const licenceNumber = ref('')
const licenceExpiry = ref('')
const availableFrom = ref('')
const notes = ref('')

const reminderOptions = [
  { value: 7, label: '1 week before' },
  { value: 3, label: '3 days before' },
  { value: 1, label: '1 day before' },
  { value: 0, label: 'Morning of' },
]

const places = ref<LearnerLocation[]>([])
const placesBusy = ref(false)
const placesError = ref('')

useHead(() => ({
  title: pupil.value ? `Edit ${pupil.value.full_name} · OwnLane` : 'Edit pupil · OwnLane',
}))

function hydrate(p: Pupil) {
  firstName.value = p.first_name
  middleName.value = p.middle_name ?? ''
  lastName.value = p.last_name
  mobile.value = p.mobile
  email.value = p.email ?? ''
  gender.value = p.gender ?? ''
  dateOfBirth.value = p.date_of_birth ?? ''
  transmission.value = p.transmission ?? ''
  preferredContact.value = p.preferred_contact ?? ''
  referredBy.value = p.referred_by ?? ''
  contacts.value = (p.contacts?.length
    ? p.contacts
    : (p.emergency_contact_name || p.emergency_contact_phone)
      ? [{
          kind: 'emergency',
          name: p.emergency_contact_name ?? '',
          phone: p.emergency_contact_phone ?? '',
          email: '',
          relationship: '',
          notes: '',
        }]
      : []
  ).map(c => ({
    kind: c.kind || 'emergency',
    name: c.name || '',
    phone: c.phone ?? '',
    email: c.email ?? '',
    relationship: c.relationship ?? '',
    notes: c.notes ?? '',
  }))
  eyesightStatus.value = p.eyesight_status ?? ''
  eyesightCheckedOn.value = p.eyesight_checked_on ?? ''
  wearsGlasses.value = !!p.wears_glasses
  medicalNotes.value = p.medical_notes ?? ''
  paymentNotes.value = p.payment_notes ?? ''
  testDate.value = p.test_date ?? ''
  testTime.value = p.practical_test_time ?? ''
  testCentre.value = p.test_centre ?? ''
  bookingRef.value = p.practical_test_booking_ref ?? ''
  cancelBy.value = p.practical_test_cancel_by ?? ''
  reminderOffsets.value = p.practical_test_reminder_offsets?.length
    ? [...p.practical_test_reminder_offsets]
    : [7, 1, 0]
  theoryStatus.value = p.theory_status ?? ''
  theoryTestDate.value = p.theory_test_date ?? ''
  theoryPassDate.value = p.theory_pass_date ?? ''
  licenceNumber.value = p.licence_number ?? ''
  licenceExpiry.value = p.licence_expiry_date ?? ''
  availableFrom.value = p.available_from ?? ''
  notes.value = p.private_notes ?? ''
}

async function loadPlaces() {
  placesError.value = ''
  try {
    places.value = await listForLearner(id.value)
  } catch (e) {
    placesError.value = extractApiError(e, 'Could not load pickup places.')
  }
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    pupil.value = await getPupil(id.value)
    hydrate(pupil.value)
    await loadPlaces()
  } catch (e) {
    pupil.value = null
    loadError.value = extractApiError(e, 'Could not load this pupil.')
  } finally {
    loading.value = false
  }
}

async function onPlaceCreate(payload: {
  label: string
  icon: LocationIconName
  address: string
  is_default?: boolean
}) {
  placesBusy.value = true
  placesError.value = ''
  try {
    await createForLearner(id.value, payload)
    await loadPlaces()
  } catch (e) {
    placesError.value = extractApiError(e, 'Could not add that place.')
  } finally {
    placesBusy.value = false
  }
}

async function onPlaceUpdate(
  placeId: number,
  payload: { label: string; icon: LocationIconName; address: string },
) {
  placesBusy.value = true
  placesError.value = ''
  try {
    await updateForLearner(id.value, placeId, payload)
    await loadPlaces()
  } catch (e) {
    placesError.value = extractApiError(e, 'Could not update that place.')
  } finally {
    placesBusy.value = false
  }
}

async function onPlaceRemove(placeId: number) {
  placesBusy.value = true
  placesError.value = ''
  try {
    await deleteForLearner(id.value, placeId)
    await loadPlaces()
  } catch (e) {
    placesError.value = extractApiError(e, 'Could not remove that place.')
  } finally {
    placesBusy.value = false
  }
}

async function onPlaceDefault(placeId: number) {
  placesBusy.value = true
  placesError.value = ''
  try {
    await setDefaultForLearner(id.value, placeId)
    await loadPlaces()
  } catch (e) {
    placesError.value = extractApiError(e, 'Could not set the default place.')
  } finally {
    placesBusy.value = false
  }
}

function addContact() {
  contacts.value.push({
    kind: contacts.value.some(c => c.kind === 'emergency') ? 'emergency' : 'parent',
    name: '',
    phone: '',
    email: '',
    relationship: '',
    notes: '',
  })
}

async function onSubmit() {
  pending.value = true
  error.value = ''
  if (theoryStatus.value === 'booked' && !theoryTestDate.value) {
    error.value = 'Add the theory test date when it’s booked.'
    pending.value = false
    return
  }
  if (theoryStatus.value === 'passed' && !theoryPassDate.value) {
    error.value = 'Add the pass date when theory is passed.'
    pending.value = false
    return
  }
  const contactPayload = contacts.value
    .filter(c => c.name.trim())
    .map(c => ({
      kind: c.kind,
      name: c.name.trim(),
      phone: c.phone.trim() || null,
      email: c.email.trim() || null,
      relationship: c.relationship.trim() || null,
      notes: c.notes.trim() || null,
    }))
  try {
    const updated = await updatePupil(id.value, {
      first_name: firstName.value.trim(),
      middle_name: middleName.value.trim() || null,
      last_name: lastName.value.trim(),
      mobile: mobile.value.trim(),
      email: email.value.trim() || null,
      gender: gender.value || null,
      date_of_birth: dateOfBirth.value || null,
      transmission: transmission.value || null,
      preferred_contact: preferredContact.value || null,
      referred_by: referredBy.value.trim() || null,
      contacts: contactPayload,
      eyesight_status: eyesightStatus.value || null,
      eyesight_checked_on: eyesightCheckedOn.value || null,
      wears_glasses: wearsGlasses.value,
      medical_notes: medicalNotes.value.trim() || null,
      payment_notes: paymentNotes.value.trim() || null,
      test_date: testDate.value || null,
      practical_test_time: testTime.value || null,
      test_centre: testCentre.value.trim() || null,
      practical_test_booking_ref: bookingRef.value.trim() || null,
      practical_test_cancel_by: cancelBy.value || null,
      practical_test_reminder_offsets: [...reminderOffsets.value].sort((a, b) => a - b),
      theory_status: theoryStatus.value || null,
      theory_test_date: theoryStatus.value === 'booked' ? (theoryTestDate.value || null) : null,
      theory_pass_date: theoryStatus.value === 'passed' ? (theoryPassDate.value || null) : null,
      licence_number: licenceNumber.value.trim() || null,
      licence_expiry_date: licenceExpiry.value || null,
      available_from: availableFrom.value || null,
      private_notes: notes.value.trim() || null,
    })
    await navigateTo(`/pupils/${updated.id}`)
  } catch (e) {
    error.value = extractApiError(e, 'Could not save changes.')
  } finally {
    pending.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.page {
  max-width: 560px;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-20);
}

.back {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  align-self: flex-start;
}

.page__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
  margin-bottom: var(--spacing-8);
}

.page__title {
  font-size: var(--text-heading-sm);
}

.form {
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--card-padding);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.form__row {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--spacing-16);
}

@media (min-width: 560px) {
  .form__row {
    grid-template-columns: 1fr 1fr 1fr;
  }
}

.reminders {
  border: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.reminders__item {
  display: flex;
  align-items: center;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
}

.form__section {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  padding-top: var(--spacing-8);
  border-top: 1px solid var(--color-frost-green);
}

.form__section-title {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
}

.form__section-hint {
  font-size: var(--text-body-sm);
  opacity: 0.7;
  margin-top: calc(-1 * var(--spacing-8));
}

.contact-edit {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  padding: var(--spacing-16);
  border: 1px solid var(--color-frost-green);
  border-radius: 16px;
  background: color-mix(in srgb, var(--color-parchment) 70%, white);
}

.ghost {
  align-self: flex-start;
  border: none;
  background: transparent;
  color: var(--color-muted);
  font: inherit;
  font-size: var(--text-body-sm);
  cursor: pointer;
  padding: 0;
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
  padding: 12px 20px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-inputs);
  background: var(--surface-canvas);
  font: inherit;
}

.field__textarea {
  border-radius: var(--radius-small);
  resize: vertical;
}

.field__input:focus,
.field__textarea:focus {
  outline: 2px solid var(--color-ownlane-green);
  outline-offset: 2px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  box-shadow: var(--shadow-button);
  cursor: pointer;
  align-self: flex-start;
}

.btn:disabled {
  opacity: 0.6;
}

.error {
  color: var(--color-marker-red);
  font-size: var(--text-body-sm);
}

.muted {
  opacity: 0.65;
}
</style>
