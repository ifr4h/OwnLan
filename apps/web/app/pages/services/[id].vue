<script setup lang="ts">
import type { ServiceDetail } from '~/composables/useServices'

useHead({ title: 'Service · OwnLane' })

const route = useRoute()
const { fetchService, updateService, schedulePriceChange } = useServices()

const id = computed(() => Number(route.params.id))
const detail = ref<ServiceDetail | null>(null)
const loading = ref(true)
const saving = ref(false)
const error = ref('')
const savedFlash = ref(false)

const name = ref('')
const kind = ref('lesson')
const duration = ref(60)
const price = ref('')
const bookingAccess = ref('instructor_only')
const visibilityPublic = ref(true)
const status = ref('active')
const isDefault = ref(false)
const description = ref('')

const futurePrice = ref('')
const futureDate = ref('')
const showFuture = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const data = await fetchService(id.value)
    detail.value = data
    const s = data.service
    name.value = s.name
    kind.value = s.kind
    duration.value = s.duration_minutes
    price.value = (s.price_pence / 100).toFixed(s.price_pence % 100 === 0 ? 0 : 2)
    bookingAccess.value = s.booking_access
    visibilityPublic.value = s.visibility_public
    status.value = s.status
    isDefault.value = s.is_default
    description.value = s.description || ''
  } catch (e) {
    error.value = extractApiError(e, 'Could not load service.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})

async function onSave() {
  saving.value = true
  error.value = ''
  savedFlash.value = false
  try {
    await updateService(id.value, {
      name: name.value.trim(),
      kind: kind.value,
      duration_minutes: duration.value,
      price: price.value.trim(),
      booking_access: bookingAccess.value,
      visibility_public: visibilityPublic.value,
      status: status.value,
      is_default: isDefault.value,
      description: description.value.trim() || null,
    })
    savedFlash.value = true
    await load()
  } catch (e) {
    error.value = extractApiError(e, 'Could not save service.')
  } finally {
    saving.value = false
  }
}

async function onSchedulePrice() {
  error.value = ''
  try {
    await schedulePriceChange(id.value, {
      price: futurePrice.value.trim(),
      effective_on: futureDate.value,
    })
    futurePrice.value = ''
    futureDate.value = ''
    showFuture.value = false
    await load()
  } catch (e) {
    error.value = extractApiError(e, 'Could not schedule price change.')
  }
}
</script>

<template>
  <section class="svc-edit ol-page">
    <header class="svc-edit__header">
      <NuxtLink to="/services" class="ol-link-action">← Services</NuxtLink>
      <h1 class="ol-page-title">{{ name || 'Service' }}</h1>
    </header>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>

    <form v-else-if="detail" class="svc-edit__form" @submit.prevent="onSave">
      <label class="field">
        <span class="field__label">Name</span>
        <input v-model="name" class="field__input field__input--name" maxlength="120" required>
      </label>

      <div class="block">
        <p class="field__label">Type</p>
        <div class="ol-seg" role="group">
          <button
            v-for="opt in detail.kind_options"
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
        <p class="field__label">Length</p>
        <div class="ol-seg" role="group">
          <button
            v-for="mins in detail.duration_presets"
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

      <label class="field">
        <span class="field__label">Price</span>
        <span class="price-wrap">
          <span aria-hidden="true">£</span>
          <input v-model="price" class="field__input field__input--bare" inputmode="decimal" required>
        </span>
      </label>

      <div class="block">
        <p class="field__label">Who can book this</p>
        <div class="access-list">
          <button
            v-for="opt in detail.booking_access_options"
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

      <div class="block">
        <p class="field__label">Status</p>
        <div class="ol-seg" role="group">
          <button
            v-for="opt in detail.status_options"
            :key="opt.value"
            class="ol-chip"
            type="button"
            :class="{ 'ol-chip--on': status === opt.value }"
            @click="status = opt.value"
          >
            {{ opt.label }}
          </button>
        </div>
      </div>

      <button
        class="toggle-row"
        type="button"
        :aria-pressed="visibilityPublic"
        @click="visibilityPublic = !visibilityPublic"
      >
        <span>
          <strong>Show on your public page</strong>
        </span>
        <span class="toggle-pill" :data-on="visibilityPublic ? 'yes' : 'no'" />
      </button>

      <button
        class="toggle-row"
        type="button"
        :aria-pressed="isDefault"
        @click="isDefault = !isDefault"
      >
        <span>
          <strong>Default when booking</strong>
          <small>Used when you don’t pick a service</small>
        </span>
        <span class="toggle-pill" :data-on="isDefault ? 'yes' : 'no'" />
      </button>

      <label class="field">
        <span class="field__label">Short description (optional)</span>
        <textarea v-model="description" class="field__textarea" rows="3" maxlength="500" />
      </label>

      <section class="future">
        <button class="more-toggle" type="button" @click="showFuture = !showFuture">
          {{ showFuture ? 'Hide future price' : 'Schedule a price change' }}
        </button>
        <div v-if="showFuture" class="future__form">
          <label class="field">
            <span class="field__label">New price</span>
            <span class="price-wrap">
              <span aria-hidden="true">£</span>
              <input v-model="futurePrice" class="field__input field__input--bare" inputmode="decimal">
            </span>
          </label>
          <label class="field">
            <span class="field__label">From date</span>
            <input v-model="futureDate" class="field__input" type="date">
          </label>
          <button class="ol-btn ol-btn--sm" type="button" @click="onSchedulePrice">Schedule</button>
        </div>
        <ul v-if="detail.price_changes.length" class="future__list">
          <li v-for="change in detail.price_changes" :key="change.id">
            {{ change.effective_on }} → {{ change.price_label }}
          </li>
        </ul>
      </section>

      <div class="actions">
        <button class="ol-btn" type="submit" :disabled="saving">
          {{ saving ? 'Saving…' : 'Save changes' }}
        </button>
        <p v-if="savedFlash" class="saved">Saved</p>
      </div>
    </form>
  </section>
</template>

<style scoped>
.svc-edit__header {
  margin-bottom: 24px;
}

.svc-edit__form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  max-width: 36rem;
}

.field__label {
  display: block;
  margin-bottom: 8px;
  font-size: var(--text-meta);
  font-weight: 600;
  color: var(--color-coffee-stone);
}

.field__input,
.field__textarea {
  width: 100%;
  min-height: 48px;
  padding: 12px 14px;
  border: 1px solid var(--color-driftwood);
  border-radius: 4px;
  background: var(--color-paper-white);
  font: inherit;
}

.field__input--name {
  min-height: 56px;
  font-size: 1.2rem;
  font-weight: 600;
}

.field__input:focus,
.field__textarea:focus {
  outline: none;
  border-color: var(--color-ink-black);
}

.price-wrap {
  display: flex;
  align-items: center;
  gap: 4px;
  min-height: 48px;
  padding: 0 14px;
  border: 1px solid var(--color-driftwood);
  border-radius: 4px;
  background: var(--color-paper-white);
}

.price-wrap:focus-within {
  border-color: var(--color-ink-black);
}

.field__input--bare {
  border: none;
  min-height: 46px;
  padding: 0;
  background: transparent;
}

.field__input--bare:focus {
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

.more-toggle {
  border: none;
  background: none;
  padding: 0;
  font: inherit;
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-ownlane-green);
  cursor: pointer;
}

.future__form {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin-top: 12px;
}

.future__list {
  margin: 12px 0 0;
  padding: 0;
  list-style: none;
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}

.actions {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-top: 8px;
}

.saved {
  margin: 0;
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
  font-weight: 600;
}
</style>
