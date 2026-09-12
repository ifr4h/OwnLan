<template>
  <section class="pupils ol-page">
    <header class="pupils__header">
      <div class="pupils__title-block">
        <div class="pupils__title-row">
          <h1 class="ol-page-title">Pupils</h1>
          <span v-if="!loading" class="pupils__total" :aria-label="`${currentTotal} pupils`">{{ currentTotal }}</span>
        </div>
      </div>
      <div class="pupils__actions ol-actions">
        <NuxtLink to="/pupils/report" class="ol-btn ol-btn--ghost ol-btn--sm">
          Insights
          <OlIcon name="chevron-right" :size="14" />
        </NuxtLink>
        <NuxtLink to="/pupils/enquiries" class="ol-btn ol-btn--ghost ol-btn--sm">
          Enquiries
          <OlIcon name="chevron-right" :size="14" />
        </NuxtLink>
        <NuxtLink to="/pupils/import" class="ol-btn ol-btn--ghost ol-btn--sm">Import CSV</NuxtLink>
        <NuxtLink to="/pupils/new" class="ol-btn ol-btn--sm">
          <OlIcon name="add" :size="16" />
          Add pupil
        </NuxtLink>
      </div>
    </header>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>
    <p v-else-if="loading" class="ol-muted">Loading…</p>

    <div v-else class="pupils__board">
      <div class="pupils__toolbar">
        <label class="pupils__search">
          <OlIcon name="search" :size="16" class="pupils__search-icon" />
          <span class="sr-only">Search pupils</span>
          <input
            v-model="query"
            class="pupils__search-input"
            type="search"
            placeholder="Search"
            autocomplete="off"
          >
        </label>

        <div ref="statusRoot" class="pupils__pop">
          <button
            class="pupils__pill"
            type="button"
            :class="{ 'pupils__pill--active': statusOpen || listTab !== 'all' }"
            :aria-expanded="statusOpen"
            aria-haspopup="listbox"
            @click="toggleStatus"
          >
            <span>Status</span>
            <span class="pupils__pill-value">{{ currentStatusLabel }}</span>
            <OlIcon name="chevron-down" :size="14" />
          </button>
          <div
            v-if="statusOpen"
            class="pupils__panel pupils__panel--status"
            role="listbox"
            aria-label="Pupil status"
          >
            <button
              v-for="opt in statusOptions"
              :key="opt.value"
              class="pupils__option"
              type="button"
              role="option"
              :aria-selected="listTab === opt.value"
              @click="onStatusPick(opt.value)"
            >
              <span class="pupils__option-main">
                <span
                  class="pupils__status-icon"
                  :class="`pupils__status-icon--${opt.tone}`"
                  aria-hidden="true"
                >
                  <OlIcon :name="opt.icon" :size="12" />
                </span>
                <span>{{ opt.label }}</span>
              </span>
              <span class="pupils__option-meta">{{ opt.count }}</span>
            </button>
          </div>
        </div>

        <button
          class="pupils__pill"
          type="button"
          :class="{ 'pupils__pill--active': filtersOpen || filtersActive }"
          :aria-expanded="filtersOpen"
          aria-haspopup="dialog"
          @click="openFilters"
        >
          <span class="pupils__pill-icon" aria-hidden="true">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
              <path d="M4 6h16M7 12h10M10 18h4" />
            </svg>
          </span>
          <span>Filters</span>
          <span v-if="filtersSummary" class="pupils__pill-value">{{ filtersSummary }}</span>
          <span v-else-if="filtersActiveCount" class="pupils__pill-count">{{ filtersActiveCount }}</span>
        </button>

        <div ref="columnsRoot" class="pupils__pop pupils__pop--end">
          <button
            class="pupils__pill pupils__pill--columns"
            type="button"
            :class="{ 'pupils__pill--columns-open': columnsOpen }"
            :aria-expanded="columnsOpen"
            aria-haspopup="true"
            @click="toggleColumns"
          >
            <span class="pupils__pill-icon" aria-hidden="true">
              <OlIcon name="columns" :size="14" />
            </span>
            <span>Columns</span>
          </button>
          <div
            v-if="columnsOpen"
            class="pupils__panel pupils__panel--columns"
            role="menu"
            aria-label="Visible columns"
          >
            <p class="pupils__columns-hint">Drag to rearrange</p>
            <div
              v-for="(col, index) in columnOrder"
              :key="col"
              class="pupils__columns-item"
              :class="{ 'pupils__columns-item--dragging': dragColIndex === index }"
              draggable="true"
              @dragstart="onColumnDragStart(index, $event)"
              @dragover.prevent="onColumnDragOver(index, $event)"
              @drop.prevent="onColumnDrop(index)"
              @dragend="onColumnDragEnd"
            >
              <span class="pupils__columns-grip" aria-hidden="true">
                <OlIcon name="grip" :size="14" />
              </span>
              <label class="pupils__columns-label" @mousedown.stop>
                <input
                  type="checkbox"
                  :checked="isVisible(col) || col === 'availability'"
                  :disabled="col === 'availability'"
                  @change="toggleColumnVisibility(col, ($event.target as HTMLInputElement).checked)"
                >
                {{ columnLabel(col) }}
              </label>
            </div>
          </div>
        </div>
      </div>

      <div
        v-if="selectedCount > 0"
        class="pupils__bulk"
        role="region"
        aria-label="Bulk actions"
      >
        <p class="pupils__bulk-count">
          {{ selectedCount }} selected
        </p>
        <div ref="bulkRoot" class="pupils__pop">
          <button
            class="pupils__pill pupils__pill--bulk"
            type="button"
            :aria-expanded="bulkStatusOpen"
            aria-haspopup="listbox"
            :disabled="bulkBusy"
            @click="toggleBulkStatus"
          >
            Set status
            <OlIcon name="chevron-down" :size="14" />
          </button>
          <div
            v-if="bulkStatusOpen"
            class="pupils__panel pupils__panel--status"
            role="listbox"
            aria-label="Set status for selected"
          >
            <button
              v-for="opt in bulkStatusOptions"
              :key="opt.value"
              class="pupils__option"
              type="button"
              role="option"
              @click="bulkSetStatus(opt.value)"
            >
              <span class="pupils__option-main">
                <span
                  class="pupils__status-icon"
                  :class="`pupils__status-icon--${opt.tone}`"
                  aria-hidden="true"
                >
                  <OlIcon :name="opt.icon" :size="12" />
                </span>
                <span>{{ opt.label }}</span>
              </span>
            </button>
          </div>
        </div>
        <button
          class="pupils__bulk-clear"
          type="button"
          :disabled="bulkBusy"
          @click="clearSelection"
        >
          Clear
        </button>
      </div>

      <div v-if="listTab === 'waiting' && filteredPupils.length > 0" class="pupils__start-chips" role="group" aria-label="Start date">
        <button
          v-for="chip in startFilterChips"
          :key="chip.value"
          type="button"
          class="ol-chip"
          :class="{ 'ol-chip--on': startFilter === chip.value }"
          @click="startFilter = chip.value"
        >
          {{ chip.label }}
          <span class="pupils__chip-count">{{ chip.count }}</span>
        </button>
      </div>

      <div v-if="filteredPupils.length === 0" class="pupils__empty">
        <h2 class="ol-empty__title">{{ emptyTitle }}</h2>
        <p class="ol-empty__copy">{{ emptyCopy }}</p>
        <div v-if="showEmptyActions" class="ol-empty__actions">
          <NuxtLink to="/pupils/new" class="ol-btn">
            Add pupil
            <span aria-hidden="true">→</span>
          </NuxtLink>
          <NuxtLink to="/pupils/import" class="ol-btn ol-btn--ghost">Import CSV</NuxtLink>
        </div>
        <button
          v-else-if="filtersActive || query.trim() || listTab !== 'all'"
          class="ol-btn ol-btn--ghost ol-btn--sm"
          type="button"
          @click="clearFilters"
        >
          Clear filters
        </button>
      </div>

      <template v-else>
        <div class="pupils__table-wrap">
          <table class="pupils__table">
            <thead>
              <tr>
                <th scope="col" class="pupils__col-check">
                  <label class="pupils__check">
                    <span class="sr-only">Select all on this page</span>
                    <input
                      type="checkbox"
                      :checked="pageAllSelected"
                      :aria-checked="pageSomeSelected ? 'mixed' : pageAllSelected"
                      @change="toggleSelectPage(($event.target as HTMLInputElement).checked)"
                    >
                  </label>
                </th>
                <th scope="col">Name</th>
                <th
                  v-for="col in orderedVisibleColumns"
                  :key="`h-${col}`"
                  scope="col"
                  :class="{ 'pupils__col-wide': col === 'availability' || col === 'email' || col === 'action' }"
                >
                  {{ columnHeader(col) }}
                </th>
                <th scope="col" class="pupils__col-open"><span class="sr-only">Open</span></th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="pupil in pageItems"
                :key="pupil.id"
                class="pupils__tr"
                :class="{ 'pupils__tr--selected': isSelected(pupil.id) }"
              >
                <td class="pupils__td-check">
                  <label class="pupils__check" @click.stop>
                    <span class="sr-only">Select {{ pupil.full_name }}</span>
                    <input
                      type="checkbox"
                      :checked="isSelected(pupil.id)"
                      @change="toggleSelect(pupil.id, ($event.target as HTMLInputElement).checked)"
                    >
                  </label>
                </td>
                <td>
                  <NuxtLink :to="`/pupils/${pupil.id}`" class="pupils__cell-link pupils__td-name">
                    <span
                      v-if="listTab === 'waiting'"
                      class="pupils__start-tab"
                      :class="startTabClass(pupil)"
                      aria-hidden="true"
                    />
                    <span class="pupils__avatar" aria-hidden="true">{{ initials(pupil) }}</span>
                    <span class="pupils__name">{{ pupil.full_name }}</span>
                  </NuxtLink>
                </td>
                <td
                  v-for="col in orderedVisibleColumns"
                  :key="`${pupil.id}-${col}`"
                  :class="{ 'pupils__col-wide': col === 'availability' || col === 'email' || col === 'action' }"
                >
                  <NuxtLink
                    :to="`/pupils/${pupil.id}`"
                    class="pupils__cell-link"
                    :class="{
                      'pupils__muted': col === 'mobile' || col === 'email' || col === 'instructor' || col === 'test' || col === 'gear' || col === 'waiting' || col === 'lessons_completed' || col === 'area' || col === 'availability',
                      'pupils__action-cell': col === 'action',
                      'pupils__progress': col === 'progress',
                    }"
                  >
                    <template v-if="col === 'mobile'">{{ pupil.mobile || '—' }}</template>
                    <template v-else-if="col === 'email'">{{ pupil.email || '—' }}</template>
                    <template v-else-if="col === 'area'">{{ ukOutwardCode(pupil.default_pickup_address) || '—' }}</template>
                    <template v-else-if="col === 'availability'">
                      <span class="pupils__availability">{{ availabilityLabel(pupil) }}</span>
                    </template>
                    <template v-else-if="col === 'status'">
                      <span class="ol-badge pupils__status-badge" :class="statusFor(pupil).tone">
                        <span
                          class="pupils__status-icon pupils__status-icon--inline"
                          :class="`pupils__status-icon--${statusFor(pupil).iconTone}`"
                          aria-hidden="true"
                        >
                          <OlIcon :name="statusFor(pupil).icon" :size="11" />
                        </span>
                        {{ statusFor(pupil).label }}
                      </span>
                    </template>
                    <template v-else-if="col === 'action'">
                      <template v-if="actionsFor(pupil).length">
                        <span
                          v-for="item in actionsFor(pupil)"
                          :key="item.label"
                          class="ol-badge"
                          :class="item.tone"
                        >
                          {{ item.label }}
                        </span>
                      </template>
                      <span v-else class="pupils__none">—</span>
                    </template>
                    <template v-else-if="col === 'instructor'">{{ pupil.instructor_name || '—' }}</template>
                    <template v-else-if="col === 'lesson'">
                      <span
                        class="ol-badge"
                        :class="pupil.lesson_booked ? 'ol-badge--success' : 'ol-badge--neutral'"
                      >
                        {{ pupil.lesson_label || 'Not booked' }}
                      </span>
                    </template>
                    <template v-else-if="col === 'lessons_completed'">
                      {{ pupil.lessons_completed ?? 0 }}
                    </template>
                    <template v-else-if="col === 'progress'">
                      <span
                        class="pupils__progress-bar"
                        role="progressbar"
                        :aria-valuenow="pupil.progress_percent ?? 0"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        :aria-label="`${pupil.progress_percent ?? 0}% through syllabus`"
                      >
                        <span
                          class="pupils__progress-fill"
                          :style="{ width: `${Math.min(100, Math.max(0, pupil.progress_percent ?? 0))}%` }"
                        />
                      </span>
                      <span class="pupils__progress-label">{{ pupil.progress_percent ?? 0 }}%</span>
                    </template>
                    <template v-else-if="col === 'theory'">
                      <span class="ol-badge" :class="theoryTone(pupil)">
                        {{ pupil.theory_label || 'No test' }}
                      </span>
                    </template>
                    <template v-else-if="col === 'practical'">
                      <span class="ol-badge" :class="practicalTone(pupil)">
                        {{ pupil.practical_label || 'No test' }}
                      </span>
                    </template>
                    <template v-else-if="col === 'test'">{{ testLabel(pupil) }}</template>
                    <template v-else-if="col === 'gear'">{{ transmissionLabel(pupil) }}</template>
                    <template v-else-if="col === 'waiting'">{{ waitingSinceLabel(pupil) || '—' }}</template>
                  </NuxtLink>
                </td>
                <td class="pupils__td-open">
                  <NuxtLink
                    :to="`/pupils/${pupil.id}`"
                    class="pupils__cell-link pupils__open-link"
                    :aria-label="`Open ${pupil.full_name}`"
                  >
                    <OlIcon name="chevron-right" :size="16" class="pupils__chevron" />
                  </NuxtLink>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="pupils__footer">
          <p class="pupils__pager-meta">{{ rangeLabel }}</p>
          <div v-if="totalPages > 1" class="pupils__pager-actions">
            <button
              class="ol-btn ol-btn--ghost ol-btn--sm"
              type="button"
              :disabled="page <= 1"
              @click="page -= 1"
            >
              Previous
            </button>
            <button
              class="ol-btn ol-btn--ghost ol-btn--sm"
              type="button"
              :disabled="page >= totalPages"
              @click="page += 1"
            >
              Next
            </button>
          </div>
        </div>
      </template>
    </div>

    <Teleport to="body">
      <div
        v-if="filtersOpen"
        class="pupils-filter"
        role="dialog"
        aria-modal="true"
        aria-labelledby="pupils-filter-title"
      >
        <button
          class="pupils-filter__backdrop"
          type="button"
          aria-label="Close filters"
          @click="closeFilters"
        />
        <div class="pupils-filter__sheet">
          <header class="pupils-filter__top">
            <button
              class="pupils-filter__icon-btn"
              type="button"
              aria-label="Close"
              @click="closeFilters"
            >
              <OlIcon name="close" :size="18" />
            </button>
            <div class="pupils-filter__actions">
              <button
                class="pupils-filter__reset"
                type="button"
                :disabled="!draftFiltersDirty && !filtersActive"
                @click="resetDraftFilters"
              >
                Reset all filters
              </button>
              <button class="pupils-filter__apply" type="button" @click="applyFilters">
                Apply
              </button>
            </div>
          </header>

          <template v-if="filterView === 'root'">
            <h2 id="pupils-filter-title" class="pupils-filter__title">Filter by</h2>
            <div class="pupils-filter__list">
              <button
                v-if="listTab === 'active' || listTab === 'all'"
                class="pupils-filter__row"
                type="button"
                @click="filterView = 'action'"
              >
                <span class="pupils-filter__row-text">
                  <span class="pupils-filter__row-label">Action</span>
                  <span class="pupils-filter__row-value">{{ draftActionLabel }}</span>
                </span>
                <OlIcon name="chevron-right" :size="16" class="pupils-filter__chevron" />
              </button>
              <button
                class="pupils-filter__row"
                type="button"
                @click="filterView = 'area'"
              >
                <span class="pupils-filter__row-text">
                  <span class="pupils-filter__row-label">Area</span>
                  <span class="pupils-filter__row-value">{{ draftAreaLabel }}</span>
                </span>
                <OlIcon name="chevron-right" :size="16" class="pupils-filter__chevron" />
              </button>
              <button
                v-if="showInstructorFilter"
                class="pupils-filter__row"
                type="button"
                @click="filterView = 'instructor'"
              >
                <span class="pupils-filter__row-text">
                  <span class="pupils-filter__row-label">Instructor</span>
                  <span class="pupils-filter__row-value">{{ draftInstructorLabel }}</span>
                </span>
                <OlIcon name="chevron-right" :size="16" class="pupils-filter__chevron" />
              </button>
              <button
                v-if="showGearFilter"
                class="pupils-filter__row"
                type="button"
                @click="filterView = 'gear'"
              >
                <span class="pupils-filter__row-text">
                  <span class="pupils-filter__row-label">Gear</span>
                  <span class="pupils-filter__row-value">{{ draftGearLabel }}</span>
                </span>
                <OlIcon name="chevron-right" :size="16" class="pupils-filter__chevron" />
              </button>
              <button
                class="pupils-filter__row"
                type="button"
                @click="filterView = 'sort'"
              >
                <span class="pupils-filter__row-text">
                  <span class="pupils-filter__row-label">Sort</span>
                  <span class="pupils-filter__row-value">{{ draftSortLabel }}</span>
                </span>
                <OlIcon name="chevron-right" :size="16" class="pupils-filter__chevron" />
              </button>
            </div>
          </template>

          <template v-else-if="filterView === 'action'">
            <button
              class="pupils-filter__back"
              type="button"
              @click="filterView = 'root'"
            >
              <OlIcon name="chevron-left" :size="16" />
              <span>Action</span>
            </button>
            <div class="pupils-filter__options" role="listbox" aria-label="Action">
              <button
                v-for="opt in actionOptions"
                :key="opt.value"
                class="pupils-filter__option"
                type="button"
                role="option"
                :aria-selected="draftAction === opt.value"
                @click="pickFilterOption(opt.value)"
              >
                <span>{{ opt.label }}</span>
                <span v-if="draftAction === opt.value" class="pupils-filter__tick" aria-hidden="true">✓</span>
              </button>
            </div>
          </template>

          <template v-else-if="filterView === 'area'">
            <button
              class="pupils-filter__back"
              type="button"
              @click="filterView = 'root'"
            >
              <OlIcon name="chevron-left" :size="16" />
              <span>Area</span>
            </button>
            <div class="pupils-filter__options" role="listbox" aria-label="Area">
              <button
                v-for="opt in areaOptions"
                :key="opt.value"
                class="pupils-filter__option"
                type="button"
                role="option"
                :aria-selected="draftArea === opt.value"
                @click="pickAreaOption(opt.value)"
              >
                <span>{{ opt.label }}</span>
                <span class="pupils-filter__option-aside">
                  <span class="pupils-filter__option-meta">{{ opt.count }}</span>
                  <span v-if="draftArea === opt.value" class="pupils-filter__tick" aria-hidden="true">✓</span>
                </span>
              </button>
            </div>
          </template>

          <template v-else-if="filterView === 'instructor'">
            <button
              class="pupils-filter__back"
              type="button"
              @click="filterView = 'root'"
            >
              <OlIcon name="chevron-left" :size="16" />
              <span>Instructor</span>
            </button>
            <div class="pupils-filter__options" role="listbox" aria-label="Instructor">
              <button
                v-for="opt in instructorOptions"
                :key="String(opt.value)"
                class="pupils-filter__option"
                type="button"
                role="option"
                :aria-selected="draftInstructor === opt.value"
                @click="pickInstructorOption(opt.value)"
              >
                <span>{{ opt.label }}</span>
                <span class="pupils-filter__option-aside">
                  <span class="pupils-filter__option-meta">{{ opt.count }}</span>
                  <span v-if="draftInstructor === opt.value" class="pupils-filter__tick" aria-hidden="true">✓</span>
                </span>
              </button>
            </div>
          </template>

          <template v-else-if="filterView === 'gear'">
            <button
              class="pupils-filter__back"
              type="button"
              @click="filterView = 'root'"
            >
              <OlIcon name="chevron-left" :size="16" />
              <span>Gear</span>
            </button>
            <div class="pupils-filter__options" role="listbox" aria-label="Gear">
              <button
                v-for="opt in gearOptions"
                :key="opt.value"
                class="pupils-filter__option"
                type="button"
                role="option"
                :aria-selected="draftGear === opt.value"
                @click="pickGearOption(opt.value)"
              >
                <span>{{ opt.label }}</span>
                <span class="pupils-filter__option-aside">
                  <span class="pupils-filter__option-meta">{{ opt.count }}</span>
                  <span v-if="draftGear === opt.value" class="pupils-filter__tick" aria-hidden="true">✓</span>
                </span>
              </button>
            </div>
          </template>

          <template v-else>
            <button
              class="pupils-filter__back"
              type="button"
              @click="filterView = 'root'"
            >
              <OlIcon name="chevron-left" :size="16" />
              <span>Sort</span>
            </button>
            <div class="pupils-filter__options" role="listbox" aria-label="Sort">
              <button
                v-for="opt in sortOptions"
                :key="opt.value"
                class="pupils-filter__option"
                type="button"
                role="option"
                :aria-selected="draftSort === opt.value"
                @click="pickFilterOption(opt.value)"
              >
                <span>{{ opt.label }}</span>
                <span v-if="draftSort === opt.value" class="pupils-filter__tick" aria-hidden="true">✓</span>
              </button>
            </div>
          </template>
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script setup lang="ts">
import type {
  PupilListAttention,
  PupilListInstructor,
  PupilListItem,
  PupilListStatus,
  PupilStatus,
  PupilStatusCounts,
} from '~/composables/usePupils'
import { ukOutwardCode } from '~/utils/ukPostcode'

