<template>
  <div class="flex flex-col h-[calc(100vh-7rem)] rounded-xl border border-border overflow-hidden" data-testid="review-view">

    <!-- Header -->
    <div class="flex items-center justify-between px-5 py-3 border-b border-border bg-card/50">
      <div>
        <h2 class="text-lg font-semibold text-foreground">Weekly Review</h2>
        <p class="text-[11px] text-muted-foreground">
          {{ daysSinceReview === null ? "You haven't completed a review yet." : `Last review ${daysSinceReview} day${daysSinceReview === 1 ? '' : 's'} ago` }}
        </p>
      </div>
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-4 text-[12px] text-muted-foreground">
          <div class="text-center">
            <p class="text-sm font-bold text-foreground">{{ inbox.length }}</p>
            <p>Inbox</p>
          </div>
          <div class="text-center">
            <p class="text-sm font-bold text-foreground">{{ itemsByStatus('next-action').length }}</p>
            <p>Actions</p>
          </div>
          <div class="text-center">
            <p class="text-sm font-bold text-foreground">{{ itemsByStatus('project').length }}</p>
            <p>Projects</p>
          </div>
          <div class="text-center">
            <p class="text-sm font-bold text-foreground">{{ itemsByStatus('waiting').length }}</p>
            <p>Waiting</p>
          </div>
        </div>
        <button
          v-if="hasAnyChecked"
          @click="resetReview"
          class="rounded-lg px-3 py-1.5 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
        >Reset</button>
        <button
          @click="completeReview"
          :disabled="!allStepsComplete"
          class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-40 disabled:pointer-events-none transition-colors"
        >Complete Review</button>
      </div>
    </div>

    <!-- Progress bar -->
    <div class="h-1 bg-muted">
      <div
        class="h-full bg-primary transition-all duration-500 ease-out rounded-r-full"
        :style="{ width: progressPercent + '%' }"
      ></div>
    </div>

    <!-- Checklist -->
    <div class="flex-1 overflow-y-auto">
      <div class="max-w-3xl mx-auto py-6 px-5 space-y-2">

        <div
          v-for="(step, stepIdx) in reviewSteps"
          :key="step.key"
          class="rounded-xl border border-border overflow-hidden transition-all"
          :class="isStepComplete(step) ? 'bg-primary/3 border-primary/20' : 'bg-card'"
        >
          <!-- Step header (clickable to expand/collapse) -->
          <button
            @click="toggleExpand(step.key)"
            class="w-full flex items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-accent/30"
          >
            <!-- Completion indicator -->
            <span
              class="w-6 h-6 rounded-full border-2 flex items-center justify-center shrink-0 transition-all text-xs"
              :class="isStepComplete(step) ? 'bg-primary border-primary text-primary-foreground' : 'border-border'"
            >
              <svg v-if="isStepComplete(step)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
              <span v-else class="text-muted-foreground font-medium">{{ stepIdx + 1 }}</span>
            </span>

            <span class="text-lg">{{ step.icon }}</span>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold" :class="isStepComplete(step) ? 'text-muted-foreground' : 'text-foreground'">{{ step.title }}</p>
              <p class="text-[11px] text-muted-foreground">{{ step.description }}</p>
            </div>

            <!-- Item count badge for dynamic steps -->
            <span
              v-if="step.dynamic"
              class="text-[11px] font-medium px-2 py-0.5 rounded-full shrink-0"
              :class="itemsByStatus(step.dynamic).length > 0 ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'"
            >{{ itemsByStatus(step.dynamic).length }}</span>

            <!-- Stuck count -->
            <span
              v-if="step.stuckProjects"
              class="text-[11px] font-medium px-2 py-0.5 rounded-full shrink-0"
              :class="stuckProjectList.length > 0 ? 'bg-amber-500/10 text-amber-600' : 'bg-muted text-muted-foreground'"
            >{{ stuckProjectList.length }}</span>

            <!-- Expand arrow -->
            <svg
              width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
              class="text-muted-foreground shrink-0 transition-transform"
              :class="expanded[step.key] ? 'rotate-180' : ''"
            ><polyline points="6 9 12 15 18 9"/></svg>
          </button>

          <!-- Step content (expanded) -->
          <div v-if="expanded[step.key]" class="px-4 pb-4 pt-1 border-t border-border/40">

            <!-- Checklist items -->
            <div v-if="step.checklist" class="space-y-1">
              <button
                v-for="(item, idx) in step.checklist"
                :key="idx"
                @click="toggleCheck(step.key + '-' + idx)"
                class="flex items-center gap-3 w-full text-left rounded-lg px-3 py-2 transition-colors"
                :class="checked[step.key + '-' + idx] ? 'bg-primary/5' : 'hover:bg-accent/50'"
              >
                <span
                  class="w-4.5 h-4.5 rounded border-2 flex items-center justify-center shrink-0 transition-all"
                  :class="checked[step.key + '-' + idx]
                    ? 'bg-primary border-primary text-primary-foreground'
                    : 'border-border'"
                >
                  <svg v-if="checked[step.key + '-' + idx]" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </span>
                <span
                  class="text-sm"
                  :class="checked[step.key + '-' + idx] ? 'text-muted-foreground line-through' : 'text-foreground'"
                >{{ item }}</span>
              </button>
            </div>

            <!-- Dynamic item list -->
            <div v-else-if="step.dynamic">
              <div v-if="itemsByStatus(step.dynamic).length === 0" class="py-4 text-center">
                <p class="text-sm text-muted-foreground">No {{ step.dynamic === 'inbox' ? 'items in inbox' : step.dynamic.replace('-', ' ') + ' items' }} — you're good!</p>
              </div>
              <div v-else class="space-y-1">
                <div
                  v-for="item in itemsByStatus(step.dynamic)"
                  :key="item.id"
                  class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors"
                  :class="checked['item-' + item.id] ? 'bg-primary/5' : 'hover:bg-accent/50'"
                >
                  <button
                    @click="toggleCheck('item-' + item.id)"
                    class="w-4.5 h-4.5 rounded border-2 flex items-center justify-center shrink-0 transition-all"
                    :class="checked['item-' + item.id]
                      ? 'bg-primary border-primary text-primary-foreground'
                      : 'border-border'"
                  >
                    <svg v-if="checked['item-' + item.id]" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  </button>
                  <div class="flex-1 min-w-0">
                    <p
                      class="text-sm font-medium truncate"
                      :class="checked['item-' + item.id] ? 'text-muted-foreground line-through' : 'text-foreground'"
                    >{{ item.title }}</p>
                    <p v-if="item.context || item.waiting_for" class="text-[11px] text-muted-foreground">
                      <span v-if="item.context">{{ item.context }}</span>
                      <span v-if="item.context && item.waiting_for"> · </span>
                      <span v-if="item.waiting_for">waiting on {{ item.waiting_for }}</span>
                    </p>
                  </div>
                  <!-- Project info -->
                  <template v-if="step.dynamic === 'project'">
                    <span class="text-[11px] font-medium px-2 py-0.5 rounded bg-muted text-muted-foreground shrink-0">
                      {{ projectNextCount(item.id) }} actions
                    </span>
                    <span v-if="isProjectStuck(item.id)" class="text-[11px] font-medium px-2 py-0.5 rounded bg-amber-500/10 text-amber-600 shrink-0">Stuck</span>
                  </template>
                  <div class="flex items-center gap-1 shrink-0">
                    <button
                      @click="$emit('open-item', item)"
                      class="text-[11px] text-muted-foreground hover:text-foreground px-2 py-1 rounded-md hover:bg-accent transition-colors"
                    >Edit</button>
                    <button
                      v-if="step.dynamic !== 'inbox'"
                      @click="markDone(item.id)"
                      class="text-[11px] text-muted-foreground hover:text-primary px-2 py-1 rounded-md hover:bg-primary/10 transition-colors"
                    >Done</button>
                  </div>
                </div>
                <!-- Inbox: process button -->
                <div v-if="step.dynamic === 'inbox' && itemsByStatus('inbox').length > 0" class="pt-2">
                  <button
                    @click="$emit('open-process')"
                    class="w-full rounded-lg bg-primary/10 text-primary px-4 py-2 text-sm font-medium hover:bg-primary/20 transition-colors"
                  >Process Inbox ({{ itemsByStatus('inbox').length }} items)</button>
                </div>
              </div>
            </div>

            <!-- Stuck projects -->
            <div v-else-if="step.stuckProjects">
              <div v-if="stuckProjectList.length === 0" class="py-4 text-center">
                <p class="text-sm text-muted-foreground">All projects have at least one next action</p>
              </div>
              <div v-else class="space-y-3">
                <div
                  v-for="project in stuckProjectList"
                  :key="project.id"
                  class="rounded-lg border border-border p-3"
                >
                  <div class="flex items-center gap-2 mb-2">
                    <span class="text-sm">🧴</span>
                    <p class="text-sm font-semibold text-foreground truncate">{{ project.title }}</p>
                  </div>
                  <div class="flex gap-2">
                    <input
                      v-model="stuckNextTitle[project.id]"
                      @keydown.enter="addNextAction(project.id)"
                      type="text"
                      placeholder="Define a next action..."
                      class="flex-1 rounded-lg border border-border bg-background px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30"
                    />
                    <button
                      @click="addNextAction(project.id)"
                      :disabled="!(stuckNextTitle[project.id] || '').trim()"
                      class="rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-40"
                    >Add</button>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

