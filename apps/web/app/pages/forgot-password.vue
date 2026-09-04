<template>
  <section class="ol-card auth-card">
    <p class="ol-eyebrow">Forgot password</p>
    <h1 class="ol-page-title">Forgot your password?</h1>
    <p class="ol-muted">
      Enter the email you use for OwnLane and we'll send you a reset link.
    </p>

    <form v-if="!sent" class="ol-stack auth-form" @submit.prevent="onSubmit">
      <label class="ol-field">
        <span class="ol-field__label">Email</span>
        <input
          v-model="email"
          class="ol-input"
          type="email"
          name="email"
          autocomplete="username"
          inputmode="email"
          required
        >
      </label>

      <p v-if="error" class="ol-error" role="alert">{{ error }}</p>

      <button class="ol-btn ol-btn--block" type="submit" :disabled="pending">
        {{ pending ? 'Sending…' : 'Request reset link' }}
      </button>
    </form>

    <div v-else class="sent" role="status">
      <h2 class="sent__title">{{ response?.headline || 'Check your email' }}</h2>
      <p class="ol-muted">{{ response?.message }}</p>
    </div>

    <p class="auth-footer ol-meta">
      <NuxtLink to="/login">Back to sign in</NuxtLink>
      ·
      <NuxtLink to="/portal/forgot-password">Learner portal password</NuxtLink>
    </p>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth' })
useHead({ title: 'Forgot password · OwnLane' })

const email = ref('')
const pending = ref(false)
const error = ref('')
const sent = ref(false)
const response = ref<{ headline: string; message: string } | null>(null)

async function onSubmit() {
  pending.value = true
  error.value = ''
  try {
    response.value = await apiFetch<{ headline: string; message: string }>(
      '/auth/password-reset/request',
      { method: 'POST', body: { email: email.value.trim() } },
    )
    sent.value = true
  } catch (e) {
    error.value = extractApiError(e, 'Could not send reset link.')
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

.sent__title {
  font-size: var(--text-heading-sm);
  margin-bottom: var(--spacing-8);
}
</style>
