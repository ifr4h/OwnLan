<template>
  <div class="site" :style="siteStyle">
    <header class="site-header">
      <nav class="site-nav" aria-label="Page sections">
        <a class="site-nav__brand" href="#top">{{ profile?.display_name }}</a>
        <button
          class="site-nav__toggle"
          type="button"
          :aria-expanded="menuOpen"
          aria-controls="site-menu"
          @click="menuOpen = !menuOpen"
        >
          Menu
        </button>
        <div id="site-menu" class="site-nav__links" :class="{ 'site-nav__links--open': menuOpen }">
          <a href="#lessons" @click="menuOpen = false">Lessons</a>
          <a href="#about" @click="menuOpen = false">About</a>
          <a href="#areas" @click="menuOpen = false">Areas</a>
          <a href="#faq" @click="menuOpen = false">FAQ</a>
          <a href="#enquire" @click="menuOpen = false">Contact</a>
        </div>
        <a
          v-if="profile?.cta_label"
          href="#enquire"
          class="site-nav__cta"
        >
          {{ profile.cta_label }}
        </a>
      </nav>
    </header>

    <p v-if="loading" class="site-muted site-wrap">Loading…</p>
    <p v-else-if="error" class="site-error site-wrap" role="alert">{{ error }}</p>

    <template v-else-if="profile">
      <div id="top" class="site-hero" :class="{ 'site-hero--cover': profile.cover_url }">
        <img
          v-if="profile.cover_url"
          :src="profile.cover_url"
          alt=""
          class="site-hero__cover"
          loading="eager"
        >
        <div class="site-wrap site-hero__inner">
          <div class="site-hero__content">
            <div v-if="profile.photo_url" class="site-hero__photo-wrap">
              <img
                :src="profile.photo_url"
                :alt="profile.display_name"
                class="site-hero__photo"
                loading="eager"
                width="112"
                height="112"
              >
            </div>
            <div>
              <h1 class="site-hero__name">{{ profile.display_name }}</h1>
              <p class="site-hero__headline">{{ profile.headline }}</p>
              <p class="site-hero__status">{{ profile.acquisition_label }}</p>
              <p v-if="profile.pricing_from_label" class="site-hero__price">{{ profile.pricing_from_label }}</p>
              <p v-if="profile.subheadline" class="site-hero__intro">{{ profile.subheadline }}</p>
              <a
                v-if="profile.cta_label"
                href="#enquire"
                class="site-hero__cta"
              >
                {{ profile.cta_label }}
              </a>
            </div>
          </div>
        </div>
      </div>

      <div class="site-wrap site-layout">
        <main class="site-main">
          <section id="lessons" class="site-section">
            <h2>Lessons &amp; prices</h2>
            <ul v-if="profile.services.length" class="service-list">
              <li v-for="service in profile.services" :key="service.id" class="service-card">
                <div class="service-card__body">
                  <h3>{{ service.name }}</h3>
                  <p v-if="service.description" class="service-card__desc">{{ service.description }}</p>
                  <p class="service-card__meta">
                    <span v-if="service.duration_label">{{ service.duration_label }}</span>
                    <strong>{{ service.price_label }}</strong>
                  </p>
                </div>
                <button
                  v-if="showEnquiry && profile.cta_label"
                  type="button"
                  class="service-card__cta"
                  @click="askAbout(service)"
                >
                  Ask about this
                </button>
              </li>
            </ul>
          </section>

          <section id="about" class="site-section">
            <h2>About</h2>
            <p v-if="profile.intro" class="site-prose">{{ profile.intro }}</p>
            <ul class="fact-list">
              <li v-if="profile.transmission">{{ profile.transmission }}</li>
              <li v-if="profile.adi_status">{{ profile.adi_status }}</li>
              <li v-if="profile.years_teaching">Teaching for {{ profile.years_teaching }} years</li>
              <li v-if="profile.languages">Languages: {{ profile.languages }}</li>
            </ul>
            <ul v-if="profile.teaching_styles.length" class="tag-list">
              <li v-for="style in profile.teaching_styles" :key="style">{{ style }}</li>
            </ul>
            <div v-if="profile.vehicle_summary" class="vehicle">
              <h3>Car</h3>
              <p>
                {{ profile.vehicle_summary }}
                <span v-if="profile.dual_controls"> · Dual controls</span>
              </p>
            </div>
          </section>

          <section id="areas" class="site-section">
            <h2>Areas I cover</h2>
            <ul class="area-list">
              <li v-for="area in profile.teaching_areas" :key="area">{{ area }}</li>
            </ul>
          </section>

          <section v-if="profile.faqs.length" id="faq" class="site-section">
            <h2>FAQ</h2>
            <div class="faq-list">
              <details v-for="item in profile.faqs" :key="item.question" class="faq-item">
                <summary>{{ item.question }}</summary>
                <p>{{ item.answer }}</p>
              </details>
            </div>
          </section>
        </main>

        <aside id="enquire" class="site-aside">
          <div v-if="submitted" class="enquiry-card enquiry-card--ok">
            <h2>Thanks{{ submittedName ? `, ${submittedName}` : '' }}</h2>
            <p>Your enquiry has been sent to {{ profile.display_name }}.</p>
          </div>

          <form
            v-else-if="showEnquiry"
            class="enquiry-card"
            @submit.prevent="onSubmit"
            @focusin="onFormFocus"
          >
            <h2>{{ enquiryTitle }}</h2>
            <p v-if="serviceInterest" class="service-interest">
              Interested in: <strong>{{ serviceInterest }}</strong>
            </p>

            <label class="field">
              <span>First name</span>
              <input v-model="form.first_name" required autocomplete="given-name">
            </label>
            <label class="field">
              <span>Last name</span>
              <input v-model="form.last_name" required autocomplete="family-name">
            </label>
            <label class="field">
              <span>Mobile</span>
              <input v-model="form.mobile" required type="tel" autocomplete="tel">
            </label>
            <label class="field">
              <span>Email <span class="optional">optional</span></span>
              <input v-model="form.email" type="email" autocomplete="email">
            </label>
            <label class="field">
              <span>Postcode</span>
              <input v-model="form.postcode" required @blur="onPostcodeBlur">
            </label>
            <p v-if="areaLabel" class="area-note">{{ areaLabel }}</p>

            <label v-if="profile.transmission_code === 'both'" class="field">
              <span>Transmission</span>
              <select v-model="form.transmission">
                <option value="automatic">Automatic</option>
                <option value="manual">Manual</option>
                <option value="either">Either</option>
              </select>
            </label>

            <fieldset class="field">
              <legend>How much driving have you done?</legend>
              <label class="radio"><input v-model="form.experience_band" type="radio" value="new"> I'm completely new</label>
              <label class="radio"><input v-model="form.experience_band" type="radio" value="few"> I've had a few lessons</label>
              <label class="radio"><input v-model="form.experience_band" type="radio" value="many"> I've had quite a few lessons</label>
              <label class="radio"><input v-model="form.experience_band" type="radio" value="returning"> I've driven before but not recently</label>
            </fieldset>

            <label class="field">
              <span>When would you like to start?</span>
              <select v-model="form.desired_start">
                <option value="asap">As soon as possible</option>
                <option value="few_weeks">Within the next few weeks</option>
                <option value="next_month">Next month</option>
                <option value="flexible">I'm flexible</option>
              </select>
            </label>

            <label class="field">
              <span>Message <span class="optional">optional</span></span>
              <textarea v-model="form.message" rows="3" />
            </label>

            <input v-model="form.website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">

            <p class="privacy-note">
              Your details go to {{ profile.display_name }} through OwnLane so they can reply about lessons.
            </p>

            <p v-if="submitError" class="site-error" role="alert">{{ submitError }}</p>

            <button class="enquiry-submit" type="submit" :disabled="submitting">
              {{ submitting ? 'Sending…' : submitButtonLabel }}
            </button>
          </form>

          <div v-else class="enquiry-card">
            <h2>Not taking new pupils</h2>
            <p class="site-muted">{{ profile.display_name }} is not taking enquiries at the moment.</p>
          </div>

          <div v-if="hasContact" class="contact-links">
            <h3>Other ways to reach {{ profile.display_name }}</h3>
            <ul>
              <li v-if="profile.contact.phone">
                <a :href="`tel:${profile.contact.phone}`">{{ profile.contact.phone }}</a>
              </li>
              <li v-if="profile.contact.email">
                <a :href="`mailto:${profile.contact.email}`">{{ profile.contact.email }}</a>
              </li>
              <li v-if="profile.contact.whatsapp_url">
                <a :href="profile.contact.whatsapp_url" rel="noopener noreferrer" target="_blank">WhatsApp</a>
              </li>
            </ul>
          </div>

          <div v-if="socialEntries.length" class="social-links">
            <ul>
              <li v-for="entry in socialEntries" :key="entry.label">
                <a :href="entry.url" rel="noopener noreferrer" target="_blank">{{ entry.label }}</a>
              </li>
            </ul>
          </div>
        </aside>
      </div>

      <footer class="site-footer">
        <div class="site-wrap">
          <p v-if="profile.powered_by_ownlane" class="powered">
            Powered by <NuxtLink to="/">OwnLane</NuxtLink>
          </p>
        </div>
      </footer>

      <a
        v-if="profile.cta_label && showEnquiry && !submitted"
        href="#enquire"
        class="site-sticky-cta"
      >
        {{ profile.cta_label }}
      </a>
    </template>
  </div>