const props = defineProps<{
  isOnline: boolean
  icons: { reviewSteps: Record<string, string> }
}>()

const emit = defineEmits<{
  'open-item': [item: any]
  'open-process': []
  'review-complete': []
}>()

const guardedRouter = {
  post(...args: Parameters<typeof router.post>) { if (!props.isOnline) return; return router.post(...args) },
  put(...args: Parameters<typeof router.put>) { if (!props.isOnline) return; return router.put(...args) },
  delete(...args: Parameters<typeof router.delete>) { if (!props.isOnline) return; return router.delete(...args) },
}

const page = usePage()
const items = computed(() => (page.props.items || []) as any[])
const itemOnly = { preserveScroll: true, only: ['items'] }

const lastReviewDate = computed(() => page.props.last_review as string | null)

const REVIEW_INTERVAL_MS = 7 * 24 * 60 * 60 * 1000
const daysSinceReview = computed(() => {
  if (!lastReviewDate.value) return null
  const last = new Date(lastReviewDate.value).getTime()
  return Math.floor((Date.now() - last) / (24 * 60 * 60 * 1000))
})

const inbox = computed(() => items.value.filter(i => i.status === 'inbox'))

function itemsByStatus(status: string) {
  return items.value.filter(i => i.status === status)
}

// Projects
const projects = computed(() => items.value.filter(i => i.status === 'project'))
const projectTasksMap = computed(() => {
  const map = new Map<string, any[]>()
  for (const item of items.value) {
    if (item.project_id) {
      if (!map.has(item.project_id)) map.set(item.project_id, [])
      map.get(item.project_id)!.push(item)
    }
  }
  return map
})

