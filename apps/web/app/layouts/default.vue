<template>
  <div
    class="app"
    :data-quick="quickOpen ? 'open' : 'closed'"
    :data-sidebar="sidebarCollapsed ? 'collapsed' : 'expanded'"
  >
    <SyncStatus />

    <!-- Desktop sidebar -->
    <aside class="app__sidebar" aria-label="Primary">
      <div class="app__side-top">
        <NuxtLink to="/today" class="app__brand" :title="sidebarCollapsed ? 'OwnLane' : undefined">
          <OlBrand :compact="sidebarCollapsed" :show-wordmark="!sidebarCollapsed" />
        </NuxtLink>

        <button
          class="app__collapse"
          type="button"
          :aria-label="sidebarCollapsed ? 'Expand menu' : 'Collapse menu'"
          :aria-expanded="!sidebarCollapsed"
          :title="sidebarCollapsed ? 'Expand menu' : 'Collapse menu'"
          @click="toggleSidebar"
        >
          <OlIcon name="chevron-right" :size="16" class="app__collapse-icon" />
        </button>
      </div>

      <nav class="app__side-nav">
        <button
          class="app__side-link app__search-btn"
          type="button"
          title="Search"
          @click="openSearch"
        >
          <OlIcon name="search" :size="20" />
          <span class="app__side-label">Search</span>
          <kbd class="app__kbd">⌘K</kbd>
        </button>
        <NuxtLink
          v-for="item in primaryNav"
          :key="item.to"
          :to="item.to"
          class="app__side-link"
          :title="item.label"
        >
          <OlIcon :name="item.icon" :size="20" />
          <span class="app__side-label">{{ item.label }}</span>
        </NuxtLink>
      </nav>

      <div class="app__side-foot">
        <NuxtLink
          to="/settings"
          class="app__side-link app__side-link--muted"
          title="Settings"
        >
          <OlIcon name="settings" :size="20" />
          <span class="app__side-label">Settings</span>
        </NuxtLink>

        <div v-if="me" class="app__account">
          <p class="app__account-name">{{ firstName }}</p>
          <button
            class="app__signout"
            type="button"
            :disabled="loggingOut"
            :title="loggingOut ? 'Signing out…' : 'Sign out'"
            @click="onLogout"
          >
            <OlIcon name="logout" :size="16" />
            <span class="app__side-label">{{ loggingOut ? '…' : 'Sign out' }}</span>
          </button>
        </div>
      </div>
    </aside>

    <div class="app__frame">
      <!-- Mobile top bar -->
      <header class="app__top">
        <NuxtLink to="/today" class="app__brand app__brand--mobile">
          <OlBrand compact />
        </NuxtLink>
        <button
          class="ol-btn ol-btn--icon app__search-btn-mobile"
          type="button"
          aria-label="Search OwnLane"
          @click="openSearch"
        >
          <OlIcon name="search" :size="20" />
        </button>
        <NuxtLink
          to="/settings"
          class="ol-btn ol-btn--icon app__settings-btn-mobile"
          aria-label="Settings"
        >
          <OlIcon name="settings" :size="20" />
        </NuxtLink>
        <button
          class="ol-btn ol-btn--icon app__quick-btn"
          type="button"
          aria-label="Quick add"
          @click="quickOpen = !quickOpen"
        >
          <OlIcon name="add" :size="20" />
        </button>
      </header>

      <p v-if="logoutWarning" class="app__warn" role="alert">{{ logoutWarning }}</p>

      <main class="app__main">
        <slot />
      </main>
    </div>

    <!-- Mobile bottom nav -->
    <nav class="app__bottom" aria-label="Primary">
      <NuxtLink
        v-for="item in primaryNav"
        :key="item.to"
        :to="item.to"
        class="app__tab"
      >
        <OlIcon :name="item.icon" :size="20" />
        <span>{{ item.label }}</span>
      </NuxtLink>
      <button
        class="app__tab app__tab--add"
        type="button"
        aria-label="Quick add"
        @click="quickOpen = !quickOpen"
      >
        <span class="app__tab-add-mark">
          <OlIcon name="add" :size="18" />
        </span>
        <span>New</span>
      </button>
    </nav>

    <!-- Quick add sheet -->
    <div
      v-if="quickOpen"
      class="quick"
      role="dialog"
      aria-modal="true"
      aria-label="Create new"
    >
      <button class="quick__backdrop" type="button" aria-label="Close" @click="quickOpen = false" />
      <div class="quick__sheet">
        <div class="quick__head">
          <p class="ol-eyebrow">Create</p>
          <button class="ol-btn ol-btn--icon" type="button" aria-label="Close" @click="quickOpen = false">
            <OlIcon name="close" :size="18" />
          </button>
        </div>
        <div class="quick__grid">
          <NuxtLink to="/teaching" class="quick__item" @click="quickOpen = false">
            <OlIcon name="lesson" :size="22" />
            <span>Teaching</span>
          </NuxtLink>
          <NuxtLink to="/lessons/new" class="quick__item" @click="quickOpen = false">
            <OlIcon name="lesson" :size="22" />
            <span>Lesson</span>
          </NuxtLink>
          <NuxtLink to="/pupils/new" class="quick__item" @click="quickOpen = false">
            <OlIcon name="pupils" :size="22" />
            <span>Pupil</span>
          </NuxtLink>
          <NuxtLink to="/accounts/payments" class="quick__item" @click="quickOpen = false">
            <OlIcon name="payment" :size="22" />
            <span>Payment</span>
          </NuxtLink>
          <NuxtLink to="/accounts/expenses" class="quick__item" @click="quickOpen = false">
            <OlIcon name="expense" :size="22" />
            <span>Expense</span>
          </NuxtLink>
        </div>
      </div>
    </div>

    <GlobalSearchDialog v-model:open="searchOpen" />
  </div>