useHead({ title: 'Pupils · OwnLane' })

type AttentionFilter = 'all' | 'has_action' | 'needs_booking' | 'owed' | 'test_soon' | 'theory_booked'
type SortKey = 'name_asc' | 'name_desc' | 'test_soon' | 'waiting_longest' | 'ready_first'
type StartFilter = 'all' | 'ready' | 'this_month' | 'next_month' | 'later' | 'none'
/** 'all' = any area, 'none' = no postcode found, else outward code e.g. MK1 */
type AreaFilter = 'all' | 'none' | string
/** 'all' = any instructor, else instructor id */
type InstructorFilter = 'all' | number
type GearFilter = 'all' | 'manual' | 'automatic' | 'either' | 'none'
type FilterView = 'root' | 'action' | 'sort' | 'area' | 'instructor' | 'gear'
type ColumnKey =
  | 'mobile'
  | 'email'
  | 'status'
  | 'action'
  | 'instructor'
  | 'lesson'
  | 'lessons_completed'
  | 'progress'
  | 'theory'
  | 'practical'
  | 'test'
  | 'gear'
  | 'waiting'
  | 'area'
  | 'availability'

const PAGE_SIZE = 25
const COLUMNS_STORAGE_KEY = 'ownlane.pupils.columns.v2'

