<template>
  <section class="ol-page" style="max-width: 560px">
    <NuxtLink to="/pupils/new" class="ol-back">← Add a pupil</NuxtLink>

    <header class="ol-page-header">
      <p class="ol-eyebrow">New pupil</p>
      <h1 class="ol-page-title">Add them yourself</h1>
      <p class="ol-muted" style="max-width: 40ch">
        Name and mobile is enough. You can fill in the rest later.
      </p>
    </header>

    <form class="ol-card ol-stack" @submit.prevent="onSubmit">
      <div class="form__row">
        <label class="ol-field">
          <span class="ol-field__label">First name</span>
          <input
            v-model="firstName"
            class="ol-input"
            type="text"
            autocomplete="given-name"
            required
            autofocus
          >
        </label>
        <label class="ol-field">
          <span class="ol-field__label">Last name</span>
          <input
            v-model="lastName"
            class="ol-input"
            type="text"
            autocomplete="family-name"
            required
          >
        </label>
      </div>

      <label class="ol-field">
        <span class="ol-field__label">Mobile</span>
        <input
          v-model="mobile"
          class="ol-input"
          type="tel"
          inputmode="tel"
          autocomplete="tel"
          placeholder="07…"
          required
        >
      </label>

      <label class="ol-field">
        <span class="ol-field__label">
          Email
          <span class="ol-field__optional">optional</span>
        </span>
        <input
          v-model="email"
          class="ol-input"
          type="email"
          autocomplete="email"
        >
      </label>

      <label class="ol-field">
        <span class="ol-field__label">
          Usual pickup
          <span class="ol-field__optional">optional</span>
        </span>
        <textarea
          v-model="pickup"
          class="ol-textarea"
          rows="2"
          placeholder="Home address or meeting point"
          autocomplete="street-address"
        />
      </label>

      <p v-if="error" class="ol-error" role="alert">{{ error }}</p>

      <button class="ol-btn" type="submit" :disabled="pending">
        {{ pending ? 'Saving…' : 'Save pupil' }}
        <span aria-hidden="true">→</span>
      </button>
    </form>
  </section>
</template>

<script setup lang="ts">
useHead({ title: 'Add pupil · OwnLane' })

const { createPupil } = usePupils()
const firstName = ref('')
const lastName = ref('')
const mobile = ref('')
const email = ref('')
const pickup = ref('')
const pending = ref(false)
const error = ref('')

async function onSubmit() {
  pending.value = true
  error.value = ''
  try {
    const pupil = await createPupil({
      first_name: firstName.value.trim(),
      last_name: lastName.value.trim(),
      mobile: mobile.value.trim(),
      email: email.value.trim() || undefined,
      default_pickup_address: pickup.value.trim() || undefined,
    })
    const { refresh } = useOnboarding()
    await refresh()
    const stage = useAuth().me.value?.onboarding?.stage
    if (stage === 'book_lesson') {
      await navigateTo(`/lessons/new?learner_id=${pupil.id}`)
    } else {
      await navigateTo(`/pupils/${pupil.id}`)
    }
  } catch (e) {
    error.value = extractApiError(e, 'Could not save this pupil.')
  } finally {
    pending.value = false
  }
}
</script>

<style scoped>
.form__row {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--spacing-16);
}

@media (min-width: 560px) {
  .form__row {
    grid-template-columns: 1fr 1fr;
  }
}
</style>
