<template>
  <aside class="sidebar" aria-label="Learner navigation">
    <NuxtLink to="/portal" class="sidebar__brand">
      <OlBrand />
    </NuxtLink>

    <nav class="sidebar__nav" aria-label="Primary">
      <NuxtLink
        v-for="item in primaryItems"
        :key="item.to"
        :to="item.to"
        class="sidebar__link"
        :class="{ 'sidebar__link--active': isActive(item) }"
      >
        <PortalNavIcon :name="item.icon" />
        <span>{{ t(item.labelKey) }}</span>
      </NuxtLink>
    </nav>

    <nav class="sidebar__secondary" aria-label="Secondary">
      <NuxtLink
        v-for="item in secondaryItems"
        :key="item.to"
        :to="item.to"
        class="sidebar__link sidebar__link--secondary"
        :class="{ 'sidebar__link--active': isActive(item) }"
      >
        <PortalNavIcon :name="item.icon" />
        <span>{{ t(item.labelKey) }}</span>
      </NuxtLink>
    </nav>

    <div class="sidebar__footer">
      <button
        type="button"
        class="sidebar__signout"
        :disabled="loggingOut"
        @click="onLogout"
      >
        {{ loggingOut ? '…' : t('nav.signOut') }}
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import {
  isPortalNavActive,
  portalPrimaryNav,
  portalSecondaryNav,
  type PortalNavItem,
} from '~/utils/portalNav'

const { t } = usePortalI18n()
const route = useRoute()
const { logout } = usePortalAuth()

const primaryItems = portalPrimaryNav
const secondaryItems = portalSecondaryNav
const loggingOut = ref(false)

function isActive(item: PortalNavItem): boolean {
  return isPortalNavActive(item, route.path)
}

async function onLogout() {
  loggingOut.value = true
  try {
    await logout()
    await navigateTo('/login')
  } finally {
    loggingOut.value = false
  }
}
</script>

<style scoped>
.sidebar {
  display: flex;
  flex-direction: column;
  width: var(--sidebar-width);
  min-height: 100dvh;
  padding: var(--spacing-24) var(--spacing-16);
  background: var(--surface-sidebar);
  border-right: 1px solid var(--color-border);
  position: sticky;
  top: 0;
  flex-shrink: 0;
}

.sidebar__brand {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
  color: inherit;
  padding: var(--spacing-4) var(--spacing-8);
  margin-bottom: var(--spacing-28);
}

.sidebar__nav {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
  flex: 1;
}

.sidebar__secondary {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-4);
  padding-top: var(--spacing-16);
  margin-top: var(--spacing-16);
  border-top: 1px solid var(--color-border);
}

.sidebar__link {
  display: flex;
  align-items: center;
  gap: var(--spacing-12);
  min-height: 44px;
  padding: 0 var(--spacing-12);
  border-radius: var(--radius-small);
  text-decoration: none;
  color: rgba(17, 17, 24, 0.72);
  font-size: var(--text-body-sm);
  transition: background var(--duration-fast) var(--ease-out),
    color var(--duration-fast) var(--ease-out);
}

.sidebar__link:hover,
.sidebar__link:focus-visible {
  background: color-mix(in srgb, var(--color-ownlane-green) 8%, transparent);
  color: var(--color-ink-black);
}

.sidebar__link--active {
  background: var(--color-paper-white);
  color: var(--color-ownlane-green);
  box-shadow: 0 1px 0 var(--color-border);
}

.sidebar__link--secondary {
  color: var(--color-muted);
}

.sidebar__footer {
  padding-top: var(--spacing-16);
  margin-top: auto;
}

.sidebar__signout {
  width: 100%;
  min-height: 40px;
  padding: 0 var(--spacing-12);
  border: none;
  border-radius: var(--radius-small);
  background: transparent;
  color: var(--color-muted);
  font: inherit;
  font-size: var(--text-body-sm);
  text-align: left;
  cursor: pointer;
}

.sidebar__signout:hover:not(:disabled),
.sidebar__signout:focus-visible:not(:disabled) {
  color: var(--color-ink-black);
  background: color-mix(in srgb, var(--color-ownlane-green) 6%, transparent);
}
</style>
