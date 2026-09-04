<template>
  <svg
    v-if="name === 'home'"
    width="20"
    height="20"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M4 10.5L12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5z"
      stroke="currentColor"
      stroke-width="1.6"
      stroke-linejoin="round"
    />
  </svg>
  <svg
    v-else-if="name === 'lessons'"
    width="20"
    height="20"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <rect x="4" y="5" width="16" height="15" rx="2" stroke="currentColor" stroke-width="1.6" />
    <path d="M8 3v4M16 3v4M4 10h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
  </svg>
  <svg
    v-else-if="name === 'progress'"
    width="20"
    height="20"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M5 19V11M12 19V5M19 19v-7"
      stroke="currentColor"
      stroke-width="1.8"
      stroke-linecap="round"
    />
  </svg>
  <svg
    v-else-if="name === 'routes'"
    width="20"
    height="20"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M6 18c0-3 2-5 5-5h2c3 0 5-2 5-5"
      stroke="currentColor"
      stroke-width="1.6"
      stroke-linecap="round"
    />
    <circle cx="6" cy="18" r="2.2" stroke="currentColor" stroke-width="1.6" />
    <circle cx="18" cy="6" r="2.2" stroke="currentColor" stroke-width="1.6" />
  </svg>
  <svg
    v-else-if="name === 'learn'"
    width="20"
    height="20"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"
      stroke="currentColor"
      stroke-width="1.6"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>
  <svg
    v-else-if="name === 'money'"
    width="20"
    height="20"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <rect x="3" y="6" width="18" height="12" rx="2" stroke="currentColor" stroke-width="1.6" />
    <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.6" />
  </svg>
  <svg
    v-else-if="name === 'practice'"
    width="20"
    height="20"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.6" />
    <path d="M12 8v4l3 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
  </svg>
  <svg
    v-else-if="name === 'test'"
    width="20"
    height="20"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M9 11l2 2 4-4M7 4h10l1 4H6l1-4z"
      stroke="currentColor"
      stroke-width="1.6"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
    <rect x="5" y="8" width="14" height="12" rx="2" stroke="currentColor" stroke-width="1.6" />
  </svg>
  <svg
    v-else-if="name === 'journey'"
    width="20"
    height="20"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <path
      d="M4 18l4-6 4 3 4-8 4 5"
      stroke="currentColor"
      stroke-width="1.6"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>
  <svg
    v-else
    width="20"
    height="20"
    viewBox="0 0 24 24"
    fill="none"
    aria-hidden="true"
  >
    <circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.6" />
    <path
      d="M5 20c0-3.5 3.1-6 7-6s7 2.5 7 6"
      stroke="currentColor"
      stroke-width="1.6"
      stroke-linecap="round"
    />
  </svg>
</template>

<script setup lang="ts">
import type { PortalNavIcon } from '~/utils/portalNav'

defineProps<{ name: PortalNavIcon }>()
</script>
