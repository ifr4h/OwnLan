<script setup lang="ts">
const route = useRoute()
const contentId = computed(() => {
  const n = Number(route.params.id)
  return Number.isFinite(n) && n > 0 ? n : null
})
</script>

<template>
  <LearningContentEditor :content-id="contentId" />
</template>
