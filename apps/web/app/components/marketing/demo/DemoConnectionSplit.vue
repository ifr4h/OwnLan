<template>
  <div class="connection">
    <div class="connection__panel">
      <p class="connection__role">Instructor</p>
      <div class="connection__box">
        <p class="connection__field">Next focus</p>
        <p class="connection__value">Larger roundabouts</p>
      </div>
      <div class="connection__box">
        <p class="connection__field">Shared resource</p>
        <p class="connection__value">Roundabout — third exit</p>
      </div>
    </div>
    <div class="connection__bridge" aria-hidden="true">
      <span>→</span>
    </div>
    <div class="connection__panel connection__panel--learner">
      <p class="connection__role">Learner portal</p>
      <div class="connection__box">
        <p class="connection__field">Next lesson</p>
        <p class="connection__value">Working on larger roundabouts</p>
      </div>
      <div class="connection__box">
        <p class="connection__field">From your last lesson</p>
        <p class="connection__value">Roundabout diagram</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.connection {
  display: grid;
  gap: var(--spacing-16);
  align-items: center;
}

@media (min-width: 768px) {
  .connection {
    grid-template-columns: 1fr auto 1fr;
  }
}

.connection__panel {
  background: var(--color-paper-white);
  border: 1px solid var(--color-border-strong);
  border-radius: var(--radius-panel);
  padding: var(--spacing-20);
}

.connection__panel--learner {
  background: linear-gradient(180deg, var(--color-frost-green), var(--color-chalk-green));
}

.connection__role {
  font-family: var(--font-martian-mono);
  font-size: 11px;
  text-transform: uppercase;
  color: var(--color-muted);
  margin-bottom: var(--spacing-12);
}

.connection__box + .connection__box {
  margin-top: var(--spacing-12);
}

.connection__field {
  font-size: 11px;
  color: var(--color-muted);
}

.connection__value {
  font-size: var(--text-body-sm);
  margin-top: 4px;
}

.connection__bridge {
  text-align: center;
  font-size: 24px;
  color: var(--color-ownlane-green);
}
</style>