</template>

<script setup lang="ts">
const SIDEBAR_KEY = 'ownlane.sidebar.collapsed'

const { me, logout } = useAuth()
const { clearLocalForLogout, runSync, online } = useOfflineTeaching()
const loggingOut = ref(false)
const logoutWarning = ref('')
const quickOpen = ref(false)
const searchOpen = ref(false)
const sidebarCollapsed = ref(false)

function openSearch() {
  searchOpen.value = true
}

function toggleSidebar() {
  sidebarCollapsed.value = !sidebarCollapsed.value
  if (import.meta.client) {
    localStorage.setItem(SIDEBAR_KEY, sidebarCollapsed.value ? '1' : '0')
  }
}

function onGlobalKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    openSearch()
  }
}

onMounted(() => {
  if (import.meta.client) {
    sidebarCollapsed.value = localStorage.getItem(SIDEBAR_KEY) === '1'
    window.addEventListener('keydown', onGlobalKeydown)
  }
})

onBeforeUnmount(() => {
  if (import.meta.client) {
    window.removeEventListener('keydown', onGlobalKeydown)
  }
})

const primaryNav = [
  { to: '/today', label: 'Today', icon: 'today' as const },
  { to: '/pupils', label: 'Pupils', icon: 'pupils' as const },
  { to: '/teaching', label: 'Teaching', icon: 'lesson' as const },
  { to: '/lessons', label: 'Diary', icon: 'diary' as const },
  { to: '/accounts', label: 'Accounts', icon: 'accounts' as const },
]

const firstName = computed(() => {
  const name = me.value?.user.name?.trim() || ''
  return name.split(/\s+/)[0] || 'You'
})

watch(quickOpen, (open) => {
  if (import.meta.client) {
    document.body.style.overflow = open ? 'hidden' : ''
  }
})

onBeforeUnmount(() => {
  if (import.meta.client) document.body.style.overflow = ''
})

