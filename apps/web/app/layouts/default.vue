<template>
  <div class="shell">
    <header class="shell__top">
      <NuxtLink to="/" class="shell__brand">
        <span class="shell__mark" aria-hidden="true" />
        <span class="shell__wordmark">OwnLane</span>
      </NuxtLink>
    </header>

    <main class="shell__main">
      <slot />
    </main>

    <nav class="shell__nav" aria-label="Primary">
      <NuxtLink
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        class="shell__nav-link"
      >
        {{ item.label }}
      </NuxtLink>
    </nav>
  </div>
</template>

<script setup lang="ts">
const navItems = [
  { to: '/today', label: 'Today' },
  { to: '/pupils', label: 'Pupils' },
  { to: '/lessons', label: 'Lessons' },
] as const
</script>

<style scoped>
.shell {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  max-width: var(--page-max-width);
  margin: 0 auto;
}

.shell__top {
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  height: 64px;
  padding: 0 var(--spacing-20);
  background: var(--surface-card);
  border-bottom: 1px solid var(--color-frost-green);
}

.shell__brand {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-12);
}

.shell__mark {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--color-ownlane-green);
  flex-shrink: 0;
}

.shell__wordmark {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  line-height: var(--leading-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.shell__main {
  flex: 1;
  padding: var(--spacing-24) var(--spacing-20) calc(72px + var(--spacing-24));
}

.shell__nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--spacing-4);
  padding: var(--spacing-12) var(--spacing-16) calc(var(--spacing-12) + env(safe-area-inset-bottom));
  background: var(--surface-card);
  border-top: 1px solid var(--color-frost-green);
}

.shell__nav-link {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 44px;
  padding: var(--spacing-8) var(--spacing-12);
  border-radius: var(--radius-buttons);
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
  color: var(--color-ink-black);
  transition: background-color 120ms ease, color 120ms ease;
}

.shell__nav-link:hover {
  background: var(--surface-wash);
}

.shell__nav-link.router-link-active {
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  box-shadow: var(--shadow-button);
}

@media (min-width: 768px) {
  .shell__nav {
    position: sticky;
    bottom: auto;
    top: 64px;
    grid-template-columns: repeat(3, max-content);
    justify-content: start;
    gap: var(--spacing-8);
    padding: var(--spacing-12) var(--spacing-20);
    border-top: none;
    border-bottom: 1px solid var(--color-frost-green);
  }

  .shell__main {
    padding-bottom: var(--spacing-40);
  }
}
</style>
