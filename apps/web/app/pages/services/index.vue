<script setup lang="ts">
import type { CatalogueService, ServicesHome } from '~/composables/useServices'

useHead({ title: 'Services · OwnLane' })

const { fetchHome } = useServices()

const loading = ref(true)
const error = ref('')
const home = ref<ServicesHome | null>(null)

const lessonServices = computed(() =>
  (home.value?.services ?? []).filter(s => s.status !== 'inactive'),
)
const inactiveServices = computed(() =>
  (home.value?.services ?? []).filter(s => s.status === 'inactive'),
)
const packages = computed(() => home.value?.packages ?? [])
const pricingRules = computed(() => home.value?.pricing_rules ?? [])
const pupilRates = computed(() => home.value?.pupil_rates ?? [])

async function load() {
  loading.value = true
  error.value = ''
  try {
    home.value = await fetchHome()
  } catch (e) {
    error.value = extractApiError(e, 'Could not load services.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})

function bookingTone(service: CatalogueService): string {
  if (service.status === 'draft') return 'Draft'
  if (service.booking_access === 'instant') return 'Available to book'
  if (service.booking_access === 'request') return 'Request only'
  return 'You book it'
}
</script>

<template>
  <section class="services ol-page">
    <header class="services__header">
      <div>
        <p class="ol-eyebrow">Services</p>
        <h1 class="ol-page-title">What pupils can book and buy</h1>
      </div>
      <NuxtLink to="/services/new" class="ol-btn ol-btn--sm">
        Add service
        <span aria-hidden="true">→</span>
      </NuxtLink>
    </header>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading services…</p>

    <template v-else-if="home">
      <section class="catalog" aria-labelledby="lessons-heading">
        <div class="catalog__head">
          <h2 id="lessons-heading" class="catalog__title">Lessons</h2>
        </div>
        <ul v-if="lessonServices.length" class="catalog__list">
          <li v-for="service in lessonServices" :key="service.id">
            <NuxtLink :to="`/services/${service.id}`" class="offer">
              <div class="offer__main">
                <p class="offer__name">
                  {{ service.name }}
                  <span v-if="service.is_default" class="offer__badge">Default</span>
                </p>
                <p class="offer__meta">
                  {{ service.duration_label }}
                  <span aria-hidden="true">·</span>
                  {{ service.price_label }}
                </p>
              </div>
              <p class="offer__status" :data-tone="service.status">{{ bookingTone(service) }}</p>
              <OlIcon name="chevron-right" :size="18" class="offer__chevron" />
            </NuxtLink>
          </li>
        </ul>
        <div v-else class="catalog__empty">
          <p>No lessons yet.</p>
          <NuxtLink to="/services/new" class="ol-btn ol-btn--sm">Add your first lesson</NuxtLink>
        </div>
        <ul v-if="inactiveServices.length" class="catalog__list catalog__list--muted">
          <li v-for="service in inactiveServices" :key="service.id">
            <NuxtLink :to="`/services/${service.id}`" class="offer offer--muted">
              <div class="offer__main">
                <p class="offer__name">{{ service.name }}</p>
                <p class="offer__meta">{{ service.duration_label }} · {{ service.price_label }}</p>
              </div>
              <p class="offer__status">Inactive</p>
            </NuxtLink>
          </li>
        </ul>
      </section>

      <section class="catalog" aria-labelledby="packages-heading">
        <div class="catalog__head">
          <h2 id="packages-heading" class="catalog__title">Packages</h2>
          <NuxtLink to="/services/packages/new" class="ol-link-action">Add package</NuxtLink>
        </div>
        <ul v-if="packages.length" class="catalog__list">
          <li v-for="pkg in packages" :key="pkg.id">
            <NuxtLink :to="`/services/packages/${pkg.id}`" class="offer">
              <div class="offer__main">
                <p class="offer__name">{{ pkg.label }}</p>
                <p class="offer__meta">
                  {{ pkg.hours_label }}
                  <span aria-hidden="true">·</span>
                  {{ pkg.price_label }}
                </p>
              </div>
              <p class="offer__status">{{ pkg.availability_label }}</p>
              <OlIcon name="chevron-right" :size="18" class="offer__chevron" />
            </NuxtLink>
          </li>
        </ul>
        <p v-else class="catalog__hint">
          Sell hour blocks from the pupil portal. Add a 10-hour block when you’re ready.
        </p>
      </section>

      <section class="catalog" aria-labelledby="pricing-heading">
        <div class="catalog__head">
          <h2 id="pricing-heading" class="catalog__title">Pricing</h2>
          <NuxtLink to="/services/pricing" class="ol-link-action">Manage pricing</NuxtLink>
        </div>
        <ul v-if="pricingRules.length" class="rule-list">
          <li v-for="rule in pricingRules" :key="rule.id" class="rule" :data-off="rule.active ? 'no' : 'yes'">
            <p class="rule__label">{{ rule.label }}</p>
            <p class="rule__value">{{ rule.adjustment_label }}</p>
          </li>
        </ul>
        <p v-else class="catalog__hint">
          Evenings, Saturdays and Sundays can have different prices when you need them.
        </p>

        <div v-if="pupilRates.length" class="pupil-rates">
          <h3 class="catalog__subtitle">Pupil rates</h3>
          <ul class="rule-list">
            <li v-for="rate in pupilRates.slice(0, 5)" :key="rate.id" class="rule">
              <p class="rule__label">
                {{ rate.learner_name }}
                <span class="rule__sub">{{ rate.service_name }}</span>
              </p>
              <p class="rule__value">{{ rate.price_label }}</p>
            </li>
          </ul>
          <NuxtLink to="/services/pricing#pupil-rates" class="ol-link-action">All pupil rates</NuxtLink>
        </div>
      </section>
    </template>
  </section>
</template>

<style scoped>
.services__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 28px;
}

.catalog {
  margin-bottom: 36px;
}

.catalog__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.catalog__title {
  margin: 0;
  font-family: var(--font-haas-grot-disp);
  font-size: 1.35rem;
  font-weight: 600;
  letter-spacing: -0.02em;
  color: var(--color-ink-black);
}

.catalog__subtitle {
  margin: 20px 0 10px;
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-bark);
}

.catalog__list {
  list-style: none;
  margin: 0;
  padding: 0;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-cards);
  overflow: hidden;
  background: var(--color-paper-white);
}

