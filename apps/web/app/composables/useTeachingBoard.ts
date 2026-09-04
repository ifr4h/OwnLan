import {
  cloneScene,
  createEmptyScene,
  createObject,
  createStroke,
  newId,
  sceneAtStep,
  type Scene,
  type SceneObject,
  type SceneObjectKind,
  type SceneStep,
  type Stroke,
  type StrokePoint,
  type TeachingTool,
} from '~/utils/teaching/scene'
import { prefersReducedMotion } from '~/utils/portalFormat'

const MAX_HISTORY = 40
const STEP_PLAY_MS = 1400
const STEP_PLAY_REDUCED_MS = 0

export function useTeachingBoard(initial?: Scene) {
  const scene = ref<Scene>(initial ? cloneScene(initial) : createEmptyScene())
  const tool = ref<TeachingTool>('select')
  const selectedId = ref<string | null>(null)
  const penColor = ref('#1E2C0F')
  const highlighterColor = ref('rgba(255, 218, 0, 0.45)')
  const penWidth = ref(3)
  const highlighterWidth = ref(18)

  const undoStack = ref<Scene[]>([])
  const redoStack = ref<Scene[]>([])

  const playing = ref(false)
  let playTimer: ReturnType<typeof setTimeout> | null = null

  const canUndo = computed(() => undoStack.value.length > 0)
  const canRedo = computed(() => redoStack.value.length > 0)

  const activeStepIndex = computed({
    get: () => scene.value.active_step,
    set: (v: number) => {
      scene.value.active_step = Math.max(0, Math.min(v, scene.value.steps.length - 1))
    },
  })

  const activeStep = computed(() => scene.value.steps[activeStepIndex.value] ?? null)

  const renderables = computed(() => sceneAtStep(scene.value, activeStepIndex.value))

  function snapshot() {
    undoStack.value.push(cloneScene(scene.value))
    if (undoStack.value.length > MAX_HISTORY) {
      undoStack.value.shift()
    }
    redoStack.value = []
  }

  function loadScene(next: Scene) {
    scene.value = cloneScene(next)
    undoStack.value = []
    redoStack.value = []
    selectedId.value = null
    stopPlay()
  }

  function resetToTemplate(templateCode: string) {
    snapshot()
    scene.value = createEmptyScene(templateCode)
    selectedId.value = null
  }

  function undo() {
    const prev = undoStack.value.pop()
    if (!prev) return
    redoStack.value.push(cloneScene(scene.value))
    scene.value = prev
    selectedId.value = null
  }

  function redo() {
    const next = redoStack.value.pop()
    if (!next) return
    undoStack.value.push(cloneScene(scene.value))
    scene.value = next
    selectedId.value = null
  }

  function setTool(next: TeachingTool) {
    tool.value = next
    if (next !== 'select') selectedId.value = null
  }

  function addObject(kind: SceneObjectKind, x = 500, y = 500) {
    snapshot()
    const obj = createObject(kind, x, y)
    scene.value.objects.push(obj)
    selectedId.value = obj.id
    tool.value = 'select'
    return obj
  }

  function updateObject(id: string, patch: Partial<SceneObject>) {
    const obj = scene.value.objects.find(o => o.id === id)
      ?? activeStep.value?.objects.find(o => o.id === id)
    if (!obj) return
    Object.assign(obj, patch)
  }

  function updateObjectCommitted(id: string, patch: Partial<SceneObject>) {
    snapshot()
    updateObject(id, patch)
  }

  function deleteObject(id: string) {
    snapshot()
    scene.value.objects = scene.value.objects.filter(o => o.id !== id)
    for (const step of scene.value.steps) {
      step.objects = step.objects.filter(o => o.id !== id)
    }
    if (selectedId.value === id) selectedId.value = null
  }

  function addStroke(stroke: Stroke) {
    snapshot()
    scene.value.strokes.push(stroke)
  }

  /** Live stroke without snapshot — commit with commitStroke. */
  function beginStroke(toolKind: Stroke['tool'], color: string, width: number, point: StrokePoint): Stroke {
    const stroke = createStroke(toolKind, color, width, [point])
    scene.value.strokes.push(stroke)
    return stroke
  }

  function appendStrokePoint(strokeId: string, point: StrokePoint) {
    const stroke = scene.value.strokes.find(s => s.id === strokeId)
    if (!stroke) return
    stroke.points.push(point)
  }

  function commitStroke() {
    // Push history after a completed stroke (current scene already has it)
    const copy = cloneScene(scene.value)
    // Remove last stroke from copy to store pre-stroke state… simpler: push current-1
    // Instead: snapshot was skipped; store previous by popping stroke temporarily
    // We store undo as scene without the last stroke:
    const withStroke = cloneScene(scene.value)
    const last = withStroke.strokes.pop()
    if (!last) return
    undoStack.value.push(withStroke)
    if (undoStack.value.length > MAX_HISTORY) undoStack.value.shift()
    redoStack.value = []
  }

  function eraseNear(x: number, y: number, radius = 28) {
    const before = scene.value.strokes.length
    scene.value.strokes = scene.value.strokes.filter((stroke) => {
      if (stroke.tool === 'eraser') return true
      return !stroke.points.some(p => Math.hypot(p.x - x, p.y - y) < radius)
    })
    if (scene.value.strokes.length !== before) {
      // already mutated — ensure undo has previous
    }
  }

  function clearAnnotations() {
    snapshot()
    scene.value.strokes = []
    for (const step of scene.value.steps) {
      step.strokes = []
    }
  }

  function addTextLabel(x: number, y: number, text: string) {
    snapshot()
    const obj: SceneObject = {
      id: newId('obj'),
      kind: 'hazard',
      x,
      y,
      rotation: 0,
      scale: 1,
      label: text,
      isText: true,
    }
    scene.value.objects.push(obj)
    selectedId.value = obj.id
    return obj
  }

  function prevStep() {
    if (activeStepIndex.value > 0) activeStepIndex.value -= 1
  }

  function nextStep() {
    if (activeStepIndex.value < scene.value.steps.length - 1) {
      activeStepIndex.value += 1
    }
  }

  function addStep() {
    snapshot()
    const n = scene.value.steps.length + 1
    const step: SceneStep = {
      id: newId('step'),
      label: `Step ${n}`,
      objects: [],
      strokes: [],
      note: '',
    }
    scene.value.steps.push(step)
    activeStepIndex.value = scene.value.steps.length - 1
  }

  function stopPlay() {
    playing.value = false
    if (playTimer) {
      clearTimeout(playTimer)
      playTimer = null
    }
  }

  function playSteps() {
    stopPlay()
    if (scene.value.steps.length <= 1) return
    playing.value = true
    activeStepIndex.value = 0
    const delay = prefersReducedMotion() ? STEP_PLAY_REDUCED_MS : STEP_PLAY_MS

    const advance = () => {
      if (!playing.value) return
      if (activeStepIndex.value >= scene.value.steps.length - 1) {
        stopPlay()
        return
      }
      if (delay === 0) {
        activeStepIndex.value = scene.value.steps.length - 1
        stopPlay()
        return
      }
      activeStepIndex.value += 1
      playTimer = setTimeout(advance, delay)
    }

    if (delay === 0) {
      activeStepIndex.value = scene.value.steps.length - 1
      stopPlay()
      return
    }
    playTimer = setTimeout(advance, delay)
  }

  onBeforeUnmount(() => stopPlay())

  return {
    scene,
    tool,
    selectedId,
    penColor,
    highlighterColor,
    penWidth,
    highlighterWidth,
    canUndo,
    canRedo,
    activeStepIndex,
    activeStep,
    renderables,
    playing,
    loadScene,
    resetToTemplate,
    undo,
    redo,
    setTool,
    snapshot,
    addObject,
    updateObject,
    updateObjectCommitted,
    deleteObject,
    addStroke,
    beginStroke,
    appendStrokePoint,
    commitStroke,
    eraseNear,
    clearAnnotations,
    addTextLabel,
    prevStep,
    nextStep,
    addStep,
    playSteps,
    stopPlay,
  }
}
