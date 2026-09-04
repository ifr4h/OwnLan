<template>
  <section class="page">
    <NuxtLink to="/pupils" class="back">← Pupils</NuxtLink>

    <header class="page__header">
      <div>
        <p class="page__eyebrow">Intake</p>
        <h1 class="page__title">Waiting for details</h1>
        <p class="page__copy">
          Open links and submitted forms from pupils.
        </p>
      </div>
      <NuxtLink to="/pupils/intake/new" class="btn">
        New link
        <span aria-hidden="true">→</span>
      </NuxtLink>
    </header>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-else-if="loading" class="muted">Loading…</p>

    <div v-else-if="items.length === 0" class="empty">
      <h2 class="empty__title">No links yet</h2>
      <p class="empty__copy">
        Create a link and send it to a pupil — they’ll fill in the useful bits.
      </p>
      <NuxtLink to="/pupils/intake/new" class="btn btn--secondary">Create a link</NuxtLink>
    </div>

    <ul v-else class="list" aria-label="Pupil intakes">
      <li v-for="item in items" :key="item.id">
        <NuxtLink
          v-if="isReviewable(item)"
          :to="`/pupils/intake/${item.id}`"
          class="card card--link"
        >
          <div class="card__main">
            <p class="card__name">{{ item.display_name }}</p>
            <p class="card__meta">{{ metaLine(item) }}</p>
          </div>
          <span class="badge" :data-status="badgeStatus(item)">
            {{ intakeStatusLabel(item.status, item) }}
          </span>
          <span class="card__chevron" aria-hidden="true">→</span>
        </NuxtLink>
        <div v-else class="card">
          <div class="card__main">
            <p class="card__name">{{ item.display_name }}</p>
            <p class="card__meta">{{ metaLine(item) }}</p>
          </div>
          <span class="badge" :data-status="badgeStatus(item)">
            {{ intakeStatusLabel(item.status, item) }}
          </span>
          <button
            v-if="item.status === 'open' && !item.is_revoked && !item.is_expired"
            class="ghost"
            type="button"
            :disabled="revokingId === item.id"
            @click="onRevoke(item.id)"
          >
            {{ revokingId === item.id ? '…' : 'Revoke' }}
          </button>
        </div>
      </li>
    </ul>

    <section v-if="waiting.length" class="waiting">
      <h2 class="waiting__title">Waiting list</h2>
      <ul class="list">
        <li v-for="pupil in waiting" :key="pupil.id">
          <NuxtLink :to="`/pupils/${pupil.id}`" class="card card--link">
            <div class="card__main">
              <p class="card__name">{{ pupil.full_name }}</p>
              <p class="card__meta">{{ pupil.mobile }}</p>
            </div>
            <span class="badge" data-status="waiting">Waiting</span>
            <span class="card__chevron" aria-hidden="true">→</span>
          </NuxtLink>
        </li>
      </ul>
    </section>
  </section>
</template>

<script setup lang="ts">
import type { IntakeListItem } from '~/composables/useIntake'
import { intakeStatusLabel } from '~/composables/useIntake'
import type { PupilListItem } from '~/composables/usePupils'

useHead({ title: 'Waiting for details · OwnLane' })

const { listIntakes, revokeIntake, listWaitingPupils } = useIntake()

const items = ref<IntakeListItem[]>([])
const waiting = ref<PupilListItem[]>([])
const loading = ref(true)
const error = ref('')
const revokingId = ref<number | null>(null)

function isReviewable(item: IntakeListItem): boolean {
  return ['submitted', 'accepted', 'waiting'].includes(item.status)
}

function badgeStatus(item: IntakeListItem): string {
  if (item.is_revoked) return 'revoked'
  if (item.status === 'open' && item.is_expired) return 'expired'
  return item.status
}

function metaLine(item: IntakeListItem): string {
  if (item.submitted_at) {
    const d = new Date(item.submitted_at.endsWith('Z') ? item.submitted_at : `${item.submitted_at}Z`)
    if (!Number.isNaN(d.getTime())) {
      return `Submitted ${d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`
    }
  }
  const d = new Date(item.created_at.endsWith('Z') ? item.created_at : `${item.created_at}Z`)
  if (!Number.isNaN(d.getTime())) {
    return `Created ${d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`
  }
  return ''
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [intakes, waitingPupils] = await Promise.all([
      listIntakes(),
      listWaitingPupils(),
    ])
    items.value = intakes
    waiting.value = waitingPupils
  } catch (e) {
    error.value = extractApiError(e, 'Could not load intakes.')
  } finally {
    loading.value = false
  }
}

async function onRevoke(id: number) {
  revokingId.value = id
  error.value = ''
  try {
    await revokeIntake(id)
    await load()
  } catch (e) {
    error.value = extractApiError(e, 'Could not revoke this link.')
  } finally {
    revokingId.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.page {
  max-width: 720px;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-20);
}

.back {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  align-self: flex-start;
}

.page__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--spacing-16);
}

.page__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
  margin-bottom: var(--spacing-8);
}

.page__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  line-height: var(--leading-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.page__copy {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  opacity: 0.75;
  max-width: 36ch;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-8);
  min-height: 48px;
  padding: 12px 20px;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  box-shadow: var(--shadow-button);
  white-space: nowrap;
  flex-shrink: 0;
  text-decoration: none;
  border: none;
  font: inherit;
  cursor: pointer;
}

.btn--secondary {
  align-self: flex-start;
}

.list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.card {
  display: flex;
  align-items: center;
  gap: var(--spacing-12);
  padding: var(--spacing-20);
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  color: inherit;
  text-decoration: none;
}

.card--link:hover,
.card--link:focus-visible {
  border-color: var(--color-ownlane-green);
  outline: none;
}

.card__main {
  flex: 1;
  min-width: 0;
}

.card__name {
  font-size: var(--text-body);
}

.card__meta {
  font-size: var(--text-body-sm);
  margin-top: var(--spacing-4);
  opacity: 0.65;
}

.card__chevron {
  color: var(--color-ownlane-green);
  flex-shrink: 0;
}

.badge {
  flex-shrink: 0;
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  padding: 6px 12px;
  border-radius: 999px;
  background: var(--color-frost-green);
}

.badge[data-status='submitted'] {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.badge[data-status='accepted'] {
  background: var(--color-soft-sage);
}

.badge[data-status='waiting'] {
  background: var(--color-hi-yellow);
}

.badge[data-status='expired'],
.badge[data-status='revoked'] {
  opacity: 0.7;
}

.ghost {
  border: none;
  background: transparent;
  font: inherit;
  font-size: var(--text-body-sm);
  text-decoration: underline;
  cursor: pointer;
  min-height: 44px;
  opacity: 0.7;
}

.empty {
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--card-padding);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.empty__title {
  font-size: var(--text-heading-sm);
}

.empty__copy {
  font-size: var(--text-body-sm);
  max-width: 36ch;
}

.waiting {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  padding-top: var(--spacing-8);
}

.waiting__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-body);
  letter-spacing: -0.01em;
}

.error {
  color: var(--color-marker-red);
}

.muted {
  opacity: 0.65;
}
</style>
