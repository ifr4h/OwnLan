<script setup lang="ts">
import type { WeatherKind } from '~/utils/weather/openMeteo'

const props = withDefaults(defineProps<{
  kind: WeatherKind
  size?: number
}>(), {
  size: 22,
})
</script>

<template>
  <svg
    class="wx-icon"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
    focusable="false"
    :data-kind="kind"
  >
    <!-- Clear / bright -->
    <g v-if="kind === 'clear'">
      <circle cx="12" cy="12" r="4" fill="#F5B942" />
      <path
        d="M12 3v2.2M12 18.8V21M3 12h2.2M18.8 12H21M5.6 5.6l1.6 1.6M16.8 16.8l1.6 1.6M5.6 18.4l1.6-1.6M16.8 7.2l1.6-1.6"
        stroke="#F5B942"
        stroke-width="1.75"
        stroke-linecap="round"
      />
    </g>

    <!-- Partly cloudy -->
    <g v-else-if="kind === 'partly_cloudy'">
      <circle cx="9" cy="9" r="3.2" fill="#F5B942" />
      <path
        d="M8.2 17.5h7.8a3.2 3.2 0 0 0 .3-6.4 4.4 4.4 0 0 0-8.4 1.4 2.8 2.8 0 0 0 .3 5z"
        fill="#9BB0C4"
      />
    </g>

    <!-- Cloudy -->
    <g v-else-if="kind === 'cloudy'">
      <path
        d="M7.5 17.5h9.2a3.5 3.5 0 0 0 .4-7 4.8 4.8 0 0 0-9.2 1.6A3 3 0 0 0 7.5 17.5z"
        fill="#8FA0B3"
      />
    </g>

    <!-- Fog -->
    <g v-else-if="kind === 'fog'">
      <path
        d="M7 11h10M5.5 14h13M7.5 17h9"
        stroke="#8FA0B3"
        stroke-width="1.75"
        stroke-linecap="round"
      />
      <path
        d="M8 9.5h8a2.8 2.8 0 0 0 .2-5.5 3.8 3.8 0 0 0-7.3 1.2A2.4 2.4 0 0 0 8 9.5z"
        fill="#B7C2CE"
      />
    </g>

    <!-- Drizzle / light rain -->
    <g v-else-if="kind === 'drizzle' || kind === 'light_rain'">
      <path
        d="M7.5 12.5h9.2a3.5 3.5 0 0 0 .4-7 4.8 4.8 0 0 0-9.2 1.6A3 3 0 0 0 7.5 12.5z"
        fill="#8FA0B3"
      />
      <path
        d="M9 15.2v2.2M12 14.5v2.2M15 15.2v2.2"
        stroke="#4A90C8"
        stroke-width="1.6"
        stroke-linecap="round"
      />
    </g>

    <!-- Rain / heavy rain -->
    <g v-else-if="kind === 'rain' || kind === 'heavy_rain'">
      <path
        d="M7.5 11.5h9.2a3.5 3.5 0 0 0 .4-7 4.8 4.8 0 0 0-9.2 1.6A3 3 0 0 0 7.5 11.5z"
        fill="#6E8499"
      />
      <path
        d="M8.5 14.2v3.2M12 13.5v3.8M15.5 14.2v3.2"
        :stroke="kind === 'heavy_rain' ? '#2F6FA8' : '#4A90C8'"
        stroke-width="1.75"
        stroke-linecap="round"
      />
    </g>

    <!-- Wintry -->
    <g v-else-if="kind === 'wintry'">
      <path
        d="M7.5 11.5h9.2a3.5 3.5 0 0 0 .4-7 4.8 4.8 0 0 0-9.2 1.6A3 3 0 0 0 7.5 11.5z"
        fill="#8FA0B3"
      />
      <path
        d="M9 14.5l.8.8M9.8 14.5l-.8.8M12 14l1 1M13 14l-1 1M15 14.5l.8.8M15.8 14.5l-.8.8M9.4 17.2l.8.8M10.2 17.2l-.8.8M14.6 17.2l.8.8M15.4 17.2l-.8.8"
        stroke="#6E9BC0"
        stroke-width="1.4"
        stroke-linecap="round"
      />
    </g>

    <!-- Thunder -->
    <g v-else-if="kind === 'thunder'">
      <path
        d="M7.5 11h9.2a3.5 3.5 0 0 0 .4-7 4.8 4.8 0 0 0-9.2 1.6A3 3 0 0 0 7.5 11z"
        fill="#6E8499"
      />
      <path d="M12.2 12.2 10 16.2h2.2l-1.2 4.2 4-5.6h-2.4l1.6-2.6z" fill="#F5B942" />
    </g>

    <!-- Windy -->
    <g v-else>
      <path
        d="M4 9.5h11a2.5 2.5 0 1 0-2.5-2.5M4 13h14a2.5 2.5 0 1 1-2.5 2.5M4 16.5h8"
        stroke="#8FA0B3"
        stroke-width="1.75"
        stroke-linecap="round"
      />
    </g>
  </svg>
</template>

<style scoped>
.wx-icon {
  display: block;
  flex-shrink: 0;
}
</style>
