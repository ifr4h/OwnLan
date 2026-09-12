<script setup lang="ts">
/**
 * Airbnb-style pickup preview: muted map + floating address pill.
 */
const props = defineProps<{
  address: string
  label?: string | null
  mapsHref: string
}>()

const embedSrc = computed(() =>
  `https://maps.google.com/maps?q=${encodeURIComponent(props.address)}&z=15&output=embed`,
)
</script>

<template>
  <div class="pickup-map">
    <div class="pickup-map__frame">
      <iframe
        class="pickup-map__iframe"
        :src="embedSrc"
        :title="`Map for ${address}`"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade"
        tabindex="-1"
      />
      <div class="pickup-map__veil" aria-hidden="true" />

      <a
        class="pickup-map__expand"
        :href="mapsHref"
        target="_blank"
        rel="noopener noreferrer"
        :aria-label="`Open ${address} in Maps`"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path
            d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </a>

      <div class="pickup-map__pill">
        <span class="pickup-map__pin" aria-hidden="true">
          <OlIcon name="pin" :size="14" />
        </span>
        <p class="pickup-map__addr">{{ address }}</p>
        <span v-if="label" class="pickup-map__meta">{{ label }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pickup-map {
  width: 100%;
}

.pickup-map__frame {
  position: relative;
  height: 168px;
  border-radius: 20px;
  overflow: hidden;
  border: 1px solid var(--color-border);
  background: color-mix(in srgb, var(--color-border) 55%, var(--color-paper-white));
}

.pickup-map__iframe {
  position: absolute;
  inset: -12% -8% -28% -8%;
  width: 116%;
  height: 140%;
  border: 0;
  pointer-events: none;
}

.pickup-map__veil {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(
      to top,
      color-mix(in srgb, var(--color-paper-white) 35%, transparent) 0%,
      transparent 42%
    );
  pointer-events: none;
}

.pickup-map__expand {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 2;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 999px;
  background: var(--color-paper-white);
  color: var(--color-ink-black);
  box-shadow: 0 2px 8px color-mix(in srgb, var(--color-ink-black) 12%, transparent);
  text-decoration: none;
}

.pickup-map__expand:hover {
  background: var(--color-parchment);
}

.pickup-map__pill {
  position: absolute;
  left: 10px;
  right: 10px;
  bottom: 10px;
  z-index: 2;
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 8px 12px 8px 8px;
  border-radius: 999px;
  background: var(--color-paper-white);
  box-shadow: 0 4px 16px color-mix(in srgb, var(--color-ink-black) 12%, transparent);
}

.pickup-map__pin {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 999px;
  background: var(--color-bark);
  color: var(--color-paper-white);
  flex-shrink: 0;
}

.pickup-map__addr {
  margin: 0;
  flex: 1;
  min-width: 0;
  font-size: 13px;
  font-weight: 600;
  line-height: 1.25;
  color: var(--color-ink-black);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.pickup-map__meta {
  flex-shrink: 0;
  max-width: 36%;
  font-size: 12px;
  color: var(--color-muted);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
</style>
