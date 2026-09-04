<script setup lang="ts">
useHead({
  script: [
    {
      type: 'application/ld+json',
      innerHTML: JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'SoftwareApplication',
        name: 'OwnLane',
        applicationCategory: 'BusinessApplication',
        operatingSystem: 'Web',
        description:
          'Operating platform for independent UK driving instructors — diary, pupils, progress, money and learner portal.',
        offers: {
          '@type': 'Offer',
          price: '0',
          priceCurrency: 'GBP',
          description: 'Free during beta',
        },
      }),
    },
  ],
})
</script>

<template>
  <span class="sr-only" aria-hidden="true" />
</template>

<style scoped>
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}
</style>
