<template>
  <div
    v-if="open"
    class="search-dialog"
    role="dialog"
    aria-modal="true"
    aria-label="Search OwnLane"
    @keydown.esc="close"
  >
    <button class="search-dialog__backdrop" type="button" aria-label="Close" @click="close" />
    <div class="search-dialog__panel">
      <label class="search-dialog__input-wrap">
        <span class="sr-only">Search OwnLane</span>
        <input
          ref="inputRef"
          v-model="query"
          class="search-dialog__input"
          type="search"
          placeholder="Search OwnLane"
          autocomplete="off"
          @keydown.down.prevent="moveHighlight(1)"
          @keydown.up.prevent="moveHighlight(-1)"
          @keydown.enter.prevent="openHighlighted"
        >
      </label>

      <p v-if="loading" class="ol-muted search-dialog__status">Searching…</p>
      <p v-else-if="error" class="ol-error search-dialog__status">{{ error }}</p>

      <template v-else-if="!query.trim()">
        <section v-if="recent?.quick_actions?.length" class="search-section">
          <p class="search-section__label">Quick actions</p>
          <ul class="search-list" role="listbox">
            <li
              v-for="(action, i) in recent.quick_actions"
              :key="action.id"
              class="search-item"
              :class="{ 'search-item--on': highlighted === i }"
              role="option"
              @mouseenter="highlighted = i"
              @click="go(action.path)"
            >
              {{ action.label }}
            </li>
          </ul>
        </section>
        <section v-if="recent?.pupils?.length" class="search-section">
          <p class="search-section__label">Recent</p>
          <ul class="search-list" role="listbox">
            <li
              v-for="(pupil, i) in recent.pupils"
              :key="pupil.id"
              class="search-item"
              :class="{ 'search-item--on': highlighted === i + (recent?.quick_actions?.length ?? 0) }"
              role="option"
              @mouseenter="highlighted = i + (recent?.quick_actions?.length ?? 0)"
              @click="go(pupil.path)"
            >
              <span class="search-item__title">{{ pupil.title }}</span>
              <span v-if="pupil.meta" class="search-item__meta">{{ pupil.meta }}</span>
            </li>
          </ul>
        </section>
      </template>

      <template v-else-if="result">
        <section
          v-for="group in result.groups.filter(g => g.items.length)"
          :key="group.type"
          class="search-section"
        >
          <p class="search-section__label">{{ group.label }}</p>
          <ul class="search-list" role="listbox">
            <li
              v-for="(item, idx) in group.items"
              :key="`${group.type}-${item.id}`"
              class="search-item"
              :class="{ 'search-item--on': flatIndex(group.type, idx) === highlighted }"
              role="option"
              @mouseenter="highlighted = flatIndex(group.type, idx)"
              @click="go(item.path)"
            >
              <span class="search-item__title">{{ item.title }}</span>
              <span v-if="item.meta" class="search-item__meta">{{ item.meta }}</span>
            </li>
          </ul>
        </section>
        <p
          v-if="result.groups.every(g => g.items.length === 0)"
          class="ol-muted search-dialog__status"
        >
          No results for “{{ query }}”.
        </p>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { SearchResult, RecentSearch } from '~/composables/useGlobalSearch'

const open = defineModel<boolean>('open', { default: false })

const { search, fetchRecent } = useGlobalSearch()
const router = useRouter()

const query = ref('')
const loading = ref(false)
const error = ref('')
const result = ref<SearchResult | null>(null)
const recent = ref<RecentSearch | null>(null)
const highlighted = ref(0)
const inputRef = ref<HTMLInputElement | null>(null)

let debounceTimer: ReturnType<typeof setTimeout> | null = null

const flatItems = computed(() => {
  if (!result.value) return []
  return result.value.groups.flatMap(g => g.items.map(item => ({ path: item.path })))
})

const emptyFlatItems = computed(() => {
  const actions = recent.value?.quick_actions ?? []
  const pupils = recent.value?.pupils ?? []
  return [...actions, ...pupils].map(a => ({ path: 'path' in a ? a.path : '' }))
})

function flatIndex(groupType: string, idx: number): number {
  if (!result.value) return 0
  let offset = 0
  for (const g of result.value.groups) {
    if (g.type === groupType) return offset + idx
    offset += g.items.length
  }
  return 0
}

function close() {
  open.value = false
}

function go(path: string) {
  close()
  void router.push(path)
}

function moveHighlight(delta: number) {
  const items = query.value.trim() ? flatItems.value : emptyFlatItems.value
  if (!items.length) return
  highlighted.value = (highlighted.value + delta + items.length) % items.length
}

function openHighlighted() {
  const items = query.value.trim() ? flatItems.value : emptyFlatItems.value
  const item = items[highlighted.value]
  if (item?.path) go(item.path)
}

async function runSearch() {
  const q = query.value.trim()
  if (q.length < 2) {
    result.value = null
    return
  }
  loading.value = true
  error.value = ''
  try {
    result.value = await search(q)
    highlighted.value = 0
  } catch (e) {
    error.value = extractApiError(e, 'Search failed.')
  } finally {
    loading.value = false
  }
}

watch(query, () => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => void runSearch(), 200)
})

watch(open, async (isOpen) => {
  if (!isOpen) {
    query.value = ''
    result.value = null
    error.value = ''
    return
  }
  try {
    recent.value = await fetchRecent()
  } catch {
    recent.value = { pupils: [], quick_actions: [] }
  }
  await nextTick()
  inputRef.value?.focus()
  highlighted.value = 0
})
</script>

<style scoped>
.search-dialog {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 12vh var(--spacing-16) var(--spacing-16);
}

.search-dialog__backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  border: 0;
  cursor: pointer;
}

.search-dialog__panel {
  position: relative;
  width: min(560px, 100%);
  max-height: 70vh;
  overflow: auto;
  background: var(--surface-elevated, #fff);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-panel);
  box-shadow: var(--shadow-lg, 0 12px 40px rgba(0, 0, 0, 0.12));
  padding: var(--spacing-12);
}

.search-dialog__input {
  width: 100%;
  font-size: var(--text-body-lg);
  padding: var(--spacing-12);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-control);
  margin-bottom: var(--spacing-12);
}

.search-section__label {
  font-size: var(--text-body-sm);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-muted);
  margin: 0 0 6px;
}

.search-list {
  list-style: none;
  margin: 0 0 var(--spacing-12);
  padding: 0;
}

.search-item {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px;
  border-radius: var(--radius-control);
  cursor: pointer;
}

.search-item--on,
.search-item:hover {
  background: var(--surface-wash);
}

.search-item__title {
  font-weight: 500;
}

.search-item__meta {
  font-size: var(--text-body-sm);
  color: var(--color-text-muted);
}

.search-dialog__status {
  padding: var(--spacing-8) var(--spacing-4);
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
