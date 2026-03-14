<template>
  <div class="relative">
    <button
      type="button"
      @click="open = !open"
      class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm text-left outline-none focus:ring-2 focus:ring-ring transition-colors"
      :class="modelValue ? 'text-foreground' : 'text-muted-foreground'"
    >
      {{ displayTime }}
    </button>

    <!-- Dropdown picker -->
    <div
      v-if="open"
      class="absolute top-full left-0 mt-1 z-50 bg-card border border-border rounded-xl shadow-xl overflow-hidden"
    >
      <div class="flex items-center gap-0 p-2">
        <!-- Hours wheel -->
        <div class="time-wheel" ref="hourWheel" @scroll="onHourScroll">
          <div class="time-wheel-pad"></div>
          <div
            v-for="h in hours"
            :key="'h-' + h"
            class="time-wheel-item"
            :class="{ 'time-wheel-selected': h === selectedHour }"
            @click="selectHour(h)"
          >{{ h.toString().padStart(2, '0') }}</div>
          <div class="time-wheel-pad"></div>
        </div>

        <span class="text-lg font-bold text-foreground px-1 select-none">:</span>

        <!-- Minutes wheel -->
        <div class="time-wheel" ref="minuteWheel" @scroll="onMinuteScroll">
          <div class="time-wheel-pad"></div>
          <div
            v-for="m in minutes"
            :key="'m-' + m"
            class="time-wheel-item"
            :class="{ 'time-wheel-selected': m === selectedMinute }"
            @click="selectMinute(m)"
          >{{ m.toString().padStart(2, '0') }}</div>
          <div class="time-wheel-pad"></div>
        </div>

        <!-- AM/PM wheel -->
        <div class="time-wheel time-wheel-narrow" ref="periodWheel" @scroll="onPeriodScroll">
          <div class="time-wheel-pad"></div>
          <div
            v-for="p in periods"
            :key="p"
            class="time-wheel-item"
            :class="{ 'time-wheel-selected': p === selectedPeriod }"
            @click="selectPeriod(p)"
          >{{ p }}</div>
          <div class="time-wheel-pad"></div>
        </div>
      </div>

      <!-- Highlight band -->
      <div class="time-wheel-highlight"></div>

      <div class="flex gap-2 p-2 pt-0">
        <button
          type="button"
          @click="clearTime"
          class="flex-1 rounded-lg py-1.5 text-xs font-medium text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
        >Clear</button>
        <button
          type="button"
          @click="confirmTime"
          class="flex-1 rounded-lg bg-primary py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        >Set</button>
      </div>
    </div>

    <!-- Click-away -->
    <div v-if="open" class="fixed inset-0 z-40" @click="confirmTime"></div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue'

const props = defineProps<{ modelValue: string }>()
const emit = defineEmits<{ (e: 'update:modelValue', v: string): void }>()

const open = ref(false)
const selectedHour = ref(9)
const selectedMinute = ref(0)
const selectedPeriod = ref<'AM' | 'PM'>('AM')

const hours = Array.from({ length: 12 }, (_, i) => i + 1) // 1-12
const minutes = Array.from({ length: 12 }, (_, i) => i * 5) // 0,5,10,...,55
const periods = ['AM', 'PM'] as const

const hourWheel = ref<HTMLElement | null>(null)
const minuteWheel = ref<HTMLElement | null>(null)
const periodWheel = ref<HTMLElement | null>(null)

const ITEM_HEIGHT = 36

const displayTime = computed(() => {
  if (!props.modelValue) return 'Set time'
  const [hh, mm] = props.modelValue.split(':').map(Number)
  const period = hh >= 12 ? 'PM' : 'AM'
  const h12 = hh === 0 ? 12 : hh > 12 ? hh - 12 : hh
  return `${h12}:${mm.toString().padStart(2, '0')} ${period}`
})

function parseModelValue() {
  if (!props.modelValue) {
    selectedHour.value = 9
    selectedMinute.value = 0
    selectedPeriod.value = 'AM'
    return
  }
  const [hh, mm] = props.modelValue.split(':').map(Number)
  selectedPeriod.value = hh >= 12 ? 'PM' : 'AM'
  selectedHour.value = hh === 0 ? 12 : hh > 12 ? hh - 12 : hh
  // Snap to nearest 5
  selectedMinute.value = Math.round(mm / 5) * 5
  if (selectedMinute.value >= 60) selectedMinute.value = 55
}