const DEFAULT_COLUMNS: ColumnKey[] = ['availability', 'mobile', 'status', 'action', 'test', 'gear']

const COLUMN_OPTIONS: { key: ColumnKey; label: string }[] = [
  { key: 'mobile', label: 'Mobile' },
  { key: 'email', label: 'Email' },
  { key: 'status', label: 'Status' },
  { key: 'action', label: 'Action' },
  { key: 'availability', label: 'Availability' },
  { key: 'area', label: 'Area' },
  { key: 'instructor', label: 'Instructor' },
  { key: 'lesson', label: 'Next lesson' },
  { key: 'lessons_completed', label: 'Lessons completed' },
  { key: 'progress', label: 'Progress' },
  { key: 'theory', label: 'Theory' },
  { key: 'practical', label: 'Practical' },
  { key: 'test', label: 'Test date' },
  { key: 'gear', label: 'Gear' },
  { key: 'waiting', label: 'Waiting time' },
]

const ALL_COLUMN_KEYS: ColumnKey[] = COLUMN_OPTIONS.map(c => c.key)

function columnLabel(key: ColumnKey): string {
  return COLUMN_OPTIONS.find(c => c.key === key)?.label || key
}

function columnHeader(key: ColumnKey): string {
  if (key === 'lessons_completed') return 'Lessons'
  return columnLabel(key)
}

type StatusTone = 'all' | 'active' | 'waiting' | 'paused' | 'passed' | 'inactive'
type StatusIcon = 'pupils' | 'check' | 'clock' | 'pause' | 'circle-check' | 'archive'

const STATUS_VISUAL: Record<PupilListStatus, {
  label: string
  icon: StatusIcon
  tone: StatusTone
  badge: string
}> = {
  all: { label: 'All', icon: 'pupils', tone: 'all', badge: 'ol-badge--neutral' },
  active: { label: 'Active', icon: 'check', tone: 'active', badge: 'ol-badge--success' },
  waiting: { label: 'On waitlist', icon: 'clock', tone: 'waiting', badge: 'ol-badge--warning' },
  paused: { label: 'Paused', icon: 'pause', tone: 'paused', badge: 'ol-badge--neutral' },
  passed: { label: 'Passed', icon: 'circle-check', tone: 'passed', badge: 'ol-badge--success' },
  inactive: { label: 'Inactive', icon: 'archive', tone: 'inactive', badge: 'ol-badge--neutral' },
}

function statusMeta(status: PupilListStatus) {
  return STATUS_VISUAL[status] || STATUS_VISUAL.active
}

const EMPTY_COUNTS: PupilStatusCounts = {
  active: 0,
  waiting: 0,
  paused: 0,
  passed: 0,
  inactive: 0,
}

const { listPupils, setPupilStatus } = usePupils()
const route = useRoute()
const pupils = ref<PupilListItem[]>([])
const attention = ref<PupilListAttention | null>(null)
const counts = ref<PupilStatusCounts>({ ...EMPTY_COUNTS })
const query = ref('')
const listTab = ref<PupilListStatus>(parseStatus(route.query.status))
const startFilter = ref<StartFilter>('all')
const attentionFilter = ref<AttentionFilter>('all')
const areaFilter = ref<AreaFilter>('all')
const instructorFilter = ref<InstructorFilter>('all')
const gearFilter = ref<GearFilter>('all')
const sort = ref<SortKey>('name_asc')
const page = ref(1)
const loading = ref(true)
const error = ref('')
const columnsOpen = ref(false)
const statusOpen = ref(false)
const filtersOpen = ref(false)
const bulkStatusOpen = ref(false)
const bulkBusy = ref(false)
const filterView = ref<FilterView>('root')
const columnsRoot = ref<HTMLElement | null>(null)
const statusRoot = ref<HTMLElement | null>(null)
const bulkRoot = ref<HTMLElement | null>(null)
const visibleColumns = ref<ColumnKey[]>([...DEFAULT_COLUMNS])
const columnOrder = ref<ColumnKey[]>([...ALL_COLUMN_KEYS])
const draftAction = ref<AttentionFilter>('all')
const draftArea = ref<AreaFilter>('all')
const draftInstructor = ref<InstructorFilter>('all')
const draftGear = ref<GearFilter>('all')
const draftSort = ref<SortKey>('name_asc')
const selectedIds = ref<number[]>([])
const multiInstructor = ref(false)
const instructors = ref<PupilListInstructor[]>([])
const offersBothTransmissions = ref(false)
const dragColIndex = ref<number | null>(null)

