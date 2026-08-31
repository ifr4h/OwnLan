<template>
  <section class="panel">
    <p class="panel__eyebrow">Instructor app</p>
    <h1 class="panel__title">Your day, without the admin mess</h1>
    <p class="panel__copy">
      OwnLane keeps pupils, lessons and today’s teaching flow in one place.
      Authentication and the daily loop come next.
    </p>

    <div class="panel__status" role="status">
      <span class="panel__status-label">API</span>
      <span
        class="panel__status-value"
        :data-state="healthState"
      >
        {{ healthLabel }}
      </span>
    </div>

    <NuxtLink to="/today" class="panel__cta">
      Go to Today
      <span aria-hidden="true">→</span>
    </NuxtLink>
  </section>
</template>

<script setup lang="ts">
type HealthResponse = {
  status: string
  service: string
  database: string
}

const healthState = ref<'loading' | 'ok' | 'error'>('loading')
const healthLabel = computed(() => {
  if (healthState.value === 'loading') return 'Checking…'
  if (healthState.value === 'ok') return 'Connected'
  return 'Unavailable'
})

onMounted(async () => {
  try {
    const data = await $fetch<HealthResponse>('/api/health')
    healthState.value = data.status === 'ok' && data.database === 'ok' ? 'ok' : 'error'
  } catch {
    healthState.value = 'error'
  }
})
</script>

<style scoped>
.panel {
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--card-padding);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  max-width: 560px;
}

.panel__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  line-height: var(--leading-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  color: var(--color-ink-black);
  opacity: 0.6;
}

.panel__title {
  font-size: var(--text-heading-sm);
  line-height: var(--leading-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.panel__copy {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
  max-width: 40ch;
}

.panel__status {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-12);
  align-self: flex-start;
  padding: var(--spacing-8) var(--spacing-16);
  background: var(--surface-wash);
  border-radius: var(--radius-tags);
}

.panel__status-label {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  letter-spacing: var(--tracking-caption-mono);
  opacity: 0.7;
}

.panel__status-value[data-state='ok'] {
  color: var(--color-ownlane-green);
}

.panel__status-value[data-state='error'] {
  color: var(--color-marker-red);
}

.panel__cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-8);
  align-self: flex-start;
  margin-top: var(--spacing-8);
  min-height: 48px;
  padding: 12px 24px;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  font-size: var(--text-body-sm);
  box-shadow: var(--shadow-button);
}
</style>
