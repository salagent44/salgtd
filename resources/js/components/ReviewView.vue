<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="open"
        class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50"
        @click.self="$emit('close')"
        @keydown.escape="$emit('close')"
      >
        <div class="bg-card border border-border rounded-xl w-full max-w-lg shadow-2xl overflow-hidden" @click.stop>

          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-4 border-b border-border">
            <div>
              <h2 class="text-base font-semibold text-foreground">Weekly Review</h2>
              <p class="text-[11px] text-muted-foreground mt-0.5">
                {{ daysSinceReview === null ? "No review completed yet" : `Last review ${daysSinceReview} day${daysSinceReview === 1 ? '' : 's'} ago` }}
              </p>
            </div>
            <button @click="$emit('close')" class="rounded-lg p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>

          <!-- Progress bar -->
          <div class="h-1 bg-muted">
            <div
              class="h-full bg-primary transition-all duration-500 ease-out rounded-r-full"
              :style="{ width: progressPercent + '%' }"
            ></div>
          </div>

          <!-- Checklist -->
          <div class="max-h-[60vh] overflow-y-auto px-5 py-4 space-y-1">
            <button
              v-for="(step, idx) in reviewSteps"
              :key="step.key"
              @click="toggleCheck(step.key)"
              class="flex items-center gap-3 w-full text-left rounded-lg px-3 py-2.5 transition-colors"
              :class="checked[step.key] ? 'bg-primary/5' : 'hover:bg-accent/50'"
            >
              <span
                class="w-5 h-5 rounded border-2 flex items-center justify-center shrink-0 transition-all"
                :class="checked[step.key]
                  ? 'bg-primary border-primary text-primary-foreground'
                  : 'border-border'"
              >
                <svg v-if="checked[step.key]" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              </span>
              <div class="flex-1 min-w-0">
                <p
                  class="text-sm font-medium"
                  :class="checked[step.key] ? 'text-muted-foreground line-through' : 'text-foreground'"
                >{{ step.title }}</p>
                <p class="text-[11px] text-muted-foreground">{{ step.description }}</p>
              </div>
            </button>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-between px-5 py-3 border-t border-border">
            <button
              v-if="hasAnyChecked"
              @click="resetReview"
              class="rounded-lg px-3 py-1.5 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
            >Reset</button>
            <span v-else></span>
            <button
              @click="completeReview"
              :disabled="!allComplete"
              class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-40 disabled:pointer-events-none transition-colors"
            >Complete Review</button>
          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

const props = defineProps<{
  open: boolean
  isOnline: boolean
}>()

const emit = defineEmits<{
  close: []
  'review-complete': []
}>()

const guardedRouter = {
  put(...args: Parameters<typeof router.put>) { if (!props.isOnline) return; return router.put(...args) },
}

const page = usePage()

const lastReviewDate = computed(() => page.props.last_review as string | null)
const daysSinceReview = computed(() => {
  if (!lastReviewDate.value) return null
  const last = new Date(lastReviewDate.value).getTime()
  return Math.floor((Date.now() - last) / (24 * 60 * 60 * 1000))
})

const reviewSteps = [
  { key: 'collect', title: 'Collect loose ends', description: 'Check inboxes, messages, notes — get everything captured' },
  { key: 'inbox', title: 'Process inbox to zero', description: 'Clarify every item — what is it? Is it actionable?' },
  { key: 'next-actions', title: 'Review next actions', description: 'Still the right next steps? Mark done or update' },
  { key: 'projects', title: 'Review projects', description: 'Each project has at least one next action?' },
  { key: 'waiting', title: 'Review waiting for', description: 'Follow up on anything overdue' },
  { key: 'someday', title: 'Review someday/maybe', description: 'Anything to activate or delete?' },
  { key: 'calendar', title: 'Check calendar', description: 'Past week and next two weeks — anything to act on?' },
  { key: 'goals', title: 'Review goals', description: 'Are your actions aligned with what matters?' },
]

const checked = ref<Record<string, boolean>>({})
const justCompleted = ref(false)

// Load saved progress
const reviewProgressRaw = computed(() => page.props.review_progress as string | null)

function loadProgress() {
  if (justCompleted.value) {
    checked.value = {}
    return
  }
  if (!reviewProgressRaw.value) {
    checked.value = {}
    return
  }
  try {
    const data = JSON.parse(reviewProgressRaw.value)
    checked.value = data.checked ?? {}
  } catch {
    checked.value = {}
  }
}

onMounted(loadProgress)
watch(() => props.open, (v) => { if (v) loadProgress() })

function toggleCheck(key: string) {
  justCompleted.value = false
  checked.value[key] = !checked.value[key]
  saveProgress()
}

let saveTimeout: ReturnType<typeof setTimeout> | null = null
function saveProgress() {
  if (saveTimeout) clearTimeout(saveTimeout)
  saveTimeout = setTimeout(() => {
    const progress = JSON.stringify({ checked: checked.value })
    guardedRouter.put('/settings/review_progress', { value: progress }, { preserveScroll: true, preserveState: true, only: [] })
  }, 500)
}

const allComplete = computed(() => reviewSteps.every(s => checked.value[s.key]))
const hasAnyChecked = computed(() => Object.values(checked.value).some(v => v))
const progressPercent = computed(() => {
  const done = reviewSteps.filter(s => checked.value[s.key]).length
  return Math.round((done / reviewSteps.length) * 100)
})

function resetReview() {
  checked.value = {}
  guardedRouter.put('/settings/review_progress', { value: null }, { preserveScroll: true, preserveState: true, only: [] })
}

function completeReview() {
  checked.value = {}
  justCompleted.value = true
  guardedRouter.put('/settings/review_progress', { value: null }, { preserveScroll: true, preserveState: true, only: [] })
  guardedRouter.put('/settings/last_review', { value: new Date().toISOString() }, { preserveScroll: true, preserveState: true, only: [] })
  emit('review-complete')
  emit('close')
}
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
