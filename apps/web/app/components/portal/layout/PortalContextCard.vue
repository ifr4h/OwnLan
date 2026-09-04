<template>
  <article class="portal-context-card">
    <h3 v-if="title" class="portal-context-card__title">{{ title }}</h3>
    <div class="portal-context-card__body">
      <slot />
    </div>
    <NuxtLink v-if="actionTo && actionLabel" :to="actionTo" class="portal-context-card__action">
      {{ actionLabel }}
      <span aria-hidden="true">→</span>
    </NuxtLink>
  </article>
</template>

<script setup lang="ts">
defineProps<{
  title?: string
  actionTo?: string
  actionLabel?: string
}>()
</script>
