<template>
  <div class="auth">
    <header class="auth__brand">
      <span class="auth__mark" aria-hidden="true" />
      <span class="auth__wordmark">OwnLane</span>
    </header>
    <main class="auth__main">
      <slot />
    </main>
  </div>
</template>

<style scoped>
.auth {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  background: var(--surface-canvas);
}

.auth__brand {
  display: flex;
  align-items: center;
  gap: var(--spacing-12);
  padding: var(--spacing-24) var(--spacing-20);
}

.auth__mark {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--color-ownlane-green);
}

.auth__wordmark {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.auth__main {
  flex: 1;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: var(--spacing-8) var(--spacing-20) var(--spacing-40);
}
</style>
