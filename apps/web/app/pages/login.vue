<template>
  <section class="card">
    <p class="card__eyebrow">Sign in</p>
    <h1 class="card__title">Welcome back</h1>
    <p class="card__copy">Sign in to pick up today’s lessons.</p>

    <form class="form" @submit.prevent="onSubmit">
      <label class="field">
        <span class="field__label">Email</span>
        <input v-model="email" class="field__input" type="email" autocomplete="email" required>
      </label>

      <label class="field">
        <span class="field__label">Password</span>
        <input
          v-model="password"
          class="field__input"
          type="password"
          autocomplete="current-password"
          required
        >
      </label>

      <p v-if="error" class="form__error" role="alert">{{ error }}</p>

      <button class="btn" type="submit" :disabled="pending">
        {{ pending ? 'Signing in…' : 'Sign in' }}
        <span aria-hidden="true">→</span>
      </button>
    </form>

    <p class="card__footer">
      New to OwnLane?
      <NuxtLink to="/register">Create an account</NuxtLink>
    </p>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'auth' })
useHead({ title: 'Sign in · OwnLane' })

const { login } = useAuth()
const email = ref('')
const password = ref('')
const pending = ref(false)
const error = ref('')

async function onSubmit() {
  pending.value = true
  error.value = ''
  try {
    await login({
      email: email.value.trim(),
      password: password.value,
    })
    await navigateTo('/today')
  } catch (e: unknown) {
    error.value = extractError(e) || 'Incorrect email or password.'
  } finally {
    pending.value = false
  }
}

function extractError(e: unknown): string {
  const err = e as { data?: { message?: string }; statusMessage?: string }
  return err?.data?.message || err?.statusMessage || ''
}
</script>

<style scoped>
.card {
  width: 100%;
  max-width: 440px;
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--card-padding);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
}

.card__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
}

.card__title {
  font-size: var(--text-heading-sm);
  line-height: var(--leading-heading-sm);
  letter-spacing: var(--tracking-heading-sm);
}

.card__copy {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
}

.form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  margin-top: var(--spacing-8);
}

.field {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-8);
}

.field__label {
  font-size: var(--text-body-sm);
}

.field__input {
  min-height: 48px;
  padding: 12px 20px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-inputs);
  background: var(--surface-canvas);
}

.field__input:focus {
  outline: 2px solid var(--color-ownlane-green);
  outline-offset: 2px;
}

.form__error {
  color: var(--color-marker-red);
  font-size: var(--text-body-sm);
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-8);
  min-height: 48px;
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  box-shadow: var(--shadow-button);
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.card__footer {
  font-size: var(--text-body-sm);
}

.card__footer a {
  color: var(--color-ownlane-green);
  text-decoration: underline;
}
</style>
