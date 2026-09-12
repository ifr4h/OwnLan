<script setup lang="ts">
import type { LearnerLocation, LocationIconName, PickupSelection } from '~/composables/useLearnerLocations'
import { LOCATION_ICONS } from '~/composables/useLearnerLocations'
import LocationIcon from '~/components/locations/LocationIcon.vue'

const props = withDefaults(defineProps<{
  locations: LearnerLocation[]
  modelValue: PickupSelection
  disabled?: boolean
  /** Instructor copy vs pupil portal copy */
  audience?: 'instructor' | 'learner'
  allowFreeform?: boolean
  allowManage?: boolean
  /** Show inline “Save a new place” form (parent handles savePlace). */
  canSavePlace?: boolean
  loading?: boolean
}>(), {
  audience: 'instructor',
  allowFreeform: true,
  allowManage: false,
  canSavePlace: false,
  loading: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: PickupSelection]
  manage: []
  savePlace: [payload: { label: string; icon: LocationIconName; address: string }]
}>()

const mode = ref<'saved' | 'once'>('saved')
const freeform = ref('')
const showAdd = ref(false)
const addLabel = ref('')
const addAddress = ref('')
const addIcon = ref<LocationIconName>('home')
const addPending = ref(false)

const shareHint = computed(() =>
  props.audience === 'learner'
    ? 'Shared with your instructor'
    : 'Visible to this pupil’s instructor',
)

const selectedId = computed(() => props.modelValue.pickup_location_id)

watch(
  () => props.modelValue,
  (v) => {
    if (v.pickup_location_id) {
      mode.value = 'saved'
    } else if (v.pickup_address) {
      mode.value = 'once'
      freeform.value = v.pickup_address
    }
  },
  { immediate: true },
)

watch(
  () => props.locations,
  (list) => {
    if (props.modelValue.pickup_location_id || props.modelValue.pickup_address) return
    const def = list.find(l => l.is_default) || list[0]
    if (def) {
      emit('update:modelValue', {
        pickup_location_id: def.id,
        pickup_address: def.address,
      })
    }
  },
  { immediate: true },
)

function selectSaved(loc: LearnerLocation) {
  mode.value = 'saved'
  emit('update:modelValue', {
    pickup_location_id: loc.id,
    pickup_address: loc.address,
  })
}

function useOnce() {
  mode.value = 'once'
  emit('update:modelValue', {
    pickup_location_id: null,
    pickup_address: freeform.value.trim() || null,
  })
}

function onFreeformInput() {
  mode.value = 'once'
  emit('update:modelValue', {
    pickup_location_id: null,
    pickup_address: freeform.value.trim() || null,
  })
}

async function onSaveNew() {
  const label = addLabel.value.trim()
  const address = addAddress.value.trim()
  if (!label || !address) return
  addPending.value = true
  try {
    emit('savePlace', { label, icon: addIcon.value, address })
    showAdd.value = false
    addLabel.value = ''
    addAddress.value = ''
    addIcon.value = 'home'
  } finally {
    addPending.value = false
  }
}
</script>

