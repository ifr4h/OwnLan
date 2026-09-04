<script setup lang="ts">
import type { OverviewObservation } from '~/utils/diary/overviewModel'
import { formatOverviewHours } from '~/utils/diary/overviewModel'

defineProps<{
  item: OverviewObservation
}>()

function cadenceLabel(cadence: string): string {
  if (cadence === 'weekly') return 'Usually weekly'
  if (cadence === 'fortnightly') return 'Usually fortnightly'
  if (cadence.startsWith('every ')) return `Usually ${cadence}`
  return cadence
}
</script>

<template>
  <div class="obs" :data-kind="item.kind">
    <template v-if="item.kind === 'tight_turnaround'">
      <p class="obs__eyebrow">Tight turnaround</p>
      <div class="obs__transition">
        <span class="obs__name">{{ item.fromName }}</span>
        <span class="obs__line" aria-hidden="true" />
        <span class="obs__name">{{ item.toName }}</span>
      </div>
      <p class="obs__detail">
        {{ item.availableMinutes }}m gap · needs ~{{ item.travelMinutes }}m
        <span class="obs__emph">{{ item.shortfallMinutes }}m short</span>
      </p>
    </template>

    <template v-else-if="item.kind === 'tight_count'">
      <p class="obs__eyebrow">Travel</p>
      <p class="obs__title">{{ item.count }} tight turnarounds</p>
    </template>

    <template v-else-if="item.kind === 'opening'">
      <p class="obs__eyebrow">
        {{ item.weekday.slice(0, 3) }} · {{ formatOverviewHours(item.durationMinutes) }} free
      </p>
      <p class="obs__title">{{ item.startDisplay }}–{{ item.endDisplay }}</p>
      <div v-if="item.matchCount" class="obs__avatars">
        <span
          v-for="(ini, i) in item.matchInitials"
          :key="i"
          class="obs__avatar"
        >{{ ini }}</span>
        <span v-if="item.matchCount > item.matchInitials.length" class="obs__more">
          +{{ item.matchCount - item.matchInitials.length }}
        </span>
        <span class="obs__fit">
          {{ item.matchCount }} pupil{{ item.matchCount === 1 ? '' : 's' }} could fit
        </span>
      </div>
    </template>

    <template v-else-if="item.kind === 'busiest_day'">
      <p class="obs__eyebrow">{{ item.date ? 'Busiest' : 'Busiest weekdays' }}</p>
      <p class="obs__title">
        {{ item.weekday }}
        <span class="obs__muted">{{ formatOverviewHours(item.teachingMinutes) }}{{ item.date ? '' : ' avg' }}</span>
      </p>
    </template>

    <template v-else-if="item.kind === 'tests'">
      <p class="obs__eyebrow">Tests ahead</p>
      <ul class="obs__tests">
        <li v-for="t in item.items" :key="t.name + t.daysUntil" class="obs__test">
          <span class="obs__test-name">{{ t.name }}</span>
          <span class="obs__test-bar" aria-hidden="true">
            <span
              class="obs__test-fill"
              :style="{ width: `${Math.max(8, 100 - Math.min(t.daysUntil, 60) * 1.4)}%` }"
            />
          </span>
          <span class="obs__test-days">{{ t.countdownLabel }}</span>
        </li>
      </ul>
    </template>

    <template v-else-if="item.kind === 'span'">
      <p class="obs__eyebrow">Teaching span</p>
      <p class="obs__title">
        {{ item.firstTime }}–{{ item.lastTime }}
        <span class="obs__muted">{{ formatOverviewHours(item.spanMinutes) }}</span>
      </p>
      <p class="obs__detail">{{ formatOverviewHours(item.teachingMinutes) }} teaching inside</p>
    </template>

    <template v-else-if="item.kind === 'travel_total'">
      <p class="obs__eyebrow">Estimated travel</p>
      <p class="obs__title">{{ formatOverviewHours(item.estimatedMinutes) }}</p>
      <p v-if="item.deltaMinutes != null && item.priorMinutes != null" class="obs__detail">
        <template v-if="item.deltaMinutes === 0">Same as the previous period</template>
        <template v-else-if="item.deltaMinutes > 0">
          {{ formatOverviewHours(item.deltaMinutes) }} more than before
        </template>
        <template v-else>
          {{ formatOverviewHours(Math.abs(item.deltaMinutes)) }} less than before
        </template>
      </p>
    </template>

    <template v-else-if="item.kind === 'cancellations'">
      <p class="obs__eyebrow">Cancellations</p>
      <p class="obs__title">
        {{ item.currentCount }} day{{ item.currentCount === 1 ? '' : 's' }} with a cancel
      </p>
      <p v-if="item.priorCount != null" class="obs__detail">
        Previous month · {{ item.priorCount }}
      </p>
    </template>

    <template v-else-if="item.kind === 'continuity'">
      <p class="obs__eyebrow">Usual pupils</p>
      <ul class="obs__pupils">
        <li v-for="p in item.pupils" :key="p.name" class="obs__pupil">
          <span class="obs__avatar">{{ p.initials }}</span>
          <span class="obs__pupil-name">{{ p.name }}</span>
          <span v-if="p.cadence" class="obs__pupil-cadence">{{ cadenceLabel(p.cadence) }}</span>
        </li>
      </ul>
    </template>
  </div>
</template>

<style scoped>
.obs {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding-top: 12px;
  border-top: 1px solid var(--color-border);
  min-width: 0;
}

.obs__eyebrow {
  margin: 0;
  font-size: 10px;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--color-muted);
  font-weight: 600;
}

.obs__title {
  margin: 0;
  font-size: var(--text-body);
  font-weight: 600;
  color: var(--color-ink-black);
  line-height: 1.3;
}

.obs__muted {
  margin-left: 6px;
  font-weight: 500;
  color: var(--color-muted);
}

.obs__detail {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
  line-height: 1.4;
}

.obs__emph {
  margin-left: 4px;
  color: #8a6d00;
  font-weight: 600;
}

.obs__transition {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 28px minmax(0, 1fr);
  gap: 6px;
  align-items: center;
}

.obs__name {
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-ink-black);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.obs__name:last-child {
  text-align: right;
}

.obs__line {
  height: 2px;
  border-radius: 999px;
  background: color-mix(in srgb, #e8c56a 80%, var(--color-border));
}

.obs__avatars {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}

.obs__avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--color-parchment);
  color: var(--color-ink-black);
  font-size: 10px;
  font-weight: 700;
}

.obs__more {
  font-size: 11px;
  color: var(--color-muted);
  font-weight: 600;
}

.obs__fit {
  font-size: var(--text-body-sm);
  color: var(--color-bark);
}

.obs__tests {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.obs__test {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(48px, 1.2fr) auto;
  gap: 8px;
  align-items: center;
}

.obs__test-name {
  font-size: var(--text-body-sm);
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.obs__test-bar {
  height: 6px;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-border) 60%, transparent);
  overflow: hidden;
}

.obs__test-fill {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: color-mix(in srgb, var(--color-ownlane-green) 55%, var(--color-soft-sage));
}

.obs__test-days {
  font-size: 11px;
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
}

.obs__pupils {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.obs__pupil {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 8px;
  align-items: center;
}

.obs__pupil-name {
  font-size: var(--text-body-sm);
  font-weight: 550;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.obs__pupil-cadence {
  font-size: 11px;
  color: var(--color-muted);
  white-space: nowrap;
}
</style>
