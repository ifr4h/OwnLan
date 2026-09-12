<template>
  <section class="ol-page">
    <header class="pupils-list-header">
      <div>
        <div class="pupils-list-header__title-row">
          <h1 class="ol-page-title">Enquiries</h1>
          <span
            v-if="!loading"
            class="pupils-list-header__count"
            :aria-label="`${filteredItems.length} enquiries`"
          >
            {{ filteredItems.length }}
          </span>
        </div>
        <p class="pupils-list-header__meta ol-meta">
          <NuxtLink to="/pupils" class="pupils-list-header__link">Pupils</NuxtLink>
          <span class="pupils-list-header__sep" aria-hidden="true">·</span>
          <NuxtLink to="/pupils/report" class="pupils-list-header__link">Insights</NuxtLink>
        </p>
      </div>
      <div class="ol-actions">
        <NuxtLink to="/settings/profile" class="ol-btn ol-btn--ghost ol-btn--sm">Your OwnLane page</NuxtLink>
      </div>
    </header>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>

    <div v-else class="pupils-list-board">
      <div class="pupils-list-toolbar">
        <label class="pupils-list-search">
          <OlIcon name="search" :size="16" class="pupils-list-search__icon" />
          <span class="sr-only">Search enquiries</span>
          <input
            v-model="query"
            class="pupils-list-search__input"
            type="search"
            placeholder="Search"
            autocomplete="off"
          >
        </label>

        <div class="enquiries__tools">
          <div class="enquiries__status" role="group" aria-label="Enquiry status">
            <button
              v-for="opt in statusFilters"
              :key="opt.value"
              class="pupils-list-pill"
              type="button"
              :class="{ 'pupils-list-pill--on': status === opt.value }"
              @click="onStatus(opt.value)"
            >
              {{ opt.label }}
            </button>
          </div>

          <div ref="layoutRoot" class="pupils-list-layout">
            <button
              class="pupils-list-layout__btn"
              type="button"
              :class="{ 'pupils-list-layout__btn--open': layoutOpen }"
              :aria-expanded="layoutOpen"
              aria-haspopup="menu"
              aria-label="Layout"
              @click="layoutOpen = !layoutOpen"
            >
              <OlIcon :name="layout === 'cards' ? 'grid' : 'list'" :size="16" />
            </button>
            <div
              v-if="layoutOpen"
              class="pupils-list-layout__panel"
              role="menu"
              aria-label="Layout"
            >
              <button
                class="pupils-list-layout__option"
                type="button"
                role="menuitemradio"
                :class="{ 'pupils-list-layout__option--on': layout === 'list' }"
                :aria-checked="layout === 'list'"
                @click="setLayout('list')"
              >
                <OlIcon name="list" :size="16" />
                List
              </button>
              <button
                class="pupils-list-layout__option"
                type="button"
                role="menuitemradio"
                :class="{ 'pupils-list-layout__option--on': layout === 'cards' }"
                :aria-checked="layout === 'cards'"
                @click="setLayout('cards')"
              >
                <OlIcon name="grid" :size="16" />
                Cards
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="filteredItems.length === 0" class="pupils-list-empty">
        <h2 class="pupils-list-empty__title">
          {{ query.trim() ? 'No matches' : 'No enquiries' }}
        </h2>
        <p class="pupils-list-empty__copy">
          <template v-if="query.trim()">Try a different name or postcode.</template>
          <template v-else>
            When someone asks about lessons through your OwnLane page, they’ll appear here.
          </template>
        </p>
        <NuxtLink
          v-if="!query.trim()"
          to="/settings/profile"
          class="ol-btn ol-btn--sm"
        >
          Set up your page
        </NuxtLink>
      </div>

      <div v-else-if="layout === 'cards'" class="pupils-list-cards">
        <article
          v-for="item in filteredItems"
          :key="item.id"
          class="pupils-list-card"
        >
          <div class="pupils-list-card__top">
            <span class="pupils-list-avatar" aria-hidden="true">{{ initials(item.full_name) }}</span>
            <div class="pupils-list-card__text">
              <p class="pupils-list-card__name">{{ item.full_name }}</p>
              <p class="pupils-list-card__detail">{{ cardDetail(item) }}</p>
            </div>
            <span class="ol-badge pupils-list-card__badge" :class="badgeTone(item)">
              {{ item.status_label }}
            </span>
          </div>

          <ul class="pupils-list-card__meta">
            <li
              v-for="(row, index) in cardMeta(item)"
              :key="`${item.id}-${row.icon}-${index}`"
              class="pupils-list-card__meta-row"
              :class="{ 'pupils-list-card__meta-row--muted': row.muted }"
            >
              <span class="pupils-list-card__meta-icon" aria-hidden="true">
                <OlIcon :name="row.icon" :size="15" />
              </span>
              <span class="pupils-list-card__meta-label">{{ row.label }}</span>
            </li>
          </ul>

          <div
            class="pupils-list-card__actions"
            :class="{ 'pupils-list-card__actions--single': !canDecline(item) }"
          >
            <button
              v-if="canDecline(item)"
              class="ol-btn ol-btn--ghost ol-btn--sm"
              type="button"
              :disabled="busyId === item.id"
              @click="onDecline(item.id)"
            >
              {{ busyId === item.id ? 'Working…' : 'Decline' }}
            </button>
            <NuxtLink :to="item.path" class="ol-btn ol-btn--sm">
              Review
            </NuxtLink>
          </div>
        </article>
      </div>

      <div v-else class="pupils-list-table-wrap">
        <table class="pupils-list-table">
          <thead>
            <tr>
              <th scope="col">Name</th>
              <th scope="col">Summary</th>
              <th scope="col">Status</th>
              <th scope="col" class="pupils-list-col-open"><span class="sr-only">Open</span></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in filteredItems" :key="item.id" class="pupils-list-tr">
              <td>
                <NuxtLink :to="item.path" class="pupils-list-cell pupils-list-td-name">
                  <span class="pupils-list-avatar" aria-hidden="true">{{ initials(item.full_name) }}</span>
                  <span class="pupils-list-name-stack">
                    <span class="pupils-list-name">{{ item.full_name }}</span>
                    <span v-if="item.fit_hint" class="pupils-list-sub">{{ item.fit_hint }}</span>
                  </span>
                </NuxtLink>
              </td>
              <td>
                <NuxtLink :to="item.path" class="pupils-list-cell pupils-list-muted">
                  {{ item.summary || '—' }}
                </NuxtLink>
              </td>
              <td>
                <NuxtLink :to="item.path" class="pupils-list-cell">
                  <span class="ol-badge">{{ item.status_label }}</span>
                </NuxtLink>
              </td>
              <td>
                <NuxtLink :to="item.path" class="pupils-list-open" tabindex="-1" aria-hidden="true">
                  <OlIcon name="chevron-right" :size="16" />
                </NuxtLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { EnquiryListItem } from '~/composables/useEnquiries'

