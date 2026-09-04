<template>
  <section class="ol-card auth-card">
    <p class="ol-eyebrow">Reset password</p>

    <template v-if="peekLoading">
      <p class="ol-muted">Checking your reset link…</p>
    </template>

    <template v-else-if="peekState === 'expired' || peekState === 'used' || peekState === 'invalid'">
      <h1 class="ol-page-title">Reset link unavailable</h1>
      <p class="ol-muted">{{ peekMessage }}</p>
      <NuxtLink to="/forgot-password" class="ol-btn ol-btn--block ol-btn--sm">
        Request another
      </NuxtLink>
    </template>

    <template v-else-if="done">
      <h1 class="ol-page-title">Password updated</h1>
      <p class="ol-muted">You can sign in with your new password.</p>
      <NuxtLink to="/login" class="ol-btn ol-btn--block">Sign in</NuxtLink>
    </template>

    <template v-else>
      <h1 class="ol-page-title">Choose a new password</h1>
      <form class="ol-stack auth-form" @submit.prevent="onSubmit">
        <label class="ol-field">
          <span class="ol-field__label">New password</span>
          <input
            v-model="password"
            class="ol-input"
            type="password"
            name="new-password"
            autocomplete="new-password"
            minlength="8"
            required
          >
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Confirm password</span>
          <input
            v-model="confirm"
            class="ol-input"
            type="password"
            name="confirm-password"
            autocomplete="new-password"
            minlength="8"
            required
          >
        </label>

        <p v-if="error" class="ol-error" role="alert">{{ error }}</p>

        <button class="ol-btn ol-btn--block" type="submit" :disabled="pending || !token">
          {{ pending ? 'Saving…' : 'Update password' }}
        </button>
      </form>
    </template>

    <p class="auth-footer ol-meta">
      <NuxtLink to="/login">Back to sign in</NuxtLink>
    </p>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth' })
useHead({ title: 'Reset password · OwnLane' })

const route = useRoute()
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))

const peekLoading = ref(true)
const peekState = ref<'valid' | 'expired' | 'used' | 'invalid'>('invalid')
const peekMessage = ref('')
const password = ref('')
const confirm = ref('')
const pending = ref(false)
const error = ref('')
const done = ref(false)

async function loadPeek() {
  peekLoading.value = true
  if (!token.value) {
    peekState.value = 'invalid'
    peekMessage.value = 'This reset link is missing its code.'
    peekLoading.value = false
    return
  }
  try {
    const res = await apiFetch<{ state: string }>(
      `/auth/password-reset/peek?token=${encodeURIComponent(token.value)}`,
    )
    const state = res.state as typeof peekState.value
    peekState.value = state
    if (state === 'expired') {
      peekMessage.value = 'This reset link has expired.'
    } else if (state === 'used') {
      peekMessage.value = 'This reset link has already been used.'
    } else if (state === 'invalid') {
      peekMessage.value = 'This reset link is invalid.'
    }
  } catch {
    peekState.value = 'invalid'
    peekMessage.value = 'This reset link is invalid.'
  } finally {
    peekLoading.value = false
  }
}

async function onSubmit() {
  error.value = ''
  if (password.value !== confirm.value) {
    error.value = 'Passwords do not match.'
    return
  }
  pending.value = true
  try {
    await apiFetch('/auth/password-reset/confirm', {
      method: 'POST',
      body: { token: token.value, password: password.value },
    })
    done.value = true
  } catch (e) {
    error.value = extractApiError(e, 'Could not update password.')
  } finally {
    pending.value = false
  }
}

onMounted(() => { void loadPeek() })
watch(token, () => { void loadPeek() })
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
</style>
