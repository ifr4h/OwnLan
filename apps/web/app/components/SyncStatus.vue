<template>
  <div class="sync" aria-live="polite">
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
.sync {
  min-height: 0;
}

.sync__chip {
  margin: 0;
  padding: 8px 16px;
  font-size: var(--text-meta);
  line-height: var(--leading-meta);
  background: var(--surface-wash);
  border-bottom: 1px solid var(--color-border);
  color: var(--color-ink-black);
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