useHead({ title: 'Enquiries · Pupils · OwnLane' })

const LAYOUT_KEY = 'ownlane.enquiries.layout'
type LayoutMode = 'list' | 'cards'

const { list, decline } = useEnquiries()
const items = ref<EnquiryListItem[]>([])
const loading = ref(true)
const error = ref('')
const status = ref('new')
const query = ref('')
const busyId = ref<number | null>(null)
const layout = ref<LayoutMode>('cards')
const layoutOpen = ref(false)
const layoutRoot = ref<HTMLElement | null>(null)

const statusFilters = [
  { value: 'new', label: 'New' },
  { value: 'contacted', label: 'Contacted' },
  { value: 'accepted', label: 'Accepted' },
  { value: 'converted', label: 'Added as pupil' },
  { value: 'declined', label: 'Declined' },
  { value: 'all', label: 'All' },
]

const filteredItems = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return items.value
  return items.value.filter((item) => {
    const hay = `${item.full_name} ${item.summary} ${item.postcode}`.toLowerCase()
    return hay.includes(q)
  })
})

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (!parts.length) return '?'
  if (parts.length === 1) return parts[0]!.slice(0, 2).toUpperCase()
  return `${parts[0]!.charAt(0)}${parts[parts.length - 1]!.charAt(0)}`.toUpperCase()
}

function canDecline(item: EnquiryListItem): boolean {
  return ['new', 'contacted', 'accepted'].includes(item.status)
}

function badgeTone(item: EnquiryListItem): string {
  if (item.status === 'new') return 'ol-badge--success'
  if (item.status === 'declined') return 'ol-badge--neutral'
  if (item.status === 'converted') return ''
  return ''
}

function cardDetail(item: EnquiryListItem): string {
  if (item.fit_hint) return item.fit_hint
  return item.status_label
}

type CardMetaIcon = 'pin' | 'gearbox' | 'notes'
type CardMetaRow = { icon: CardMetaIcon; label: string; muted?: boolean }

function cardMeta(item: EnquiryListItem): CardMetaRow[] {
  const transmission = item.transmission
    ? (item.transmission === 'automatic'
        ? 'Automatic'
        : item.transmission === 'manual'
          ? 'Manual'
          : item.transmission)
    : null
  const gearbox = transmission ? `${transmission} lessons` : null

  return [
    {
      icon: 'pin',
      label: item.postcode || 'No area yet',
      muted: !item.postcode,
    },
    {
      icon: 'gearbox',
      label: gearbox || 'Gearbox not set',
      muted: !gearbox,
    },
    {
      icon: 'notes',
      label: item.summary || 'Not shared yet',
      muted: !item.summary,
    },
  ]
}

function setLayout(mode: LayoutMode) {
  layout.value = mode
  layoutOpen.value = false
  if (import.meta.client) {
    try {
      localStorage.setItem(LAYOUT_KEY, mode)
    } catch {
      /* ignore */
    }
  }
}

function onDocPointerDown(event: PointerEvent) {
  if (!layoutOpen.value || !layoutRoot.value) return
  if (event.target instanceof Node && !layoutRoot.value.contains(event.target)) {
    layoutOpen.value = false
  }
}

function onStatus(next: string) {
  if (status.value === next) return
  status.value = next
  void load()
}

async function onDecline(id: number) {
  busyId.value = id
  error.value = ''
  try {
    await decline(id)
    await load()
  } catch (e) {
    error.value = extractApiError(e, 'Could not decline this enquiry.')
  } finally {
    busyId.value = null
  }
}

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

onMounted(() => {
  if (import.meta.client) {
    try {
      const saved = localStorage.getItem(LAYOUT_KEY)
      if (saved === 'list' || saved === 'cards') layout.value = saved
    } catch {
      /* ignore */
    }
  }
  document.addEventListener('pointerdown', onDocPointerDown)
  void load()
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', onDocPointerDown)
})
</script>

<style scoped>
.enquiries__tools {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin-left: auto;
}

.enquiries__status {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.pupils-list-layout {
  margin-left: 0;
}

@media (max-width: 720px) {
  .enquiries__tools {
    margin-left: 0;
    width: 100%;
  }

  .pupils-list-layout {
    margin-left: auto;
  }
}
</style>
