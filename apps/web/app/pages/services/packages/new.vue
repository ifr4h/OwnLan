<script setup lang="ts">
useHead({ title: 'Add package · OwnLane' })

const router = useRouter()
const { createPackage } = useServices()

const label = ref('10-hour block')
const hours = ref('10')
const price = ref('')
const portalVisible = ref(true)
const active = ref(true)
const saving = ref(false)
const error = ref('')

async function onSave() {
  saving.value = true
  error.value = ''
  try {
    await createPackage({
      label: label.value.trim(),
      purchased_hours: hours.value.trim(),
      price: price.value.trim(),
      portal_visible: portalVisible.value,
      active: active.value,
    })
    await navigateTo('/services')
  } catch (e) {
    error.value = extractApiError(e, 'Could not save package.')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="pkg ol-page">
    <header class="pkg__header">
      <NuxtLink to="/services" class="ol-link-action">← Services</NuxtLink>
      <h1 class="ol-page-title">Add a package</h1>
      <p class="pkg__lead">Hour blocks pupils can buy from their portal.</p>
    </header>

    <p v-if="error" class="ol-error" role="alert">{{ error }}</p>

    <form class="pkg__form" @submit.prevent="onSave">
      <label class="field">
        <span class="field__label">Name</span>
        <input v-model="label" class="field__input" maxlength="120" required placeholder="10-hour block">
      </label>

      <div class="row">
        <label class="field">
          <span class="field__label">Hours</span>
          <input v-model="hours" class="field__input" inputmode="decimal" required placeholder="10">
        </label>
        <label class="field">
          <span class="field__label">Price</span>
          <span class="price-wrap">
            <span aria-hidden="true">£</span>
            <input v-model="price" class="field__input field__input--bare" inputmode="decimal" required placeholder="380">
          </span>
        </label>
      </div>

      <button class="toggle-row" type="button" :aria-pressed="portalVisible" @click="portalVisible = !portalVisible">
        <span><strong>Pupils can buy this</strong></span>
        <span class="toggle-pill" :data-on="portalVisible ? 'yes' : 'no'" />
      </button>

      <button class="toggle-row" type="button" :aria-pressed="active" @click="active = !active">
        <span><strong>Active</strong></span>
        <span class="toggle-pill" :data-on="active ? 'yes' : 'no'" />
      </button>

      <div class="actions">
        <button class="ol-btn" type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save package' }}</button>
        <button class="ol-btn ol-btn--ghost" type="button" @click="router.push('/services')">Cancel</button>
      </div>
    </form>
  </section>
</template>

<style scoped>
.pkg__header { margin-bottom: 24px; }
.pkg__lead { margin: 8px 0 0; color: var(--color-muted); font-size: var(--text-body-sm); }
.pkg__form { display: flex; flex-direction: column; gap: 18px; max-width: 28rem; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field__label { display: block; margin-bottom: 8px; font-size: var(--text-meta); font-weight: 600; color: var(--color-coffee-stone); }
.field__input { width: 100%; min-height: 48px; padding: 12px 14px; border: 1px solid var(--color-driftwood); border-radius: 4px; background: var(--color-paper-white); font: inherit; }
.field__input:focus { outline: none; border-color: var(--color-ink-black); }
.price-wrap { display: flex; align-items: center; gap: 4px; min-height: 48px; padding: 0 14px; border: 1px solid var(--color-driftwood); border-radius: 4px; background: var(--color-paper-white); }
.price-wrap:focus-within { border-color: var(--color-ink-black); }
.field__input--bare { border: none; min-height: 46px; padding: 0; background: transparent; }
.field__input--bare:focus { outline: none; }
.toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 16px; width: 100%; padding: 14px 16px; border: 1px solid var(--color-border); border-radius: var(--radius-cards); background: var(--color-paper-white); text-align: left; font: inherit; cursor: pointer; }
.toggle-pill { width: 44px; height: 26px; border-radius: 999px; background: var(--color-border); position: relative; flex-shrink: 0; }
.toggle-pill::after { content: ''; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%; background: white; transition: transform var(--duration-fast) ease; }
.toggle-pill[data-on='yes'] { background: var(--color-ownlane-green); }
.toggle-pill[data-on='yes']::after { transform: translateX(18px); }
.actions { display: flex; flex-wrap: wrap; gap: 10px; padding-top: 8px; }
</style>
