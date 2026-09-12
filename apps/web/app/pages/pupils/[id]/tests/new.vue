<template>
  <section class="ol-page">
    <NuxtLink :to="`/pupils/${pupilId}/tests`" class="ol-back">← Practical tests</NuxtLink>
    <header class="ol-page-header">
      <h1 class="ol-page-title">Log test result</h1>
      <p class="ol-meta">
        {{ pupilName ? `For ${pupilName}` : 'Enter what was on the DL25 sheet' }}
      </p>
    </header>

    <p v-if="loadError" class="ol-error" role="alert">{{ loadError }}</p>
    <p v-else-if="!catalogue" class="ol-muted">Loading…</p>

    <template v-else>
      <PracticalTestFaultSheet
        ref="sheet"
        :catalogue="catalogue"
        :pupil-first-name="pupilFirstName"
        :default-centre="defaultCentre"
      />
      <p v-if="saveError" class="ol-error" role="alert">{{ saveError }}</p>
      <div class="actions">
        <button class="ol-btn" type="button" :disabled="saving" @click="onSave">
          {{ saving ? 'Saving…' : 'Save test result' }}
        </button>
        <NuxtLink :to="`/pupils/${pupilId}/tests`" class="ol-btn ol-btn--ghost">Cancel</NuxtLink>
      </div>
    </template>
  </section>
</template>

<script setup lang="ts">
import type { PracticalCatalogue } from '~/composables/usePracticalTests'
import PracticalTestFaultSheet from '~/components/tests/PracticalTestFaultSheet.vue'

const route = useRoute()
const pupilId = computed(() => Number(route.params.id))
const { fetchCatalogue, create } = usePracticalTests()
const { getPupil } = usePupils()

useHead({ title: 'Log test result · OwnLane' })

const catalogue = ref<PracticalCatalogue | null>(null)
const sheet = ref<InstanceType<typeof PracticalTestFaultSheet> | null>(null)
const pupilName = ref('')
const pupilFirstName = ref('')
const defaultCentre = ref<string | null>(null)
const loadError = ref('')
const saveError = ref('')
const saving = ref(false)

onMounted(async () => {
  try {
    const [cat, pupil] = await Promise.all([
      fetchCatalogue(),
      getPupil(pupilId.value),
    ])
    catalogue.value = cat
    pupilName.value = pupil.full_name || `${pupil.first_name} ${pupil.last_name || ''}`.trim()
    pupilFirstName.value = pupil.first_name
    defaultCentre.value = pupil.test_centre || null
  } catch (e) {
    loadError.value = extractApiError(e, 'Could not load the fault sheet.')
  }
})

async function onSave() {
  if (!sheet.value) return
  saving.value = true
  saveError.value = ''
  try {
    const payload = sheet.value.toPayload(pupilId.value)
    const saved = await create(payload)
    await navigateTo(`/practical-tests/${saved.id}`)
  } catch (e) {
    saveError.value = extractApiError(e, 'Could not save that test result.')
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-8);
  margin-top: var(--spacing-16);
}
</style>
