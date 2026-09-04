<template>
  <div
    class="portal-main-grid"
    :class="{
      'portal-main-grid--split': variant === 'split',
      'portal-main-grid--master-detail': variant === 'master-detail',
      'portal-main-grid--learn': variant === 'learn',
    }"
  >
    <slot />
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    variant?: 'stack' | 'split' | 'master-detail' | 'learn'
  }>(),
  { variant: 'stack' },
)
</script>
