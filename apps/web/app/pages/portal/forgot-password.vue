<template>
  <section class="card">
    <p class="card__eyebrow">Forgot password</p>
    <h1 class="card__title">Forgot your password?</h1>
    <p class="card__copy">
      Enter the email you use for the learner portal and we'll send you a reset link.
    </p>

    <form v-if="!sent" class="form" @submit.prevent="onSubmit">
      <label class="field">
        <span class="field__label">Email</span>
        <input
          v-model="email"
          class="field__input"
          type="email"
          name="email"
          autocomplete="username"
          inputmode="email"
          required
        >
      </label>

      <p v-if="error" class="form__error" role="alert">{{ error }}</p>

      <button class="btn" type="submit" :disabled="pending">
        {{ pending ? 'Sending…' : 'Request reset link' }}
      </button>
    </form>

    <div v-else role="status">
      <h2 class="card__title card__title--sm">{{ response?.headline || 'Check your email' }}</h2>
      <p class="card__copy">{{ response?.message }}</p>
    </div>

    <p class="footer">
      <NuxtLink to="/login" class="text-link">Back to sign in</NuxtLink>
    </p>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'portal' })
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
      '/portal/password-reset/request',
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
.card {
  width: 100%;
  background: var(--surface-card);
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-cards);
  box-shadow: var(--shadow-card);
  padding: var(--card-padding);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
  margin-top: var(--spacing-24);
}

.card__eyebrow {
  font-family: var(--font-martian-mono);
  font-size: var(--text-caption-mono);
  letter-spacing: var(--tracking-caption-mono);
  text-transform: uppercase;
  opacity: 0.6;
}

.card__title {
  font-family: var(--font-haas-grot-disp);
  font-size: var(--text-heading-sm);
  line-height: var(--leading-heading-sm);
}

.card__title--sm {
  font-size: var(--text-body-lg);
}

.card__copy {
  font-size: var(--text-body-sm);
  line-height: var(--leading-body-sm);
}

.form {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-16);
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
  min-height: 48px;
  padding: 12px 24px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  cursor: pointer;
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.text-link {
  color: var(--color-ownlane-green);
  text-decoration: underline;
  font-size: var(--text-body-sm);
}

.footer {
  margin-top: var(--spacing-8);
}
</style>
