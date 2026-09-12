<script setup lang="ts">
useHead({ title: 'Add service · OwnLane' })

const router = useRouter()
const { createService, fetchHome } = useServices()

const name = ref('Standard lesson')
const kind = ref('lesson')
const duration = ref(60)
const price = ref('40')
const bookingAccess = ref('instructor_only')
const visibilityPublic = ref(true)
const status = ref('active')
const showMore = ref(false)
const saving = ref(false)
const error = ref('')

const kindOptions = ref<Array<{ value: string; label: string }>>([])
const bookingOptions = ref<Array<{ value: string; label: string }>>([])
const durationPresets = ref([30, 45, 60, 90, 120])

const previewDuration = computed(() => {
  const m = duration.value
  if (m % 60 === 0) return m === 60 ? '1 hour' : `${m / 60} hours`
  return `${m} min`
})

const previewPrice = computed(() => {
  const raw = price.value.trim().replace(/£/g, '')
  if (!raw) return '£0.00'
  const n = Number(raw)
  if (Number.isNaN(n)) return price.value
  return `£${n.toFixed(2)}`
})

onMounted(async () => {
  try {
    const home = await fetchHome()
    kindOptions.value = home.kind_options
    bookingOptions.value = home.booking_access_options
    durationPresets.value = home.duration_presets
  } catch {
    kindOptions.value = [
      { value: 'lesson', label: 'Lesson' },
      { value: 'mock_test', label: 'Mock test' },
    ]
    bookingOptions.value = [
      { value: 'instructor_only', label: 'You book it' },
      { value: 'request', label: 'Pupils can request' },
      { value: 'instant', label: 'Pupils can book instantly' },
    ]
  }
})

