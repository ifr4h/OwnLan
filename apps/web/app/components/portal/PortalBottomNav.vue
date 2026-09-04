<template>
  <div class="portal-bottom-nav portal-hide-desktop">
    <nav class="bnav" aria-label="Learner portal">
      <NuxtLink
        v-for="item in primaryItems"
        :key="item.to"
        :to="item.to"
        class="bnav__item"
        :class="{ 'bnav__item--active': isActive(item) }"
      >
        <span class="bnav__icon" aria-hidden="true">
          <PortalNavIcon :name="item.icon" />
        </span>
        <span class="bnav__label">{{ t(item.labelKey) }}</span>
      </NuxtLink>

      <button
        type="button"
        class="bnav__item"
        :class="{ 'bnav__item--active': moreOpen || moreActive }"
        :aria-expanded="moreOpen"
        aria-controls="portal-more-sheet"
        @click="moreOpen = !moreOpen"
      >
        <span class="bnav__icon" aria-hidden="true">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
            <circle cx="6" cy="12" r="1.6" fill="currentColor" />
            <circle cx="12" cy="12" r="1.6" fill="currentColor" />
            <circle cx="18" cy="12" r="1.6" fill="currentColor" />
          </svg>
        </span>
        <span class="bnav__label">{{ t('nav.more') }}</span>
      </button>
    </nav>

    <div
      v-if="moreOpen"
      id="portal-more-sheet"
      class="more"
      role="dialog"
      aria-modal="true"
      :aria-label="t('nav.more')"
    >
      <button class="more__backdrop" type="button" aria-label="Close" @click="moreOpen = false" />
      <div class="more__sheet">
        <p class="more__eyebrow">{{ t('nav.more') }}</p>
        <NuxtLink
          v-for="item in moreItems"
          :key="item.to"
          :to="item.to"
          class="more__link"
          @click="moreOpen = false"
        >
          <PortalNavIcon :name="item.icon" />
          {{ t(item.labelKey) }}
          <span class="more__chevron" aria-hidden="true">→</span>
        </NuxtLink>
        <button
          type="button"
          class="more__link more__link--btn"
          :disabled="loggingOut"
          @click="onLogout"
        >
          {{ loggingOut ? '…' : t('nav.signOut') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  isPortalNavActive,
  portalMobileMoreNav,
  portalMobilePrimaryNav,
  type PortalNavItem,
} from '~/utils/portalNav'

const { t } = usePortalI18n()
const route = useRoute()
const { logout } = usePortalAuth()
const moreOpen = ref(false)
const loggingOut = ref(false)

const primaryItems = portalMobilePrimaryNav
const moreItems = portalMobileMoreNav

const moreActive = computed(() =>
  moreItems.some(item => isActive(item)),
)

function isActive(item: PortalNavItem): boolean {
  return isPortalNavActive(item, route.path)
}

async function onLogout() {
  loggingOut.value = true
  try {
    await logout()
    await navigateTo('/login')
    moreOpen.value = false
  } finally {
    loggingOut.value = false
  }
}

watch(moreOpen, (open) => {
  if (import.meta.client) {
    document.body.style.overflow = open ? 'hidden' : ''
  }
})

onBeforeUnmount(() => {
  if (import.meta.client) document.body.style.overflow = ''
})
</script>

<style scoped>
.portal-bottom-nav {
  display: contents;
}

.bnav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 0;
  padding: 6px 4px calc(6px + env(safe-area-inset-bottom));
  background: var(--color-paper-white);
  border-top: 1px solid var(--color-border);
  z-index: var(--z-nav);
}

.bnav__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 2px;
  min-height: 52px;
  text-decoration: none;
  color: var(--color-muted);
  font-size: 11px;
  letter-spacing: -0.01em;
  border-radius: var(--radius-small);
  border: none;
  background: transparent;
  font: inherit;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}

.bnav__item--active {
  color: var(--color-ownlane-green);
}

.bnav__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 24px;
}

.bnav__label {
  line-height: 1.2;
}

.more {
  position: fixed;
  inset: 0;
  z-index: calc(var(--z-nav) + 5);
  display: flex;
  align-items: flex-end;
  justify-content: center;
}

.more__backdrop {
  position: absolute;
  inset: 0;
  border: none;
  background: rgba(17, 17, 24, 0.35);
}

.more__sheet {
  position: relative;
  width: min(440px, 100%);
  padding: 16px 16px calc(20px + env(safe-area-inset-bottom));
  background: var(--color-paper-white);
  border-radius: 24px 24px 0 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.more__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: 8px;
}

.more__link {
  display: flex;
  align-items: center;
  gap: var(--spacing-12);
  min-height: 52px;
  padding: 12px 4px;
  text-decoration: none;
  color: inherit;
  font-size: var(--text-body-sm);
  background: transparent;
  font: inherit;
  text-align: left;
  width: 100%;
  cursor: pointer;
  border-bottom: 1px solid var(--color-border);
}

.more__link--btn {
  border: none;
  margin-top: var(--spacing-8);
}

.more__chevron {
  margin-left: auto;
}

@media (prefers-reduced-motion: reduce) {
  .more__sheet {
    animation: none;
  }
}
</style>
