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

        <label class="field">
          <span class="field__label">
            Usual pickup
            <span class="field__optional">optional</span>
          </span>
          <textarea v-model="pickup" class="field__textarea" rows="2" />
        </label>

        <div class="form__section">
          <p class="form__section-title">Test</p>
          <label class="field">
            <span class="field__label">
              Test date
              <span class="field__optional">optional</span>
            </span>
            <input v-model="testDate" class="field__input" type="date">
          </label>
          <label class="field">
            <span class="field__label">
              Test centre
              <span class="field__optional">optional</span>
            </span>
            <input v-model="testCentre" class="field__input" type="text">
          </label>
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

const route = useRoute()
const id = computed(() => Number(route.params.id))
const { getPupil, updatePupil } = usePupils()

const pupil = ref<Pupil | null>(null)
const loading = ref(true)
const loadError = ref('')
const pending = ref(false)
const error = ref('')

const firstName = ref('')
const lastName = ref('')
const mobile = ref('')
const email = ref('')
const pickup = ref('')
const testDate = ref('')
const testCentre = ref('')
const notes = ref('')

useHead(() => ({
  title: pupil.value ? `Edit ${pupil.value.full_name} · OwnLane` : 'Edit pupil · OwnLane',
}))

function hydrate(p: Pupil) {
  firstName.value = p.first_name
  lastName.value = p.last_name
  mobile.value = p.mobile
  email.value = p.email ?? ''
  pickup.value = p.default_pickup_address ?? ''
  testDate.value = p.test_date ?? ''
  testCentre.value = p.test_centre ?? ''
  notes.value = p.private_notes ?? ''
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    pupil.value = await getPupil(id.value)
    hydrate(pupil.value)
  } catch (e) {
    pupil.value = null
    loadError.value = extractApiError(e, 'Could not load this pupil.')
  } finally {
    loading.value = false
  }
}

async function onSubmit() {
  pending.value = true
  error.value = ''
  try {
    const updated = await updatePupil(id.value, {
      first_name: firstName.value.trim(),
      last_name: lastName.value.trim(),
      mobile: mobile.value.trim(),
      email: email.value.trim() || null,
      default_pickup_address: pickup.value.trim() || null,
      test_date: testDate.value || null,
      test_centre: testCentre.value.trim() || null,
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
    grid-template-columns: 1fr 1fr;
  }
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
