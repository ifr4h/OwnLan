<script setup lang="ts">
/**
 * Shared Teaching Studio workspace — board canvas + tools + save.
 */
import type { SceneObjectKind, Stroke } from '~/utils/teaching/scene'
import { createEmptyScene } from '~/utils/teaching/scene'

const props = defineProps<{
  resourceId?: number | null
  initialTemplate?: string
  lessonId?: number | null
  momentId?: number | null
}>()

const { t } = useTeachingI18n()
const {
  fetchResource,
  createResource,
  updateResource,
  attachToLesson,
} = useTeaching()

const {
  scene,
  tool,
  selectedId,
  canUndo,
  canRedo,
  activeStepIndex,
  activeStep,
  renderables,
  playing,
  loadScene,
  setTool,
  undo,
  redo,
  snapshot,
  addObject,
  updateObject,
  clearAnnotations,
  beginStroke,
  appendStrokePoint,
  commitStroke,
  eraseNear,
  addTextLabel,
  prevStep,
  nextStep,
  addStep,
  playSteps,
  stopPlay,
} = useTeachingBoard(createEmptyScene(props.initialTemplate || 'blank'))

const title = ref('')
const favourite = ref(false)
const saving = ref(false)
const saveMsg = ref('')
const error = ref('')
const loading = ref(!!props.resourceId)
const liveStrokeId = ref<string | null>(null)
const eraserSnapshotTaken = ref(false)

const TeachingCanvas = defineAsyncComponent(() => import('~/components/teaching/TeachingCanvas.vue'))
const TeachingToolbar = defineAsyncComponent(() => import('~/components/teaching/TeachingToolbar.vue'))
const TeachingObjectPalette = defineAsyncComponent(() => import('~/components/teaching/TeachingObjectPalette.vue'))

useHead(() => ({
  title: title.value
    ? `${title.value} · Teaching · OwnLane`
    : 'Teaching board · OwnLane',
}))

onMounted(async () => {
  if (props.resourceId) {
    try {
      const res = await fetchResource(props.resourceId)
      title.value = res.title
      favourite.value = res.is_favourite
      loadScene(res.scene)
    } catch (e) {
      error.value = extractApiError(e, 'Could not load this board.')
    } finally {
      loading.value = false
    }
  } else {
    loadScene(createEmptyScene(props.initialTemplate || 'blank'))
    loading.value = false
  }
})

function onStrokeBegin(
  toolKind: Stroke['tool'],
  color: string,
  width: number,
  point: { x: number; y: number; pressure?: number },
) {
  const stroke = beginStroke(toolKind, color, width, point)
  liveStrokeId.value = stroke.id
}

function onStrokePoint(point: { x: number; y: number; pressure?: number }) {
  if (!liveStrokeId.value) return
  appendStrokePoint(liveStrokeId.value, point)
}

function onStrokeEnd() {
  if (liveStrokeId.value) {
    commitStroke()
    liveStrokeId.value = null
  }
  eraserSnapshotTaken.value = false
}

function onErase(x: number, y: number) {
  if (!eraserSnapshotTaken.value) {
    snapshot()
    eraserSnapshotTaken.value = true
  }
  eraseNear(x, y)
}

function onObjectMove(id: string, x: number, y: number) {
  updateObject(id, { x, y })
}

function onObjectMoveEnd(id: string, x: number, y: number) {
  snapshot()
  updateObject(id, { x, y })
}

function onObjectRotate(id: string, rotation: number) {
  updateObject(id, { rotation })
}

function onTextPlace(x: number, y: number) {
  const text = window.prompt('Text on board')
  if (!text?.trim()) return
  addTextLabel(x, y, text.trim())
  setTool('select')
}

function onAddObject(kind: SceneObjectKind) {
  addObject(kind, 500, 420)
}

async function onSave() {
  saving.value = true
  error.value = ''
  saveMsg.value = ''
  try {
    const name = title.value.trim() || 'Untitled board'
    title.value = name
    if (props.resourceId) {
      await updateResource(props.resourceId, {
        title: name,
        scene: scene.value,
        is_favourite: favourite.value,
        template_code: scene.value.template,
      })
      saveMsg.value = t('board.saved')
    } else if (props.lessonId) {
      await attachToLesson(props.lessonId, {
        scene: scene.value,
        title: name,
        kind: 'board',
        route_moment_id: props.momentId ?? undefined,
        learner_visible: true,
      })
      saveMsg.value = t('board.saved')
    } else {
      const created = await createResource({
        title: name,
        kind: 'board',
        template_code: scene.value.template,
        scene: scene.value,
        is_favourite: favourite.value,
        category: null,
      })
      saveMsg.value = t('board.saved')
      await navigateTo(`/teaching/${created.id}`, { replace: true })
    }
  } catch (e) {
    error.value = extractApiError(e, 'Could not save.')
  } finally {
    saving.value = false
  }
}