function scrollToSelected() {
  nextTick(() => {
    if (hourWheel.value) {
      const idx = hours.indexOf(selectedHour.value)
      hourWheel.value.scrollTop = idx * ITEM_HEIGHT
    }
    if (minuteWheel.value) {
      const idx = minutes.indexOf(selectedMinute.value)
      minuteWheel.value.scrollTop = idx * ITEM_HEIGHT
    }
    if (periodWheel.value) {
      const idx = periods.indexOf(selectedPeriod.value)
      periodWheel.value.scrollTop = idx * ITEM_HEIGHT
    }
  })
}

watch(open, (v) => {
  if (v) {
    parseModelValue()
    scrollToSelected()
  }
})

function selectHour(h: number) {
  selectedHour.value = h
  if (hourWheel.value) {
    const idx = hours.indexOf(h)
    hourWheel.value.scrollTo({ top: idx * ITEM_HEIGHT, behavior: 'smooth' })
  }
}

function selectMinute(m: number) {
  selectedMinute.value = m
  if (minuteWheel.value) {
    const idx = minutes.indexOf(m)
    minuteWheel.value.scrollTo({ top: idx * ITEM_HEIGHT, behavior: 'smooth' })
  }
}

function selectPeriod(p: 'AM' | 'PM') {
  selectedPeriod.value = p
  if (periodWheel.value) {
    const idx = periods.indexOf(p)
    periodWheel.value.scrollTo({ top: idx * ITEM_HEIGHT, behavior: 'smooth' })
  }
}

let hourScrollTimer: ReturnType<typeof setTimeout> | null = null
function onHourScroll() {
  if (hourScrollTimer) clearTimeout(hourScrollTimer)
  hourScrollTimer = setTimeout(() => {
    if (!hourWheel.value) return
    const idx = Math.round(hourWheel.value.scrollTop / ITEM_HEIGHT)
    const clamped = Math.max(0, Math.min(idx, hours.length - 1))
    selectedHour.value = hours[clamped]
    hourWheel.value.scrollTo({ top: clamped * ITEM_HEIGHT, behavior: 'smooth' })
  }, 80)
}

let minuteScrollTimer: ReturnType<typeof setTimeout> | null = null
function onMinuteScroll() {
  if (minuteScrollTimer) clearTimeout(minuteScrollTimer)
  minuteScrollTimer = setTimeout(() => {
    if (!minuteWheel.value) return
    const idx = Math.round(minuteWheel.value.scrollTop / ITEM_HEIGHT)
    const clamped = Math.max(0, Math.min(idx, minutes.length - 1))
    selectedMinute.value = minutes[clamped]
    minuteWheel.value.scrollTo({ top: clamped * ITEM_HEIGHT, behavior: 'smooth' })
  }, 80)
}

let periodScrollTimer: ReturnType<typeof setTimeout> | null = null
function onPeriodScroll() {
  if (periodScrollTimer) clearTimeout(periodScrollTimer)
  periodScrollTimer = setTimeout(() => {
    if (!periodWheel.value) return
    const idx = Math.round(periodWheel.value.scrollTop / ITEM_HEIGHT)
    const clamped = Math.max(0, Math.min(idx, periods.length - 1))
    selectedPeriod.value = periods[clamped]
    periodWheel.value.scrollTo({ top: clamped * ITEM_HEIGHT, behavior: 'smooth' })
  }, 80)
}

function to24(h: number, period: 'AM' | 'PM'): number {
  if (period === 'AM') return h === 12 ? 0 : h
  return h === 12 ? 12 : h + 12
}

function confirmTime() {
  const hh = to24(selectedHour.value, selectedPeriod.value)
  emit('update:modelValue', `${hh.toString().padStart(2, '0')}:${selectedMinute.value.toString().padStart(2, '0')}`)
  open.value = false
}

function clearTime() {
  emit('update:modelValue', '')
  open.value = false
}
</script>

<style scoped>
.time-wheel {
  width: 52px;
  height: calc(36px * 3);
  overflow-y: auto;
  scroll-snap-type: y mandatory;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  position: relative;
}
.time-wheel::-webkit-scrollbar { display: none; }
.time-wheel-narrow { width: 44px; }
.time-wheel-pad { height: 36px; flex-shrink: 0; }
.time-wheel-item {
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 500;
  color: var(--muted-foreground);
  cursor: pointer;
  scroll-snap-align: start;
  transition: color 150ms, opacity 150ms;
  user-select: none;
  opacity: 0.4;
}
.time-wheel-item:hover { opacity: 0.7; }
.time-wheel-selected {
  color: var(--foreground);
  font-weight: 700;
  opacity: 1 !important;
}
.time-wheel-highlight {
  position: absolute;
  top: calc(8px + 36px);
  left: 8px;
  right: 8px;
  height: 36px;
  border-radius: 8px;
  background: var(--accent);
  opacity: 0.5;
  pointer-events: none;
}
</style>
