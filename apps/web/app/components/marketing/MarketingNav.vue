<template>
  <header class="mnav" :data-open="menuOpen ? 'yes' : 'no'">
    <div class="mnav__inner marketing-shell">
      <NuxtLink to="/" class="mnav__brand" @click="menuOpen = false">
        <OlBrand />
        <span class="mnav__beta">{{ site.betaLabel }}</span>
      </NuxtLink>

      <nav class="mnav__desktop" aria-label="Main">
        <div
          v-for="item in site.nav"
          :key="item.label"
          class="mnav__item"
        >
          <template v-if="'children' in item && item.children">
            <button
              type="button"
              class="mnav__trigger"
              :aria-expanded="productOpen"
              aria-controls="mnav-product"
              @click="productOpen = !productOpen"
            >
              {{ item.label }}
            </button>
            <div
              v-show="productOpen"
              id="mnav-product"
              class="mnav__dropdown"
              role="menu"
            >
              <NuxtLink
                v-for="child in item.children"
                :key="child.to"
                :to="child.to"
                class="mnav__dropdown-link"
                role="menuitem"
                @click="productOpen = false"
              >
                {{ child.label }}
              </NuxtLink>
            </div>
          </template>
          <NuxtLink
            v-else-if="'to' in item"
            :to="item.to"
            class="mnav__link"
          >
            {{ item.label }}
          </NuxtLink>
        </div>
      </nav>

      <div class="mnav__actions">
        <NuxtLink :to="site.cta.signInTo" class="mnav__signin">
          {{ site.cta.signIn }}
        </NuxtLink>
        <NuxtLink :to="site.cta.primaryTo" class="marketing-btn marketing-btn--primary mnav__cta">
          {{ site.cta.primary }}
        </NuxtLink>
        <button
          type="button"
          class="mnav__toggle"
          :aria-expanded="menuOpen"
          aria-controls="mnav-mobile"
          @click="menuOpen = !menuOpen"
        >
          <span class="sr-only">Menu</span>
          <span aria-hidden="true">{{ menuOpen ? '×' : '☰' }}</span>
        </button>
      </div>
    </div>

    <nav
      v-show="menuOpen"
      id="mnav-mobile"
      class="mnav__mobile"
      aria-label="Mobile"
    >
      <div class="mnav__mobile-inner marketing-shell">
        <p class="mnav__mobile-label">Product</p>
        <NuxtLink
          v-for="child in productLinks"
          :key="child.to"
          :to="child.to"
          class="mnav__mobile-link"
          @click="menuOpen = false"
        >
          {{ child.label }}
        </NuxtLink>
        <NuxtLink to="/instructors" class="mnav__mobile-link" @click="menuOpen = false">
          For instructors
        </NuxtLink>
        <NuxtLink to="/learners" class="mnav__mobile-link" @click="menuOpen = false">
          For learners
        </NuxtLink>
        <NuxtLink to="/pricing" class="mnav__mobile-link" @click="menuOpen = false">
          Pricing
        </NuxtLink>
      </div>
    </nav>
  </header>
</template>

<script setup lang="ts">
import { marketingSite as site } from '~/marketing/content/site'

const menuOpen = ref(false)
const productOpen = ref(false)

const productLinks = computed(() => {
  const product = site.nav.find(item => 'children' in item)
  return product && 'children' in product ? product.children : []
})

onMounted(() => {
  const close = (e: MouseEvent) => {
    const target = e.target as HTMLElement
    if (!target.closest('.mnav__item')) {
      productOpen.value = false
    }
  }
  document.addEventListener('click', close)
  onUnmounted(() => document.removeEventListener('click', close))
})
</script>

<style scoped>
.mnav {
  position: sticky;
  top: 0;
  z-index: var(--z-nav);
  background: color-mix(in srgb, var(--color-chalk-green) 92%, transparent);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid var(--color-border);
}

.mnav__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-16);
  min-height: 64px;
}

.mnav__brand {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-8);
  text-decoration: none;
  color: inherit;
}

.mnav__beta {
  font-family: var(--font-martian-mono);
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  padding: 2px 8px;
  border-radius: var(--radius-tags);
  background: var(--color-frost-green);
  color: var(--color-ownlane-green);
}

.mnav__desktop {
  display: none;
  align-items: center;
  gap: var(--spacing-8);
}

.mnav__item {
  position: relative;
}

.mnav__link,
.mnav__trigger {
  padding: 8px 12px;
  font-size: var(--text-body-sm);
  background: none;
  border: none;
  cursor: pointer;
  color: inherit;
  text-decoration: none;
}

.mnav__dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0;
  min-width: 220px;
  padding: var(--spacing-8);
  background: var(--color-paper-white);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-panel);
  box-shadow: var(--shadow-soft);
}

.mnav__dropdown-link {
  display: block;
  padding: 10px 12px;
  border-radius: var(--radius-small);
  font-size: var(--text-body-sm);
  text-decoration: none;
  color: inherit;
}

.mnav__dropdown-link:hover {
  background: var(--color-frost-green);
}

.mnav__actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-8);
}

.mnav__signin {
  display: none;
  font-size: var(--text-body-sm);
  text-decoration: none;
  color: inherit;
}

.mnav__cta {
  display: none;
  min-height: 40px;
  padding: 8px 16px;
  font-size: 14px;
}

.mnav__toggle {
  display: inline-grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-small);
  background: var(--color-paper-white);
  font-size: 18px;
  cursor: pointer;
}

.mnav__mobile {
  border-top: 1px solid var(--color-border);
  background: var(--color-paper-white);
  padding-block: var(--spacing-16);
}

.mnav__mobile-inner {
  display: grid;
  gap: var(--spacing-4);
}

.mnav__mobile-label {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
  margin-top: var(--spacing-8);
}

.mnav__mobile-link {
  display: block;
  padding: 12px 0;
  font-size: var(--text-body-sm);
  text-decoration: none;
  color: inherit;
  border-bottom: 1px solid var(--color-border);
}

@media (min-width: 1024px) {
  .mnav__desktop {
    display: flex;
  }

  .mnav__signin,
  .mnav__cta {
    display: inline-flex;
  }

  .mnav__toggle,
  .mnav__mobile {
    display: none;
  }
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
</style>