const orderedVisibleColumns = computed(() => {
  const cols = columnOrder.value.filter(key => visibleColumns.value.includes(key))
  if (cols.includes('availability')) return cols
  const insertAt = Math.min(1, cols.length)
  return [...cols.slice(0, insertAt), 'availability', ...cols.slice(insertAt)]
})

function parseStatus(value: unknown): PupilListStatus {
  const allowed: PupilListStatus[] = ['all', 'active', 'waiting', 'paused', 'passed', 'inactive']
  if (typeof value === 'string' && (allowed as string[]).includes(value)) {
    return value as PupilListStatus
  }
  return 'all'
}

const totalAll = computed(() =>
  counts.value.active
  + counts.value.waiting
  + counts.value.paused
  + counts.value.passed
  + counts.value.inactive,
)

const currentTotal = computed(() =>
  listTab.value === 'all' ? totalAll.value : (counts.value[listTab.value] ?? pupils.value.length),
)

const statusOptions = computed(() => ([
  { value: 'all' as PupilListStatus, ...statusMeta('all'), count: totalAll.value },
  { value: 'active' as PupilListStatus, ...statusMeta('active'), count: counts.value.active },
  { value: 'waiting' as PupilListStatus, ...statusMeta('waiting'), count: counts.value.waiting },
  { value: 'paused' as PupilListStatus, ...statusMeta('paused'), count: counts.value.paused },
  { value: 'passed' as PupilListStatus, ...statusMeta('passed'), count: counts.value.passed },
  { value: 'inactive' as PupilListStatus, ...statusMeta('inactive'), count: counts.value.inactive },
]))

const currentStatusLabel = computed(() => statusMeta(listTab.value).label)

const startFilterChips = computed(() => {
  const all = pupils.value
  const count = (filter: StartFilter) => all.filter(p => matchesStartFilter(p, filter)).length
  return [
    { value: 'all' as StartFilter, label: 'All', count: all.length },
    { value: 'ready' as StartFilter, label: 'Ready', count: count('ready') },
    { value: 'this_month' as StartFilter, label: 'This month', count: count('this_month') },
    { value: 'next_month' as StartFilter, label: 'Next month', count: count('next_month') },
    { value: 'later' as StartFilter, label: 'Later', count: count('later') },
    { value: 'none' as StartFilter, label: 'No date', count: count('none') },
  ].filter(c => c.value === 'all' || c.count > 0)
})

const actionOptions = computed(() => {
  const opts: { value: AttentionFilter; label: string }[] = [
    { value: 'all', label: 'Any' },
    { value: 'has_action', label: 'Needs action' },
  ]
  if ((attention.value?.no_future_booking_count ?? 0) > 0) {
    opts.push({
      value: 'needs_booking',
      label: `Needs booking (${attention.value?.no_future_booking_count})`,
    })
  }
  if ((attention.value?.outstanding_pence ?? 0) > 0) {
    opts.push({ value: 'owed', label: 'Money owed' })
  }
  if ((attention.value?.tests_soon_count ?? 0) > 0) {
    opts.push({
      value: 'test_soon',
      label: `Test soon (${attention.value?.tests_soon_count})`,
    })
  }
  opts.push({ value: 'theory_booked', label: 'Theory booked' })
  return opts
})

const sortOptions = computed(() => {
  if (listTab.value === 'waiting') {
    return [
      { value: 'ready_first' as SortKey, label: 'Ready soonest' },
      { value: 'waiting_longest' as SortKey, label: 'Longest waiting' },
      { value: 'name_asc' as SortKey, label: 'Name A–Z' },
      { value: 'name_desc' as SortKey, label: 'Name Z–A' },
    ]
  }
  return [
    { value: 'name_asc' as SortKey, label: 'Name A–Z' },
    { value: 'name_desc' as SortKey, label: 'Name Z–A' },
    { value: 'test_soon' as SortKey, label: 'Test date' },
  ]
})

const areaOptions = computed(() => {
  const counts = new Map<string, number>()
  let none = 0
  for (const pupil of pupils.value) {
    const area = ukOutwardCode(pupil.default_pickup_address)
    if (!area) {
      none += 1
      continue
    }
    counts.set(area, (counts.get(area) || 0) + 1)
  }
  const opts: { value: AreaFilter; label: string; count: number }[] = [
    { value: 'all', label: 'Any', count: pupils.value.length },
  ]
  const areas = [...counts.entries()].sort((a, b) => a[0].localeCompare(b[0], 'en-GB'))
  for (const [area, count] of areas) {
    opts.push({ value: area, label: area, count })
  }
  if (none > 0) {
    opts.push({ value: 'none', label: 'No postcode', count: none })
  }
  return opts
})

/** School / Business: show when org has 2+ instructors (plan billing comes later). */
const showInstructorFilter = computed(() => multiInstructor.value && instructors.value.length > 1)

/** Only useful when the org teaches both manual and automatic. */
const showGearFilter = computed(() => offersBothTransmissions.value)

const instructorOptions = computed(() => {
  const counts = new Map<number, number>()
  for (const pupil of pupils.value) {
    const id = pupil.instructor_id
    if (id == null || id <= 0) continue
    counts.set(id, (counts.get(id) || 0) + 1)
  }
  const opts: { value: InstructorFilter; label: string; count: number }[] = [
    { value: 'all', label: 'Any', count: pupils.value.length },
  ]
  for (const instructor of instructors.value) {
    opts.push({
      value: instructor.id,
      label: instructor.display_name,
      count: counts.get(instructor.id) || 0,
    })
  }
  return opts
})

const gearOptions = computed(() => {
  let manual = 0
  let automatic = 0
  let either = 0
  let none = 0
  for (const pupil of pupils.value) {
    if (pupil.transmission === 'manual') manual += 1
    else if (pupil.transmission === 'automatic') automatic += 1
    else if (pupil.transmission === 'either') either += 1
    else none += 1
  }
  const opts: { value: GearFilter; label: string; count: number }[] = [
    { value: 'all', label: 'Any', count: pupils.value.length },
    { value: 'manual', label: 'Manual', count: manual },
    { value: 'automatic', label: 'Automatic', count: automatic },
  ]
  if (either > 0) opts.push({ value: 'either', label: 'Either', count: either })
  if (none > 0) opts.push({ value: 'none', label: 'Not set', count: none })
  return opts
})

const defaultSort = computed<SortKey>(() =>
  listTab.value === 'waiting' ? 'ready_first' : 'name_asc',
)

const actionFilterEnabled = computed(() =>
  listTab.value === 'active' || listTab.value === 'all',
)

/** Filters button only — status is its own control. */
const filtersActive = computed(() =>
  attentionFilter.value !== 'all'
  || areaFilter.value !== 'all'
  || (showInstructorFilter.value && instructorFilter.value !== 'all')
  || (showGearFilter.value && gearFilter.value !== 'all')
  || sort.value !== defaultSort.value,
)

const filtersActiveCount = computed(() => {
  let n = 0
  if (actionFilterEnabled.value && attentionFilter.value !== 'all') n += 1
  if (areaFilter.value !== 'all') n += 1
  if (showInstructorFilter.value && instructorFilter.value !== 'all') n += 1
  if (showGearFilter.value && gearFilter.value !== 'all') n += 1
  if (sort.value !== defaultSort.value) n += 1
  return n
})

const filtersSummary = computed(() => {
  if (filtersActiveCount.value !== 1) return ''
  if (actionFilterEnabled.value && attentionFilter.value !== 'all') {
    const opt = actionOptions.value.find(o => o.value === attentionFilter.value)
    return opt?.label.replace(/\s*\(\d+\)$/, '') || ''
  }
  if (areaFilter.value !== 'all') {
    if (areaFilter.value === 'none') return 'No postcode'
    return areaFilter.value
  }
  if (showInstructorFilter.value && instructorFilter.value !== 'all') {
    return instructors.value.find(i => i.id === instructorFilter.value)?.display_name || ''
  }
  if (showGearFilter.value && gearFilter.value !== 'all') {
    return gearOptions.value.find(o => o.value === gearFilter.value)?.label || ''
  }
  if (sort.value !== defaultSort.value) {
    return sortOptions.value.find(o => o.value === sort.value)?.label || ''
  }
  return ''
})

const draftActionLabel = computed(() => {
  const opt = actionOptions.value.find(o => o.value === draftAction.value)
  return opt?.label.replace(/\s*\(\d+\)$/, '') || 'Any'
})

const draftAreaLabel = computed(() => {
  if (draftArea.value === 'all') return 'Any'
  if (draftArea.value === 'none') return 'No postcode'
  return draftArea.value
})

const draftInstructorLabel = computed(() => {
  if (draftInstructor.value === 'all') return 'Any'
  return instructors.value.find(i => i.id === draftInstructor.value)?.display_name || 'Any'
})

const draftGearLabel = computed(() =>
  gearOptions.value.find(o => o.value === draftGear.value)?.label || 'Any',
)

const draftSortLabel = computed(() =>
  sortOptions.value.find(o => o.value === draftSort.value)?.label || 'Name A–Z',
)

const draftFiltersDirty = computed(() =>
  draftAction.value !== 'all'
  || draftArea.value !== 'all'
  || draftInstructor.value !== 'all'
  || draftGear.value !== 'all'
  || draftSort.value !== defaultSort.value,
)

