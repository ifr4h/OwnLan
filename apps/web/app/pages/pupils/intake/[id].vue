<template>
  <section class="page">
    <NuxtLink to="/pupils/intake" class="back">← Waiting for details</NuxtLink>

    <p v-if="loading" class="muted">Loading…</p>
    <p v-else-if="error" class="error" role="alert">{{ error }}</p>

    <template v-else-if="brief">
      <header class="hero">
        <p class="hero__eyebrow">{{ brief.headline.tag }}</p>
        <h1 class="hero__title">{{ brief.headline.full_name }}</h1>
        <p v-if="aboutBits" class="hero__about">{{ aboutBits }}</p>
        <p class="hero__contact">
          <a v-if="brief.contact.mobile" :href="`tel:${brief.contact.mobile}`">
            {{ brief.contact.mobile }}
          </a>
          <template v-if="brief.contact.email">
            <span aria-hidden="true"> · </span>
            <a :href="`mailto:${brief.contact.email}`">{{ brief.contact.email }}</a>
          </template>
        </p>
      </header>

      <aside v-if="brief.attention.length" class="flags" aria-label="Things to note">
        <p v-for="flag in brief.attention" :key="flag.code" class="flags__item">
          {{ flag.message }}
        </p>
      </aside>

      <section class="panel">
        <h2 class="panel__title">Experience</h2>
        <ul class="lines">
          <li v-for="(line, i) in brief.experience.lines" :key="i">{{ line }}</li>
        </ul>
      </section>

      <section v-if="brief.skills_learner_says.length" class="panel">
        <h2 class="panel__title">They say they’ve covered</h2>
        <p class="panel__hint">As reported by the pupil — not an assessment.</p>
        <ul class="chips">
          <li v-for="skill in brief.skills_learner_says" :key="skill" class="chip">
            {{ skill }}
          </li>
        </ul>
      </section>

      <section class="panel">
        <h2 class="panel__title">Theory</h2>
        <p v-if="brief.theory" class="theory" :data-urgency="brief.theory.urgency">
          {{ brief.theory.label }}
        </p>
        <p v-else class="panel__empty">Not given</p>
      </section>

      <section class="panel">
        <h2 class="panel__title">Practical</h2>
        <template v-if="brief.practical.booked">
          <dl class="facts">
            <div v-if="brief.practical.date" class="facts__row">
              <dt>Date</dt>
              <dd>{{ formatDate(brief.practical.date) }}</dd>
            </div>
            <div v-if="brief.practical.time" class="facts__row">
              <dt>Time</dt>
              <dd>{{ brief.practical.time }}</dd>
            </div>
            <div v-if="brief.practical.centre" class="facts__row">
              <dt>Centre</dt>
              <dd>{{ brief.practical.centre }}</dd>
            </div>
          </dl>
        </template>
        <p v-else class="panel__empty">No practical test booked yet</p>
      </section>

      <section class="panel">
        <h2 class="panel__title">Availability</h2>
        <ul v-if="brief.availability.labels.length" class="lines">
          <li v-for="(label, i) in brief.availability.labels" :key="i">{{ label }}</li>
        </ul>
        <p v-else class="panel__empty">Not shared</p>
        <p v-if="brief.availability.note" class="note">{{ brief.availability.note }}</p>
      </section>

      <section
        v-if="brief.goal || brief.instructor_should_know || brief.confidence || brief.private_practice"
        class="panel"
      >
        <h2 class="panel__title">About</h2>
        <dl class="facts">
          <div v-if="brief.goal" class="facts__row">
            <dt>Goal</dt>
            <dd>{{ goalLabel(brief.goal) }}</dd>
          </div>
          <div v-if="brief.instructor_should_know" class="facts__row facts__row--block">
            <dt>First-lesson context</dt>
            <dd>{{ brief.instructor_should_know }}</dd>
          </div>
          <div v-if="brief.confidence" class="facts__row">
            <dt>Confidence</dt>
            <dd>{{ confidenceLabel(brief.confidence) }}</dd>
          </div>
          <div v-if="brief.private_practice" class="facts__row">
            <dt>Private practice</dt>
            <dd>{{ privatePracticeLabel(brief.private_practice) }}</dd>
          </div>
        </dl>
      </section>

      <div v-if="actionError" class="error" role="alert">{{ actionError }}</div>

      <div v-if="showActions" class="actions">
        <button
          v-if="brief.actions.can_accept"
          class="btn"
          type="button"
          :disabled="acting"
          @click="onAccept"
        >
          {{ acting === 'accept' ? 'Accepting…' : 'Accept pupil' }}
        </button>
        <button
          v-if="brief.actions.can_waitlist"
          class="btn btn--ghost"
          type="button"
          :disabled="acting"
          @click="onWaitlist"
        >
          {{ acting === 'waitlist' ? 'Adding…' : 'Add to waiting list' }}
        </button>
      </div>

      <p v-else-if="brief.learner_id" class="done">
        <NuxtLink :to="`/pupils/${brief.learner_id}`">
          View {{ brief.headline.full_name.split(' ')[0] }}’s profile →
        </NuxtLink>
      </p>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { IntakeBrief } from '~/composables/useIntake'

useHead({ title: 'Review pupil · OwnLane' })

const route = useRoute()
const id = computed(() => Number(route.params.id))

