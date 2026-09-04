<script setup lang="ts">
const TeachingStudioWorkspace = defineAsyncComponent(
  () => import('~/components/teaching/TeachingStudioWorkspace.vue'),
)

const route = useRoute()
const template = computed(() => {
  const t = route.query.template
  return typeof t === 'string' && t ? t : 'blank'
})
const lessonId = computed(() => {
  const v = route.query.lessonId
  return typeof v === 'string' && v ? Number(v) : null
})
const momentId = computed(() => {
  const v = route.query.momentId
  return typeof v === 'string' && v ? Number(v) : null
})
</script>

<template>
  <ClientOnly>
    <TeachingStudioWorkspace
      :initial-template="template"
      :lesson-id="lessonId"
      :moment-id="momentId"
    />
    <template #fallback>
      <p class="ol-muted">Loading board…</p>
    </template>
  </ClientOnly>
</template>