const filteredPupils = computed(() => {
  let items = [...pupils.value]
  const q = query.value.trim().toLowerCase()
  if (q) {
    const compact = q.replace(/\s+/g, '')
    items = items.filter((p) => {
      const name = p.full_name.toLowerCase()
      const mobile = (p.mobile || '').replace(/\s+/g, '')
      const email = (p.email || '').toLowerCase()
      return name.includes(q) || mobile.includes(compact) || email.includes(q)
    })
  }

  if (actionFilterEnabled.value && attentionFilter.value !== 'all') {
    items = items.filter((p) => {
      if (attentionFilter.value === 'has_action') return actionsFor(p).length > 0
      if (attentionFilter.value === 'needs_booking') return Boolean(p.needs_booking)
      if (attentionFilter.value === 'owed') return (p.outstanding_pence ?? 0) > 0
      if (attentionFilter.value === 'test_soon') return Boolean(p.test_soon)
      if (attentionFilter.value === 'theory_booked') return p.theory_status === 'booked'
      return true
    })
  }

  if (areaFilter.value !== 'all') {
    items = items.filter((p) => {
      const area = ukOutwardCode(p.default_pickup_address)
      if (areaFilter.value === 'none') return !area
      return area === areaFilter.value
    })
  }

  if (showInstructorFilter.value && instructorFilter.value !== 'all') {
    items = items.filter(p => p.instructor_id === instructorFilter.value)
  }

  if (showGearFilter.value && gearFilter.value !== 'all') {
    items = items.filter((p) => {
      if (gearFilter.value === 'none') return !p.transmission
      return p.transmission === gearFilter.value
    })
  }

  if (listTab.value === 'waiting' && startFilter.value !== 'all') {
    items = items.filter(p => matchesStartFilter(p, startFilter.value))
  }

  items.sort((a, b) => comparePupils(a, b, sort.value))
  return items
})

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredPupils.value.length / PAGE_SIZE)),
)

const pageItems = computed(() => {
  const start = (page.value - 1) * PAGE_SIZE
  return filteredPupils.value.slice(start, start + PAGE_SIZE)
})

const bulkStatusOptions = computed(() =>
  statusOptions.value.filter((o): o is typeof o & { value: PupilStatus } => o.value !== 'all'),
)

const selectedCount = computed(() => selectedIds.value.length)

const pageAllSelected = computed(() =>
  pageItems.value.length > 0
  && pageItems.value.every(p => selectedIds.value.includes(p.id)),
)

const pageSomeSelected = computed(() => {
  if (pageAllSelected.value) return false
  return pageItems.value.some(p => selectedIds.value.includes(p.id))
})

const rangeLabel = computed(() => {
  const total = filteredPupils.value.length
  if (total === 0) return '0 pupils'
  const start = (page.value - 1) * PAGE_SIZE + 1
  const end = Math.min(page.value * PAGE_SIZE, total)
  if (total <= PAGE_SIZE) {
    return total === 1 ? '1 pupil' : `${total} pupils`
  }
  return `${start}–${end} of ${total}`
})

const emptyTitle = computed(() => {
  if (query.value.trim()) return 'No matches'
  if (listTab.value === 'all') return 'No pupils yet'
  if (listTab.value === 'waiting') return 'Nobody on the waitlist'
  if (listTab.value === 'paused') return 'No paused pupils'
  if (listTab.value === 'passed') return 'No passed pupils yet'
  if (listTab.value === 'inactive') return 'No inactive pupils'
  if (areaFilter.value !== 'all') return 'Nobody in that area'
  if (showInstructorFilter.value && instructorFilter.value !== 'all') return 'Nobody with that instructor'
  if (showGearFilter.value && gearFilter.value !== 'all') return 'Nobody with that gearbox'
  if (attentionFilter.value !== 'all') return 'Nobody with that action'
  return 'No pupils yet'
})

const emptyCopy = computed(() => {
  if (query.value.trim()) return 'Try a different name or number.'
  if (listTab.value === 'all') {
    return 'Add your first pupil, or import a CSV if you’re moving from another system.'
  }
  if (listTab.value === 'waiting') {
    return 'Pupils on the waitlist show up here until you’ve got space for them.'
  }
  if (listTab.value === 'paused') {
    return 'Pause a pupil when they’re taking a break from lessons.'
  }
  if (listTab.value === 'passed') {
    return 'Mark a pupil as passed when they’ve got their licence.'
  }
  if (listTab.value === 'inactive') {
    return 'Archived pupils show up here.'
  }
  if (areaFilter.value !== 'all') {
    return 'Try a different area, or clear filters to see everyone.'
  }
  if (showInstructorFilter.value && instructorFilter.value !== 'all') {
    return 'Try a different instructor, or clear filters to see everyone.'
  }
  if (showGearFilter.value && gearFilter.value !== 'all') {
    return 'Try a different gear filter, or clear filters to see everyone.'
  }
  if (attentionFilter.value !== 'all') {
    return 'Try a different action filter, or clear filters to see everyone.'
  }
  return 'Add your first pupil, or import a CSV if you’re moving from another system.'
})

const showEmptyActions = computed(() =>
  (listTab.value === 'active' || listTab.value === 'all')
  && !query.value.trim()
  && attentionFilter.value === 'all'
  && areaFilter.value === 'all'
  && instructorFilter.value === 'all'
  && gearFilter.value === 'all'
  && pupils.value.length === 0,
)

watch(filteredPupils, () => {
  if (page.value > totalPages.value) page.value = totalPages.value
})

watch([listTab, attentionFilter, areaFilter, instructorFilter, gearFilter, query], () => {
  clearSelection()
})

function isSelected(id: number): boolean {
  return selectedIds.value.includes(id)
}

function toggleSelect(id: number, checked: boolean) {
  if (checked) {
    if (!selectedIds.value.includes(id)) selectedIds.value = [...selectedIds.value, id]
  } else {
    selectedIds.value = selectedIds.value.filter(x => x !== id)
  }
}

function toggleSelectPage(checked: boolean) {
  const ids = pageItems.value.map(p => p.id)
  if (checked) {
    const set = new Set(selectedIds.value)
    ids.forEach(id => set.add(id))
    selectedIds.value = [...set]
  } else {
    const drop = new Set(ids)
    selectedIds.value = selectedIds.value.filter(id => !drop.has(id))
  }
}

function clearSelection() {
  selectedIds.value = []
  bulkStatusOpen.value = false
}

function toggleBulkStatus() {
  statusOpen.value = false
  columnsOpen.value = false
  bulkStatusOpen.value = !bulkStatusOpen.value
}

async function bulkSetStatus(status: PupilStatus) {
  if (!selectedIds.value.length || bulkBusy.value) return
  bulkStatusOpen.value = false
  bulkBusy.value = true
  error.value = ''
  try {
    const results = await Promise.allSettled(
      selectedIds.value.map(id => setPupilStatus(id, status)),
    )
    const failed = results.filter(r => r.status === 'rejected').length
    clearSelection()
    await load()
    if (failed) {
      error.value = `Couldn’t update ${failed} pupil${failed === 1 ? '' : 's'}.`
    }
  } catch {
    error.value = 'Couldn’t update selected pupils.'
  } finally {
    bulkBusy.value = false
  }
}

function isVisible(key: ColumnKey): boolean {
  return visibleColumns.value.includes(key)
}

function toggleColumnVisibility(key: ColumnKey, checked: boolean) {
  if (key === 'availability' && !checked) return
  if (checked) {
    if (!visibleColumns.value.includes(key)) {
      visibleColumns.value = [...visibleColumns.value, key]
    }
  } else {
    visibleColumns.value = visibleColumns.value.filter(k => k !== key)
    if (visibleColumns.value.length === 0) {
      visibleColumns.value = ['availability', 'status', 'action']
    }
  }
  persistColumns()
}

function onColumnDragStart(index: number, event: DragEvent) {
  dragColIndex.value = index
  event.dataTransfer?.setData('text/plain', String(index))
  if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
}

function onColumnDragOver(index: number, event: DragEvent) {
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move'
  if (dragColIndex.value === null || dragColIndex.value === index) return
  const next = [...columnOrder.value]
  const [moved] = next.splice(dragColIndex.value, 1)
  next.splice(index, 0, moved)
  columnOrder.value = next
  dragColIndex.value = index
}

function onColumnDrop(index: number) {
  dragColIndex.value = null
  persistColumns()
}

function onColumnDragEnd() {
  dragColIndex.value = null
  persistColumns()
}

function persistColumns() {
  if (!import.meta.client) return
  if (visibleColumns.value.length === 0) {
    visibleColumns.value = ['status', 'action']
  }
  localStorage.setItem(COLUMNS_STORAGE_KEY, JSON.stringify({
    order: columnOrder.value,
    visible: visibleColumns.value,
  }))
}

function loadColumns() {
  if (!import.meta.client) return
  const allowed = new Set(ALL_COLUMN_KEYS)
  try {
    const raw = localStorage.getItem(COLUMNS_STORAGE_KEY)
    if (raw) {
      const parsed = JSON.parse(raw) as unknown
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        const data = parsed as { order?: unknown; visible?: unknown }
        if (Array.isArray(data.order)) {
          const order = data.order.filter((k): k is ColumnKey => typeof k === 'string' && allowed.has(k as ColumnKey))
          for (const key of ALL_COLUMN_KEYS) {
            if (!order.includes(key)) order.push(key)
          }
          if (order.length) columnOrder.value = order
        }
        if (Array.isArray(data.visible)) {
          const visible = data.visible.filter((k): k is ColumnKey => typeof k === 'string' && allowed.has(k as ColumnKey))
          if (visible.length) visibleColumns.value = visible
        }
        return
      }
    }

    // Migrate v1 (plain visible array).
    const legacy = localStorage.getItem('ownlane.pupils.columns')
    if (!legacy) return
    const parsed = JSON.parse(legacy) as unknown
    if (!Array.isArray(parsed)) return
    const visible = parsed.filter((k): k is ColumnKey => typeof k === 'string' && allowed.has(k as ColumnKey))
    if (visible.length) {
      visibleColumns.value = visible
      persistColumns()
    }
  } catch {
    // ignore corrupt storage
  }
}

