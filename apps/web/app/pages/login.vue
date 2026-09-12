<template>
  <section class="ol-card auth-card">
    <p class="ol-eyebrow">Sign in</p>
    <h1 class="ol-page-title">Welcome back</h1>
    <p class="ol-muted">Sign in with your email and password. We’ll take you to the right place.</p>

    <form class="ol-stack auth-form" @submit.prevent="onSubmit">
      <label class="ol-field">
        <span class="ol-field__label">Email or username</span>
        <input
          v-model="email"
          class="ol-input"
          type="text"
          autocomplete="username"
          autocapitalize="off"
          autocorrect="off"
          spellcheck="false"
          required
        >
      </label>

      <label class="ol-field">
        <span class="ol-field__label">Password</span>
        <input
          v-model="password"
          class="ol-input"
          type="password"
          autocomplete="current-password"
          required
        >
      </label>

      <p v-if="error" class="ol-error" role="alert">{{ error }}</p>

      <p class="forgot">
        <NuxtLink to="/forgot-password">Forgot your password?</NuxtLink>
      </p>

      <button class="ol-btn ol-btn--block" type="submit" :disabled="pending">
        {{ pending ? 'Signing in…' : 'Sign in' }}
        <span aria-hidden="true">→</span>
      </button>
    </form>

    <p class="auth-footer ol-meta">
      First time? Use the join link from your instructor, or
      <NuxtLink to="/register">create an instructor account</NuxtLink>.
      <br>
      <NuxtLink to="/portal/forgot-password">Forgot learner portal password?</NuxtLink>
    </p>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth' })
useHead({ title: 'Sign in · OwnLane' })

const route = useRoute()
const { signIn } = useSignIn()
const { extractApiError, safeAppRedirect } = useApi()
const { isAuthenticated: instructorAuth, fetchMe: fetchInstructor } = useAuth()
const { isAuthenticated: learnerAuth, fetchMe: fetchLearner } = usePortalAuth()

const email = ref('')
const password = ref('')
const pending = ref(false)
const error = ref('')

function postAuthPath(fallback: string): string {
  return safeAppRedirect(route.query.redirect) || fallback
}

onMounted(async () => {
  await Promise.all([fetchInstructor(), fetchLearner()])
  if (instructorAuth.value) {
    await navigateTo(postAuthPath('/today'))
  } else if (learnerAuth.value) {
    await navigateTo(postAuthPath('/portal'))
  }
})

async function onSubmit() {
  pending.value = true
  error.value = ''
  try {
    const result = await signIn({
      email: email.value.trim(),
      password: password.value,
    })
    const redirect = postAuthPath(result.redirect)
    await navigateTo(redirect)
  } catch (e: unknown) {
    error.value = extractApiError(e, 'Email or password is incorrect.')
  } finally {
    pending.value = false
  }
}
</script>

<style scoped>
.auth-card {
  width: 100%;
  max-width: 440px;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.auth-form {
  margin-top: var(--spacing-8);
}

.auth-footer a {
  color: var(--color-ownlane-green);
  text-decoration: underline;
}

.forgot {
  margin: 0;
  font-size: var(--text-body-sm);
}

.forgot a {
  color: var(--color-ownlane-green);
  text-decoration: underline;
}
</style>
