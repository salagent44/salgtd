<template>
  <div class="flex flex-col h-[calc(100vh-7rem)] rounded-xl border border-border overflow-hidden" data-testid="calendar-view">

    <!-- Month navigation + view toggle -->
    <div class="flex items-center justify-between px-5 py-3 border-b border-border bg-card/50">
      <button @click="prevMonth" class="rounded-lg px-4 py-2 text-[15px] text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">&larr;</button>
      <div class="text-center">
        <h2 class="text-lg font-semibold text-foreground">{{ monthName }} {{ currentYear }}</h2>
      </div>
      <div class="flex items-center gap-2">
        <!-- View toggle -->
        <div class="flex rounded-lg bg-muted p-0.5 gap-0.5">
          <button
            @click="calendarMode = 'grid'"
            class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
            :class="calendarMode === 'grid' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            data-testid="calendar-grid-btn"
          >
            <svg class="inline -mt-0.5 mr-0.5" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
            Grid
          </button>
          <button
            @click="calendarMode = 'list'"
            class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
            :class="calendarMode === 'list' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            data-testid="calendar-list-btn"
          >
            <svg class="inline -mt-0.5 mr-0.5" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            List
          </button>
          <button
            @click="calendarMode = 'upcoming'"
            class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
            :class="calendarMode === 'upcoming' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
            data-testid="calendar-upcoming-btn"
          >
            <svg class="inline -mt-0.5 mr-0.5" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Upcoming
          </button>
        </div>
        <button @click="goToToday" class="rounded-lg px-4 py-2 text-sm font-medium bg-muted text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">Today</button>
        <button
          @click="openAddModal(selectedDate)"
          class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
          data-testid="calendar-add-btn"
        >+ Event</button>
        <button @click="nextMonth" class="rounded-lg px-4 py-2 text-[15px] text-muted-foreground hover:bg-accent hover:text-foreground transition-colors">&rarr;</button>
      </div>
    </div>

    <!-- ===== GRID VIEW ===== -->
    <template v-if="calendarMode === 'grid'">
      <!-- Day headers -->
      <div class="grid grid-cols-7 border-b border-border bg-card/30">
        <div v-for="day in dayNames" :key="day" class="px-2 py-2 text-center text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
          {{ day }}
        </div>
      </div>

      <!-- Calendar grid -->
      <div class="flex-1 grid grid-cols-7 auto-rows-fr">
        <div
          v-for="(cell, idx) in calendarCells"
          :key="idx"
          class="border-r border-b border-border/40 p-1 min-h-0 transition-colors cursor-pointer group"
          :class="{
            'bg-card/20': cell.currentMonth,
            'bg-transparent opacity-40': !cell.currentMonth,
            'ring-1 ring-inset ring-primary/40': cell.isToday,
            'bg-primary/5 ring-1 ring-inset ring-primary/30': dragOverDate === cell.dateStr,
          }"
          @click="openAddModal(cell.dateStr)"
          @dragover.prevent="onDragOver(cell.dateStr)"
          @dragleave="onDragLeave"
          @drop.prevent="onDrop(cell.dateStr)"
          :data-testid="cell.isToday ? 'calendar-today' : undefined"
          :data-date="cell.dateStr"
        >
          <div class="flex items-center justify-between mb-0.5">
            <span
              class="text-[12px] font-medium w-6 h-6 flex items-center justify-center rounded-full"
              :class="{
                'bg-primary text-primary-foreground': cell.isToday,
                'text-foreground': cell.currentMonth && !cell.isToday,
                'text-muted-foreground': !cell.currentMonth,
              }"
            >{{ cell.day }}</span>
          </div>
          <!-- Events for this day -->
          <div class="space-y-0.5 overflow-hidden">
            <div
              v-for="event in getEventsForDate(cell.dateStr)"
              :key="event.id + '-' + cell.dateStr"
              class="py-1 text-[13px] leading-snug font-medium truncate cursor-grab active:cursor-grabbing transition-all"
              :class="[
                eventColorClass(event.color),
                draggingId === event.id ? 'opacity-40 scale-95' : '',
                multiDayPosition(event, cell.dateStr),
              ]"
              draggable="true"
              @dragstart="onDragStart(event, $event)"
              @dragend="onDragEnd"
              @click.stop="editEvent(event)"
              :data-testid="'calendar-event-' + event.id"
            >
              <template v-if="isMultiDayStart(event, cell.dateStr) || !isMultiDay(event)">
                <span v-if="event.recurrence" class="opacity-60 mr-0.5">🔁</span><span v-if="event.event_time" class="opacity-70 mr-1">{{ event.event_time }}</span>{{ event.title }}
              </template>
              <template v-else>
                <span class="opacity-0">.</span>
              </template>
            </div>
            <div
              v-if="getEventsForDate(cell.dateStr).length > 3"
              class="text-[12px] text-muted-foreground pl-1"
            >+{{ getEventsForDate(cell.dateStr).length - 3 }} more</div>
          </div>
        </div>
      </div>
    </template>

    <!-- ===== LIST VIEW ===== -->
    <div v-else-if="calendarMode === 'list'" class="flex-1 overflow-y-auto cal-list-view" data-testid="calendar-list-view">
      <div v-if="listViewDays.length === 0" class="p-12 text-center">
        <p class="text-sm text-muted-foreground">No events this month</p>
        <button
          @click="openAddModal(selectedDate)"
          class="mt-3 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        >+ Add Event</button>
      </div>
      <div v-for="dayGroup in listViewDays" :key="dayGroup.dateStr" class="cal-list-day group">
        <div
          class="cal-list-day-header sticky top-0"
          :class="{ 'cal-list-today': dayGroup.isToday }"
          @dragover.prevent="onDragOver(dayGroup.dateStr)"
          @dragleave="onDragLeave"
          @drop.prevent="onDrop(dayGroup.dateStr)"
        >
          <div class="flex items-center gap-3">
            <span
              class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
              :class="dayGroup.isToday ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground'"
            >{{ dayGroup.dayNum }}</span>
            <div>
              <p class="text-sm font-semibold text-foreground">{{ dayGroup.weekday }}</p>
              <p class="text-[11px] text-muted-foreground">{{ dayGroup.fullDate }}</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-[11px] text-muted-foreground">{{ dayGroup.events.length }} {{ dayGroup.events.length === 1 ? 'event' : 'events' }}</span>
            <button
              @click="openAddModal(dayGroup.dateStr)"
              class="opacity-0 group-hover:opacity-100 rounded-md px-2 py-1 text-[11px] font-medium bg-primary/10 text-primary hover:bg-primary/20 transition-all"
            >+ Add</button>
          </div>
        </div>
        <div class="divide-y divide-border/40">
          <div
            v-for="event in dayGroup.events"
            :key="event.id"
            class="cal-list-event group/item"
            :class="draggingId === event.id ? 'opacity-40' : ''"
            draggable="true"
            @dragstart="onDragStart(event, $event)"
            @dragend="onDragEnd"
            @click="editEvent(event)"
          >
            <div class="flex items-center gap-3 flex-1 min-w-0">
              <div class="w-2.5 h-2.5 rounded-full shrink-0" :class="eventDotClass(event.color)"></div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-foreground truncate">{{ event.title }}</p>
                <p v-if="event.description" class="text-[11px] text-muted-foreground truncate">{{ event.description }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3 shrink-0">
              <span v-if="event.event_time" class="text-xs text-muted-foreground font-mono">{{ event.event_time }}</span>
              <button
                @click.stop="deleteEvent(event.id)"
                class="opacity-0 group-hover/item:opacity-100 text-[10px] text-destructive/50 hover:text-destructive transition-all"
              >Remove</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Days without events (collapsed) -->
      <div v-if="emptyDaysCount > 0" class="px-5 py-3 text-center">
        <p class="text-[11px] text-muted-foreground">{{ emptyDaysCount }} days with no events</p>
      </div>
    </div>

    <!-- ===== UPCOMING VIEW (Things 3 style) ===== -->
    <div v-else class="flex-1 overflow-y-auto cal-list-view" data-testid="calendar-upcoming-view">
      <div v-if="upcomingEvents.length === 0" class="p-12 text-center">
        <p class="text-sm text-muted-foreground">No upcoming events</p>
        <button
          @click="openAddModal(selectedDate)"
          class="mt-3 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
        >+ Add Event</button>
      </div>
      <div v-for="group in upcomingGroups" :key="group.label" class="cal-upcoming-group">
        <div class="cal-upcoming-group-header">
          <span class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">{{ group.label }}</span>
          <span class="text-[10px] text-muted-foreground">{{ group.events.length }} {{ group.events.length === 1 ? 'event' : 'events' }}</span>
        </div>
        <div class="divide-y divide-border/30">
          <div
            v-for="item in group.events"
            :key="item.event.id"
            class="cal-upcoming-item"
            @click="editEvent(item.event)"
          >
            <p class="text-sm" :class="item.daysAway === 0 ? 'text-red-500' : item.daysAway <= 3 ? 'text-yellow-500' : 'text-foreground'">
              <span class="font-medium">{{ item.event.title }}</span>
              <span :class="item.daysAway === 0 ? 'text-red-400' : item.daysAway <= 3 ? 'text-yellow-400' : 'text-muted-foreground'">
                {{ ` due in ${item.daysAway} ${item.daysAway === 1 ? 'day' : 'days'}` }}
              </span>
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Add Event Modal ===== -->
    <div v-if="addingEvent" class="fixed inset-0 bg-black/40 flex items-start justify-center pt-[10vh] p-4 z-50" @click.self="addingEvent = false">
      <div class="bg-card rounded-xl border border-border shadow-xl w-full max-w-md overflow-hidden" role="dialog">
        <div class="px-5 pt-5 pb-3">
          <p class="text-sm font-semibold text-foreground">New Event</p>
        </div>
        <div class="px-5 pb-5 space-y-3">
          <input
            ref="addTitleInput"
            v-model="newEvent.title"
            type="text"
            placeholder="Event title"
            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
            @keydown.enter="saveNewEvent"
            @keydown.esc="addingEvent = false"
            data-testid="calendar-event-title"
          />
          <!-- Dates -->
          <div class="flex gap-2">
            <div class="flex-1">
              <label class="text-[11px] text-muted-foreground block mb-1">Start date</label>
              <input v-model="newEvent.startDate" type="date" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring text-foreground" />
            </div>
            <div class="flex-1">
              <label class="text-[11px] text-muted-foreground block mb-1">End date <span class="opacity-50">(optional)</span></label>
              <input v-model="newEvent.endDate" type="date" :min="newEvent.startDate" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring text-foreground" />
            </div>
          </div>
          <!-- Times -->
          <div class="flex gap-2 items-end">
            <div class="flex-1">
              <label class="text-[11px] text-muted-foreground block mb-1">Start time</label>
              <TimePicker v-model="newEvent.startTime" data-testid="calendar-event-time" />
            </div>
            <div class="flex-1">
              <label class="text-[11px] text-muted-foreground block mb-1">End time</label>
              <TimePicker v-model="newEvent.endTime" />
            </div>
            <select
              v-model="newEvent.color"
              class="rounded-lg border border-input bg-background px-2 py-2 text-sm outline-none focus:ring-2 focus:ring-ring text-foreground"
              data-testid="calendar-event-color"
            >
              <option value="blue">Blue</option>
              <option value="red">Red</option>
              <option value="green">Green</option>
              <option value="yellow">Yellow</option>
              <option value="purple">Purple</option>
            </select>
          </div>
          <!-- Recurrence -->
          <div>
            <label class="text-[11px] text-muted-foreground block mb-1">Repeat</label>
            <select v-model="newEvent.recurrence" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring text-foreground">
              <option value="">No repeat</option>
              <option value="weekly">Every week</option>
              <option value="monthly">Every month</option>
              <option value="yearly">Every year (e.g. birthday)</option>
            </select>
          </div>
          <input
            v-model="newEvent.description"
            type="text"
            placeholder="Description (optional)"
            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
            @keydown.enter="saveNewEvent"
            @keydown.esc="addingEvent = false"
            data-testid="calendar-event-desc"
          />
          <div class="flex gap-2 pt-1">
            <button
              @click="saveNewEvent"
              :disabled="!newEvent.title.trim()"
              class="flex-1 rounded-lg bg-primary px-4 py-2.5 text-[15px] font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-40 disabled:pointer-events-none transition-colors"
              data-testid="calendar-add-event-btn"
            >Add Event</button>
            <button
              @click="addingEvent = false"
              class="rounded-lg bg-muted px-4 py-2.5 text-[15px] font-medium text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
            >Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== Edit Event Modal ===== -->
    <div v-if="editingEvent" class="fixed inset-0 bg-black/40 flex items-start justify-center pt-[10vh] p-4 z-50" @click.self="editingEvent = null">
      <div class="bg-card rounded-xl border border-border shadow-xl w-full max-w-md overflow-hidden" role="dialog">
        <div class="px-5 pt-5 pb-3 flex items-center justify-between">
          <p class="text-sm font-semibold text-foreground">Edit Event</p>
          <button
            @click="deleteEvent(editingEvent!.id)"
            class="text-[12px] text-destructive/60 hover:text-destructive transition-colors"
          >Delete</button>
        </div>
        <div class="px-5 pb-5 space-y-3">
          <input
            v-model="editingEvent.title"
            type="text"
            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
            @keydown.enter="saveEditEvent"
            @keydown.esc="editingEvent = null"
            data-testid="calendar-edit-title"
          />
          <!-- Dates -->
          <div class="flex gap-2">
            <div class="flex-1">
              <label class="text-[11px] text-muted-foreground block mb-1">Start date</label>
              <input v-model="editingEvent.event_date" type="date" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring text-foreground" />
            </div>
            <div class="flex-1">
              <label class="text-[11px] text-muted-foreground block mb-1">End date</label>
              <input v-model="editingEvent.end_date" type="date" :min="editingEvent.event_date" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring text-foreground" />
            </div>
          </div>
          <!-- Times -->
          <div class="flex gap-2 items-end">
            <div class="flex-1">
              <label class="text-[11px] text-muted-foreground block mb-1">Start time</label>
              <TimePicker v-model="editEventTime" />
            </div>
            <div class="flex-1">
              <label class="text-[11px] text-muted-foreground block mb-1">End time</label>
              <TimePicker v-model="editEventEndTime" />
            </div>
            <select
              v-model="editingEvent.color"
              class="rounded-lg border border-input bg-background px-2 py-2 text-sm outline-none focus:ring-2 focus:ring-ring text-foreground"
            >
              <option value="blue">Blue</option>
              <option value="red">Red</option>
              <option value="green">Green</option>
              <option value="yellow">Yellow</option>
              <option value="purple">Purple</option>
            </select>
          </div>
          <!-- Recurrence -->
          <div>
            <label class="text-[11px] text-muted-foreground block mb-1">Repeat</label>
            <select v-model="editingEvent.recurrence" class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring text-foreground">
              <option :value="null">No repeat</option>
              <option value="weekly">Every week</option>
              <option value="monthly">Every month</option>
              <option value="yearly">Every year (e.g. birthday)</option>
            </select>
          </div>
          <input
            v-model="editingEvent.description"
            type="text"
            placeholder="Description"
            class="w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
            @keydown.enter="saveEditEvent"
            @keydown.esc="editingEvent = null"
          />
          <div class="flex gap-2 pt-1">
            <button
              @click="saveEditEvent"
              class="flex-1 rounded-lg bg-primary px-4 py-2.5 text-[15px] font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
              data-testid="calendar-save-edit-btn"
            >Save</button>
            <button
              @click="editingEvent = null"
              class="rounded-lg bg-muted px-4 py-2.5 text-[15px] font-medium text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
            >Cancel</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Drag ghost indicator -->
    <div v-if="draggingId" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 pointer-events-none">
      <div class="bg-card border border-border rounded-lg shadow-lg px-3 py-1.5 text-xs font-medium text-foreground">
        Drop on a day to move event
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import TimePicker from './TimePicker.vue'

const props = defineProps<{ isOnline: boolean }>()

const guardedRouter = {
  post(...args: Parameters<typeof router.post>) { if (!props.isOnline) return; return router.post(...args) },
  put(...args: Parameters<typeof router.put>) { if (!props.isOnline) return; return router.put(...args) },
  delete(...args: Parameters<typeof router.delete>) { if (!props.isOnline) return; return router.delete(...args) },
}

const page = usePage()

interface CalendarEvent {
  id: string
  title: string
  event_date: string
  end_date: string | null
  event_time: string | null
  end_time: string | null
  description: string
  color: string
  recurrence: string | null
  created_at: string
  updated_at: string
}

interface CalendarCell {
  day: number
  dateStr: string
  currentMonth: boolean
  isToday: boolean
}

const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const events = computed(() => (page.props.events || []) as CalendarEvent[])
const currentMonth = ref(new Date().getMonth())
const currentYear = ref(new Date().getFullYear())
const selectedDate = ref(new Date().toISOString().split('T')[0])
const editingEvent = ref<CalendarEvent | null>(null)
const addingEvent = ref(false)
const addTitleInput = ref<HTMLInputElement | null>(null)
const calendarMode = ref<'grid' | 'list' | 'upcoming'>('grid')

// Drag and drop state
const draggingId = ref<string | null>(null)
const dragOverDate = ref<string | null>(null)

const newEvent = ref({
  title: '',
  startDate: '',
  endDate: '',
  startTime: '',
  endTime: '',
  description: '',
  color: 'blue',
  recurrence: '' as string,
})


const monthName = computed(() => {
  return new Date(currentYear.value, currentMonth.value).toLocaleDateString('en', { month: 'long' })
})

const calendarCells = computed((): CalendarCell[] => {
  const cells: CalendarCell[] = []
  const firstDay = new Date(currentYear.value, currentMonth.value, 1)
  const lastDay = new Date(currentYear.value, currentMonth.value + 1, 0)
  const startDow = firstDay.getDay()
  const today = new Date().toISOString().split('T')[0]

  // Previous month padding
  const prevLast = new Date(currentYear.value, currentMonth.value, 0)
  for (let i = startDow - 1; i >= 0; i--) {
    const d = prevLast.getDate() - i
    const date = new Date(currentYear.value, currentMonth.value - 1, d)
    cells.push({
      day: d,
      dateStr: formatDateStr(date),
      currentMonth: false,
      isToday: formatDateStr(date) === today,
    })
  }

  // Current month
  for (let d = 1; d <= lastDay.getDate(); d++) {
    const date = new Date(currentYear.value, currentMonth.value, d)
    cells.push({
      day: d,
      dateStr: formatDateStr(date),
      currentMonth: true,
      isToday: formatDateStr(date) === today,
    })
  }

  // Next month padding (fill to 42 cells = 6 rows)
  const remaining = 42 - cells.length
  for (let d = 1; d <= remaining; d++) {
    const date = new Date(currentYear.value, currentMonth.value + 1, d)
    cells.push({
      day: d,
      dateStr: formatDateStr(date),
      currentMonth: false,
      isToday: formatDateStr(date) === today,
    })
  }

  return cells
})

const addModalDateLabel = computed(() => {
  const d = new Date(selectedDate.value + 'T12:00:00')
  return d.toLocaleDateString('en', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
})

const editModalDateLabel = computed(() => {
  if (!editingEvent.value) return ''
  const d = new Date(editingEvent.value.event_date + 'T12:00:00')
  return d.toLocaleDateString('en', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
})

const selectedDayEvents = computed(() => {
  return events.value
    .filter(e => e.event_date === selectedDate.value)
    .sort((a, b) => (a.event_time || '99:99').localeCompare(b.event_time || '99:99'))
})

// List view computed
interface ListViewDay {
  dateStr: string
  dayNum: number
  weekday: string
  fullDate: string
  isToday: boolean
  events: CalendarEvent[]
}

const listViewDays = computed((): ListViewDay[] => {
  const lastDay = new Date(currentYear.value, currentMonth.value + 1, 0)
  const today = new Date().toISOString().split('T')[0]
  const days: ListViewDay[] = []

  for (let d = 1; d <= lastDay.getDate(); d++) {
    const date = new Date(currentYear.value, currentMonth.value, d)
    const dateStr = formatDateStr(date)
    const dayEvents = events.value
      .filter(e => eventOccursOnDate(e, dateStr))
      .sort((a, b) => (a.event_time || '99:99').localeCompare(b.event_time || '99:99'))
    if (dayEvents.length === 0) continue
    days.push({
      dateStr,
      dayNum: d,
      weekday: date.toLocaleDateString('en', { weekday: 'long' }),
      fullDate: date.toLocaleDateString('en', { month: 'long', day: 'numeric', year: 'numeric' }),
      isToday: dateStr === today,
      events: dayEvents,
    })
  }
  return days
})

const emptyDaysCount = computed(() => {
  const lastDay = new Date(currentYear.value, currentMonth.value + 1, 0)
  return lastDay.getDate() - listViewDays.value.length
})

// Upcoming view (Things 3 style)
interface UpcomingItem {
  event: CalendarEvent
  daysAway: number
  dateLabel: string
  urgencyClass: string
}
interface UpcomingGroup {
  label: string
  events: UpcomingItem[]
}

const upcomingEvents = computed((): UpcomingItem[] => {
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const todayMs = today.getTime()
  const items: UpcomingItem[] = []
  const seen = new Set<string>()

  // Check each day for the next 90 days for recurring/multi-day events
  for (let d = 0; d <= 90; d++) {
    const checkDate = new Date(todayMs + d * 86400000)
    const dateStr = formatDateStr(checkDate)
    for (const event of events.value) {
      const key = event.id + '-' + dateStr
      if (seen.has(key)) continue
      if (!eventOccursOnDate(event, dateStr)) continue
      seen.add(key)
      const dateLabel = checkDate.toLocaleDateString('en', { weekday: 'short', month: 'short', day: 'numeric' })
      let urgencyClass = 'cal-countdown-later'
      if (d === 0) urgencyClass = 'cal-countdown-today'
      else if (d <= 2) urgencyClass = 'cal-countdown-soon'
      else if (d <= 7) urgencyClass = 'cal-countdown-week'
      items.push({ event, daysAway: d, dateLabel, urgencyClass })
    }
  }
  items.sort((a, b) => a.daysAway - b.daysAway || (a.event.event_time || '').localeCompare(b.event.event_time || ''))
  return items
})

const upcomingGroups = computed((): UpcomingGroup[] => {
  const groups: UpcomingGroup[] = []
  const buckets: { label: string; filter: (d: number) => boolean }[] = [
    { label: 'Today', filter: d => d === 0 },
    { label: 'Tomorrow', filter: d => d === 1 },
    { label: 'This Week', filter: d => d >= 2 && d <= 7 },
    { label: 'Next Week', filter: d => d > 7 && d <= 14 },
    { label: 'Later', filter: d => d > 14 },
  ]
  for (const bucket of buckets) {
    const items = upcomingEvents.value.filter(e => bucket.filter(e.daysAway))
    if (items.length > 0) groups.push({ label: bucket.label, events: items })
  }
  return groups
})

function formatDateStr(date: Date): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

function eventOccursOnDate(event: CalendarEvent, dateStr: string): boolean {
  // Check direct match
  if (event.event_date === dateStr) return true
  // Check multi-day span
  if (event.end_date && dateStr >= event.event_date && dateStr <= event.end_date) return true
  // Check recurrence
  if (event.recurrence) {
    const eventD = new Date(event.event_date + 'T12:00:00')
    const checkD = new Date(dateStr + 'T12:00:00')
    if (checkD < eventD) return false
    if (event.recurrence === 'yearly') {
      return eventD.getMonth() === checkD.getMonth() && eventD.getDate() === checkD.getDate()
    }
    if (event.recurrence === 'monthly') {
      return eventD.getDate() === checkD.getDate()
    }
    if (event.recurrence === 'weekly') {
      return eventD.getDay() === checkD.getDay()
    }
  }
  return false
}

function isMultiDay(event: CalendarEvent): boolean {
  return !!event.end_date && event.end_date !== event.event_date
}

function isMultiDayStart(event: CalendarEvent, dateStr: string): boolean {
  return isMultiDay(event) && event.event_date === dateStr
}

function multiDayPosition(event: CalendarEvent, dateStr: string): string {
  if (!isMultiDay(event)) return 'rounded px-2'
  const isStart = event.event_date === dateStr
  const isEnd = event.end_date === dateStr
  // 5px = 4px cell padding + 1px border to bridge across cells
  if (isStart) return 'multiday-event rounded-l pl-2 multiday-start'
  if (isEnd) return 'multiday-event rounded-r pr-2 multiday-end'
  return 'multiday-event multiday-mid'
}

function getEventsForDate(dateStr: string): CalendarEvent[] {
  return events.value
    .filter(e => eventOccursOnDate(e, dateStr))
    .sort((a, b) => (a.event_time || '99:99').localeCompare(b.event_time || '99:99'))
    .slice(0, 4)
}

function eventColorClass(color: string): string {
  const map: Record<string, string> = {
    blue: 'bg-blue-500/20 text-blue-700',
    red: 'bg-red-500/20 text-red-700',
    green: 'bg-green-500/20 text-green-700',
    yellow: 'bg-yellow-500/20 text-yellow-700',
    purple: 'bg-purple-500/20 text-purple-700',
  }
  return map[color] || map.blue
}

function eventDotClass(color: string): string {
  const map: Record<string, string> = {
    blue: 'bg-blue-400',
    red: 'bg-red-400',
    green: 'bg-green-400',
    yellow: 'bg-yellow-400',
    purple: 'bg-purple-400',
  }
  return map[color] || map.blue
}

function prevMonth() {
  if (currentMonth.value === 0) {
    currentMonth.value = 11
    currentYear.value--
  } else {
    currentMonth.value--
  }
}

function nextMonth() {
  if (currentMonth.value === 11) {
    currentMonth.value = 0
    currentYear.value++
  } else {
    currentMonth.value++
  }
}

function goToToday() {
  const now = new Date()
  currentMonth.value = now.getMonth()
  currentYear.value = now.getFullYear()
  selectedDate.value = formatDateStr(now)
}

function openAddModal(dateStr: string) {
  selectedDate.value = dateStr
  newEvent.value = { title: '', startDate: dateStr, endDate: dateStr, startTime: '', endTime: '', description: '', color: 'blue', recurrence: '' }
  addingEvent.value = true
  nextTick(() => addTitleInput.value?.focus())
}

// Keep end date >= start date
watch(() => newEvent.value.startDate, (newStart) => {
  if (newEvent.value.endDate && newEvent.value.endDate < newStart) {
    newEvent.value.endDate = newStart
  }
})

function saveNewEvent() {
  if (!newEvent.value.title.trim()) return
  guardedRouter.post('/events', {
    title: newEvent.value.title.trim(),
    event_date: newEvent.value.startDate,
    end_date: newEvent.value.endDate || null,
    event_time: newEvent.value.startTime || null,
    end_time: newEvent.value.endTime || null,
    description: newEvent.value.description || '',
    color: newEvent.value.color,
    recurrence: newEvent.value.recurrence || null,
  }, { preserveScroll: true, only: ['events'], onSuccess: () => { addingEvent.value = false } })
}

const editEventTime = computed({
  get: () => editingEvent.value?.event_time || '',
  set: (v) => { if (editingEvent.value) editingEvent.value.event_time = v || null },
})

const editEventEndTime = computed({
  get: () => editingEvent.value?.end_time || '',
  set: (v) => { if (editingEvent.value) editingEvent.value.end_time = v || null },
})

function editEvent(event: CalendarEvent) {
  editingEvent.value = { ...event }
}

function saveEditEvent() {
  if (!editingEvent.value) return
  guardedRouter.put(`/events/${editingEvent.value.id}`, {
    title: editingEvent.value.title,
    event_date: editingEvent.value.event_date,
    end_date: editingEvent.value.end_date || null,
    event_time: editingEvent.value.event_time || null,
    end_time: editingEvent.value.end_time || null,
    description: editingEvent.value.description || '',
    color: editingEvent.value.color,
    recurrence: editingEvent.value.recurrence || null,
  }, { preserveScroll: true, only: ['events'], onSuccess: () => { editingEvent.value = null } })
}

function deleteEvent(id: string) {
  guardedRouter.delete(`/events/${id}`, { preserveScroll: true, only: ['events'], onSuccess: () => { editingEvent.value = null } })
}

// ===== Drag and drop =====
function onDragStart(event: CalendarEvent, e: DragEvent) {
  draggingId.value = event.id
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/plain', event.id)
  }
}

function onDragEnd() {
  draggingId.value = null
  dragOverDate.value = null
}

function onDragOver(dateStr: string) {
  dragOverDate.value = dateStr
}

function onDragLeave() {
  dragOverDate.value = null
}

function onDrop(targetDate: string) {
  if (!draggingId.value) return
  const event = events.value.find(e => e.id === draggingId.value)
  if (event && event.event_date !== targetDate) {
    guardedRouter.put(`/events/${event.id}/move`, { event_date: targetDate }, { preserveScroll: true, only: ['events'] })
  }
  draggingId.value = null
  dragOverDate.value = null
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    if (editingEvent.value) { e.preventDefault(); editingEvent.value = null; return }
    if (addingEvent.value) { e.preventDefault(); addingEvent.value = false; return }
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
})
onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
})
</script>