const { getIntakeBrief, acceptIntake, waitlistIntake } = useIntake()

const brief = ref<IntakeBrief | null>(null)
const loading = ref(true)
const error = ref('')
const actionError = ref('')
const acting = ref<'accept' | 'waitlist' | null>(null)

const aboutBits = computed(() => {
  if (!brief.value) return ''
  const bits: string[] = []
  if (brief.value.headline.area) bits.push(brief.value.headline.area)
  const t = brief.value.headline.transmission
  if (t === 'manual') bits.push('Manual')
  else if (t === 'automatic') bits.push('Automatic')
  else if (t === 'either') bits.push('Not sure on transmission')
  else if (t) bits.push(t)
  return bits.join(' · ')
})

const showActions = computed(() => {
  if (!brief.value) return false
  if (brief.value.status !== 'submitted') return false
  return brief.value.actions.can_accept || brief.value.actions.can_waitlist
})

function formatDate(ymd: string): string {
  const d = new Date(`${ymd}T12:00:00`)
  if (Number.isNaN(d.getTime())) return ymd
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function goalLabel(goal: string): string {
  const map: Record<string, string> = {
    from_scratch: 'Starting from scratch',
    gain_confidence: 'Building confidence',
    pass_test: 'Preparing for a test',
    returning: 'Returning after a break',
    switching: 'Switching instructors',
    particular_area: 'Improving a particular area',
    // legacy values from earlier drafts
    learn_to_drive: 'Learn to drive',
    pass_soon: 'Pass soon',
    refresh: 'Refresh skills',
  }
  return map[goal] ?? goal
}

function confidenceLabel(value: string): string {
  const map: Record<string, string> = {
    very_nervous: 'Very nervous',
    a_little: 'A little nervous',
    okay: 'Okay',
    confident: 'Confident',
  }
  return map[value] ?? value
}

function privatePracticeLabel(value: string): string {
  const map: Record<string, string> = {
    yes: 'Yes',
    no: 'No',
    sometimes: 'Sometimes',
  }
  return map[value] ?? value
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    brief.value = await getIntakeBrief(id.value)
  } catch (e) {
    error.value = extractApiError(e, 'Could not load this intake.')
  } finally {
    loading.value = false
  }
}

async function onAccept() {
  acting.value = 'accept'
  actionError.value = ''
  try {
    const result = await acceptIntake(id.value)
    await navigateTo(`/pupils/${result.learner_id}`)
  } catch (e) {
    actionError.value = extractApiError(e, 'Could not accept this pupil.')
  } finally {
    acting.value = null
  }
}

async function onWaitlist() {
  acting.value = 'waitlist'
  actionError.value = ''
  try {
    const result = await waitlistIntake(id.value)
    await navigateTo(`/pupils/${result.learner_id}`)
  } catch (e) {
    actionError.value = extractApiError(e, 'Could not add to waiting list.')
  } finally {
    acting.value = null
  }
}

onMounted(() => {
  void load()
})
</script>

<style scoped>
.page {
  max-width: 640px;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.back {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
  align-self: flex-start;
}

.hero {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.hero__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
}

.hero__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.hero__about,
.hero__contact {
  font-size: var(--text-body-sm);
  opacity: 0.8;
}

.hero__contact a {
  color: var(--color-ownlane-green);
}

.flags {
  background: var(--color-frost-green);
  border-radius: var(--radius-cards);
  padding: var(--spacing-16) var(--spacing-20);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.flags__item {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
}

.panel {
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--spacing-20);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
}

.panel__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-body);
  letter-spacing: -0.01em;
}

.panel__hint {
  font-size: var(--text-body-sm);
  opacity: 0.65;
  margin-top: calc(var(--spacing-8) * -1);
}

.panel__empty {
  font-size: var(--text-body-sm);
  opacity: 0.65;
}

.lines {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
}

.chips {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
}

.chip {
  padding: 8px 14px;
  border-radius: 999px;
  background: var(--color-chalk-green);
  font-size: var(--text-body-sm);
}

.theory {
  font-size: var(--text-body-sm);
  padding: 10px 14px;
  border-radius: var(--radius-small);
  background: var(--color-chalk-green);
  align-self: flex-start;
}

.theory[data-urgency='soon'],
.theory[data-urgency='approaching'] {
  background: var(--color-hi-yellow);
}

.theory[data-urgency='expired'] {
  background: var(--color-bubblegum-pink);
}

.facts {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  margin: 0;
}

.facts__row {
  display: grid;
  grid-template-columns: 7rem 1fr;
  gap: var(--spacing-8);
  font-size: var(--text-body-sm);
}

.facts__row--block {
  grid-template-columns: 1fr;
}

.facts__row dt {
  opacity: 0.6;
}

.note {
  font-size: var(--text-body-sm);
  opacity: 0.8;
}

.actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
  padding-top: var(--spacing-8);
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  box-shadow: var(--shadow-button);
  font: inherit;
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn--ghost {
  background: transparent;
  color: var(--color-ink-black);
  box-shadow: none;
  border: 1px solid var(--color-frost-green);
}

.done a {
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
}

.error {
  color: var(--color-marker-red);
  font-size: var(--text-body-sm);
}

.muted {
  opacity: 0.65;
}
</style>
