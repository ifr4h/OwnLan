<template>
  <div class="auth">
    <header class="auth__brand">
      <OlBrand />
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
  background:
    radial-gradient(ellipse 80% 50% at 50% -10%, rgba(226, 240, 231, 0.9), transparent 70%),
    var(--surface-canvas);
}

.auth__brand {
  display: flex;
  align-items: center;
  padding: var(--spacing-24) var(--spacing-20);
}

.auth__main {
  flex: 1;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: var(--spacing-8) var(--spacing-20) var(--spacing-40);
}
</style>
