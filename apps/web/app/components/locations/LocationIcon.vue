<script setup lang="ts">
import type { LocationIconName } from '~/composables/useLearnerLocations'
import { LOCATION_ICONS } from '~/composables/useLearnerLocations'

const props = withDefaults(defineProps<{
  name: LocationIconName | string
  size?: number
}>(), {
  size: 18,
})

const paths: Record<string, string> = {
  home: 'M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5z',
  work: 'M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M4 7h16v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7zM4 12h16',
  school: 'M22 10 12 5 2 10l10 5 10-5zM6 12.5v4.5c0 .5 2.5 2.5 6 2.5s6-2 6-2.5v-4.5M22 10v6',
  gym: 'M6.5 6.5v11M17.5 6.5v11M4 9.5h5M15 9.5h5M4 14.5h5M15 14.5h5M9 12h6',
  pin: 'M12 22s7-5.33 7-12a7 7 0 1 0-14 0c0 6.67 7 12 7 12zM12 11.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z',
  star: 'M12 3l2.4 5.4 5.9.5-4.5 3.9 1.4 5.7L12 15.8 6.8 18.5l1.4-5.7L3.7 8.9l5.9-.5L12 3z',
  train: 'M8 3h8a3 3 0 0 1 3 3v9H5V6a3 3 0 0 1 3-3zM5 15l-1.5 4M19 15l1.5 4M8 19h8M9 11h.01M15 11h.01',
  car: 'M5 11 6.5 6.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11M3 16v2a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1h12v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-2M3 16h18v-3a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3zM7 13.5h.01M17 13.5h.01',
}

const resolved = computed(() =>
  LOCATION_ICONS.includes(props.name as LocationIconName) ? props.name : 'pin',
)
</script>

<template>
  <svg
    class="loc-icon"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
    focusable="false"
  >
    <path
      :d="paths[resolved]"
      stroke="currentColor"
      stroke-width="1.7"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>
</template>

<style scoped>
.loc-icon {
  display: block;
  flex-shrink: 0;
}
</style>