.catalog__list--muted {
  margin-top: 10px;
  opacity: 0.72;
}

.catalog__list li + li {
  border-top: 1px solid var(--color-border);
}

.offer {
  display: grid;
  grid-template-columns: 1fr auto auto;
  align-items: center;
  gap: 12px;
  padding: 16px 18px;
  text-decoration: none;
  color: inherit;
  transition: background-color var(--duration-fast) ease;
}

.offer:hover {
  background: var(--color-parchment);
}

.offer--muted {
  grid-template-columns: 1fr auto;
}

.offer__name {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
  color: var(--color-ink-black);
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.offer__badge {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-ownlane-green);
  background: color-mix(in srgb, var(--color-ownlane-green) 12%, white);
  padding: 2px 8px;
  border-radius: 999px;
}

.offer__meta {
  margin: 4px 0 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.offer__status {
  margin: 0;
  font-size: var(--text-meta);
  font-weight: 500;
  color: var(--color-bark);
  text-align: right;
  white-space: nowrap;
}

.offer__status[data-tone='draft'] {
  color: var(--color-muted);
}

.offer__chevron {
  color: var(--color-ash-mist);
}

.catalog__empty {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
  padding: 24px;
  border: 1px dashed var(--color-driftwood);
  border-radius: var(--radius-cards);
  background: var(--color-parchment);
}

.catalog__empty p,
.catalog__hint {
  margin: 0;
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}

.rule-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 8px;
}

.rule {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0;
  border-bottom: 1px solid var(--color-border);
}

.rule[data-off='yes'] {
  opacity: 0.5;
}

.rule__label {
  margin: 0;
  font-weight: 500;
  color: var(--color-ink-black);
}

.rule__sub {
  display: block;
  margin-top: 2px;
  font-weight: 400;
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.rule__value {
  margin: 0;
  font-weight: 600;
  color: var(--color-ink-black);
  white-space: nowrap;
}

.pupil-rates {
  margin-top: 8px;
}

@media (max-width: 640px) {
  .services__header {
    flex-direction: column;
  }

  .offer {
    grid-template-columns: 1fr auto;
  }

  .offer__status {
    grid-column: 1;
    text-align: left;
  }

  .offer__chevron {
    grid-row: 1 / span 2;
    grid-column: 2;
  }
}
</style>