async function onSave() {
  saving.value = true
  error.value = ''
  try {
    const res = await createService({
      name: name.value.trim(),
      kind: kind.value,
      duration_minutes: duration.value,
      price: price.value.trim(),
      booking_access: bookingAccess.value,
      visibility_public: visibilityPublic.value,
      status: status.value,
    })
    await navigateTo(`/services/${res.service.id}`)
  } catch (e) {
    error.value = extractApiError(e, 'Could not save service.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="svc-form ol-page">
    <header class="svc-form__header">
      <NuxtLink to="/services" class="ol-link-action">← Services</NuxtLink>
      <h1 class="ol-page-title">Add a service</h1>
      <p class="svc-form__lead">Name it, set the length and price. You can fine-tune who books it afterwards.</p>
    </header>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>

    <div class="svc-form__layout">
      <form class="svc-form__main" @submit.prevent="onSave">
        <label class="name-field">
          <span class="name-field__label">Name</span>
          <input
            v-model="name"
            class="name-field__input"
            maxlength="120"
            required
            placeholder="Standard lesson"
            autocomplete="off"
          >
        </label>

        <div class="block">
          <p class="block__label">Type</p>
          <div class="ol-seg" role="group" aria-label="Service type">
            <button
              v-for="opt in kindOptions"
              :key="opt.value"
              class="ol-chip"
              type="button"
              :class="{ 'ol-chip--on': kind === opt.value }"
              @click="kind = opt.value"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>

        <div class="block">
          <p class="block__label">Length</p>
          <div class="ol-seg" role="group" aria-label="Duration">
            <button
              v-for="mins in durationPresets"
              :key="mins"
              class="ol-chip"
              type="button"
              :class="{ 'ol-chip--on': duration === mins }"
              @click="duration = mins"
            >
              {{ mins % 60 === 0 ? (mins === 60 ? '1h' : `${mins / 60}h`) : `${mins}m` }}
            </button>
          </div>
        </div>

        <label class="price-field">
          <span class="price-field__label">Price</span>
          <span class="price-field__wrap">
            <span class="price-field__symbol" aria-hidden="true">£</span>
            <input
              v-model="price"
              class="price-field__input"
              inputmode="decimal"
              required
              placeholder="40"
              autocomplete="off"
            >
          </span>
        </label>

        <div class="block">
          <p class="block__label">Who can book this</p>
          <div class="access-list" role="group" aria-label="Booking access">
            <button
              v-for="opt in bookingOptions"
              :key="opt.value"
              class="access"
              type="button"
              :class="{ 'access--on': bookingAccess === opt.value }"
              @click="bookingAccess = opt.value"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>

        <button class="more-toggle" type="button" @click="showMore = !showMore">
          {{ showMore ? 'Hide extra options' : 'More options' }}
        </button>

        <div v-if="showMore" class="more">
          <button
            class="toggle-row"
            type="button"
            :aria-pressed="visibilityPublic"
            @click="visibilityPublic = !visibilityPublic"
          >
            <span>
              <strong>Show on your public page</strong>
              <small>Pupils and enquiries can see this price</small>
            </span>
            <span class="toggle-pill" :data-on="visibilityPublic ? 'yes' : 'no'" />
          </button>
          <div class="block">
            <p class="block__label">Status</p>
            <div class="ol-seg" role="group">
              <button class="ol-chip" type="button" :class="{ 'ol-chip--on': status === 'active' }" @click="status = 'active'">Active</button>
              <button class="ol-chip" type="button" :class="{ 'ol-chip--on': status === 'draft' }" @click="status = 'draft'">Draft</button>
            </div>
          </div>
        </div>

        <div class="svc-form__actions">
          <button class="ol-btn" type="submit" :disabled="saving">
            {{ saving ? 'Saving…' : 'Save service' }}
          </button>
          <button class="ol-btn ol-btn--ghost" type="button" @click="router.push('/services')">Cancel</button>
        </div>
      </form>

      <aside class="preview" aria-label="Preview">
        <p class="ol-eyebrow">Pupils will see</p>
        <div class="preview__card">
          <p class="preview__name">{{ name.trim() || 'Untitled' }}</p>
          <p class="preview__meta">{{ previewDuration }} · {{ previewPrice }}</p>
        </div>
      </aside>
    </div>
  </section>
</template>

<style scoped>
.svc-form__header {
  margin-bottom: 28px;
}

.svc-form__lead {
  margin: 8px 0 0;
  max-width: 36rem;
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}

.svc-form__layout {
  display: grid;
  gap: 28px;
}

@media (min-width: 900px) {
  .svc-form__layout {
    grid-template-columns: minmax(0, 1fr) 280px;
    align-items: start;
  }
}

.svc-form__main {
  display: flex;
  flex-direction: column;
  gap: 22px;
  max-width: 36rem;
}

.name-field__label,
.block__label,
.price-field__label {
  display: block;
  margin-bottom: 8px;
  font-size: var(--text-meta);
  font-weight: 600;
  color: var(--color-coffee-stone);
}

.name-field__input {
  width: 100%;
  min-height: 56px;
  padding: 12px 16px;
  border: 1px solid var(--color-driftwood);
  border-radius: var(--radius-inputs, 4px);
  background: var(--color-paper-white);
  font: inherit;
  font-size: 1.25rem;
  font-weight: 600;
  letter-spacing: -0.02em;
}

.name-field__input:focus {
  outline: none;
  border-color: var(--color-ink-black);
}

.price-field__wrap {
  display: flex;
  align-items: center;
  gap: 4px;
  min-height: 56px;
  padding: 8px 16px;
  border: 1px solid var(--color-driftwood);
  border-radius: var(--radius-inputs, 4px);
  background: var(--color-paper-white);
}

.price-field__wrap:focus-within {
  border-color: var(--color-ink-black);
}

.price-field__symbol {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--color-muted);
}

.price-field__input {
  flex: 1;
  border: none;
  background: transparent;
  font: inherit;
  font-size: 1.25rem;
  font-weight: 600;
  min-width: 0;
}

.price-field__input:focus {
  outline: none;
}

.access-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.access {
  text-align: left;
  padding: 14px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-cards);
  background: var(--color-paper-white);
  font: inherit;
  font-weight: 500;
  cursor: pointer;
}

.access--on {
  border-color: var(--color-ownlane-green);
  background: color-mix(in srgb, var(--color-ownlane-green) 8%, white);
}

.more-toggle {
  align-self: flex-start;
  border: none;
  background: none;
  padding: 0;
  font: inherit;
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-ownlane-green);
  cursor: pointer;
}

.more {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding-top: 4px;
}

.toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  width: 100%;
  padding: 14px 16px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-cards);
  background: var(--color-paper-white);
  text-align: left;
  font: inherit;
  cursor: pointer;
}

.toggle-row strong {
  display: block;
  font-weight: 600;
}

.toggle-row small {
  display: block;
  margin-top: 2px;
  color: var(--color-muted);
  font-size: var(--text-meta);
}

.toggle-pill {
  width: 44px;
  height: 26px;
  border-radius: 999px;
  background: var(--color-border);
  position: relative;
  flex-shrink: 0;
  transition: background-color var(--duration-fast) ease;
}

.toggle-pill::after {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: white;
  transition: transform var(--duration-fast) ease;
}

.toggle-pill[data-on='yes'] {
  background: var(--color-ownlane-green);
}

.toggle-pill[data-on='yes']::after {
  transform: translateX(18px);
}

.svc-form__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  padding-top: 8px;
}

.preview__card {
  margin-top: 10px;
  padding: 20px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-cards);
  background: var(--color-parchment);
}

.preview__name {
  margin: 0;
  font-weight: 600;
  font-size: 1.05rem;
}

.preview__meta {
  margin: 6px 0 0;
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}
</style>