function onDocumentClick(event: MouseEvent) {
  const target = event.target as Node
  if (statusOpen.value && statusRoot.value && !statusRoot.value.contains(target)) {
    statusOpen.value = false
  }
  if (columnsOpen.value && columnsRoot.value && !columnsRoot.value.contains(target)) {
    columnsOpen.value = false
  }
  if (bulkStatusOpen.value && bulkRoot.value && !bulkRoot.value.contains(target)) {
    bulkStatusOpen.value = false
  }
}

function onDocumentKeydown(event: KeyboardEvent) {
  if (event.key !== 'Escape') return
  if (filtersOpen.value) {
    if (filterView.value !== 'root') {
      filterView.value = 'root'
      return
    }
    closeFilters()
    return
  }
  statusOpen.value = false
  columnsOpen.value = false
  bulkStatusOpen.value = false
}

function toggleStatus() {
  columnsOpen.value = false
  bulkStatusOpen.value = false
  statusOpen.value = !statusOpen.value
}

function toggleColumns() {
  statusOpen.value = false
  bulkStatusOpen.value = false
  columnsOpen.value = !columnsOpen.value
}

function onStatusPick(status: PupilListStatus) {
  statusOpen.value = false
  selectStatus(status)
}

function openFilters() {
  statusOpen.value = false
  columnsOpen.value = false
  bulkStatusOpen.value = false
  draftAction.value = attentionFilter.value
  draftArea.value = areaFilter.value
  draftInstructor.value = instructorFilter.value
  draftGear.value = gearFilter.value
  draftSort.value = sort.value
  filterView.value = 'root'
  filtersOpen.value = true
  if (import.meta.client) document.body.style.overflow = 'hidden'
}

function closeFilters() {
  filtersOpen.value = false
  filterView.value = 'root'
  if (import.meta.client) document.body.style.overflow = ''
}

function resetDraftFilters() {
  draftAction.value = 'all'
  draftArea.value = 'all'
  draftInstructor.value = 'all'
  draftGear.value = 'all'
  draftSort.value = defaultSort.value
}

function applyFilters() {
  attentionFilter.value = actionFilterEnabled.value ? draftAction.value : 'all'
  areaFilter.value = draftArea.value
  instructorFilter.value = showInstructorFilter.value ? draftInstructor.value : 'all'
  gearFilter.value = showGearFilter.value ? draftGear.value : 'all'
  sort.value = draftSort.value
  page.value = 1
  closeFilters()
}

function pickFilterOption(value: AttentionFilter | SortKey) {
  if (filterView.value === 'action') {
    draftAction.value = value as AttentionFilter
  } else {
    draftSort.value = value as SortKey
  }
  filterView.value = 'root'
}

function pickAreaOption(value: AreaFilter) {
  draftArea.value = value
  filterView.value = 'root'
}

function pickInstructorOption(value: InstructorFilter) {
  draftInstructor.value = value
  filterView.value = 'root'
}

function pickGearOption(value: GearFilter) {
  draftGear.value = value
  filterView.value = 'root'
}

function selectStatus(status: PupilListStatus) {
  if (listTab.value === status) return
  listTab.value = status
  attentionFilter.value = 'all'
  startFilter.value = 'all'
  page.value = 1
  sort.value = status === 'waiting' ? 'ready_first' : 'name_asc'
  void navigateTo({
    path: '/pupils',
    query: status === 'all' ? {} : { status },
  }, { replace: true })
  void load()
}

function ensureAvailabilityColumnVisible() {
  if (visibleColumns.value.includes('availability')) return
  visibleColumns.value = ['availability', ...visibleColumns.value]
  persistColumns()
}

function resetFilters() {
  attentionFilter.value = 'all'
  areaFilter.value = 'all'
  instructorFilter.value = 'all'
  gearFilter.value = 'all'
  sort.value = defaultSort.value
  page.value = 1
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const result = await listPupils('', listTab.value)
    pupils.value = result.items
    attention.value = result.attention ?? null
    counts.value = result.counts ?? { ...EMPTY_COUNTS }
    multiInstructor.value = Boolean(result.multi_instructor)
    instructors.value = result.instructors ?? []
    offersBothTransmissions.value = Boolean(result.offers_both_transmissions)
    if (!multiInstructor.value) instructorFilter.value = 'all'
    if (!offersBothTransmissions.value) gearFilter.value = 'all'
  } catch (e) {
    error.value = extractApiError(e, 'Could not load pupils.')
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  query.value = ''
  resetFilters()
  if (listTab.value !== 'all') {
    selectStatus('all')
  } else {
    void load()
  }
}

function initials(pupil: PupilListItem): string {
  const first = (pupil.first_name || '').trim().charAt(0)
  const last = (pupil.last_name || '').trim().charAt(0)
  return `${first}${last}`.toUpperCase() || '?'
}

function transmissionLabel(pupil: PupilListItem): string {
  if (pupil.transmission === 'manual') return 'Manual'
  if (pupil.transmission === 'automatic') return 'Auto'
  return '—'
}

function formatOwed(pence: number): string {
  return `£${(pence / 100).toFixed(2)} owed`
}

/** Lifecycle / stage — not attention. */
function statusFor(pupil: PupilListItem): {
  label: string
  tone: string
  icon: StatusIcon
  iconTone: StatusTone
} {
  const status = (pupil.status
    || (pupil.archived_at ? 'inactive' : undefined)
    || pupil.lifecycle
    || 'active') as PupilStatus

  const meta = statusMeta(
    (['active', 'waiting', 'paused', 'passed', 'inactive'] as PupilStatus[]).includes(status)
      ? status
      : 'active',
  )
  return {
    label: pupil.status_label || meta.label,
    tone: meta.badge,
    icon: meta.icon,
    iconTone: meta.tone,
  }
}

/** Things that need doing for this pupil. */
function actionsFor(pupil: PupilListItem): { label: string; tone: string }[] {
  const items: { label: string; tone: string }[] = []
  if ((pupil.outstanding_pence ?? 0) > 0) {
    items.push({ label: formatOwed(pupil.outstanding_pence ?? 0), tone: 'ol-badge--danger' })
  }
  if (pupil.needs_booking) {
    items.push({ label: 'Needs booking', tone: 'ol-badge--warning' })
  }
  if (pupil.test_soon) {
    items.push({ label: 'Test soon', tone: 'ol-badge--neutral' })
  }
  if (pupil.theory_status === 'booked') {
    items.push({ label: 'Theory booked', tone: 'ol-badge--neutral' })
  }
  if (listTab.value === 'waiting' && pupil.gap_matches?.summary) {
    items.push({ label: pupil.gap_matches.summary, tone: 'ol-badge--warning' })
  }
  return items
}

function testLabel(pupil: PupilListItem): string {
  if (pupil.test_date) return formatShortDate(pupil.test_date)
  return '—'
}

function theoryTone(pupil: PupilListItem): string {
  const label = pupil.theory_label || 'No test'
  if (label === 'Passed') return 'ol-badge--success'
  if (label === 'Booked') return 'ol-badge--neutral'
  return 'ol-badge--neutral'
}

function practicalTone(pupil: PupilListItem): string {
  const label = pupil.practical_label || 'No test'
  if (label === 'Passed') return 'ol-badge--success'
  if (label === 'No test') return 'ol-badge--neutral'
  return 'ol-badge--neutral'
}

function waitingSinceLabel(pupil: PupilListItem): string | null {
  if (!pupil.waiting_list_joined_at) return null
  const joined = new Date(pupil.waiting_list_joined_at.includes('T')
    ? pupil.waiting_list_joined_at
    : `${pupil.waiting_list_joined_at.replace(' ', 'T')}Z`)
  if (Number.isNaN(joined.getTime())) return null
  const days = Math.max(0, Math.floor((Date.now() - joined.getTime()) / 86_400_000))
  if (days === 0) return 'Joined today'
  if (days === 1) return '1 day'
  return `${days} days`
}

function availabilityLabel(pupil: PupilListItem): string {
  const bits = [
    pupil.available_from_label || null,
    pupil.availability_summary || null,
  ].filter(Boolean)
  if (!bits.length) return 'Not set'
  return bits.join(' · ')
}

function matchesStartFilter(pupil: PupilListItem, filter: StartFilter): boolean {
  if (filter === 'all') return true
  if (filter === 'none') return !pupil.available_from
  if (filter === 'ready') return Boolean(pupil.available_ready)
  if (!pupil.available_from) return false
  const now = new Date()
  const thisMonth = now.getMonth() + 1
  const nextMonth = thisMonth === 12 ? 1 : thisMonth + 1
  const month = pupil.available_from_month ?? Number(pupil.available_from.slice(5, 7))
  if (filter === 'this_month') return !pupil.available_ready && month === thisMonth
  if (filter === 'next_month') return month === nextMonth
  if (filter === 'later') {
    return !pupil.available_ready && month !== thisMonth && month !== nextMonth
  }
  return true
}

