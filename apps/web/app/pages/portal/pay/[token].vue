<template>
  <PortalPage>
    <template #header>
      <h1 class="portal-page__title">Payment cancelled</h1>
    </template>

    <div class="ol-stack">
      <p class="ol-meta">No money has been taken.</p>
      <NuxtLink class="ol-btn" to="/portal/money">Back to payments</NuxtLink>
    </div>
  </PortalPage>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'portal' })
useHead({ title: 'Payment · OwnLane' })
</script>
