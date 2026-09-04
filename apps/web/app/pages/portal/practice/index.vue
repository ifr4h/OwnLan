<script setup lang="ts">
definePageMeta({ layout: 'portal' })

const { t } = usePortalI18n()

type PracticeSession = {
  id: number
  practised_at: string
  duration_minutes: number
  skill_codes: string[]
  feeling: string | null
  note: string | null
}

const items = ref<PracticeSession[]>([])
const loading = ref(true)
const error = ref('')
const saving = ref(false)

const duration = ref('30')
const feeling = ref('')
const note = ref('')

useHead({ title: 'Private practice · OwnLane' })

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await apiFetch<{ items: PracticeSession[] }>('/portal/practice')
    items.value = res.items ?? []
  } catch (e) {
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    loading.value = false
  }
}

async function onSave() {
  saving.value = true
  error.value = ''
  try {
    const minutes = Number(duration.value)
    await apiFetch('/portal/practice', {
      method: 'POST',
      body: {
        duration_minutes: minutes,
        feeling: feeling.value || null,
        note: note.value.trim() || null,
      },
    })
    note.value = ''
    feeling.value = ''
    await load()
  } catch (e) {
    error.value = extractApiError(e, t('common.errorGeneric'))
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void load()
})

function feelingLabel(f: string | null) {
  if (f === 'difficult') return t('practice.feelingDifficult')
  if (f === 'okay') return t('practice.feelingOkay')
  if (f === 'comfortable') return t('practice.feelingComfortable')
  return ''
}
</script>

<template>
  <div class="prac">
    <header class="prac__hero">
      <h1 class="prac__title">{{ t('practice.title') }}</h1>
    </header>

    <form class="prac__form" @submit.prevent="onSave">
      <h2 class="prac__h">{{ t('practice.log') }}</h2>
      <label class="field">
        <span class="field__label">{{ t('practice.duration') }}</span>
        <input v-model="duration" class="field__input" type="number" min="5" max="480" required>
      </label>
      <fieldset class="feel">
        <legend class="field__label">{{ t('practice.feeling') }}</legend>
        <label class="feel__opt">
          <input v-model="feeling" type="radio" value="difficult">
          {{ t('practice.feelingDifficult') }}
        </label>
        <label class="feel__opt">
          <input v-model="feeling" type="radio" value="okay">
          {{ t('practice.feelingOkay') }}
        </label>
        <label class="feel__opt">
          <input v-model="feeling" type="radio" value="comfortable">
          {{ t('practice.feelingComfortable') }}
        </label>
      </fieldset>
      <label class="field">
        <span class="field__label">{{ t('practice.note') }}</span>
        <textarea v-model="note" class="field__input" rows="2" />
      </label>
      <button class="ol-btn" type="submit" :disabled="saving">
        {{ saving ? t('common.loading') : t('practice.save') }}
      </button>
    </form>

    <p v-if="error" class="prac__err" role="alert">{{ error }}</p>

    <section class="prac__list-wrap">
      <h2 class="prac__h">{{ t('practice.list') }}</h2>
      <p v-if="loading" class="prac__msg">{{ t('common.loading') }}</p>
      <ul v-else-if="items.length" class="prac__list">
        <li v-for="item in items" :key="item.id" class="prac__row">
          <span class="prac__mins">{{ item.duration_minutes }} min</span>
          <span v-if="feelingLabel(item.feeling)">{{ feelingLabel(item.feeling) }}</span>
          <span v-if="item.note" class="prac__note">{{ item.note }}</span>
        </li>
      </ul>
      <p v-else class="prac__msg">{{ t('practice.empty') }}</p>
    </section>
  </div>
</template>

<style scoped>
.prac {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-28);
  padding-top: var(--spacing-8);
}

.prac__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-md);
}

.prac__h {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: 12px;
}

.prac__form {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: var(--spacing-16);
  background: var(--color-frost-green);
  border-radius: var(--radius-panel);
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field__label {
  font-size: var(--text-meta);
}

.field__input {
  min-height: 48px;
  padding: 10px 14px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-small);
  background: var(--color-paper-white);
  font: inherit;
}

.feel {
  border: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.feel__opt {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  font-size: var(--text-body-sm);
}

.prac__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.prac__row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-height: 52px;
  padding: 12px 0;
  border-bottom: 1px solid var(--color-border);
  font-size: var(--text-body-sm);
}

.prac__mins {
  font-family: var(--font-martian-mono);
  font-size: var(--text-meta);
}

.prac__note {
  color: var(--color-muted);
  font-size: var(--text-meta);
}

.prac__msg {
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}

.prac__err {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}
</style>