function startTabClass(pupil: PupilListItem): string {
  if (pupil.available_ready) return 'pupils__start-tab--ready'
  if (!pupil.available_from_month) return 'pupils__start-tab--none'
  return `pupils__start-tab--m${pupil.available_from_month}`
}

function formatShortDate(isoDate: string): string {
  const d = new Date(`${isoDate}T12:00:00`)
  if (Number.isNaN(d.getTime())) return isoDate
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function comparePupils(a: PupilListItem, b: PupilListItem, key: SortKey): number {
  if (key === 'name_desc') {
    return b.full_name.localeCompare(a.full_name, 'en-GB')
  }
  if (key === 'test_soon') {
    const at = a.test_date || '9999-99-99'
    const bt = b.test_date || '9999-99-99'
    if (at !== bt) return at.localeCompare(bt)
    return a.full_name.localeCompare(b.full_name, 'en-GB')
  }
  if (key === 'ready_first') {
    const ar = a.available_ready ? 0 : 1
    const br = b.available_ready ? 0 : 1
    if (ar !== br) return ar - br
    const ad = a.available_from || '9999-99-99'
    const bd = b.available_from || '9999-99-99'
    if (ad !== bd) return ad.localeCompare(bd)
    const aw = a.waiting_list_joined_at || ''
    const bw = b.waiting_list_joined_at || ''
    if (aw !== bw) return aw.localeCompare(bw)
    return a.full_name.localeCompare(b.full_name, 'en-GB')
  }
  if (key === 'waiting_longest') {
    const aw = a.waiting_list_joined_at || ''
    const bw = b.waiting_list_joined_at || ''
    if (aw !== bw) return aw.localeCompare(bw)
    return a.full_name.localeCompare(b.full_name, 'en-GB')
  }
  return a.full_name.localeCompare(b.full_name, 'en-GB')
}

watch(query, () => {
  page.value = 1
})

onMounted(() => {
  loadColumns()
  ensureAvailabilityColumnVisible()
  document.addEventListener('click', onDocumentClick)
  document.addEventListener('keydown', onDocumentKeydown)
  void load()
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
  document.removeEventListener('keydown', onDocumentKeydown)
  document.body.style.overflow = ''
})
</script>

<style scoped>
.pupils {
  gap: var(--spacing-20);
}

.pupils__header {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--spacing-16);
}

.pupils__title-block {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
}

.pupils__title-row {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pupils__total {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 28px;
  padding: 0 9px;
  border-radius: 999px;
  background: var(--surface-wash);
  color: var(--color-muted);
  font-size: var(--text-meta);
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.pupils__actions {
  flex-shrink: 0;
}

.pupils__board {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-panel);
  background: var(--surface-card);
  overflow: visible;
  min-height: 360px;
  display: flex;
  flex-direction: column;
}

.pupils__toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 12px;
  border-bottom: 1px solid var(--color-border);
  background: var(--surface-wash);
  align-items: center;
  border-radius: var(--radius-panel) var(--radius-panel) 0 0;
  position: relative;
  z-index: 20;
}

.pupils__bulk {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-success-wash);
}

.pupils__bulk-count {
  margin: 0;
  margin-right: 4px;
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-ink-black);
}

.pupils__pill--bulk {
  background: var(--surface-card);
}

.pupils__bulk-clear {
  margin-left: auto;
  border: none;
  background: transparent;
  color: var(--color-muted);
  font: inherit;
  font-size: var(--text-body-sm);
  cursor: pointer;
  padding: 8px 4px;
}

.pupils__bulk-clear:hover:not(:disabled) {
  color: var(--color-ink-black);
}

.pupils__bulk-clear:disabled {
  opacity: 0.5;
  cursor: wait;
}

.pupils__col-check,
.pupils__td-check {
  width: 44px;
  vertical-align: middle;
}

.pupils__col-check {
  padding: 10px 12px !important;
}

.pupils__check {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  min-height: 44px;
  padding: 0 12px;
  cursor: pointer;
}

.pupils__col-check .pupils__check {
  min-height: 0;
  padding: 0;
}

.pupils__check input {
  width: 16px;
  height: 16px;
  margin: 0;
  accent-color: var(--color-ownlane-green);
  cursor: pointer;
}

.pupils__tr--selected {
  background: color-mix(in srgb, var(--color-success-wash) 55%, transparent);
}

.pupils__tr--selected:hover {
  background: color-mix(in srgb, var(--color-success-wash) 75%, transparent);
}

.pupils__search {
  position: relative;
  flex: 1 1 200px;
  min-width: 160px;
}

.pupils__search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-muted);
  pointer-events: none;
}

.pupils__search-input {
  width: 100%;
  min-height: 40px;
  padding: 0 14px 0 36px;
  border: 1px solid var(--color-border);
  border-radius: 20px;
  background: var(--surface-card);
  color: var(--color-ink-black);
  font: inherit;
  font-size: var(--text-body-sm);
  appearance: none;
  -webkit-appearance: none;
}

.pupils__search-input::-webkit-search-decoration,
.pupils__search-input::-webkit-search-cancel-button,
.pupils__search-input::-webkit-search-results-button,
.pupils__search-input::-webkit-search-results-decoration {
  display: none;
}

.pupils__search-input::placeholder {
  color: var(--color-ash-mist, var(--color-muted));
}

.pupils__search-input:hover {
  border-color: var(--color-ash-mist);
}

.pupils__search-input:focus {
  outline: none;
  border-color: var(--color-ink-black);
}

.pupils__pop {
  position: relative;
}

.pupils__pop--end {
  margin-left: auto;
}

.pupils__pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 40px;
  padding: 0 14px;
  border: 1px solid var(--color-border);
  border-radius: 20px;
  background: var(--surface-card);
  color: var(--color-ink-black);
  font: inherit;
  font-size: var(--text-body-sm);
  font-weight: 500;
  cursor: pointer;
  white-space: nowrap;
}

.pupils__pill:hover {
  border-color: var(--color-ash-mist);
  background: var(--color-paper-white, var(--surface-card));
}

.pupils__pill:focus-visible {
  outline: none;
  border-color: var(--color-ink-black);
}

.pupils__pill--active {
  border-color: var(--color-ink-black);
  background: var(--color-paper-white, var(--surface-card));
}

.pupils__pill--columns {
  border-radius: 8px;
  border-color: transparent;
  background: color-mix(in srgb, var(--color-driftwood) 42%, var(--color-parchment));
  color: var(--color-ink-black);
}

.pupils__pill--columns .pupils__pill-icon {
  color: var(--color-bark, var(--color-ink-black));
}

.pupils__pill--columns:hover {
  border-color: transparent;
  background: color-mix(in srgb, var(--color-driftwood) 58%, var(--color-parchment));
  color: var(--color-ink-black);
}

.pupils__pill--columns:focus-visible {
  outline: 2px solid var(--color-ink-black);
  outline-offset: 2px;
  border-color: transparent;
}

.pupils__pill--columns-open {
  border-color: transparent;
  background: color-mix(in srgb, var(--color-driftwood) 65%, var(--color-parchment));
}

.pupils__pill-icon {
  display: inline-flex;
  color: var(--color-muted);
}

