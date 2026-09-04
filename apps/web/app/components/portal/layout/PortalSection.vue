<template>
  <section class="portal-section" :aria-labelledby="title ? titleId : undefined">
    <h2 v-if="title" :id="titleId" class="portal-section__title">{{ title }}</h2>
    <slot />
  </section>
</template>

<script setup lang="ts">
const props = defineProps<{ title?: string }>()
const titleId = computed(() =>
  props.title ? `portal-section-${props.title.replace(/\s+/g, '-').toLowerCase()}` : undefined,
)
</script>