async function toggleFavourite() {
  favourite.value = !favourite.value
  if (props.resourceId) {
    try {
      await updateResource(props.resourceId, { is_favourite: favourite.value })
    } catch {
      favourite.value = !favourite.value
    }
  }
}
</script>

<template>
  <div class="studio">
    <header class="studio__bar">
      <NuxtLink to="/teaching" class="studio__back">← Teaching</NuxtLink>
      <input
        v-model="title"
        class="studio__title"
        type="text"
        :placeholder="t('board.titlePlaceholder')"
        maxlength="120"
      >
      <div class="studio__actions">
        <button
          type="button"
          class="studio__fav"
          :aria-pressed="favourite"
          @click="toggleFavourite"
        >
          {{ favourite ? '★' : '☆' }}
          <span class="studio__fav-label">{{ t('board.favourite') }}</span>
        </button>
        <button
          type="button"
          class="studio__save"
          :disabled="saving"
          @click="onSave"
        >
          {{ saving ? t('board.saving') : t('board.save') }}
        </button>
      </div>
    </header>

    <p v-if="loading" class="studio__msg">Loading…</p>
    <p v-else-if="error" class="studio__err" role="alert">{{ error }}</p>
    <p v-else-if="saveMsg" class="studio__ok" role="status">{{ saveMsg }}</p>

    <template v-if="!loading">
      <ClientOnly>
        <TeachingToolbar
          :tool="tool"
          :can-undo="canUndo"
          :can-redo="canRedo"
          :step-index="activeStepIndex"
          :step-count="scene.steps.length"
          :step-label="activeStep?.label"
          :playing="playing"
          @update:tool="setTool"
          @undo="undo"
          @redo="redo"
          @clear="clearAnnotations"
          @prev-step="prevStep"
          @next-step="nextStep"
          @add-step="addStep"
          @play="playSteps"
          @stop-play="stopPlay"
        />

        <div class="studio__stage">
          <TeachingCanvas
            :scene="scene"
            :objects="renderables.objects"
            :strokes="renderables.strokes"
            :tool="tool"
            :selected-id="selectedId"
            @update:selected-id="(id) => (selectedId = id)"
            @stroke-begin="onStrokeBegin"
            @stroke-point="onStrokePoint"
            @stroke-end="onStrokeEnd"
            @erase="onErase"
            @object-move="onObjectMove"
            @object-move-end="onObjectMoveEnd"
            @object-rotate="onObjectRotate"
            @text-place="onTextPlace"
          />
        </div>

        <TeachingObjectPalette @add="onAddObject" />
      </ClientOnly>
    </template>
  </div>
</template>

<style scoped>
.studio {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-12);
  min-height: calc(100dvh - 120px);
}

.studio__bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--spacing-8);
}

.studio__back {
  min-height: 44px;
  display: inline-flex;
  align-items: center;
  color: var(--color-muted);
  font-size: var(--text-body-sm);
  text-decoration: none;
}

.studio__title {
  flex: 1;
  min-width: 160px;
  min-height: 48px;
  padding: 10px 14px;
  border: 1px solid var(--color-frost-green);
  border-radius: var(--radius-small);
  background: var(--color-paper-white);
  font: inherit;
  font-size: var(--text-body-sm);
}

.studio__actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.studio__fav {
  min-height: 44px;
  padding: 8px 12px;
  border: 1px solid var(--color-frost-green);
  border-radius: 14px;
  background: transparent;
  font: inherit;
  font-size: var(--text-meta);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.studio__fav[aria-pressed='true'] {
  color: var(--color-ownlane-green);
  background: var(--color-success-wash);
}

.studio__fav-label {
  display: none;
}

@media (min-width: 640px) {
  .studio__fav-label {
    display: inline;
  }
}

.studio__save {
  min-height: 48px;
  padding: 10px 18px;
  border: none;
  border-radius: var(--radius-buttons);
  background: var(--color-ownlane-green);
  color: var(--color-paper-white);
  font: inherit;
  box-shadow: var(--shadow-button);
  cursor: pointer;
}

.studio__save:disabled {
  opacity: 0.6;
}

.studio__stage {
  flex: 1;
  min-height: 420px;
  height: min(62vh, 640px);
}

.studio__msg,
.studio__ok {
  font-size: var(--text-body-sm);
  color: var(--color-ownlane-green);
}

.studio__err {
  font-size: var(--text-body-sm);
  color: var(--color-danger);
}
</style>