.pupils__pill-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: var(--color-ownlane-green);
  color: var(--color-on-accent, #fffefb);
  font-size: 11px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.pupils__pill-value {
  color: var(--color-ink-black);
  font-weight: 500;
}

.pupils__panel {
  position: absolute;
  top: calc(100% + 8px);
  left: 0;
  z-index: 40;
  width: min(320px, calc(100vw - 32px));
  padding: 12px;
  border: 1px solid var(--color-border);
  border-radius: 12px;
  background: var(--color-paper-white, var(--surface-card));
  box-shadow: 0 12px 28px rgba(32, 21, 21, 0.12);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.pupils__pop--end .pupils__panel {
  left: auto;
  right: 0;
}

.pupils__panel--narrow {
  width: min(220px, calc(100vw - 32px));
  gap: 2px;
  padding: 8px;
}

.pupils__panel--columns {
  width: min(280px, calc(100vw - 32px));
  max-height: min(70vh, 520px);
  overflow-y: auto;
  gap: 2px;
  padding: 8px;
}

.pupils__panel--status {
  width: min(260px, calc(100vw - 32px));
  max-height: min(70vh, 420px);
  overflow-y: auto;
  gap: 2px;
  padding: 8px;
}

.pupils__option-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pupils__option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  padding: 9px 10px;
  border: none;
  border-radius: 8px;
  background: transparent;
  color: var(--color-ink-black);
  font: inherit;
  font-size: var(--text-body-sm);
  text-align: left;
  cursor: pointer;
}

.pupils__option-main {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.pupils__status-icon {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 7px;
}

.pupils__status-icon--inline {
  width: 16px;
  height: 16px;
  border-radius: 5px;
}

.pupils__status-icon--all {
  background: var(--surface-wash);
  color: var(--color-bark, var(--color-ink-black));
}

.pupils__status-icon--active {
  background: var(--color-success-wash);
  color: var(--color-success);
}

.pupils__status-icon--waiting {
  background: var(--color-warning-wash);
  color: var(--color-warning);
}

.pupils__status-icon--paused {
  background: color-mix(in srgb, var(--color-diary-break) 35%, var(--surface-wash));
  color: var(--color-diary-break-ink);
}

.pupils__status-icon--passed {
  background: var(--color-success-wash);
  color: var(--color-ownlane-green);
}

.pupils__status-icon--inactive {
  background: var(--surface-wash);
  color: var(--color-muted);
}

.pupils__status-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.pupils__option-check {
  flex-shrink: 0;
  width: 12px;
  color: var(--color-ownlane-green);
  font-size: 12px;
  font-weight: 700;
  line-height: 1;
  text-align: center;
}

.pupils__option:hover {
  background: var(--surface-wash);
}

.pupils__option[aria-selected='true'] {
  background: var(--surface-wash);
  font-weight: 600;
}

.pupils__option-meta {
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
  font-weight: 500;
}

.pupils__columns-hint {
  margin: 0 0 4px;
  padding: 4px 10px;
  font-size: 11px;
  color: var(--color-muted);
}

.pupils__columns-item {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 4px 6px;
  border-radius: 8px;
  font-size: var(--text-body-sm);
  cursor: grab;
  user-select: none;
}

.pupils__columns-item:active {
  cursor: grabbing;
}

.pupils__columns-item--dragging {
  background: var(--surface-wash);
  opacity: 0.85;
}

.pupils__columns-item:hover {
  background: var(--surface-wash);
}

.pupils__columns-grip {
  display: inline-flex;
  color: var(--color-ash-mist, var(--color-muted));
  flex-shrink: 0;
}

.pupils__columns-label {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
  cursor: pointer;
  padding: 4px 0;
}

.pupils__columns-label input {
  accent-color: var(--color-ownlane-green);
}

.pupils__empty {
  padding: 48px 24px;
  text-align: center;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.pupils__table-wrap {
  overflow-x: auto;
  flex: 1;
}

.pupils__table {
  width: 100%;
  border-collapse: collapse;
}

.pupils__table th {
  padding: 10px 14px;
  border-bottom: 1px solid var(--color-border);
  color: var(--color-muted);
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-align: left;
  text-transform: uppercase;
  white-space: nowrap;
}

.pupils__col-open {
  width: 40px;
}

.pupils__col-wide {
  min-width: 14rem;
}

.pupils__tr {
  transition: background-color var(--duration-fast) ease;
}

.pupils__tr:hover {
  background: var(--surface-wash);
}

.pupils__table td {
  padding: 0;
  border-bottom: 1px solid var(--color-border);
  vertical-align: middle;
  font-size: var(--text-body-sm);
}

.pupils__tr:last-child td {
  border-bottom: none;
}

.pupils__cell-link {
  display: block;
  padding: 12px 14px;
  color: inherit;
  text-decoration: none;
  min-width: 0;
}

.pupils__td-name {
  display: flex;
  align-items: center;
  gap: 10px;
}

.pupils__availability {
  display: block;
  line-height: 1.35;
  white-space: normal;
}

.pupils__start-tab {
  width: 4px;
  align-self: stretch;
  min-height: 28px;
  border-radius: 2px;
  flex-shrink: 0;
  background: var(--color-frost-green);
}

.pupils__start-tab--ready {
  background: var(--color-ownlane-green);
}

.pupils__start-tab--none {
  opacity: 0.45;
}

.pupils__start-tab--m1 { background: #5b8def; }
.pupils__start-tab--m2 { background: #6a9aef; }
.pupils__start-tab--m3 { background: #4db6ac; }
.pupils__start-tab--m4 { background: #66bb6a; }
.pupils__start-tab--m5 { background: #9ccc65; }
.pupils__start-tab--m6 { background: #d4e157; }
.pupils__start-tab--m7 { background: #ffca28; }
.pupils__start-tab--m8 { background: #ffa726; }
.pupils__start-tab--m9 { background: #ff8a65; }
.pupils__start-tab--m10 { background: #e57373; }
.pupils__start-tab--m11 { background: #ba68c8; }
.pupils__start-tab--m12 { background: #7986cb; }

.pupils__start-chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.pupils__chip-count {
  opacity: 0.65;
  margin-left: 4px;
  font-size: 0.85em;
}

.pupils__avatar {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 999px;
  background: var(--surface-wash);
  color: var(--color-bark, var(--color-ink-black));
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.02em;
}

.pupils__name {
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pupils__muted {
  color: var(--color-bark, var(--color-ink-black));
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.pupils__action-cell {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.pupils__progress {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 110px;
}

.pupils__progress-bar {
  flex: 1;
  height: 8px;
  min-width: 64px;
  border-radius: 999px;
  background: var(--surface-wash);
  overflow: hidden;
}

.pupils__progress-fill {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--color-ownlane-green);
}

.pupils__progress-label {
  flex-shrink: 0;
  font-size: var(--text-meta);
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
}

.pupils__none {
  color: var(--color-muted);
}

.pupils__td-open {
  width: 40px;
  text-align: right;
}

.pupils__open-link {
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.pupils__chevron {
  color: var(--color-muted);
}

.pupils__footer {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 10px 14px;
  border-top: 1px solid var(--color-border);
  background: var(--surface-wash);
  border-radius: 0 0 var(--radius-panel) var(--radius-panel);
  margin-top: auto;
}

.pupils__pager-meta {
  margin: 0;
  font-size: var(--text-meta);
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
}

.pupils__pager-actions {
  display: flex;
  gap: 8px;
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

.pupils-filter {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}

.pupils-filter__backdrop {
  position: absolute;
  inset: 0;
  border: none;
  padding: 0;
  background: rgba(32, 21, 21, 0.4);
  cursor: pointer;
}

.pupils-filter__sheet {
  position: relative;
  z-index: 1;
  width: min(440px, 100%);
  max-height: min(720px, calc(100vh - 32px));
  overflow: auto;
  padding: 16px 20px 28px;
  border-radius: 16px;
  background: var(--color-paper-white, var(--surface-card));
  box-shadow: 0 24px 48px rgba(32, 21, 21, 0.18);
}

.pupils-filter__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
}

.pupils-filter__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border: none;
  border-radius: 999px;
  background: transparent;
  color: var(--color-ink-black);
  cursor: pointer;
}

.pupils-filter__icon-btn:hover {
  background: var(--surface-wash);
}

.pupils-filter__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.pupils-filter__reset {
  min-height: 36px;
  padding: 0 14px;
  border: none;
  border-radius: 999px;
  background: var(--surface-wash);
  color: var(--color-ink-black);
  font: inherit;
  font-size: var(--text-body-sm);
  font-weight: 500;
  cursor: pointer;
}

.pupils-filter__reset:hover:not(:disabled) {
  background: var(--color-border);
}

.pupils-filter__reset:disabled {
  opacity: 0.45;
  cursor: default;
}

.pupils-filter__apply {
  min-height: 36px;
  padding: 0 16px;
  border: none;
  border-radius: 999px;
  background: var(--color-ink-black);
  color: var(--color-paper-white, #fffefb);
  font: inherit;
  font-size: var(--text-body-sm);
  font-weight: 600;
  cursor: pointer;
}

.pupils-filter__apply:hover {
  opacity: 0.92;
}

.pupils-filter__title {
  margin: 0 0 8px;
  font-size: clamp(1.75rem, 4vw, 2.25rem);
  font-weight: 700;
  letter-spacing: -0.02em;
  color: var(--color-ink-black);
  line-height: 1.15;
}

.pupils-filter__list {
  display: flex;
  flex-direction: column;
}

.pupils-filter__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  padding: 16px 0;
  border: none;
  border-bottom: 1px solid var(--color-border);
  background: transparent;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.pupils-filter__row:last-child {
  border-bottom: none;
}

.pupils-filter__row:hover .pupils-filter__row-label {
  color: var(--color-ownlane-green);
}

.pupils-filter__row-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.pupils-filter__row-label {
  font-size: var(--text-body);
  font-weight: 600;
  color: var(--color-ink-black);
}

.pupils-filter__row-value {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.pupils-filter__chevron {
  flex-shrink: 0;
  color: var(--color-muted);
}

.pupils-filter__back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 4px 0 12px;
  padding: 8px 0;
  border: none;
  background: transparent;
  color: var(--color-ink-black);
  font: inherit;
  font-size: var(--text-body);
  font-weight: 600;
  cursor: pointer;
}

.pupils-filter__options {
  display: flex;
  flex-direction: column;
}

.pupils-filter__option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  padding: 14px 0;
  border: none;
  border-bottom: 1px solid var(--color-border);
  background: transparent;
  color: var(--color-ink-black);
  font: inherit;
  font-size: var(--text-body-sm);
  text-align: left;
  cursor: pointer;
}

.pupils-filter__option-aside {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-left: auto;
}

.pupils-filter__option-meta {
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
}

.pupils-filter__option:last-child {
  border-bottom: none;
}

.pupils-filter__option[aria-selected='true'] {
  font-weight: 600;
}

.pupils-filter__tick {
  color: var(--color-ownlane-green);
  font-weight: 700;
}

@media (max-width: 720px) {
  .pupils__pop--end {
    margin-left: 0;
  }

  .pupils-filter {
    align-items: flex-end;
    padding: 0;
  }

  .pupils-filter__sheet {
    width: 100%;
    max-height: min(88vh, 720px);
    border-radius: 16px 16px 0 0;
    padding-bottom: max(24px, env(safe-area-inset-bottom));
  }
}
</style>
