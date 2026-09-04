<script setup lang="ts">
definePageMeta({ layout: 'companion' })

const route = useRoute()
const learnerId = computed(() => Number(route.params.id))
const { learnerHome, addPracticeNote, fetchMe } = useCompanion()

type Home = Awaited<ReturnType<typeof learnerHome>>

const data = ref<Home | null>(null)
const loading = ref(true)
const error = ref('')
const note = ref('')
const notePending = ref(false)
const noteMsg = ref('')

useHead(() => ({
  title: data.value
    ? `${data.value.learner.first_name} · Companion · OwnLane`
    : 'Companion · OwnLane',
}))

const perms = computed(() => data.value?.permissions ?? {})

const sessionId = computed(() => {
  const raw = data.value?.latest_practice_session_id
  return typeof raw === 'number' && raw > 0 ? raw : null
})

async function load() {
  loading.value = true
  error.value = ''
  await fetchMe()
  try {
    data.value = await learnerHome(learnerId.value)
  } catch (e) {
    data.value = null
    error.value = extractApiError(e, 'Could not load this learner.')
  } finally {
    loading.value = false
  }
}

async function onSaveNote() {
  if (!sessionId.value || !note.value.trim()) return
  notePending.value = true
  noteMsg.value = ''
  try {
    await addPracticeNote(learnerId.value, sessionId.value, note.value.trim())
    noteMsg.value = 'Note saved.'
    note.value = ''
  } catch (e) {
    noteMsg.value = extractApiError(e, 'Could not save note.')
  } finally {
    notePending.value = false
  }
}

function lessonLabel(lesson: NonNullable<Home['next_lesson']>) {
  return (
    (lesson.starts_at_display as string | undefined)
    || (lesson.starts_at_time as string | undefined)
    || 'Upcoming lesson'
  )
}

function lessonDuration(lesson: NonNullable<Home['next_lesson']>) {
  const mins = lesson.duration_minutes as number | undefined
  if (!mins) return ''
  if (mins % 60 === 0) {
    const h = mins / 60
    return h === 1 ? '1 hr' : `${h} hrs`
  }
  return `${mins} min`
}

onMounted(() => {
  void load()
})

watch(learnerId, () => {
  void load()
})
</script>

