<template>
  <div class="companion-shell">
    <header class="companion-shell__top">
      <NuxtLink to="/companion" class="companion-shell__brand">
        <OlBrand compact />
      </NuxtLink>
      <p class="companion-shell__role">{{ roleLabel }}</p>
    </header>
    <main class="companion-shell__main">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
const route = useRoute()
const roleLabel = computed(() => {
  if (route.path.includes('/login') || route.path.includes('/join')) return 'Companion'
  return 'Helping someone learn'
})
</script>

<style scoped>
.companion-shell {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  max-width: 720px;
  margin: 0 auto;
  background: var(--color-chalk-green, #F3F8F4);
}

.companion-shell__top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-12);
  padding: var(--spacing-16) var(--spacing-20);
  position: sticky;
  top: 0;
  z-index: 10;
  background: color-mix(in srgb, var(--color-chalk-green, #F3F8F4) 92%, transparent);
  backdrop-filter: blur(8px);
}

.companion-shell__brand {
  display: inline-flex;
  align-items: center;
  text-decoration: none;
  color: inherit;
  min-height: 44px;
}

.companion-shell__role {
  margin: 0;
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-muted);
}

.companion-shell__main {
  flex: 1;
  padding: 0 var(--spacing-20) var(--spacing-32);
}
</style>