</template>

<script setup lang="ts">
import type { PublicProfile, PublicService } from '~/composables/usePublicProfile'

definePageMeta({ layout: false })

const route = useRoute()
const slug = computed(() => String(route.params.slug ?? ''))
const sourceTag = computed(() => (typeof route.query.src === 'string' ? route.query.src : undefined))

const { fetchProfile, checkArea, submitEnquiry, trackEvent } = usePublicProfile()

const profile = ref<PublicProfile | null>(null)
const loading = ref(true)
const error = ref('')
const submitting = ref(false)
const submitError = ref('')
const submitted = ref(false)
const submittedName = ref('')
const areaLabel = ref('')
const serviceInterest = ref('')
const menuOpen = ref(false)
const trackedView = ref(false)
const trackedFormStart = ref(false)

const form = reactive({
  first_name: '',
  last_name: '',
  mobile: '',
  email: '',
  postcode: '',
  transmission: 'automatic',
  experience_band: 'new',
  desired_start: 'asap',
  message: '',
  website: '',
  availability: { days: [] as Array<{ weekday: number; slots: string[] }> },
})

const siteStyle = computed(() => {
  if (!profile.value) return {}
  return {
    '--site-accent': profile.value.branding.accent_colour,
    '--site-accent-fg': profile.value.branding.accent_foreground,
  }
})