async function onLogout() {
  loggingOut.value = true
  logoutWarning.value = ''
  try {
    if (online.value) {
      await runSync(false)
    }
    const result = await clearLocalForLogout()
    if (result.blocked) {
      logoutWarning.value = result.pending === 1
        ? '1 lesson update is still waiting to sync. Connect, then sign out.'
        : `${result.pending} lesson updates are still waiting to sync. Connect, then sign out.`
      return
    }
    await logout()
    await navigateTo('/login')
  } finally {
    loggingOut.value = false
  }
}
</script>

<style scoped>
.app {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: var(--surface-canvas);
}

.app__sidebar {
  display: none;
}

.app__top {
  position: sticky;
  top: 0;
  z-index: var(--z-nav);
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 56px;
  padding: 0 var(--spacing-16);
  background: rgba(255, 255, 255, 0.92);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--color-border);
}

.app__brand {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
  color: inherit;
}

.app__frame {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.app__main {
  flex: 1;
  width: 100%;
  padding: var(--spacing-20) var(--spacing-16) calc(88px + env(safe-area-inset-bottom));
}

.app__warn {
  margin: 0;
  padding: 10px 16px;
  background: var(--color-warning-wash);
  color: var(--color-warning);
  font-size: var(--text-meta);
}

.app__bottom {
  position: fixed;
  z-index: var(--z-nav);
  bottom: 0;
  left: 0;
  right: 0;
  display: grid;
  grid-template-columns: repeat(6, 1fr);
  gap: 2px;
  padding: 6px 4px calc(6px + env(safe-area-inset-bottom));
  background: rgba(255, 255, 255, 0.96);
  backdrop-filter: blur(12px);
  border-top: 1px solid var(--color-border);
}

.app__tab {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  min-height: 52px;
  padding: 4px 2px;
  border-radius: 14px;
  border: none;
  background: transparent;
  color: var(--color-muted);
  font-size: 11px;
  letter-spacing: -0.01em;
  transition: color var(--duration-fast) ease, background-color var(--duration-fast) ease;
}

.app__tab:hover {
  color: var(--color-ink-black);
  background: var(--surface-wash);
}

.app__tab.router-link-active {
  color: var(--color-ownlane-green);
  background: var(--color-success-wash);
}

.app__tab--add {
  color: var(--color-ink-black);
}

.app__tab-add-mark {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
}

.app__quick-btn {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  border-color: transparent;
}

/* Quick sheet */
.quick {
  position: fixed;
  inset: 0;
  z-index: calc(var(--z-nav) + 5);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.quick__backdrop {
  position: absolute;
  inset: 0;
  border: none;
  background: rgba(17, 17, 24, 0.35);
}

.quick__sheet {
  position: relative;
  width: min(440px, 100%);
  padding: 16px 16px calc(20px + env(safe-area-inset-bottom));
  background: var(--surface-card);
  border-radius: 24px 24px 0 0;
  box-shadow: var(--shadow-soft);
  animation: sheet-up var(--duration-med) var(--ease-out);
}

@keyframes sheet-up {
  from { transform: translateY(16px); opacity: 0.6; }
  to { transform: translateY(0); opacity: 1; }
}

.quick__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.quick__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.quick__item {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 10px;
  padding: 16px;
  border-radius: var(--radius-panel);
  background: var(--surface-wash);
  font-size: var(--text-body-sm);
  transition: background-color var(--duration-fast) ease, transform var(--duration-fast) ease;
}

.quick__item:hover {
  background: var(--color-frost-green);
}

.quick__item:active {
  transform: scale(0.98);
}

@media (min-width: 900px) {
  .app {
    flex-direction: row;
    align-items: stretch;
  }

  .app__sidebar {
    display: flex;
    position: sticky;
    top: 0;
    z-index: var(--z-sidebar);
    flex-direction: column;
    width: var(--sidebar-width);
    min-height: 100dvh;
    padding: 20px 14px;
    background: var(--surface-sidebar);
    border-right: 1px solid var(--color-border);
    flex-shrink: 0;
    transition: width var(--duration-med) var(--ease-out), padding var(--duration-med) var(--ease-out);
  }

  .app[data-sidebar='collapsed'] .app__sidebar {
    width: var(--sidebar-width-collapsed);
    padding: 16px 10px;
  }

  .app__side-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin: 0 8px 20px;
  }

  .app[data-sidebar='collapsed'] .app__side-top {
    flex-direction: column;
    margin: 0 0 16px;
    gap: 10px;
  }

  .app__brand {
    margin: 0;
    min-width: 0;
  }

  .app__collapse {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    flex-shrink: 0;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.7);
    color: var(--color-muted);
    cursor: pointer;
    transition: background-color var(--duration-fast) ease, color var(--duration-fast) ease;
  }

  .app__collapse:hover {
    background: var(--color-paper-white);
    color: var(--color-ink-black);
  }

  .app__collapse-icon {
    transition: transform var(--duration-med) var(--ease-out);
  }

  .app[data-sidebar='expanded'] .app__collapse-icon {
    transform: rotate(180deg);
  }

  .app__side-nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
  }

  .app__side-link {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 44px;
    padding: 10px 14px;
    border-radius: 14px;
    color: var(--color-ink-black);
    font-size: var(--text-body-sm);
    transition: background-color var(--duration-fast) ease, color var(--duration-fast) ease;
  }

  .app[data-sidebar='collapsed'] .app__side-link {
    justify-content: center;
    padding: 10px;
    gap: 0;
  }

  .app__side-link:hover {
    background: rgba(255, 255, 255, 0.65);
  }

  .app__side-link.router-link-active {
    background: var(--color-ownlane-green);
    color: var(--color-paper-white);
    box-shadow: none;
  }

  .app__side-link--muted {
    color: var(--color-muted);
  }

  .app__side-link--muted.router-link-active {
    color: var(--color-paper-white);
  }

  .app[data-sidebar='collapsed'] .app__side-label,
  .app[data-sidebar='collapsed'] .app__kbd,
  .app[data-sidebar='collapsed'] .app__account-name {
    display: none;
  }

  .app__search-btn {
    width: 100%;
    border: none;
    background: transparent;
    cursor: pointer;
    text-align: left;
  }

  .app[data-sidebar='collapsed'] .app__search-btn {
    text-align: center;
  }

  .app__kbd {
    margin-left: auto;
    font-size: 11px;
    padding: 2px 6px;
    border-radius: 6px;
    border: 1px solid var(--color-border);
    color: var(--color-muted);
    font-family: inherit;
  }

  .app__search-btn-mobile {
    margin-right: 4px;
  }

  .app__side-foot {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: auto;
    padding-top: 16px;
  }

  .app__account {
    padding: 12px 14px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.7);
  }

  .app[data-sidebar='collapsed'] .app__account {
    padding: 8px;
    display: flex;
    justify-content: center;
  }

  .app__account-name {
    font-size: var(--text-body-sm);
    margin-bottom: 6px;
  }

  .app__signout {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    background: transparent;
    color: var(--color-muted);
    font-size: var(--text-meta);
    padding: 0;
    min-height: 28px;
    cursor: pointer;
  }

  .app[data-sidebar='collapsed'] .app__signout {
    justify-content: center;
    width: 100%;
    min-height: 32px;
  }

  .app__signout:hover {
    color: var(--color-ink-black);
  }

  .app__top,
  .app__bottom {
    display: none;
  }

  .app__frame {
    flex: 1;
    min-width: 0;
  }

  .app__main {
    padding: 28px 32px 48px;
    max-width: none;
  }

  .quick {
    align-items: center;
  }

  .quick__sheet {
    border-radius: 24px;
    padding: 20px;
    animation: sheet-fade var(--duration-med) var(--ease-out);
  }

  @keyframes sheet-fade {
    from { transform: scale(0.98); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
  }
}
</style>
