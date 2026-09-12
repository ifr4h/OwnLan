<script setup lang="ts">
const route = useRoute()

const tabs = [
  { to: '/accounts', label: 'Overview', exact: true },
  { to: '/accounts/payments', label: 'Payments' },
  { to: '/accounts/expenses', label: 'Expenses' },
  { to: '/accounts/reports', label: 'Reports' },
  { to: '/accounts/test-faults', label: 'Test faults' },
  { to: '/accounts/vehicles', label: 'Vehicles' },
  { to: '/accounts/mileage', label: 'Mileage' },
]

function isActive(tab: { to: string; exact?: boolean }): boolean {
  if (tab.exact) return route.path === tab.to || route.path === tab.to + '/'
  return route.path.startsWith(tab.to)
}
</script>

<template>
  <nav class="accounts-nav ol-seg" aria-label="Accounts sections">
    <NuxtLink
      v-for="tab in tabs"
      :key="tab.to"
      :to="{ path: tab.to, query: route.query.from || route.query.to ? { from: route.query.from, to: route.query.to } : {} }"
      class="ol-chip"
      :class="{ 'ol-chip--on': isActive(tab) }"
    >
      {{ tab.label }}
    </NuxtLink>
  </nav>
</template>

<style scoped>
.accounts-nav {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-6);
  margin-bottom: var(--spacing-16);
}
</style>