const showEnquiry = computed(() =>
  Boolean(profile.value?.allows_enquiry || profile.value?.allows_waiting_list),
)

const enquiryTitle = computed(() => {
  if (!profile.value) return 'Ask about lessons'
  if (profile.value.allows_waiting_list && !profile.value.allows_enquiry) return 'Join waiting list'
  return 'Ask about lessons'
})

const submitButtonLabel = computed(() => {
  if (!profile.value) return 'Send enquiry'
  if (profile.value.allows_waiting_list && !profile.value.allows_enquiry) return 'Join waiting list'
  return 'Send enquiry'
})

const hasContact = computed(() => {
  const c = profile.value?.contact
  return Boolean(c?.phone || c?.email || c?.whatsapp_url)
})

const socialEntries = computed(() => {
  const links = profile.value?.social_links ?? {}
  const labels: Record<string, string> = {
    instagram: 'Instagram',
    facebook: 'Facebook',
    tiktok: 'TikTok',
    youtube: 'YouTube',
  }
  return Object.entries(links).map(([key, url]) => ({
    label: labels[key] ?? key,
    url,
  }))
})

useHead(() => {
  if (!profile.value) return {}
  const seo = profile.value.seo
  const scripts = [{
    type: 'application/ld+json',
    innerHTML: JSON.stringify(profile.value.structured_data),
  }]

  return {
    title: seo.title,
    meta: [
      { name: 'description', content: seo.description },
      { name: 'robots', content: seo.robots },
      { property: 'og:title', content: seo.title },
      { property: 'og:description', content: seo.description },
      { property: 'og:url', content: seo.canonical },
      ...(seo.og_image ? [{ property: 'og:image', content: seo.og_image }] : []),
    ],
    link: [{ rel: 'canonical', href: seo.canonical }],
    script: scripts,
  }
})

function askAbout(service: PublicService) {
  serviceInterest.value = service.name
  void trackEvent(slug.value, 'service_click', sourceTag.value)
  document.getElementById('enquire')?.scrollIntoView({ behavior: 'smooth' })
}

function onFormFocus() {
  if (trackedFormStart.value) return
  trackedFormStart.value = true
  void trackEvent(slug.value, 'enquiry_started', sourceTag.value)
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    profile.value = await fetchProfile(slug.value)
    if (profile.value.transmission_code === 'manual') form.transmission = 'manual'
    if (!trackedView.value) {
      trackedView.value = true
      void trackEvent(slug.value, 'view', sourceTag.value)
    }
  } catch (e) {
    error.value = extractApiError(e, 'Profile not found.')
  } finally {
    loading.value = false
  }
}

