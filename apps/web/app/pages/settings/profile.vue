<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <NuxtLink to="/settings" class="ol-link-action">← Settings</NuxtLink>
      <p class="ol-eyebrow">Settings</p>
      <h1 class="ol-page-title">Your page</h1>
      <p class="ol-meta">Share one link when someone asks about lessons.</p>
    </header>

    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="loadError" class="ol-error" role="alert">{{ loadError }}</p>

    <template v-else-if="editor">
      <section class="ol-panel ol-stack">
        <div class="status-row">
          <p>
            Status: <strong>{{ editor.profile.status }}</strong>
            <span v-if="editor.profile.updated_at" class="ol-muted"> · Updated {{ formatDate(editor.profile.updated_at) }}</span>
          </p>
          <div class="ol-actions">
            <button v-if="editor.profile.status !== 'published'" class="ol-btn ol-btn--sm" type="button" :disabled="busy" @click="onPublish">
              Publish
            </button>
            <button v-else class="ol-btn ol-btn--ghost ol-btn--sm" type="button" :disabled="busy" @click="onUnpublish">
              Unpublish
            </button>
            <NuxtLink to="/settings/profile/preview" class="ol-btn ol-btn--ghost ol-btn--sm">Preview</NuxtLink>
          </div>
        </div>

        <ul v-if="editor.publish_blockers.length" class="blockers">
          <li v-for="item in editor.publish_blockers" :key="item">{{ item }}</li>
        </ul>

        <label v-if="editor.profile.share_url" class="ol-field">
          <span class="ol-field__label">Your page link</span>
          <div class="link-row">
            <input :value="editor.profile.share_url" class="ol-input" readonly>
            <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" @click="copyLink">Copy link</button>
          </div>
        </label>

        <div v-if="editor.analytics" class="analytics">
          <h2 class="ol-section-title">Your page · {{ editor.analytics.month_label.toLowerCase() }}</h2>
          <ul class="analytics-grid">
            <li><strong>{{ editor.analytics.visits }}</strong> visits</li>
            <li><strong>{{ editor.analytics.enquiries_submitted }}</strong> enquiries</li>
            <li><strong>{{ editor.analytics.pupils_added }}</strong> added as pupils</li>
          </ul>
        </div>
      </section>

      <form class="ol-stack" @submit.prevent="onSave">
        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">Basic details</h2>
          <label class="ol-field">
            <span class="ol-field__label">Page URL</span>
            <input v-model="slug" class="ol-input" placeholder="amina-yusuf">
            <span class="ol-field__hint">ownlane.co.uk/instructors/your-url</span>
          </label>
          <label class="ol-field">
            <span class="ol-field__label">Business name <span class="optional">optional</span></span>
            <input v-model="businessName" class="ol-input" placeholder="Amina Yusuf Driving Tuition">
          </label>
          <label class="ol-field">
            <span class="ol-field__label">Introduction</span>
            <textarea v-model="intro" class="ol-textarea" rows="4" placeholder="Tell learners a little about how you teach." />
          </label>
          <label class="ol-field">
            <span class="ol-field__label">Profile photo</span>
            <input type="file" accept="image/jpeg,image/png,image/webp" @change="onPhoto">
          </label>
        </section>

        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">Branding</h2>
          <label class="ol-field">
            <span class="ol-field__label">Accent colour</span>
            <input v-model="accentColour" class="ol-input" type="color">
          </label>
          <label class="ol-field">
            <span class="ol-field__label">Cover image <span class="optional">optional</span></span>
            <input type="file" accept="image/jpeg,image/png,image/webp" @change="onCover">
          </label>
        </section>

        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">Lessons &amp; prices</h2>
          <label class="ol-field">
            <span class="ol-field__label">Transmission</span>
            <select v-model="transmission" class="ol-select">
              <option value="">Choose…</option>
              <option v-for="opt in editor.transmission_options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </label>
          <div v-for="(row, i) in services" :key="i" class="service-row">
            <input v-model="row.name" class="ol-input" placeholder="Name">
            <select v-model="row.type" class="ol-select">
              <option v-for="opt in editor.service_type_options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <input v-model.number="row.duration_minutes" class="ol-input" type="number" min="0" step="15" placeholder="Minutes">
            <input v-model="row.price" class="ol-input" inputmode="decimal" placeholder="£">
            <button v-if="services.length > 1" class="ol-btn ol-btn--ghost ol-btn--sm" type="button" @click="services.splice(i, 1)">Remove</button>
          </div>
          <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" @click="addService">Add service</button>
        </section>

        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">About</h2>
          <fieldset class="style-grid">
            <legend class="ol-field__label">Teaching style</legend>
            <label v-for="opt in editor.teaching_style_options" :key="opt.value" class="ol-check">
              <input v-model="teachingStyles" type="checkbox" :value="opt.value">
              {{ opt.label }}
            </label>
          </fieldset>
          <label class="ol-field">
            <span class="ol-field__label">Vehicle</span>
            <input v-model="vehicleSummary" class="ol-input" placeholder="Automatic Toyota Corolla">
          </label>
          <label class="ol-check">
            <input v-model="dualControls" type="checkbox">
            Dual controls
          </label>
        </section>

        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">Areas</h2>
          <label class="ol-field">
            <span class="ol-field__label">Teaching areas</span>
            <textarea v-model="areasText" class="ol-textarea" rows="4" placeholder="One area per line" />
          </label>
        </section>

        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">FAQ</h2>
          <div v-for="(faq, i) in faqs" :key="i" class="faq-row">
            <input v-model="faq.question" class="ol-input" placeholder="Question">
            <textarea v-model="faq.answer" class="ol-textarea" rows="2" placeholder="Answer" />
            <button v-if="faqs.length > 1" class="ol-btn ol-btn--ghost ol-btn--sm" type="button" @click="faqs.splice(i, 1)">Remove</button>
          </div>
          <button class="ol-btn ol-btn--ghost ol-btn--sm" type="button" @click="faqs.push({ question: '', answer: '' })">Add question</button>
        </section>

        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">Contact</h2>
          <label class="ol-field">
            <span class="ol-field__label">Phone</span>
            <input v-model="contactPhone" class="ol-input" type="tel">
          </label>
          <label class="ol-check">
            <input v-model="showPhone" type="checkbox">
            Show phone on public page
          </label>
          <label class="ol-field">
            <span class="ol-field__label">Email</span>
            <input v-model="contactEmail" class="ol-input" type="email">
          </label>
          <label class="ol-check">
            <input v-model="showEmail" type="checkbox">
            Show email on public page
          </label>
          <label class="ol-field">
            <span class="ol-field__label">WhatsApp number</span>
            <input v-model="whatsapp" class="ol-input" type="tel" placeholder="07700 900123">
          </label>
          <label class="ol-field">
            <span class="ol-field__label">Instagram</span>
            <input v-model="socialInstagram" class="ol-input" placeholder="https://instagram.com/...">
          </label>
          <label class="ol-field">
            <span class="ol-field__label">Facebook</span>
            <input v-model="socialFacebook" class="ol-input" placeholder="https://facebook.com/...">
          </label>
        </section>

        <section class="ol-panel ol-stack">
          <h2 class="ol-section-title">New pupils</h2>
          <label class="ol-field">
            <span class="ol-field__label">Status</span>
            <select v-model="acquisitionMode" class="ol-select">
              <option v-for="opt in editor.acquisition_options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </label>
          <label class="ol-check">
            <input v-model="allowWaitingList" type="checkbox">
            Allow waiting list enquiries when full
          </label>
          <label class="ol-check">
            <input v-model="allowIndexing" type="checkbox">
            Allow search engines to index my page
          </label>
        </section>

        <p v-if="saveError" class="ol-error" role="alert">{{ saveError }}</p>
        <p v-if="saved" class="ok" role="status">Saved.</p>
        <button class="ol-btn" type="submit" :disabled="saving">Save page</button>
      </form>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { ProfileEditor } from '~/composables/useProfileSettings'