<style scoped>
/* List view styles */
.cal-list-view {
  background: var(--background);
}

.cal-list-day {
  border-bottom: 1px solid var(--border);
}

.cal-list-day-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  background: color-mix(in oklch, var(--card), var(--background) 50%);
  border-bottom: 1px solid color-mix(in oklch, var(--border), transparent 50%);
  z-index: 5;
}

.cal-list-today .cal-list-day-header {
  background: color-mix(in oklch, var(--primary), transparent 92%);
}

.cal-list-event {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 20px 10px 36px;
  cursor: pointer;
  transition: background 100ms;
}
.cal-list-event:hover {
  background: color-mix(in oklch, var(--accent), transparent 50%);
}

/* Upcoming view */
.cal-upcoming-group {
  border-bottom: 1px solid var(--border);
}
.cal-upcoming-group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 20px;
  background: color-mix(in oklch, var(--card), var(--background) 50%);
  border-bottom: 1px solid color-mix(in oklch, var(--border), transparent 50%);
}
.cal-upcoming-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 20px;
  cursor: pointer;
  transition: background 100ms;
}
.cal-upcoming-item:hover {
  background: color-mix(in oklch, var(--accent), transparent 50%);
}
.cal-upcoming-countdown {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 12px;
  flex-shrink: 0;
}
.cal-countdown-today {
  background: color-mix(in oklch, var(--primary), transparent 80%);
  color: var(--primary);
}
.cal-countdown-soon {
  background: color-mix(in oklch, oklch(0.65 0.2 25), transparent 85%);
  color: oklch(0.55 0.2 25);
}
.cal-countdown-week {
  background: color-mix(in oklch, oklch(0.65 0.16 85), transparent 85%);
  color: oklch(0.50 0.16 85);
}
.cal-countdown-later {
  background: color-mix(in oklch, var(--foreground), transparent 92%);
  color: color-mix(in oklch, var(--foreground), transparent 40%);
}

/* Drag feedback on grid */
[data-date] {
  transition: background 100ms, box-shadow 100ms;
}

/* Multi-day event bars - bridge across cell borders */
.multiday-event {
  position: relative;
  z-index: 2;
}
.multiday-start {
  margin-right: -5px; /* 4px padding + 1px border */
  padding-right: 5px;
}
.multiday-end {
  margin-left: -5px;
  padding-left: 5px;
}
.multiday-mid {
  margin-left: -5px;
  margin-right: -5px;
  padding-left: 5px;
  padding-right: 5px;
}
</style>
