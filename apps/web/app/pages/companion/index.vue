<script setup lang="ts">
definePageMeta({ layout: 'companion' })
useHead({ title: 'Companion · OwnLane' })

const { me, fetchMe, logout, isAuthenticated, ready } = useCompanion()
const loading = ref(true)
const error = ref('')
const loggingOut = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const payload = await fetchMe()
    if (!payload) {
      await navigateTo('/login')
    }
  } catch (e) {
    error.value = extractApiError(e, 'Something went wrong.')
  } finally {
    loading.value = false
  }
}

async function onLogout() {
  loggingOut.value = true
  try {
    await logout()
    await navigateTo('/login')
  } finally {
    loggingOut.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="home">
    <header class="home__hero">
      <p class="home__eyebrow">Companion</p>
      <h1 class="home__title">People you’re helping</h1>
      <p v-if="me?.account" class="home__meta">Signed in as {{ me.account.name || me.account.email }}</p>
    </header>

    <p v-if="loading || !ready" class="home__msg">Loading…</p>
    <p v-else-if="error" class="home__err" role="alert">{{ error }}</p>

    <template v-else-if="isAuthenticated && me">
      <ul v-if="me.learners.length" class="home__list">
        <li v-for="learner in me.learners" :key="learner.learner_id">
          <NuxtLink :to="`/companion/learners/${learner.learner_id}`" class="home__row">
            <span class="home__row-title">
              {{ learner.first_name || learner.display_label || learner.full_name }}
            </span>
            <span v-if="learner.relationship_label" class="home__row-meta">
              {{ learner.relationship_label }}
            </span>
          </NuxtLink>
        </li>
      </ul>
      <p v-else class="home__msg">
        No learners linked yet. Ask them to invite you from their OwnLane account.
      </p>

      <button type="button" class="home__signout" :disabled="loggingOut" @click="onLogout">
        {{ loggingOut ? '…' : 'Sign out' }}
      </button>
    </template>
  </div>
</template>

<style scoped>
.home {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-24);
  padding-top: var(--spacing-8);
}

.home__hero {
  margin-left: calc(-1 * var(--spacing-20));
  margin-right: calc(-1 * var(--spacing-20));
  padding: var(--spacing-28) var(--spacing-20);
  background: linear-gradient(165deg, var(--color-frost-green), var(--color-chalk-green));
}

.home__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  text-transform: uppercase;
  color: var(--color-ownlane-green);
  margin: 0;
}

.home__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-lg);
  margin: var(--spacing-8) 0 0;
}

.home__meta {
  margin-top: var(--spacing-8);
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.home__list {
  list-style: none;
  margin: 0;
  padding: 0;
}

.home__row {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-height: 56px;
  padding: 12px 0;
  text-decoration: none;
  color: inherit;
  border-bottom: 1px solid var(--color-border);
}

.home__row-title {
  font-size: var(--text-body-sm);
}

.home__row-meta {
  font-size: var(--text-meta);
  color: var(--color-muted);
}

.home__msg {
  color: var(--color-muted);
  font-size: var(--text-body-sm);
}

.home__err {
  color: var(--color-danger);
  font-size: var(--text-body-sm);
}

.home__signout {
  min-height: 44px;
  width: fit-content;
  border: none;
  background: transparent;
  color: var(--color-muted);
  font: inherit;
  font-size: var(--text-body-sm);
  cursor: pointer;
  padding: 0;
}
</style>