<template>
  <div class="lp" :data-disabled="disabled ? 'yes' : 'no'">
    <div class="lp__head">
      <p class="lp__hint">{{ shareHint }}</p>
      <button
        v-if="allowManage"
        class="lp__manage"
        type="button"
        :disabled="disabled"
        @click="emit('manage')"
      >
        Manage places
      </button>
    </div>

    <p v-if="loading" class="lp__empty">Loading places…</p>

    <div v-else-if="locations.length" class="lp__list" role="listbox" aria-label="Saved places">
      <button
        v-for="loc in locations"
        :key="loc.id"
        type="button"
        class="lp__place"
        :class="{ 'lp__place--on': mode === 'saved' && selectedId === loc.id }"
        role="option"
        :aria-selected="mode === 'saved' && selectedId === loc.id"
        :disabled="disabled"
        @click="selectSaved(loc)"
      >
        <span class="lp__icon-wrap" aria-hidden="true">
          <LocationIcon :name="loc.icon" :size="18" />
        </span>
        <span class="lp__place-text">
          <span class="lp__place-label">
            {{ loc.label }}
            <span v-if="loc.is_default" class="lp__default">Usual</span>
          </span>
          <span class="lp__place-addr">{{ loc.address }}</span>
        </span>
      </button>
    </div>
    <p v-else class="lp__empty">No saved places yet.</p>

    <div v-if="allowFreeform" class="lp__once">
      <button
        type="button"
        class="lp__once-toggle"
        :class="{ 'lp__once-toggle--on': mode === 'once' }"
        :disabled="disabled"
        @click="useOnce"
      >
        Use once
      </button>
      <textarea
        v-if="mode === 'once' || !locations.length"
        v-model="freeform"
        class="ol-textarea"
        rows="2"
        :disabled="disabled"
        placeholder="One-off address for this lesson only"
        @input="onFreeformInput"
      />
    </div>

    <div v-if="canSavePlace" class="lp__add">
      <button
        v-if="!showAdd"
        class="lp__add-btn"
        type="button"
        :disabled="disabled"
        @click="showAdd = true"
      >
        Save a new place
      </button>
      <div v-else class="lp__add-form">
        <p class="ol-field__label">New place</p>
        <div class="lp__icons" role="group" aria-label="Icon">
          <button
            v-for="icon in LOCATION_ICONS"
            :key="icon"
            type="button"
            class="lp__icon-btn"
            :class="{ 'lp__icon-btn--on': addIcon === icon }"
            :aria-pressed="addIcon === icon"
            :disabled="disabled || addPending"
            :title="icon"
            @click="addIcon = icon"
          >
            <LocationIcon :name="icon" :size="16" />
          </button>
        </div>
        <input
          v-model="addLabel"
          class="ol-input"
          type="text"
          maxlength="64"
          placeholder="Label (Home, Work…)"
          :disabled="disabled || addPending"
        >
        <textarea
          v-model="addAddress"
          class="ol-textarea"
          rows="2"
          placeholder="Full address"
          :disabled="disabled || addPending"
        />
        <div class="lp__add-actions">
          <button
            class="ol-btn ol-btn--sm"
            type="button"
            :disabled="disabled || addPending || !addLabel.trim() || !addAddress.trim()"
            @click="onSaveNew"
          >
            {{ addPending ? 'Saving…' : 'Save place' }}
          </button>
          <button
            class="ol-btn ol-btn--ghost ol-btn--sm"
            type="button"
            :disabled="addPending"
            @click="showAdd = false"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.lp {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.lp[data-disabled='yes'] {
  opacity: 0.72;
}

.lp__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
}

.lp__hint {
  margin: 0;
  font-size: 12px;
  color: var(--color-muted);
}

.lp__manage {
  border: none;
  background: transparent;
  color: var(--color-ownlane-green);
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  white-space: nowrap;
}

.lp__list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.lp__place {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  width: 100%;
  text-align: left;
  padding: 10px 12px;
  border-radius: var(--radius-small);
  border: 1px solid var(--color-border);
  background: var(--color-parchment);
  cursor: pointer;
  font: inherit;
  color: inherit;
}

.lp__place--on {
  border-color: var(--color-ownlane-green);
  background: color-mix(in srgb, var(--color-ownlane-green) 8%, var(--color-parchment));
}

.lp__icon-wrap {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--color-paper-white);
  color: var(--color-bark);
  flex-shrink: 0;
}

.lp__place-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.lp__place-label {
  font-size: var(--text-body-sm);
  font-weight: 600;
  color: var(--color-ink-black);
}

.lp__default {
  margin-left: 6px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-ownlane-green);
}

.lp__place-addr {
  font-size: 12px;
  color: var(--color-muted);
  line-height: 1.35;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.lp__empty {
  margin: 0;
  font-size: var(--text-body-sm);
  color: var(--color-muted);
}

.lp__once {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.lp__once-toggle {
  align-self: flex-start;
  border: 1px solid var(--color-border);
  border-radius: 20px;
  background: var(--color-paper-white);
  padding: 6px 12px;
  font-size: 12px;
  font-weight: 600;
  color: var(--color-bark);
  cursor: pointer;
}

.lp__once-toggle--on {
  border-color: var(--color-ownlane-green);
  color: var(--color-ownlane-green);
  background: color-mix(in srgb, var(--color-ownlane-green) 8%, var(--color-paper-white));
}

.lp__add-btn {
  border: none;
  background: transparent;
  color: var(--color-ownlane-green);
  font-size: var(--text-body-sm);
  font-weight: 600;
  cursor: pointer;
  padding: 0;
  align-self: flex-start;
}

.lp__add-form {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border-radius: var(--radius-small);
  border: 1px solid var(--color-border);
  background: var(--color-parchment);
}

.lp__icons {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.lp__icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: 1px solid var(--color-border);
  background: var(--color-paper-white);
  color: var(--color-bark);
  cursor: pointer;
}

.lp__icon-btn--on {
  border-color: var(--color-ownlane-green);
  color: var(--color-ownlane-green);
  background: color-mix(in srgb, var(--color-ownlane-green) 10%, var(--color-paper-white));
}

.lp__add-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
</style>