<template>
  <div class="learner">
    <NuxtLink to="/companion" class="learner__back">← People you’re helping</NuxtLink>

    <p v-if="loading" class="learner__msg">Loading…</p>
    <p v-else-if="error" class="learner__err" role="alert">{{ error }}</p>

    <template v-else-if="data">
      <header class="learner__hero">
        <p class="learner__eyebrow">Companion view</p>
        <h1 class="learner__title">{{ data.learner.first_name }}</h1>
        <p class="learner__hint">You only see what they’ve chosen to share.</p>
      </header>

      <section v-if="perms.lessons" class="learner__block">
        <h2 class="learner__h">Next lesson</h2>
        <p v-if="data.next_lesson" class="learner__text">
          {{ lessonLabel(data.next_lesson) }}
          <span v-if="lessonDuration(data.next_lesson)"> · {{ lessonDuration(data.next_lesson) }}</span>
        </p>
        <p v-else class="learner__msg">No upcoming lesson booked.</p>
      </section>

      <section v-if="perms.progress" class="learner__block">
        <h2 class="learner__h">Current focus</h2>
        <p v-if="data.next_focus" class="learner__text">{{ data.next_focus }}</p>
        <p v-else class="learner__msg">No focus set yet.</p>
        <p v-if="data.last_lesson_summary" class="learner__sub">{{ data.last_lesson_summary }}</p>
      </section>

      <section v-if="perms.money && data.money" class="learner__block">
        <h2 class="learner__h">Lesson credit</h2>
        <p class="learner__text">{{ data.money.credit_label }}</p>
        <p v-if="data.money.amount_due_pence > 0" class="learner__sub">
          Amount due: {{ data.money.amount_due_label }}
        </p>
      </section>

      <section v-if="perms.test" class="learner__block">
        <h2 class="learner__h">Tests</h2>
        <p class="learner__msg">Test details shared by their instructor.</p>
      </section>

      <section v-if="perms.practice" class="learner__block">
        <h2 class="learner__h">Practice with {{ data.learner.first_name }}</h2>
        <p v-if="data.practice_focus" class="learner__text">{{ data.practice_focus }}</p>

        <div v-if="data.practice_resources?.length" class="learner__from-inst">
          <h3 class="learner__h3">From their instructor</h3>
          <ul class="learner__resources">
            <li v-for="r in data.practice_resources" :key="r.id">
              <p class="learner__res-title">{{ r.title }}</p>
              <p v-if="r.note" class="learner__sub">{{ r.note }}</p>
              <ClientOnly v-if="r.scene && Object.keys(r.scene).length">
                <LearnSceneViewer :scene="r.scene as never" class="learner__scene" />
              </ClientOnly>
            </li>
          </ul>
        </div>

        <div v-if="data.recent_practice?.length" class="learner__recent">
          <h3 class="learner__h3">Recent practice</h3>
          <ul class="learner__resources">
            <li v-for="s in data.recent_practice" :key="s.id">
              <p class="learner__res-title">{{ s.duration_minutes }} min</p>
              <p v-if="s.feeling" class="learner__sub">
                How it felt (learner): {{ s.feeling }}
              </p>
              <p v-if="s.learner_reflection" class="learner__sub">
                Learner note: {{ s.learner_reflection }}
              </p>
              <p v-if="s.companion_note" class="learner__sub">
                Your note: {{ s.companion_note }}
              </p>
            </li>
          </ul>
        </div>

        <form v-if="sessionId" class="learner__note-form" @submit.prevent="onSaveNote">
          <label class="learner__note-label" for="companion-note">
            Add a short note on their latest practice
          </label>
          <textarea
            id="companion-note"
            v-model="note"
            class="learner__note"
            rows="3"
            maxlength="500"
          />
          <button type="submit" class="learner__btn" :disabled="notePending || !note.trim()">
            {{ notePending ? 'Saving…' : 'Save note' }}
          </button>
          <p v-if="noteMsg" class="learner__msg" role="status">{{ noteMsg }}</p>
        </form>
      </section>

      <p
        v-if="!perms.lessons && !perms.progress && !perms.money && !perms.practice && !perms.test"
        class="learner__msg"
      >
        Nothing shared yet for this person.
      </p>
    </template>
  </div>
</template>

<style scoped>
.learner {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-24);
  padding-top: var(--spacing-8);
}

.learner__back {
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  color: var(--color-muted);
  text-decoration: none;
  font-size: var(--text-body-sm);
}

.learner__hero {
  margin-left: calc(-1 * var(--spacing-20));
  margin-right: calc(-1 * var(--spacing-20));
  padding: var(--spacing-28) var(--spacing-20);
  background: linear-gradient(165deg, var(--color-frost-green), var(--color-chalk-green));
}

.learner__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-ownlane-green);
  margin: 0;
}

.learner__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-lg);
  margin: var(--spacing-8) 0 0;
}

.learner__hint {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.learner__h {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin: 0 0 var(--spacing-8);
}

.learner__text {
  margin: 0;
  font-size: var(--text-body);
  max-width: 40ch;
}

.learner__sub {
  margin: 8px 0 0;
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.learner__msg {
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.learner__err {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.learner__resources {
  list-style: none;
  margin: var(--spacing-12) 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.learner__res-title {
  margin: 0;
  font-size: var(--text-body-sm);
}

.learner__note-form {
  margin-top: var(--spacing-16);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.learner__note-label {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.learner__note {
  min-height: 88px;
  padding: 12px 14px;
  border: 1px solid var(--color-border);
  border-radius: 14px;
  background: var(--color-paper-white);
  font: inherit;
  font-size: var(--text-body-sm);
  resize: vertical;
}

.learner__btn {
  min-height: 48px;
  width: fit-content;
  padding: 10px 18px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  font: inherit;
  font-size: var(--text-body-sm);
  cursor: pointer;
}

.learner__btn:disabled {
  opacity: 0.7;
}

.learner__h3 {
  font-size: var(--text-body-sm);
  font-weight: 600;
  margin: var(--spacing-16) 0 var(--spacing-8);
}

.learner__scene {
  margin-top: var(--spacing-12);
}
</style>