useHead({ title: 'Your page · Settings · OwnLane' })

const { fetchProfile, updateProfile, publish, unpublish, uploadPhoto, uploadCover } = useProfileSettings()

const loading = ref(true)
const loadError = ref('')
const saving = ref(false)
const saveError = ref('')
const saved = ref(false)
const busy = ref(false)
const editor = ref<ProfileEditor | null>(null)

const slug = ref('')
const businessName = ref('')
const intro = ref('')
const accentColour = ref('#2D6A4F')
const transmission = ref('')
const areasText = ref('')
const acquisitionMode = ref('closed')
const allowWaitingList = ref(true)
const allowIndexing = ref(true)
const vehicleSummary = ref('')
const dualControls = ref(false)
const teachingStyles = ref<string[]>([])
const services = ref<Array<{ id?: string; name: string; type: string; duration_minutes: number; price: string; description?: string }>>([])
const faqs = ref<Array<{ question: string; answer: string }>>([{ question: '', answer: '' }])
const contactPhone = ref('')
const contactEmail = ref('')
const whatsapp = ref('')
const showPhone = ref(false)
const showEmail = ref(false)
const socialInstagram = ref('')
const socialFacebook = ref('')

function formatDate(value: string): string {
  return new Date(value).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

async function load() {
  loading.value = true
  try {
    editor.value = await fetchProfile()
    applyEditor(editor.value)
  } catch (e) {
    loadError.value = extractApiError(e, 'Could not load profile.')
  } finally {
    loading.value = false
  }
}

function applyEditor(data: ProfileEditor) {
  slug.value = data.profile.slug
  businessName.value = data.profile.business_name ?? ''
  intro.value = data.profile.intro ?? ''
  accentColour.value = data.profile.accent_colour ?? '#2D6A4F'
  transmission.value = data.profile.transmission ?? ''
  areasText.value = data.profile.teaching_areas.join('\n')
  acquisitionMode.value = data.profile.acquisition_mode
  allowWaitingList.value = data.profile.allow_waiting_list
  allowIndexing.value = data.profile.allow_indexing
  vehicleSummary.value = data.profile.vehicle_summary ?? ''
  dualControls.value = data.profile.dual_controls
  teachingStyles.value = [...data.profile.teaching_styles]
  faqs.value = data.profile.faqs.length ? data.profile.faqs.map(f => ({ ...f })) : [{ question: '', answer: '' }]
  contactPhone.value = data.profile.contact_phone ?? ''
  contactEmail.value = data.profile.contact_email ?? ''
  whatsapp.value = data.profile.whatsapp ?? ''
  showPhone.value = data.profile.show_phone
  showEmail.value = data.profile.show_email
  socialInstagram.value = data.profile.social_links.instagram ?? ''
  socialFacebook.value = data.profile.social_links.facebook ?? ''

  const rawServices = data.profile.services
  services.value = rawServices.length
    ? rawServices.map((row, i) => ({
        id: String(row.id ?? `service-${i + 1}`),
        name: String(row.name ?? ''),
        type: String(row.type ?? 'lesson'),
        duration_minutes: Number(row.duration_minutes ?? 60),
        price: String((Number(row.price_pence ?? 0) / 100) || ''),
        description: row.description ? String(row.description) : undefined,
      }))
    : data.profile.public_pricing.map((row, i) => ({
        id: `lesson-${row.duration_minutes}`,
        name: row.label ?? `${row.duration_minutes} minutes`,
        type: 'lesson',
        duration_minutes: row.duration_minutes,
        price: String(row.price_pence / 100),
      }))
}

function poundsToPence(value: string): number {
  const cleaned = value.replace(/[^0-9.]/g, '')
  const parts = cleaned.split('.')
  const pounds = parseInt(parts[0] || '0', 10)
  const pencePart = (parts[1] ?? '00').padEnd(2, '0').slice(0, 2)
  return pounds * 100 + parseInt(pencePart, 10)
}

function addService() {
  services.value.push({ name: '', type: 'lesson', duration_minutes: 60, price: '' })
}

function buildPayload() {
  return {
    slug: slug.value.trim(),
    business_name: businessName.value.trim() || null,
    intro: intro.value,
    accent_colour: accentColour.value,
    transmission: transmission.value || null,
    teaching_areas: areasText.value.split('\n').map(s => s.trim()).filter(Boolean),
    teaching_styles: teachingStyles.value,
    vehicle_summary: vehicleSummary.value,
    dual_controls: dualControls.value,
    services: services.value.map((row, i) => ({
      id: row.id ?? `service-${i + 1}`,
      name: row.name,
      type: row.type,
      duration_minutes: row.duration_minutes,
      price_pence: poundsToPence(row.price),
      description: row.description ?? null,
      public: true,
    })),
    faqs: faqs.value.filter(f => f.question.trim() && f.answer.trim()),
    contact_phone: contactPhone.value || null,
    contact_email: contactEmail.value || null,
    whatsapp: whatsapp.value || null,
    show_phone: showPhone.value,
    show_email: showEmail.value,
    allow_indexing: allowIndexing.value,
    social_links: {
      instagram: socialInstagram.value || null,
      facebook: socialFacebook.value || null,
    },
    acquisition_mode: acquisitionMode.value,
    allow_waiting_list: allowWaitingList.value,
  }
}

async function onSave() {
  saving.value = true
  saveError.value = ''
  saved.value = false
  try {
    editor.value = await updateProfile(buildPayload())
    applyEditor(editor.value)
    saved.value = true
  } catch (e) {
    saveError.value = extractApiError(e, 'Could not save profile.')
  } finally {
    saving.value = false
  }
}

async function onPublish() {
  busy.value = true
  try {
    await updateProfile(buildPayload())
    editor.value = await publish()
    applyEditor(editor.value)
  } catch (e) {
    saveError.value = extractApiError(e, 'Could not publish.')
  } finally {
    busy.value = false
  }
}

async function onUnpublish() {
  busy.value = true
  try {
    editor.value = await unpublish()
    applyEditor(editor.value)
  } catch (e) {
    saveError.value = extractApiError(e, 'Could not unpublish.')
  } finally {
    busy.value = false
  }
}

async function onPhoto(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  try {
    editor.value = await uploadPhoto(file)
  } catch (err) {
    saveError.value = extractApiError(err, 'Could not upload photo.')
  }
}

async function onCover(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  try {
    editor.value = await uploadCover(file)
  } catch (err) {
    saveError.value = extractApiError(err, 'Could not upload cover image.')
  }
}

async function copyLink() {
  const url = editor.value?.profile.share_url
  if (!url) return
  try {
    await navigator.clipboard.writeText(url)
    saved.value = true
  } catch {
    saveError.value = 'Could not copy link.'
  }
}

onMounted(() => void load())
</script>

<style scoped>
.status-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  align-items: center;
}

.blockers {
  margin: 0;
  padding-left: 1.2rem;
  color: var(--color-text-muted);
}

.link-row {
  display: flex;
  gap: 8px;
}

.service-row,
.faq-row {
  display: grid;
  gap: 8px;
  margin-bottom: 12px;
}

@media (min-width: 720px) {
  .service-row {
    grid-template-columns: 1.4fr 1fr 0.7fr 0.7fr auto;
    align-items: center;
  }
}

.style-grid {
  border: 0;
  padding: 0;
  margin: 0 0 12px;
  display: grid;
  gap: 8px;
}

.analytics-grid {
  list-style: none;
  padding: 0;
  margin: 8px 0 0;
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}

.optional {
  color: var(--color-text-muted);
  font-weight: normal;
}

.ok {
  color: var(--color-success);
}
</style>