async function onPostcodeBlur() {
  if (!form.postcode.trim() || !profile.value) return
  try {
    const result = await checkArea(slug.value, form.postcode)
    areaLabel.value = result.status === 'within'
      ? `This looks within ${profile.value.display_name}'s usual teaching area.`
      : result.status === 'outside'
        ? `This may be outside ${profile.value.display_name}'s usual area. You can still send an enquiry.`
        : ''
  } catch {
    areaLabel.value = ''
  }
}

async function onSubmit() {
  submitError.value = ''
  submitting.value = true
  try {
    const result = await submitEnquiry(slug.value, {
      ...form,
      email: form.email || undefined,
      message: form.message || undefined,
      service_interest: serviceInterest.value || undefined,
      source_tag: sourceTag.value,
    })
    submitted.value = true
    submittedName.value = result.first_name
  } catch (e) {
    submitError.value = extractApiError(e, 'Could not send enquiry.')
  } finally {
    submitting.value = false
  }
}

onMounted(() => void load())
</script>

<style scoped>
.site {
  min-height: 100vh;
  background: var(--color-paper-white, #faf9f6);
  color: var(--color-ink-black, #1a1a1a);
  padding-bottom: calc(88px + env(safe-area-inset-bottom));
}

.site-wrap {
  width: min(1120px, 100%);
  margin: 0 auto;
  padding-inline: var(--spacing-16);
}

.site-header {
  position: sticky;
  top: 0;
  z-index: 20;
  background: rgba(250, 249, 246, 0.95);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid var(--color-border, #e5e2db);
}

.site-nav {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px var(--spacing-16);
  max-width: 1120px;
  margin: 0 auto;
}

.site-nav__brand {
  font-weight: 700;
  color: inherit;
  text-decoration: none;
  margin-right: auto;
}

.site-nav__toggle {
  display: inline-flex;
  border: 1px solid var(--color-border, #e5e2db);
  background: #fff;
  border-radius: 10px;
  padding: 8px 12px;
  font: inherit;
}

.site-nav__links {
  display: none;
  gap: 16px;
}

.site-nav__links a {
  color: var(--color-text-muted, #5c5c5c);
  text-decoration: none;
  font-size: 0.95rem;
}

.site-nav__links--open {
  display: flex;
  position: absolute;
  left: 0;
  right: 0;
  top: 56px;
  flex-direction: column;
  background: #fff;
  border-bottom: 1px solid var(--color-border, #e5e2db);
  padding: 12px var(--spacing-16) 16px;
}

.site-nav__cta {
  display: none;
  padding: 10px 14px;
  border-radius: 10px;
  background: var(--site-accent, #2d6a4f);
  color: var(--site-accent-fg, #fff);
  text-decoration: none;
  font-weight: 600;
  white-space: nowrap;
}

@media (min-width: 900px) {
  .site-nav__toggle {
    display: none;
  }

  .site-nav__links {
    display: flex;
    position: static;
    flex-direction: row;
    background: transparent;
    border: 0;
    padding: 0;
  }

  .site-nav__cta {
    display: inline-flex;
  }
}

.site-hero {
  padding: var(--spacing-24) 0;
  border-bottom: 1px solid var(--color-border, #e5e2db);
  position: relative;
  overflow: hidden;
}

.site-hero--cover {
  padding-top: 120px;
}

.site-hero__cover {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  opacity: 0.22;
}

.site-hero__inner {
  position: relative;
}

.site-hero__content {
  display: flex;
  gap: var(--spacing-16);
  align-items: flex-start;
}

.site-hero__photo {
  width: 112px;
  height: 112px;
  border-radius: 18px;
  object-fit: cover;
  border: 3px solid #fff;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
}

.site-hero__name {
  font-size: clamp(1.85rem, 4vw, 2.5rem);
  margin: 0 0 6px;
  line-height: 1.1;
}

.site-hero__headline {
  font-size: 1.1rem;
  margin: 0 0 8px;
  color: var(--color-text-muted, #5c5c5c);
}

.site-hero__status,
.site-hero__price {
  margin: 0 0 6px;
  font-weight: 600;
}

.site-hero__intro {
  margin: 12px 0 0;
  max-width: 42rem;
  line-height: 1.55;
}

.site-hero__cta {
  display: inline-flex;
  margin-top: 16px;
  padding: 12px 18px;
  border-radius: 12px;
  background: var(--site-accent, #2d6a4f);
  color: var(--site-accent-fg, #fff);
  text-decoration: none;
  font-weight: 600;
}

.site-layout {
  display: grid;
  gap: var(--spacing-24);
  padding-block: var(--spacing-24);
}

@media (min-width: 960px) {
  .site-layout {
    grid-template-columns: minmax(0, 1fr) 360px;
    align-items: start;
  }

  .site-aside {
    position: sticky;
    top: 72px;
  }
}

.site-section {
  margin-bottom: var(--spacing-32);
  scroll-margin-top: 80px;
}

.site-section h2 {
  font-size: 1.35rem;
  margin: 0 0 12px;
}

.site-prose {
  white-space: pre-wrap;
  line-height: 1.6;
  margin: 0 0 12px;
}

.service-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: grid;
  gap: 12px;
}

.service-card {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  padding: 14px 16px;
  border: 1px solid var(--color-border, #e5e2db);
  border-radius: 14px;
  background: #fff;
}

.service-card h3 {
  margin: 0 0 4px;
  font-size: 1rem;
}

.service-card__desc {
  margin: 0 0 6px;
  color: var(--color-text-muted, #5c5c5c);
  font-size: 0.95rem;
}

.service-card__meta {
  display: flex;
  gap: 12px;
  margin: 0;
  font-size: 0.95rem;
}

.service-card__cta {
  border: 1px solid var(--color-border, #e5e2db);
  background: #fff;
  border-radius: 10px;
  padding: 8px 12px;
  font: inherit;
  white-space: nowrap;
  cursor: pointer;
}

.fact-list,
.area-list,
.tag-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.tag-list li,
.area-list li {
  padding: 6px 10px;
  border-radius: 999px;
  background: #fff;
  border: 1px solid var(--color-border, #e5e2db);
  font-size: 0.95rem;
}

.faq-list {
  display: grid;
  gap: 8px;
}

.faq-item {
  border: 1px solid var(--color-border, #e5e2db);
  border-radius: 12px;
  background: #fff;
  padding: 10px 14px;
}

.faq-item summary {
  cursor: pointer;
  font-weight: 600;
}

.faq-item p {
  margin: 8px 0 0;
  line-height: 1.55;
  color: var(--color-text-muted, #5c5c5c);
}

.enquiry-card {
  background: #fff;
  border: 1px solid var(--color-border, #e5e2db);
  border-radius: 16px;
  padding: var(--spacing-16);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.04);
}

.enquiry-card .field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.enquiry-card input,
.enquiry-card select,
.enquiry-card textarea {
  padding: 10px 12px;
  border: 1px solid var(--color-border, #e5e2db);
  border-radius: 10px;
  font: inherit;
}

.enquiry-card fieldset {
  border: 0;
  padding: 0;
  margin: 0 0 12px;
}

.radio {
  display: flex;
  gap: 8px;
  margin-bottom: 6px;
  font-size: 0.95rem;
}

.service-interest {
  margin: 0 0 12px;
  padding: 8px 10px;
  border-radius: 10px;
  background: rgba(45, 106, 79, 0.08);
  font-size: 0.95rem;
}

.enquiry-submit {
  width: 100%;
  margin-top: 8px;
  padding: 14px 16px;
  border: 0;
  border-radius: 12px;
  background: var(--site-accent, #2d6a4f);
  color: var(--site-accent-fg, #fff);
  font-weight: 600;
  font-size: 1rem;
  cursor: pointer;
}

.contact-links,
.social-links {
  margin-top: 16px;
}

.contact-links ul,
.social-links ul {
  list-style: none;
  padding: 0;
  margin: 8px 0 0;
}

.contact-links a,
.social-links a {
  color: var(--site-accent, #2d6a4f);
}

.site-footer {
  border-top: 1px solid var(--color-border, #e5e2db);
  padding: 20px 0 28px;
}

.powered {
  margin: 0;
  font-size: 0.85rem;
  color: var(--color-text-muted, #5c5c5c);
}

.powered a {
  color: inherit;
}

.site-sticky-cta {
  position: fixed;
  left: 16px;
  right: 16px;
  bottom: calc(16px + env(safe-area-inset-bottom));
  display: grid;
  place-items: center;
  padding: 14px;
  border-radius: 12px;
  background: var(--site-accent, #2d6a4f);
  color: var(--site-accent-fg, #fff);
  font-weight: 600;
  text-decoration: none;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
  z-index: 30;
}

@media (min-width: 960px) {
  .site-sticky-cta {
    display: none;
  }
}

.site-muted {
  color: var(--color-text-muted, #5c5c5c);
}

.site-error {
  color: #b42318;
}

.optional {
  color: var(--color-text-muted);
  font-weight: normal;
}

.privacy-note,
.area-note {
  font-size: 0.9rem;
  color: var(--color-text-muted);
}

.hp {
  position: absolute;
  left: -9999px;
  opacity: 0;
}
</style>
