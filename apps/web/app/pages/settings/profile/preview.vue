<template>
  <section class="ol-page">
    <header class="ol-page-header">
      <NuxtLink to="/settings/profile" class="ol-link-action">← Profile editor</NuxtLink>
      <p class="ol-eyebrow">Preview</p>
      <h1 class="ol-page-title">Profile preview</h1>
      <p class="ol-meta">This is roughly what learners will see.</p>
    </header>
    <p v-if="loading" class="ol-muted">Loading…</p>
    <p v-else-if="error" class="ol-error" role="alert">{{ error }}</p>
    <NuxtLink v-else-if="slug" :to="`/instructors/${slug}`" class="ol-btn" target="_blank">
      Open preview
    </NuxtLink>
  </section>
</template>

<script setup lang="ts">
useHead({ title: 'Preview · Profile · OwnLane' })

const { preview } = useProfileSettings()
const loading = ref(true)
const error = ref('')
const slug = ref('')

onMounted(async () => {
  try {
    const data = await preview()
    slug.value = String(data.slug ?? '')
  } catch (e) {
    error.value = extractApiError(e, 'Could not load preview.')
  } finally {
    loading.value = false
  }
})
</script>
