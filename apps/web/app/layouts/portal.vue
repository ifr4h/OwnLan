<template>
  <div class="portal-shell" :class="{ 'portal-shell--nav': showNav }">
    <PortalSidebar v-if="showNav" class="portal-hide-mobile" />

    <div class="portal-shell__frame">
      <header v-if="showNav" class="portal-shell__top portal-hide-desktop">
        <NuxtLink to="/portal" class="portal-shell__brand">
          <OlBrand compact />
        </NuxtLink>

        <div class="portal-shell__actions">
          <NuxtLink to="/portal/profile" class="portal-shell__profile" :aria-label="t('nav.profile')">
            <PortalNavIcon name="profile" />
          </NuxtLink>
        </div>
      </header>

      <p v-if="showStale" class="portal-shell__stale" role="status">
        {{ t('common.offlineStale') }}
        <span v-if="syncLabel"> · {{ t('common.lastSynced', { time: syncLabel }) }}</span>
      </p>

      <main class="portal-shell__main">
        <slot />
      </main>
    </div>

    <PortalBottomNav v-if="showNav" />
  </div>
</template>

<script setup lang="ts">
const { isAuthenticated } = usePortalAuth()
const { stale, readLastSyncLabel, bindConnectivity } = usePortal()
const { t } = usePortalI18n()
const route = useRoute()

const syncLabel = ref('')

const isPublic = computed(
  () => route.path === '/portal/login' || route.path === '/portal/join',
)

const showNav = computed(() => isAuthenticated.value && !isPublic.value)
const showStale = computed(() => showNav.value && stale.value)

let unbindConnectivity: (() => void) | undefined

onMounted(() => {
  unbindConnectivity = bindConnectivity()
  syncLabel.value = readLastSyncLabel()
})

onBeforeUnmount(() => {
  unbindConnectivity?.()
})

watch(stale, () => {
  syncLabel.value = readLastSyncLabel()
})
</script>

<style scoped>
.portal-shell {
  min-height: 100dvh;
  background: var(--surface-canvas);
}

.portal-shell--nav .portal-shell__main {
  padding-bottom: calc(72px + env(safe-area-inset-bottom) + var(--spacing-24));
}

.portal-shell__frame {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}

.portal-shell__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-16) var(--spacing-20);
  position: sticky;
  top: 0;
  z-index: calc(var(--z-nav) + 1);
  background: color-mix(in srgb, var(--surface-canvas) 92%, transparent);
  backdrop-filter: blur(8px);
}

.portal-shell__brand {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
  color: inherit;
  min-height: 44px;
}

.portal-shell__profile {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 44px;
  min-height: 44px;
  border-radius: var(--radius-buttons);
  color: var(--color-ink-black);
  text-decoration: none;
}

.portal-shell__stale {
  margin: 0 var(--spacing-20);
  padding: var(--spacing-8) var(--spacing-12);
  background: var(--color-warning-wash);
  color: var(--color-warning);
  border-radius: var(--radius-small);
  font-size: var(--text-meta);
}

.portal-shell__main {
  flex: 1;
  width: 100%;
  padding: 0 var(--spacing-20) var(--spacing-32);
}

@media (min-width: 768px) {
  .portal-shell__main {
    padding-left: var(--spacing-28);
    padding-right: var(--spacing-28);
  }
}

@media (min-width: 1024px) {
  .portal-shell--nav {
    display: flex;
  }

  .portal-shell--nav .portal-shell__main {
    padding: var(--spacing-24) var(--spacing-32) var(--spacing-48);
    max-width: var(--portal-max-width);
  }
}

@media (min-width: 1440px) {
  .portal-shell__main {
    padding-left: var(--spacing-40);
    padding-right: var(--spacing-40);
  }
}
</style>
