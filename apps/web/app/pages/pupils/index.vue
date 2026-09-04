<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <div class="ol-page-header__row">
        <div>
          <p class="ol-eyebrow">Pupils</p>
          <h1 class="ol-page-title">Your pupils</h1>
          <p class="pupils__sub">
            <NuxtLink to="/pupils/enquiries" class="ol-link-action">Enquiries</NuxtLink>
            ·
            <NuxtLink to="/pupils/intake" class="ol-link-action">Waiting for details</NuxtLink>
          </p>
        </div>
        <div class="ol-actions">
          <NuxtLink to="/pupils/import" class="ol-btn ol-btn--ghost ol-btn--sm">Import CSV</NuxtLink>
          <NuxtLink to="/pupils/new" class="ol-btn ol-btn--sm">
            Add pupil
            <span aria-hidden="true">→</span>
          </NuxtLink>
        </div>
      </div>
    </header>

    <label class="ol-field">
      <span class="sr-only">Search pupils</span>
      <input
        v-model="query"
        class="ol-input"
        type="search"
        placeholder="Search by name or mobile"
        autocomplete="off"
        @input="onSearch"
      >
    </label>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>

    <template v-else>
      <div
        v-if="attention?.lines?.length && !query.trim()"
        class="attention-summary"
        role="status"
        aria-label="Pupils needing attention"
      >
        <p v-for="line in attention.lines" :key="line" class="attention-summary__line">
          {{ line }}
        </p>
      </div>

      <div v-if="pupils.length === 0" class="ol-empty">
      <h2 class="ol-empty__title">
        {{ query.trim() ? 'No matches' : 'No pupils yet' }}
      </h2>
      <p class="ol-empty__copy">
        {{
          query.trim()
            ? 'Try a different name or number.'
            : 'Add your first pupil, or import a CSV if you’re moving from another system.'
        }}
      </p>
      <div v-if="!query.trim()" class="ol-empty__actions">
        <NuxtLink to="/pupils/new" class="ol-btn">
          Add pupil
          <span aria-hidden="true">→</span>
        </NuxtLink>
        <NuxtLink to="/pupils/import" class="ol-btn ol-btn--ghost">Import CSV</NuxtLink>
      </div>
    </div>

    <div v-else class="ol-panel ol-panel--flush">
      <ul class="ol-list-divide" aria-label="Pupils">
        <li v-for="pupil in pupils" :key="pupil.id">
          <NuxtLink :to="`/pupils/${pupil.id}`" class="ol-row">
            <div class="ol-row__main">
              <p class="ol-row__title">{{ pupil.full_name }}</p>
              <p class="ol-row__meta">
                <span>{{ pupil.mobile }}</span>
                <span v-if="pupil.attention_hint" class="ol-row__hint">{{ pupil.attention_hint }}</span>
              </p>
            </div>
            <OlIcon name="chevron-right" :size="16" class="ol-row__chevron" />
          </NuxtLink>
        </li>
      </ul>
    </div>

    <section v-if="waiting.length && !query.trim()" class="waiting">
      <h2 class="ol-section-title">Waiting list</h2>
      <div class="ol-panel ol-panel--flush">
        <ul class="ol-list-divide" aria-label="Waiting list">
          <li v-for="pupil in waiting" :key="pupil.id">
            <NuxtLink :to="`/pupils/${pupil.id}`" class="ol-row">
              <div class="ol-row__main">
                <p class="ol-row__title">{{ pupil.full_name }}</p>
                <p class="ol-row__meta">
                  <span>{{ pupil.mobile }}</span>
                  <span v-if="pupil.gap_matches?.summary" class="ol-row__hint">{{ pupil.gap_matches.summary }}</span>
                </p>
              </div>
              <span class="ol-badge ol-badge--warning">Waiting</span>
              <OlIcon name="chevron-right" :size="16" class="ol-row__chevron" />
            </NuxtLink>
          </li>
        </ul>
      </div>
    </section>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { PupilListAttention, PupilListItem } from '~/composables/usePupils'

useHead({ title: 'Pupils · OwnLane' })

const { listPupils, listWaitingPupils } = usePupils()
const pupils = ref<PupilListItem[]>([])
const waiting = ref<PupilListItem[]>([])
const attention = ref<PupilListAttention | null>(null)
const query = ref('')
const loading = ref(true)
const error = ref('')
let searchTimer: ReturnType<typeof setTimeout> | null = null

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [listResult, waitingList] = await Promise.all([
      listPupils(query.value),
      listWaitingPupils(),
    ])
    pupils.value = listResult.items
    attention.value = listResult.attention ?? null
    waiting.value = waitingList
  } catch (e) {
    error.value = extractApiError(e, 'Could not load pupils.')
  } finally {
    loading.value = false
  }
}

function onSearch() {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    void load()
  }, 200)
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.pupils__sub {
  margin-top: var(--spacing-8);
}

.waiting {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.attention-summary {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
  padding: var(--spacing-12) var(--spacing-16);
  border-radius: var(--radius-md);
  background: var(--colour-surface-raised);
  border: 1px solid var(--colour-border-subtle);
}

.attention-summary__line {
  margin: 0;
  font-size: var(--font-size-sm);
  font-weight: 500;
  color: var(--colour-text-primary);
}

.ol-row__meta {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-4) var(--spacing-8);
}

.ol-row__hint {
  color: var(--colour-warning-text, var(--colour-text-secondary));
  font-size: var(--font-size-xs);
}

@media (min-width: 768px) {
  .ol-row__hint::before {
    content: '·';
    margin-right: var(--spacing-4);
    color: var(--colour-text-tertiary);
  }
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}
</style>