function projectNextCount(id: string): number {
  return (projectTasksMap.value.get(id) || []).filter((t: any) => t.status === 'next-action').length
}

function isProjectStuck(id: string): boolean {
  const tasks = projectTasksMap.value.get(id) || []
  return tasks.filter((t: any) => t.status === 'next-action').length === 0
}

const stuckProjectList = computed(() => projects.value.filter(p => isProjectStuck(p.id)))

// Review steps
const reviewSteps = computed(() => {
  const icons = props.icons.reviewSteps
  return [
    {
      key: 'collect',
      title: 'Collect Loose Ends',
      icon: icons.collect,
      description: 'Get everything out of your head and into the inbox.',
      checklist: [
        'Check physical inboxes (desk, bag, mail)',
        'Check email inboxes and flag anything actionable',
        'Check messaging apps (Slack, texts, voicemail)',
        'Check notes, sticky notes, or scraps of paper',
        'Do a mind sweep — anything nagging you?',
      ],
    },
    {
      key: 'inbox',
      title: 'Process Inbox to Zero',
      icon: icons.inbox,
      description: 'Clarify every item. What is it? Is it actionable?',
      dynamic: 'inbox',
    },
    {
      key: 'next-actions',
      title: 'Review Next Actions',
      icon: icons.nextActions,
      description: 'Is each one still the right next step? Mark done or update.',
      dynamic: 'next-action',
    },
    {
      key: 'projects',
      title: 'Review Projects',
      icon: icons.projects,
      description: 'Does each project have at least one next action?',
      dynamic: 'project',
    },
    {
      key: 'stuck-projects',
      title: 'Fix Stuck Projects',
      icon: icons.stuck,
      description: 'These projects have no next action. Define one to keep them moving.',
      stuckProjects: true,
    },
    {
      key: 'waiting',
      title: 'Review Waiting For',
      icon: icons.waiting,
      description: 'Follow up on anything overdue. Is anyone still blocked?',
      dynamic: 'waiting',
    },
    {
      key: 'someday',
      title: 'Review Someday/Maybe',
      icon: icons.someday,
      description: 'Anything ready to activate? Anything to delete?',
      dynamic: 'someday',
    },
    {
      key: 'calendar',
      title: 'Check Calendar',
      icon: icons.calendar,
      description: 'Look back at the past week and ahead at the next two.',
      checklist: [
        'Review last 7 days — anything unfinished?',
        'Review next 14 days — anything to prepare for?',
        'Any deadlines or appointments you need to plan around?',
      ],
    },
    {
      key: 'goals',
      title: 'Review Goals & Horizons',
      icon: icons.goals,
      description: 'Are your actions aligned with what matters?',
      checklist: [
        'Are your current projects supporting your goals?',
        'Any new projects or commitments to capture?',
        'Anything you should say no to or renegotiate?',
        'How are you feeling about your workload?',
      ],
    },
  ]
})

