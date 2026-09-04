<script setup lang="ts">
const TeachingStudioWorkspace = defineAsyncComponent(
  () => import('~/components/teaching/TeachingStudioWorkspace.vue'),
)

const route = useRoute()
const id = computed(() => Number(route.params.id))
</script>

<template>
  <ClientOnly>
    <TeachingStudioWorkspace :resource-id="id" />
    <template #fallback>
      <p class="ol-muted">Loading board…</p>
    </template>
  </ClientOnly>
</template>
