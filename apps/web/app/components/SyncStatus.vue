<template>
  <div
    v-if="authBlocked || banner !== 'hidden'"
    class="sync"
    aria-live="polite"
  >
    <p v-if="authBlocked" class="sync__chip sync__chip--warn">
      Sign in again to sync saved changes
    </p>
    <p v-else-if="banner === 'offline'" class="sync__chip">
      Offline — changes will sync automatically
      <span v-if="pendingCount > 0" class="sync__pending">· {{ pendingCount }} waiting</span>
    </p>
    <p v-else-if="banner === 'syncing'" class="sync__chip">
      Back online — syncing…
    </p>
    <p v-else-if="banner === 'back_online'" class="sync__chip sync__chip--ok">
      Synced
    </p>
  </div>
</template>

<script setup lang="ts">
const { banner, pendingCount, authBlocked, bindConnectivity } = useOfflineTeaching()

let unbind: (() => void) | undefined

onMounted(() => {
  unbind = bindConnectivity()
})

onBeforeUnmount(() => {
  unbind?.()
})
</script>

<style scoped>
/* Overlay — never push page content (no layout shift on diary). */
.sync {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 90;
  pointer-events: none;
}

.sync__chip {
  pointer-events: auto;
  margin: 0;
  padding: 8px 16px;
  font-size: var(--text-meta);
  line-height: var(--leading-meta);
  background: var(--surface-wash);
  border-bottom: 1px solid var(--color-border);
  color: var(--color-ink-black);
  box-shadow: 0 8px 24px rgba(32, 21, 21, 0.08);
}

.sync__chip--ok {
  background: var(--color-success-wash);
  color: var(--color-success);
  border-bottom-color: transparent;
}

.sync__chip--warn {
  background: var(--color-warning-wash);
  color: var(--color-warning);
  border-bottom-color: transparent;
}

.sync__pending {
  opacity: 0.75;
}
</style>
