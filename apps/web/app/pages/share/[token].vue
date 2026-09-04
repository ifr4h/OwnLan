<script setup lang="ts">
definePageMeta({ layout: false })

const route = useRoute()
const token = computed(() => String(route.params.token || ''))

type SharePeek = {
  resource_type: string
  expires_at: string
  resource: {
    title?: string
    note?: string | null
    summary?: string | null
    slug?: string
    distance_metres?: number | null
    duration_seconds?: number | null
  }
  note: string
}

const data = ref<SharePeek | null>(null)
const loading = ref(true)
const error = ref('')

useHead(() => ({
  title: data.value?.resource?.title
    ? `${data.value.resource.title} · Shared · OwnLane`
    : 'Shared · OwnLane',
}))

async function load() {
  loading.value = true
  error.value = ''
  try {
    data.value = await apiFetch<SharePeek>(`/share/${encodeURIComponent(token.value)}`)
  } catch (e) {
    data.value = null
    error.value = extractApiError(e, 'This share link is no longer available.')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="share">
    <header class="share__top">
      <OlBrand />
    </header>

    <main class="share__main">
      <p v-if="loading" class="share__msg">Loading…</p>
      <p v-else-if="error" class="share__err" role="alert">{{ error }}</p>

      <template v-else-if="data">
        <p class="share__eyebrow">Temporary share</p>
        <h1 class="share__title">{{ data.resource.title || 'Shared item' }}</h1>
        <p v-if="data.resource.summary" class="share__copy">{{ data.resource.summary }}</p>
        <p v-if="data.resource.note" class="share__copy">{{ data.resource.note }}</p>
        <p class="share__note">{{ data.note }}</p>
        <p class="share__expires">Available until {{ data.expires_at }}</p>
      </template>
    </main>
  </div>
</template>

<style scoped>
.share {
  min-height: 100dvh;
  background: var(--color-chalk-green, #F3F8F4);
  color: var(--color-ink-black, #111118);
  max-width: 640px;
  margin: 0 auto;
}

.share__top {
  display: flex;
  align-items: center;
  padding: 20px;
}

.share__main {
  padding: 12px 20px 48px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.share__eyebrow {
  font-family: var(--font-martian-mono, ui-monospace, monospace);
  font-size: 11px;
  text-transform: uppercase;
  color: var(--color-ownlane-green, #168B55);
  margin: 0;
}

.share__title {
  font-family: var(--font-haas-grot-disp, system-ui, sans-serif);
  font-size: clamp(28px, 6vw, 42px);
  letter-spacing: -0.03em;
  margin: 0;
  line-height: 1.1;
}

.share__copy {
  margin: 0;
  font-size: 15px;
  max-width: 40ch;
  line-height: 1.45;
}

.share__note,
.share__expires,
.share__msg {
  margin: 0;
  font-size: 13px;
  color: var(--color-muted, #5c5c66);
}

.share__err {
  margin: 0;
  color: var(--color-danger, #c0392b);
  font-size: 15px;
}
</style>