// State
const checked = ref<Record<string, boolean>>({})
const expanded = ref<Record<string, boolean>>({})
const stuckNextTitle = ref<Record<string, string>>({})

// Load saved progress
const reviewProgressRaw = computed(() => page.props.review_progress as string | null)

onMounted(() => {
  loadProgress()
  // Expand first incomplete step
  for (const step of reviewSteps.value) {
    if (!isStepComplete(step)) {
      expanded.value[step.key] = true
      break
    }
  }
})

function loadProgress() {
  if (reviewProgressRaw.value) {
    try {
      const data = JSON.parse(reviewProgressRaw.value)
      checked.value = data.checked ?? {}
    } catch {
      checked.value = {}
    }
  }
}

function toggleCheck(key: string) {
  checked.value[key] = !checked.value[key]
  saveProgress()
}

function toggleExpand(key: string) {
  expanded.value[key] = !expanded.value[key]
}

// Auto-save debounced
let saveTimeout: ReturnType<typeof setTimeout> | null = null
function saveProgress() {
  if (saveTimeout) clearTimeout(saveTimeout)
  saveTimeout = setTimeout(() => {
    const progress = JSON.stringify({ checked: checked.value })
    guardedRouter.put('/settings/review_progress', { value: progress }, { preserveScroll: true, preserveState: true })
  }, 500)
}

function isStepComplete(step: any): boolean {
  if (step.checklist) {
    return step.checklist.every((_: string, idx: number) => checked.value[step.key + '-' + idx])
  }
  if (step.dynamic) {
    const items = itemsByStatus(step.dynamic)
    if (items.length === 0) return true
    return items.every((item: any) => checked.value['item-' + item.id])
  }
  if (step.stuckProjects) {
    return stuckProjectList.value.length === 0
  }
  return false
}

const allStepsComplete = computed(() => reviewSteps.value.every(s => isStepComplete(s)))
const hasAnyChecked = computed(() => Object.values(checked.value).some(v => v))

const progressPercent = computed(() => {
  const total = reviewSteps.value.length
  const done = reviewSteps.value.filter(s => isStepComplete(s)).length
  return Math.round((done / total) * 100)
})

function resetReview() {
  checked.value = {}
  guardedRouter.put('/settings/review_progress', { value: null }, { preserveScroll: true, preserveState: true })
}

function completeReview() {
  checked.value = {}
  guardedRouter.put('/settings/review_progress', { value: null }, { preserveScroll: true, preserveState: true })
  guardedRouter.put('/settings/last_review', { value: new Date().toISOString() }, { preserveScroll: true, preserveState: true })
  emit('review-complete')
}

function markDone(itemId: string) {
  guardedRouter.post(`/items/${itemId}/process`, { status: 'done' }, itemOnly)
}

function addNextAction(projectId: string) {
  const title = (stuckNextTitle.value[projectId] || '').trim()
  if (!title) return
  guardedRouter.post('/items', {
    title,
    status: 'next-action',
    project_id: projectId,
  }, {
    ...itemOnly,
    onSuccess: () => { stuckNextTitle.value[projectId] = '' },
  })
}

// Auto-collapse completed steps, auto-expand next incomplete
watch(checked, () => {
  for (const step of reviewSteps.value) {
    if (isStepComplete(step) && expanded.value[step.key]) {
      // Delay collapse to let the user see the check animation
      setTimeout(() => {
        expanded.value[step.key] = false
        // Expand next incomplete
        for (const s of reviewSteps.value) {
          if (!isStepComplete(s) && !expanded.value[s.key]) {
            expanded.value[s.key] = true
            break
          }
        }
      }, 600)
      break
    }
  }
}, { deep: true })
</script>
