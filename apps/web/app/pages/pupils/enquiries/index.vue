<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <div class="ol-page-header__row">
        <div>
          <p class="ol-eyebrow">Pupils</p>
          <h1 class="ol-page-title">Enquiries</h1>
          <p class="ol-meta">
            <NuxtLink to="/pupils" class="ol-link-action">Pupils</NuxtLink>
            ·
            <NuxtLink to="/pupils/intake" class="ol-link-action">Waiting for details</NuxtLink>
          </p>
        </div>
        <NuxtLink to="/settings/profile" class="ol-btn ol-btn--ghost ol-btn--sm">Your OwnLane page</NuxtLink>
      </div>
    </header>

    <div class="filters ol-seg" role="group" aria-label="Filter enquiries">
      <button
        v-for="opt in statusFilters"
        :key="opt.value"
        class="ol-chip"
        type="button"
        :class="{ 'ol-chip--on': status === opt.value }"
        @click="status = opt.value; void load()"
      >
        {{ opt.label }}
      </button>
    </div>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>

    <div v-else-if="items.length === 0" class="ol-empty">
      <h2 class="ol-empty__title">No enquiries</h2>
      <p class="ol-empty__copy">When someone asks about lessons through your OwnLane page, they’ll appear here.</p>
      <NuxtLink to="/settings/profile" class="ol-btn">Set up your page</NuxtLink>
    </div>

    <div v-else class="ol-panel ol-panel--flush">
      <ul class="ol-list-divide">
        <li v-for="item in items" :key="item.id">
          <NuxtLink :to="item.path" class="ol-row">
            <div class="ol-row__main">
              <p class="ol-row__title">{{ item.full_name }}</p>
              <p class="ol-row__meta">
                <span>{{ item.summary }}</span>
                <span class="ol-badge">{{ item.status_label }}</span>
              </p>
              <p v-if="item.fit_hint" class="ol-row__hint">{{ item.fit_hint }}</p>
            </div>
            <OlIcon name="chevron-right" :size="16" class="ol-row__chevron" />
          </NuxtLink>
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { EnquiryListItem } from '~/composables/useEnquiries'

useHead({ title: 'Enquiries · Pupils · OwnLane' })

const { list } = useEnquiries()
const items = ref<EnquiryListItem[]>([])
const loading = ref(true)
const error = ref('')
const status = ref('new')

const statusFilters = [
  { value: 'new', label: 'New' },
  { value: 'contacted', label: 'Contacted' },
  { value: 'accepted', label: 'Accepted' },
  { value: 'converted', label: 'Added as pupil' },
  { value: 'declined', label: 'Declined' },
  { value: 'all', label: 'All' },
]

async function load() {
  loading.value = true
  error.value = ''
  try {
    items.value = await list(status.value === 'all' ? undefined : status.value)
  } catch (e) {
    error.value = extractApiError(e, 'Could not load enquiries.')
  } finally {
    loading.value = false
  }
}

onMounted(() => void load())
</script>

<style scoped>
.filters {
  margin-bottom: var(--spacing-16);
}
</style>
