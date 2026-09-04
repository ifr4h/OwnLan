<template>
  <PortalPage>
    <section class="done ol-card">
      <h1 class="done__title">{{ title }}</h1>
      <p class="done__when">{{ day }}</p>
      <p class="done__when">{{ time }}</p>
      <p v-if="isRequest" class="done__hint">Waiting for your instructor</p>
      <NuxtLink to="/portal" class="ol-btn">Back to home</NuxtLink>
    </section>
  </PortalPage>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'portal' })
useHead({ title: 'Done · OwnLane' })

const route = useRoute()
const outcome = computed(() => String(route.query.outcome || 'booked'))
const day = computed(() => String(route.query.day || ''))
const time = computed(() => String(route.query.time || ''))
const isRequest = computed(() => outcome.value === 'requested')
const title = computed(() => (isRequest.value ? 'Requested' : 'Booked'))
</script>

<style scoped>
.done {
  padding: var(--spacing-24);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  align-items: flex-start;
}

.done__title {
  font-size: var(--text-heading-md);
}

.done__when {
  font-size: var(--text-body-lg);
}

.done__hint {
  color: rgba(17, 17, 24, 0.7);
}
</style>
