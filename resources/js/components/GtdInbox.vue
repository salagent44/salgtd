<template>
  <div class="h-screen bg-background text-foreground flex flex-col overflow-hidden">
    <!-- Offline banner -->
    <div v-if="!isOnline" class="bg-red-600 text-white text-center py-2 px-4 text-sm font-medium shrink-0">
      <span class="mr-1">🔌</span> No connection — changes are disabled until reconnected
    </div>

    <!-- Update notification banner -->
    <div v-if="buildReady && !updateApplying" class="bg-primary text-primary-foreground text-center py-2 px-4 text-sm font-medium shrink-0 flex items-center justify-center gap-3">
      <span>A new version is ready to install</span>
      <button
        @click="applyUpdate"
        class="rounded-full bg-primary-foreground text-primary px-4 py-1 text-xs font-semibold hover:opacity-90 transition-opacity"
      >Apply Update</button>
    </div>
    <div v-if="updateApplying" class="bg-amber-500 text-white text-center py-2 px-4 text-sm font-medium shrink-0">
      Applying update... page will reload momentarily
    </div>

    <div class="max-w-[1800px] w-full mx-auto flex flex-col flex-1 min-h-0 px-3 md:px-6 pt-3 md:pt-6">

      <!-- Top nav bar -->
      <div class="flex items-center justify-between mb-4 md:mb-6">
        <div class="flex items-center gap-4">
          <!-- Desktop view switcher (hidden on mobile — bottom nav used instead) -->
          <div class="hidden md:flex rounded-lg bg-muted p-0.5" data-testid="view-nav">
            <button
              v-for="v in views"
              :key="v.key"
              @click="currentView = v.key"
              class="rounded-lg px-5 py-2 text-[15px] font-medium transition-colors"
              :class="currentView === v.key ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
              :data-testid="'nav-' + v.key"
            >{{ v.icon }} {{ v.label }}</button>
          </div>
          <!-- Mobile view title -->
          <p class="md:hidden text-lg font-bold text-foreground">{{ views.find(v => v.key === currentView)?.icon }} {{ views.find(v => v.key === currentView)?.label }}</p>
        </div>
        <div class="flex items-center gap-1.5 md:gap-2">
          <template v-if="currentView === 'tasks'">
            <button
              @click="openProcess"
              :disabled="inbox.length === 0"
              class="rounded-lg px-2.5 py-1.5 md:px-4 md:py-2 text-xs md:text-sm font-medium transition-colors disabled:opacity-40 disabled:pointer-events-none"
              :class="clarifyBtnClass"
              data-testid="process-btn"
            ><svg class="inline -mt-0.5 mr-1" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg><span class="hidden sm:inline">Clarify </span>({{ inbox.length }})</button>
            <button
              @click="reviewOpen = true"
              class="hidden sm:inline-flex rounded-lg bg-muted px-4 py-2 text-sm font-medium text-muted-foreground hover:bg-accent hover:text-foreground transition-colors group relative"
              :class="{ 'review-pulse': reviewOverdue }"
              data-testid="review-btn"
            >
              {{ themeIcons.review }} {{ hasReviewProgress ? 'Review (in progress)' : 'Review' }}
              <span class="absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded bg-foreground text-background text-[10px] px-2 py-1 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
                {{ nextReviewLabel }}
              </span>
            </button>
          </template>
          <div class="hidden sm:flex items-center gap-2">
            <!-- App status -->
            <div
              class="group relative flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] font-medium transition-colors cursor-default"
              :class="isOnline ? 'text-green-500' : 'text-red-500'"
              data-testid="app-status"
            >
              <span class="relative flex h-2 w-2">
                <span
                  class="relative inline-flex h-2 w-2 rounded-full"
                  :class="isOnline ? 'bg-green-500' : 'bg-red-500'"
                ></span>
              </span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="2"/><path d="M12 8v4l3 3"/></svg>
              <div class="pointer-events-none absolute top-full right-0 mt-2 w-48 rounded-lg border border-border bg-popover px-3 py-2.5 text-xs shadow-lg opacity-0 translate-y-1 group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-150 z-50">
                <p class="font-semibold text-popover-foreground mb-0.5">App Server</p>
                <p class="text-muted-foreground">Polls <span class="font-mono text-[10px]">/health</span> every 15s to check the backend is reachable.</p>
                <p class="mt-1.5 font-medium" :class="isOnline ? 'text-green-500' : 'text-red-500'">{{ isOnline ? 'Connected' : 'Disconnected' }}</p>
              </div>
            </div>
            <!-- SMTP status -->
            <div
              class="group relative flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] font-medium transition-colors cursor-default"
              :class="smtpStatus === 'up' ? 'text-green-500' : smtpStatus === 'down' ? 'text-red-500' : 'text-muted-foreground/40'"
              data-testid="smtp-status"
            >
              <span
                class="inline-flex h-2 w-2 rounded-full"
                :class="smtpStatus === 'up' ? 'bg-green-500' : smtpStatus === 'down' ? 'bg-red-500' : 'bg-muted-foreground/30'"
              ></span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
              <div class="pointer-events-none absolute top-full right-0 mt-2 w-48 rounded-lg border border-border bg-popover px-3 py-2.5 text-xs shadow-lg opacity-0 translate-y-1 group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-150 z-50">
                <p class="font-semibold text-popover-foreground mb-0.5">Mail Server</p>
                <p class="text-muted-foreground">Checks the SMTP server on port 25 for inbound email capture.</p>
                <p class="mt-1.5 font-medium" :class="smtpStatus === 'up' ? 'text-green-500' : smtpStatus === 'down' ? 'text-red-500' : 'text-muted-foreground'">{{ smtpStatus === 'up' ? 'Running' : smtpStatus === 'down' ? 'Down' : 'Checking...' }}</p>
              </div>
            </div>
          </div>
          <button
            @click="hotkeysOpen = true"
            class="rounded-lg p-2 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
            title="Keyboard shortcuts (?)"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M6 12h.01M10 12h.01M14 12h.01M18 12h.01M8 16h8"/></svg>
          </button>
          <button
            @click="settingsOpen = true"
            class="rounded-lg p-2 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
            title="Settings"
            data-testid="settings-btn"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          </button>
          <button
            @click="guardedRouter.post('/logout', {}, { onSuccess: () => window.location.href = '/login' })"
            class="rounded-lg p-2 text-muted-foreground hover:bg-accent hover:text-foreground transition-colors"
            title="Log out"
            data-testid="logout-btn"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          </button>
        </div>
      </div>

      <!-- ===== TASKS VIEW ===== -->
      <div v-if="currentView === 'tasks'" class="max-w-[1000px] w-full mx-auto flex flex-col flex-1 min-h-0">

      <!-- Sticky task header -->
      <div class="shrink-0 space-y-4 md:space-y-6 pb-2">

      <!-- Task pills — only shown when count > 0 -->
      <div class="flex items-center gap-1 md:gap-1.5 overflow-x-auto no-scrollbar -mx-1 px-1">
        <button
          v-if="nextActions.length > 0 || activePill === 'next-actions'"
          @click="setActivePill('next-actions')"
          class="rounded-lg px-3 md:px-4 py-2.5 md:py-2 text-[13px] md:text-xs font-semibold transition-all border-b-2 shrink-0"
          :class="activePill === 'next-actions' ? 'bg-primary text-primary-foreground border-primary' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground border-transparent'"
          data-testid="next-actions-btn"
        >
          Next Actions <span class="ml-1 opacity-70">{{ nextActions.length }}</span>
        </button>
        <button
          v-if="waitingItems.length > 0 || activePill === 'waiting'"
          @click="setActivePill('waiting')"
          class="rounded-lg px-3 md:px-4 py-2.5 md:py-2 text-[13px] md:text-xs font-semibold transition-all border-b-2 shrink-0"
          :class="activePill === 'waiting' ? 'bg-amber-600 text-white border-amber-400' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground border-transparent'"
          data-testid="waiting-btn"
        >
          <span v-if="hasStaleWaiting">❗</span> Waiting <span class="ml-1 opacity-70">{{ waitingItems.length }}</span>
        </button>
        <button
          v-if="projects.length > 0 || activePill === 'projects'"
          @click="setActivePill('projects')"
          class="rounded-lg px-3 md:px-4 py-2.5 md:py-2 text-[13px] md:text-xs font-semibold transition-all border-b-2 shrink-0"
          :class="activePill === 'projects' ? 'bg-primary text-primary-foreground border-primary' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground border-transparent'"
          data-testid="projects-btn"
        >
          <span v-if="stuckProjects.size > 0">🧴</span> Projects <span class="ml-1 opacity-70">{{ projects.length }}</span>
        </button>
        <button
          v-if="inbox.length > 0 || activePill === 'inbox'"
          @click="setActivePill('inbox')"
          class="rounded-lg px-3 md:px-4 py-2.5 md:py-2 text-[13px] md:text-xs font-semibold transition-all border-b-2 shrink-0"
          :class="activePill === 'inbox' ? 'bg-primary text-primary-foreground border-primary' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground border-transparent'"
          data-testid="inbox-btn"
        >
          Inbox <span class="ml-1 opacity-70">{{ inbox.length }}</span>
        </button>
        <button
          v-if="somedayItems.length > 0 || activePill === 'someday'"
          @click="setActivePill('someday')"
          class="rounded-lg px-3 md:px-4 py-2.5 md:py-2 text-[13px] md:text-xs font-semibold transition-all border-b-2 shrink-0"
          :class="activePill === 'someday' ? 'bg-primary text-primary-foreground border-primary' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground border-transparent'"
          data-testid="someday-btn"
        >
          Someday <span class="ml-1 opacity-70">{{ somedayItems.length }}</span>
        </button>
        <button
          v-if="ticklerItems.length > 0 || activePill === 'tickler'"
          @click="setActivePill('tickler')"
          class="rounded-lg px-3 md:px-4 py-2.5 md:py-2 text-[13px] md:text-xs font-semibold transition-all border-b-2 shrink-0"
          :class="activePill === 'tickler' ? 'bg-violet-600 text-white border-violet-400' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground border-transparent'"
          data-testid="tickler-btn"
        >
          Tickler <span class="ml-1 opacity-70">{{ ticklerItems.length }}</span>
        </button>
        <button
          v-if="doneItems.length > 0 || activePill === 'done'"
          @click="setActivePill('done')"
          class="rounded-lg px-3 md:px-4 py-2.5 md:py-2 text-[13px] md:text-xs font-semibold transition-all border-b-2 shrink-0"
          :class="activePill === 'done' ? 'bg-green-600 text-white border-green-400' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground border-transparent'"
          data-testid="done-btn"
        >
          Done <span class="ml-1 opacity-70">{{ doneItems.length }}</span>
        </button>
        <button
          v-if="flaggedItems.length > 0 || activePill === 'flagged'"
          @click="setActivePill('flagged')"
          class="rounded-lg px-3 md:px-4 py-2.5 md:py-2 text-[13px] md:text-xs font-semibold transition-all border-b-2 shrink-0"
          :class="activePill === 'flagged' ? 'bg-red-600 text-white border-red-400' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground border-transparent'"
          data-testid="flagged-btn"
        >
          Flagged <span class="ml-1 opacity-70">{{ flaggedItems.length }}</span>
        </button>
      </div>

      <Separator class="mb-2" />

      <!-- Item count -->
      <p class="text-xs text-muted-foreground/60">Showing {{ activeListCount }} item{{ activeListCount === 1 ? '' : 's' }}</p>

      </div><!-- end sticky task header -->

      <!-- ===== SCROLLABLE CONTENT AREA ===== -->
      <div class="flex-1 min-h-0 overflow-y-auto pb-20 md:pb-6 pt-3" style="scrollbar-gutter: stable">

      <!-- Next Actions (default) -->
      <div v-if="activePill === 'next-actions'">
        <div v-if="nextActions.length === 0" class="text-center py-12 text-muted-foreground">
          <p class="text-3xl mb-2">✓</p>
          <p class="text-sm">No next actions</p>
        </div>
        <template v-else>
          <!-- Context & tag sub-filters -->
          <div v-if="usedContexts.length > 0 || usedTags.length > 0" class="flex flex-wrap gap-1.5 mb-4">
            <button
              @click="activeContextFilter = null; activeTagFilter = null"
              :class="activeContextFilter === null && activeTagFilter === null ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground'"
              class="rounded-full px-3 md:px-3.5 py-2 md:py-1.5 text-[13px] font-medium transition-colors"
            >All</button>
            <button
              v-for="ctx in usedContexts"
              :key="ctx"
              @click="toggleContextFilter(ctx)"
              :class="activeContextFilter === ctx ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground'"
              class="rounded-full px-3 md:px-3.5 py-2 md:py-1.5 text-[13px] font-medium transition-colors"
              data-testid="context-filter"
            >{{ ctx }}</button>
            <span v-if="usedTags.length > 0 && usedContexts.length > 0" class="text-muted-foreground/30 self-center">|</span>
            <button
              v-for="tag in usedTags"
              :key="tag"
              @click="activeTagFilter = activeTagFilter === tag ? null : tag"
              :class="activeTagFilter === tag ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground'"
              class="rounded-full px-3 md:px-3.5 py-2 md:py-1.5 text-[13px] font-medium transition-colors"
              data-testid="tag-filter"
            >#{{ tag }}</button>
          </div>
          <div v-if="filteredNextActions.length === 0 && (activeContextFilter || activeTagFilter)" class="text-center py-12 text-muted-foreground">
            <p class="text-3xl mb-2">🔍</p>
            <p class="text-sm">No items match this filter</p>
            <button @click="activeContextFilter = null; activeTagFilter = null" class="text-xs text-primary hover:underline mt-2">Clear filters</button>
          </div>
          <div class="space-y-2">
            <Card v-for="(item, idx) in filteredNextActions.slice(0, renderLimits['next-actions'])" :key="item.id" class="cursor-pointer transition-colors !py-0 !gap-0 border-l-2" :class="[selectedIds.has(item.id) ? 'ring-2 ring-primary bg-primary/10' : idx % 2 === 0 ? 'bg-muted/30 hover:!bg-muted/50' : 'bg-muted/10 hover:!bg-muted/30', item.flagged ? 'border-l-red-500' : 'border-l-transparent']" @click="onCardClick(item, $event)">
              <CardContent class="!px-3 md:!px-4 py-3 md:py-2.5 flex items-center gap-2.5 md:gap-3">
                <span v-if="selectedIds.size > 0" class="w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-all" :class="selectedIds.has(item.id) ? 'bg-primary border-primary text-primary-foreground' : 'border-muted-foreground/40'"><svg v-if="selectedIds.has(item.id)" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                <span v-if="item.flagged" class="text-red-500 text-xs shrink-0">{{ themeIcons.flag }}</span>
                <svg v-if="item.email" class="shrink-0 text-blue-500" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                <p class="text-[15px] font-semibold flex-1 truncate min-w-0" :title="item.title">{{ item.title }}</p>
                <span v-if="checklistProgress(item)" class="text-[11px] font-medium px-1.5 py-0.5 rounded shrink-0" :class="checklistProgress(item)!.done === checklistProgress(item)!.total ? 'bg-green-500/15 text-green-500' : 'bg-muted text-muted-foreground'">{{ checklistProgress(item)!.done }}/{{ checklistProgress(item)!.total }}</span>
                <span v-if="item.context" class="text-xs text-muted-foreground shrink-0">{{ item.context }}</span>
                <span v-for="t in (item.tags || [])" :key="t.id" class="text-[11px] font-medium px-1.5 py-0.5 rounded bg-primary/10 text-primary shrink-0">#{{ t.tag }}</span>
                <span v-if="item.project_id" class="text-[11px] font-medium px-2 py-0.5 rounded bg-muted text-muted-foreground shrink-0 truncate max-w-[100px]" :title="projectName(item.project_id)">📁 {{ projectName(item.project_id) }}</span>
              </CardContent>
            </Card>
          </div>
          <button v-if="filteredNextActions.length > renderLimits['next-actions']" @click="showMore('next-actions')" class="w-full mt-3 rounded-lg bg-muted/50 hover:bg-muted py-2.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors">
            Show more ({{ filteredNextActions.length - renderLimits['next-actions'] }} remaining)
          </button>
        </template>
      </div>

      <!-- Waiting -->
      <div v-else-if="activePill === 'waiting'">
        <div v-if="waitingItems.length === 0" class="text-center py-12 text-muted-foreground">
          <p class="text-3xl mb-2">⏳</p>
          <p class="text-sm">Nothing waiting</p>
        </div>
        <div class="space-y-2">
          <Card v-for="(item, idx) in waitingItems.slice(0, renderLimits.waiting)" :key="item.id" class="cursor-pointer transition-colors !py-0 !gap-0 border-l-2" :class="[selectedIds.has(item.id) ? 'ring-2 ring-primary bg-primary/10' : idx % 2 === 0 ? 'bg-muted/30 hover:!bg-muted/50' : 'bg-muted/10 hover:!bg-muted/30', item.flagged ? 'border-l-red-500' : isWaitingStale(item) ? 'border-l-red-400' : 'border-l-transparent']" @click="onCardClick(item, $event)">
            <CardContent class="!px-3 md:!px-4 py-3 md:py-2.5 flex items-center gap-2.5 md:gap-3">
              <span v-if="selectedIds.size > 0" class="w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-all" :class="selectedIds.has(item.id) ? 'bg-primary border-primary text-primary-foreground' : 'border-muted-foreground/40'"><svg v-if="selectedIds.has(item.id)" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
              <span v-if="item.flagged" class="text-red-500 text-xs shrink-0">{{ themeIcons.flag }}</span>
              <svg v-if="item.email" class="shrink-0 text-blue-500" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
              <p class="text-[15px] font-semibold flex-1 truncate min-w-0" :title="item.title">{{ item.title }}</p>
              <span v-if="checklistProgress(item)" class="text-[11px] font-medium px-1.5 py-0.5 rounded shrink-0" :class="checklistProgress(item)!.done === checklistProgress(item)!.total ? 'bg-green-500/15 text-green-500' : 'bg-muted text-muted-foreground'">{{ checklistProgress(item)!.done }}/{{ checklistProgress(item)!.total }}</span>
              <span v-if="item.waiting_for" class="text-xs font-medium px-2 py-0.5 rounded border shrink-0" :class="isWaitingStale(item) ? 'border-red-600/40 bg-red-500/15 text-red-600' : 'border-amber-600/40 bg-amber-500/10 text-amber-700'">{{ item.waiting_for }}</span>
              <span v-if="item.waiting_date" class="text-xs font-medium px-2 py-0.5 rounded border shrink-0" :class="isWaitingStale(item) ? 'border-red-600/40 bg-red-500/15 text-red-600' : 'border-amber-600/40 bg-amber-500/10 text-amber-700'">{{ formatDate(item.waiting_date) }}</span>
              <span v-if="item.project_id" class="text-[11px] font-medium px-2 py-0.5 rounded bg-muted text-muted-foreground shrink-0 truncate max-w-[100px]" :title="projectName(item.project_id)">📁 {{ projectName(item.project_id) }}</span>
              <span v-for="t in (item.tags || [])" :key="t.id" class="text-[11px] font-medium px-1.5 py-0.5 rounded bg-primary/10 text-primary shrink-0">#{{ t.tag }}</span>
            </CardContent>
          </Card>
        </div>
        <button v-if="waitingItems.length > renderLimits.waiting" @click="showMore('waiting')" class="w-full mt-3 rounded-lg bg-muted/50 hover:bg-muted py-2.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors">
          Show more ({{ waitingItems.length - renderLimits.waiting }} remaining)
        </button>
      </div>

      <!-- Projects -->
      <div v-else-if="activePill === 'projects'">
        <div v-if="projects.length === 0" class="text-center py-12 text-muted-foreground">
          <p class="text-3xl mb-2">📁</p>
          <p class="text-sm">No projects</p>
        </div>
        <div class="space-y-2">
          <template v-for="(item, idx) in projects.slice(0, renderLimits.projects)" :key="item.id">
          <Card class="!py-0 !gap-0 cursor-pointer transition-colors border-l-2" :class="[selectedIds.has(item.id) ? 'ring-2 ring-primary bg-primary/10' : idx % 2 === 0 ? 'bg-muted/30 hover:!bg-muted/50' : 'bg-muted/10 hover:!bg-muted/30', item.flagged ? 'border-l-red-500' : 'border-l-transparent']" @click="onCardClick(item, $event)">
            <CardContent class="!px-4 py-2.5">
              <div class="flex items-center gap-3">
                <span v-if="selectedIds.size > 0" class="w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-all" :class="selectedIds.has(item.id) ? 'bg-primary border-primary text-primary-foreground' : 'border-muted-foreground/40'"><svg v-if="selectedIds.has(item.id)" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
                <button @click.stop="toggleProjectExpand(item.id)" class="text-muted-foreground hover:text-foreground transition-colors shrink-0">
                  <svg :class="expandedProjects.has(item.id) ? 'rotate-90' : ''" class="transition-transform" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </button>
                <span v-if="item.flagged" class="text-red-500 text-xs shrink-0">{{ themeIcons.flag }}</span>
                <div class="flex-1 min-w-0">
                  <p class="text-[15px] font-semibold truncate" :title="item.title">{{ item.title }}</p>
                </div>
                <span v-for="t in (item.tags || [])" :key="t.id" class="text-[11px] font-medium px-1.5 py-0.5 rounded bg-primary/10 text-primary shrink-0">#{{ t.tag }}</span>
                <span class="text-[11px] font-medium px-2 py-0.5 rounded bg-muted text-muted-foreground shrink-0">{{ (projectTasksMap.get(item.id) || []).length }} tasks</span>
                <span v-if="stuckProjects.has(item.id)" class="shrink-0" title="No next action">🧴</span>
              </div>
            </CardContent>
          </Card>
          <!-- Expanded project tasks -->
          <div v-if="expandedProjects.has(item.id)" class="ml-6 space-y-1 border-l-2 border-border/40 pl-3">
            <div v-if="(projectTasksMap.get(item.id) || []).length === 0" class="py-2 text-xs text-muted-foreground">No linked tasks — assign tasks to this project</div>
            <Card v-for="task in (projectTasksMap.get(item.id) || [])" :key="task.id" class="!py-0 !gap-0 cursor-pointer transition-colors bg-muted/20 hover:!bg-muted/40" @click="openItem(task)">
              <CardContent class="!px-3 py-2 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full shrink-0" :class="{
                  'bg-primary': task.status === 'next-action',
                  'bg-amber-500': task.status === 'waiting',
                  'bg-violet-500': task.status === 'tickler',
                  'bg-green-500': task.status === 'done',
                  'bg-muted-foreground/40': !['next-action', 'waiting', 'tickler', 'done'].includes(task.status),
                }"></span>
                <p class="text-[13px] font-medium flex-1 truncate min-w-0" :title="task.title">{{ task.title }}</p>
                <span v-if="checklistProgress(task)" class="text-[10px] font-medium px-1 py-0.5 rounded shrink-0" :class="checklistProgress(task)!.done === checklistProgress(task)!.total ? 'bg-green-500/15 text-green-500' : 'bg-muted text-muted-foreground'">{{ checklistProgress(task)!.done }}/{{ checklistProgress(task)!.total }}</span>
                <span v-if="task.context" class="text-[11px] text-muted-foreground shrink-0">{{ task.context }}</span>
                <Badge :variant="bucketVariant(task.status)" class="text-[10px]">{{ bucketLabel(task.status) }}</Badge>
              </CardContent>
            </Card>
          </div>
          </template>
        </div>
        <button v-if="projects.length > renderLimits.projects" @click="showMore('projects')" class="w-full mt-3 rounded-lg bg-muted/50 hover:bg-muted py-2.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors">
          Show more ({{ projects.length - renderLimits.projects }} remaining)
        </button>
      </div>

      <!-- Inbox -->
      <div v-else-if="activePill === 'inbox'">
        <div v-if="inbox.length === 0" class="text-center py-12 text-muted-foreground">
          <p class="text-3xl mb-2">✓</p>
          <p class="text-sm">Inbox zero</p>
        </div>
        <div class="space-y-2">
          <Card v-for="(item, idx) in inbox.slice(0, renderLimits.inbox)" :key="item.id" class="cursor-pointer transition-colors !py-0 !gap-0 border-l-2" :class="[selectedIds.has(item.id) ? 'ring-2 ring-primary bg-primary/10' : idx % 2 === 0 ? 'bg-muted/30 hover:!bg-muted/50' : 'bg-muted/10 hover:!bg-muted/30', item.flagged ? 'border-l-red-500' : 'border-l-transparent']" @click="onCardClick(item, $event)">
            <CardContent class="!px-3 md:!px-4 py-3 md:py-2.5 flex items-center gap-2.5 md:gap-3">
              <span v-if="selectedIds.size > 0" class="w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-all" :class="selectedIds.has(item.id) ? 'bg-primary border-primary text-primary-foreground' : 'border-muted-foreground/40'"><svg v-if="selectedIds.has(item.id)" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
              <span v-if="item.flagged" class="text-red-500 text-xs shrink-0">{{ themeIcons.flag }}</span>
              <svg v-if="item.email" class="shrink-0 text-blue-500" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
              <p class="text-[15px] font-semibold flex-1 truncate min-w-0" :title="item.title">{{ item.title }}</p>
              <span v-if="checklistProgress(item)" class="text-[11px] font-medium px-1.5 py-0.5 rounded shrink-0" :class="checklistProgress(item)!.done === checklistProgress(item)!.total ? 'bg-green-500/15 text-green-500' : 'bg-muted text-muted-foreground'">{{ checklistProgress(item)!.done }}/{{ checklistProgress(item)!.total }}</span>
              <span v-for="t in (item.tags || [])" :key="t.id" class="text-[11px] font-medium px-1.5 py-0.5 rounded bg-primary/10 text-primary shrink-0">#{{ t.tag }}</span>
              <Badge variant="outline">Inbox</Badge>
            </CardContent>
          </Card>
        </div>
        <button v-if="inbox.length > renderLimits.inbox" @click="showMore('inbox')" class="w-full mt-3 rounded-lg bg-muted/50 hover:bg-muted py-2.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors">
          Show more ({{ inbox.length - renderLimits.inbox }} remaining)
        </button>
      </div>

      <!-- Someday -->
      <div v-else-if="activePill === 'someday'">
        <div v-if="somedayItems.length === 0" class="text-center py-12 text-muted-foreground">
          <p class="text-3xl mb-2">🌱</p>
          <p class="text-sm">No someday/maybe items</p>
        </div>
        <div class="space-y-2">
          <Card v-for="(item, idx) in somedayItems.slice(0, renderLimits.someday)" :key="item.id" class="cursor-pointer transition-colors !py-0 !gap-0 border-l-2" :class="[selectedIds.has(item.id) ? 'ring-2 ring-primary bg-primary/10' : idx % 2 === 0 ? 'bg-muted/30 hover:!bg-muted/50' : 'bg-muted/10 hover:!bg-muted/30', item.flagged ? 'border-l-red-500' : 'border-l-transparent']" @click="onCardClick(item, $event)">
            <CardContent class="!px-3 md:!px-4 py-3 md:py-2.5 flex items-center gap-2.5 md:gap-3">
              <span v-if="selectedIds.size > 0" class="w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-all" :class="selectedIds.has(item.id) ? 'bg-primary border-primary text-primary-foreground' : 'border-muted-foreground/40'"><svg v-if="selectedIds.has(item.id)" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
              <span v-if="item.flagged" class="text-red-500 text-xs shrink-0">{{ themeIcons.flag }}</span>
              <svg v-if="item.email" class="shrink-0 text-blue-500" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
              <p class="text-[15px] font-semibold flex-1 truncate min-w-0" :title="item.title">{{ item.title }}</p>
              <span v-if="checklistProgress(item)" class="text-[11px] font-medium px-1.5 py-0.5 rounded shrink-0" :class="checklistProgress(item)!.done === checklistProgress(item)!.total ? 'bg-green-500/15 text-green-500' : 'bg-muted text-muted-foreground'">{{ checklistProgress(item)!.done }}/{{ checklistProgress(item)!.total }}</span>
              <span v-for="t in (item.tags || [])" :key="t.id" class="text-[11px] font-medium px-1.5 py-0.5 rounded bg-primary/10 text-primary shrink-0">#{{ t.tag }}</span>
              <span v-if="item.project_id" class="text-[11px] font-medium px-2 py-0.5 rounded bg-muted text-muted-foreground shrink-0 truncate max-w-[100px]" :title="projectName(item.project_id)">📁 {{ projectName(item.project_id) }}</span>
            </CardContent>
          </Card>
        </div>
        <button v-if="somedayItems.length > renderLimits.someday" @click="showMore('someday')" class="w-full mt-3 rounded-lg bg-muted/50 hover:bg-muted py-2.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors">
          Show more ({{ somedayItems.length - renderLimits.someday }} remaining)
        </button>
      </div>

      <!-- Tickler -->
      <div v-else-if="activePill === 'tickler'">
        <div v-if="ticklerItems.length === 0" class="text-center py-12 text-muted-foreground">
          <p class="text-3xl mb-2">{{ themeIcons.calendar }}</p>
          <p class="text-sm">No tickler items</p>
        </div>
        <div class="space-y-2">
          <Card v-for="(item, idx) in ticklerItems.slice(0, renderLimits.tickler)" :key="item.id" class="cursor-pointer transition-colors !py-0 !gap-0 border-l-2" :class="[selectedIds.has(item.id) ? 'ring-2 ring-primary bg-primary/10' : idx % 2 === 0 ? 'bg-muted/30 hover:!bg-muted/50' : 'bg-muted/10 hover:!bg-muted/30', item.flagged ? 'border-l-red-500' : 'border-l-transparent']" @click="onCardClick(item, $event)">
            <CardContent class="!px-3 md:!px-4 py-3 md:py-2.5 flex items-center gap-2.5 md:gap-3">
              <span v-if="selectedIds.size > 0" class="w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-all" :class="selectedIds.has(item.id) ? 'bg-primary border-primary text-primary-foreground' : 'border-muted-foreground/40'"><svg v-if="selectedIds.has(item.id)" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
              <span v-if="item.flagged" class="text-red-500 text-xs shrink-0">{{ themeIcons.flag }}</span>
              <svg v-if="item.email" class="shrink-0 text-blue-500" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
              <p class="text-[15px] font-semibold flex-1 truncate min-w-0" :title="item.title">{{ item.title }}</p>
              <span v-if="checklistProgress(item)" class="text-[11px] font-medium px-1.5 py-0.5 rounded shrink-0" :class="checklistProgress(item)!.done === checklistProgress(item)!.total ? 'bg-green-500/15 text-green-500' : 'bg-muted text-muted-foreground'">{{ checklistProgress(item)!.done }}/{{ checklistProgress(item)!.total }}</span>
              <span v-for="t in (item.tags || [])" :key="t.id" class="text-[11px] font-medium px-1.5 py-0.5 rounded bg-primary/10 text-primary shrink-0">#{{ t.tag }}</span>
              <span v-if="item.tickler_date" class="text-xs font-medium px-2 py-0.5 rounded border border-violet-600/40 bg-violet-500/10 text-violet-700 dark:text-violet-300 shrink-0">{{ formatDate(item.tickler_date) }}</span>
            </CardContent>
          </Card>
        </div>
        <button v-if="ticklerItems.length > renderLimits.tickler" @click="showMore('tickler')" class="w-full mt-3 rounded-lg bg-muted/50 hover:bg-muted py-2.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors">
          Show more ({{ ticklerItems.length - renderLimits.tickler }} remaining)
        </button>
      </div>

      <!-- Done -->
      <div v-else-if="activePill === 'done'">
        <div v-if="doneItems.length === 0" class="text-center py-12 text-muted-foreground">
          <p class="text-3xl mb-2">✓</p>
          <p class="text-sm">No completed items</p>
        </div>
        <div v-if="doneItems.length > 0 && !confirmingClearDone" class="flex justify-end mb-3">
          <button @click="confirmingClearDone = true" class="text-xs text-muted-foreground/60 hover:text-destructive transition-colors">Clear all done</button>
        </div>
        <div v-if="confirmingClearDone" class="rounded-xl border border-destructive/30 bg-destructive/10 p-3 flex items-center justify-between gap-3 mb-3">
          <p class="text-sm text-destructive font-medium">Delete all {{ doneItems.length }} completed items?</p>
          <div class="flex gap-2">
            <button @click="confirmingClearDone = false" class="text-xs text-muted-foreground hover:text-foreground px-3 py-1.5 transition-colors">Cancel</button>
            <button @click="clearAllDone" class="rounded-lg bg-destructive hover:bg-destructive/90 text-white px-4 py-2 text-sm font-semibold transition-colors">Delete All</button>
          </div>
        </div>
        <div class="space-y-2">
          <Card v-for="(item, idx) in doneItems.slice(0, renderLimits.done)" :key="item.id" class="cursor-pointer transition-colors !py-0 !gap-0 border-l-2" :class="[selectedIds.has(item.id) ? 'ring-2 ring-primary bg-primary/10' : idx % 2 === 0 ? 'bg-muted/30 hover:!bg-muted/50' : 'bg-muted/10 hover:!bg-muted/30', item.flagged ? 'border-l-red-500' : 'border-l-transparent']" @click="onCardClick(item, $event)">
            <CardContent class="!px-3 md:!px-4 py-3 md:py-2.5 flex items-center gap-2.5 md:gap-3">
              <span v-if="selectedIds.size > 0" class="w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-all" :class="selectedIds.has(item.id) ? 'bg-primary border-primary text-primary-foreground' : 'border-muted-foreground/40'"><svg v-if="selectedIds.has(item.id)" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
              <span v-if="item.flagged" class="text-red-500 text-xs shrink-0">{{ themeIcons.flag }}</span>
              <svg v-if="item.email" class="shrink-0 text-blue-500" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
              <p class="text-[15px] font-semibold flex-1 truncate min-w-0 line-through text-muted-foreground" :title="item.title">{{ item.title }}</p>
              <span v-if="item.original_status" class="text-[10px] font-medium px-2 py-0.5 rounded-full border border-border/60 bg-muted text-muted-foreground shrink-0">{{ bucketLabel(item.original_status as Status) }}</span>
              <span v-if="item.completed_at" class="text-[10px] font-medium px-2 py-0.5 rounded-full border border-green-600/40 bg-green-500/10 text-green-700 dark:text-green-300 shrink-0">{{ formatCompletedAt(item.completed_at) }}</span>
            </CardContent>
          </Card>
        </div>
        <button v-if="doneItems.length > renderLimits.done" @click="showMore('done')" class="w-full mt-3 rounded-lg bg-muted/50 hover:bg-muted py-2.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors">
          Show more ({{ doneItems.length - renderLimits.done }} remaining)
        </button>
      </div>

      <!-- Flagged -->
      <div v-else-if="activePill === 'flagged'">
        <div v-if="flaggedItems.length === 0" class="text-center py-12 text-muted-foreground">
          <p class="text-3xl mb-2">{{ themeIcons.flag }}</p>
          <p class="text-sm">No flagged items</p>
        </div>
        <div class="space-y-2">
          <Card v-for="(item, idx) in flaggedItems.slice(0, renderLimits.flagged)" :key="item.id" class="cursor-pointer transition-colors !py-0 !gap-0 border-l-2 border-l-red-500" :class="[selectedIds.has(item.id) ? 'ring-2 ring-primary bg-primary/10' : idx % 2 === 0 ? 'bg-muted/30 hover:!bg-muted/50' : 'bg-muted/10 hover:!bg-muted/30']" @click="onCardClick(item, $event)">
            <CardContent class="!px-3 md:!px-4 py-3 md:py-2.5 flex items-center gap-2.5 md:gap-3">
              <span v-if="selectedIds.size > 0" class="w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-all" :class="selectedIds.has(item.id) ? 'bg-primary border-primary text-primary-foreground' : 'border-muted-foreground/40'"><svg v-if="selectedIds.has(item.id)" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
              <span class="text-red-500 text-xs shrink-0">{{ themeIcons.flag }}</span>
              <svg v-if="item.email" class="shrink-0 text-blue-500" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
              <p class="text-[15px] font-semibold flex-1 truncate min-w-0" :title="item.title">{{ item.title }}</p>
              <span v-if="checklistProgress(item)" class="text-[11px] font-medium px-1.5 py-0.5 rounded shrink-0" :class="checklistProgress(item)!.done === checklistProgress(item)!.total ? 'bg-green-500/15 text-green-500' : 'bg-muted text-muted-foreground'">{{ checklistProgress(item)!.done }}/{{ checklistProgress(item)!.total }}</span>
              <span v-if="item.context" class="text-xs text-muted-foreground shrink-0">{{ item.context }}</span>
              <span v-for="t in (item.tags || [])" :key="t.id" class="text-[11px] font-medium px-1.5 py-0.5 rounded bg-primary/10 text-primary shrink-0">#{{ t.tag }}</span>
              <span v-if="item.waiting_for" class="text-xs font-medium px-2 py-0.5 rounded border shrink-0" :class="isWaitingStale(item) ? 'border-red-600/40 bg-red-500/15 text-red-600' : 'border-amber-600/40 bg-amber-500/10 text-amber-700'">{{ item.waiting_for }}</span>
              <Badge :variant="bucketVariant(item.status)">{{ bucketLabel(item.status) }}</Badge>
            </CardContent>
          </Card>
        </div>
        <button v-if="flaggedItems.length > renderLimits.flagged" @click="showMore('flagged')" class="w-full mt-3 rounded-lg bg-muted/50 hover:bg-muted py-2.5 text-xs font-medium text-muted-foreground hover:text-foreground transition-colors">
          Show more ({{ flaggedItems.length - renderLimits.flagged }} remaining)
        </button>
      </div>

      </div><!-- end scrollable content area -->

      <!-- Export Done Modal -->
      <div
        v-if="exportModalOpen"
        class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50"
        @click.self="exportModalOpen = false"
      >
        <div class="bg-card border border-border rounded-xl w-full max-w-lg shadow-xl overflow-hidden">
          <div class="flex items-center justify-between px-5 pt-5 pb-3">
            <p class="text-sm font-semibold text-foreground">{{ themeIcons.done }} Completed Tasks</p>
            <button @click="exportModalOpen = false" class="text-muted-foreground hover:text-foreground transition-colors p-1">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="px-5 pb-2">
            <pre class="bg-muted/50 border border-border rounded-lg p-4 text-sm text-foreground font-mono whitespace-pre-wrap max-h-[50vh] overflow-y-auto select-all" data-testid="export-text">{{ exportMarkdown }}</pre>
          </div>
          <div class="px-5 pb-5 flex justify-end">
            <button
              @click="copyExport"
              class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
            >{{ exportCopied ? '✓ Copied!' : 'Copy to Clipboard' }}</button>
          </div>
        </div>
      </div>

      <!-- Bulk action toolbar -->
      <Transition name="bulk-toolbar">
        <div v-if="selectedIds.size > 0" class="fixed bottom-20 md:bottom-6 left-1/2 -translate-x-1/2 z-40">
          <div class="bg-card border border-border rounded-2xl shadow-2xl px-3 md:px-4 py-2.5 md:py-3 flex items-center gap-2 md:gap-3 max-w-[95vw] overflow-x-auto no-scrollbar">
            <span class="text-xs font-semibold text-muted-foreground whitespace-nowrap">{{ selectedIds.size }} selected</span>
            <div class="w-px h-6 bg-border"></div>
            <button @click="bulkAction('next-action')" class="bulk-btn text-primary" title="Next Action">{{ themeIcons.nextAction }}</button>
            <button @click="bulkAction('project')" class="bulk-btn" title="Project">📁</button>
            <button @click="bulkAction('waiting')" class="bulk-btn" title="Waiting">⏳</button>
            <button @click="bulkAction('someday')" class="bulk-btn" title="Someday">🌱</button>
            <button @click="bulkAction('tickler')" class="bulk-btn" title="Tickler">🗓️</button>
            <button @click="bulkAction('done')" class="bulk-btn text-green-500" title="Done">{{ themeIcons.done }}</button>
            <div class="w-px h-6 bg-border"></div>
            <button @click="bulkToggleFlag" class="bulk-btn text-red-500" title="Toggle Flag">{{ themeIcons.flag }}</button>
            <button @click="bulkAction('inbox')" class="bulk-btn" title="Move to Inbox">{{ themeIcons.inbox }}</button>
            <button @click="bulkDelete" class="bulk-btn text-destructive" title="Delete">🗑️</button>
            <div class="w-px h-6 bg-border"></div>
            <button @click="selectedIds.clear()" class="text-xs text-muted-foreground hover:text-foreground transition-colors px-2 py-1" title="Clear selection">✕</button>
          </div>
        </div>
      </Transition>

      </div><!-- end tasks view -->

      <!-- ===== NOTES VIEW ===== -->
      <div v-if="currentView === 'notes'" class="flex-1 min-h-0 overflow-y-auto pb-20 md:pb-6">
        <NotesView :is-online="isOnline" />
      </div>

      <!-- ===== CALENDAR VIEW ===== -->
      <div v-if="currentView === 'calendar'" class="flex-1 min-h-0 overflow-y-auto pb-20 md:pb-6">
        <CalendarView :is-online="isOnline" />
      </div>

      <!-- Review modal -->
      <ReviewView
        :open="reviewOpen"
        :is-online="isOnline"
        @close="reviewOpen = false"
        @review-complete="onReviewComplete"
      />

    </div><!-- end max-w wrapper -->

    <!-- ===== Mobile Bottom Nav ===== -->
    <!-- Mobile FAB: Add to inbox -->
    <button
      @click="openQuickCapture"
      class="fixed bottom-20 right-4 z-50 md:hidden w-12 h-12 rounded-full bg-primary text-primary-foreground shadow-lg flex items-center justify-center hover:bg-primary/90 active:scale-95 transition-all"
    >
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    </button>

    <nav class="fixed bottom-0 left-0 right-0 z-50 md:hidden bg-card/95 backdrop-blur-lg border-t border-border safe-bottom">
      <div class="flex items-center justify-around h-14">
        <button
          v-for="v in views"
          :key="'mobile-' + v.key"
          @click="currentView = v.key"
          class="flex flex-col items-center justify-center gap-0.5 flex-1 h-full transition-colors"
          :class="currentView === v.key ? 'text-primary' : 'text-muted-foreground'"
        >
          <span class="text-lg leading-none">{{ v.icon }}</span>
          <span class="text-[10px] font-semibold">{{ v.label }}</span>
        </button>
        <button
          @click="settingsOpen = true"
          class="flex flex-col items-center justify-center gap-0.5 flex-1 h-full transition-colors text-muted-foreground"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
          <span class="text-[10px] font-semibold">Settings</span>
        </button>
      </div>
    </nav>

    <!-- ===== Settings Modal ===== -->
    <div
      v-if="settingsOpen"
      class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50"
      @click.self="settingsOpen = false"
    >
      <div class="bg-card border border-border rounded-xl w-full max-w-2xl shadow-xl overflow-hidden max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between px-5 pt-5 pb-3">
          <div class="flex items-center gap-2">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            <p class="text-sm font-semibold text-foreground">Settings</p>
          </div>
          <button
            @click="settingsOpen = false"
            class="text-muted-foreground hover:text-foreground transition-colors p-1"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>

        <div class="px-5 pb-5 space-y-5">
          <!-- Theme -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-3">Theme</p>
            <div class="grid grid-cols-1 gap-2">
              <button
                v-for="t in themes"
                :key="t.key"
                @click="setTheme(t.key)"
                class="flex items-center gap-3 w-full rounded-lg px-3 py-2.5 text-left transition-colors"
                :class="currentTheme === t.key ? 'bg-primary/10 ring-1 ring-primary/30' : 'hover:bg-accent'"
              >
                <span
                  class="w-8 h-8 rounded-full border-2 shadow-sm shrink-0"
                  :class="currentTheme === t.key ? 'border-primary scale-110' : 'border-border'"
                  :style="{ backgroundColor: t.swatch }"
                ></span>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-foreground">{{ t.name }}</p>
                  <p class="text-[11px] text-muted-foreground">{{ t.description }}</p>
                </div>
                <svg v-if="currentTheme === t.key" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="text-primary shrink-0"><polyline points="20 6 9 17 4 12"/></svg>
              </button>
            </div>
          </div>

          <!-- Notes font -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-3">Notes Font</p>
            <div class="grid grid-cols-2 gap-1.5">
              <button
                v-for="f in noteFonts"
                :key="f.key"
                @click="setNoteFont(f.key)"
                class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-left transition-colors"
                :class="selectedNoteFont === f.key ? 'bg-primary/10 ring-1 ring-primary/30' : 'hover:bg-accent'"
              >
                <span
                  class="text-lg w-8 text-center shrink-0"
                  :style="{ fontFamily: f.family }"
                >Aa</span>
                <span class="text-[12px] text-foreground truncate">{{ f.name }}</span>
              </button>
            </div>
          </div>

          <!-- Contexts -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-3">Contexts</p>
            <div class="space-y-1.5">
              <div
                v-for="ctx in contexts"
                :key="ctx.id"
                class="flex items-center justify-between rounded-lg px-3 py-2 bg-muted/30"
              >
                <span class="text-sm text-foreground">{{ ctx.name }}</span>
                <button
                  v-if="!ctx.built_in"
                  @click="deleteContext(ctx.id)"
                  class="text-[10px] text-destructive/50 hover:text-destructive transition-colors"
                >Remove</button>
                <span v-else class="text-[10px] text-muted-foreground">built-in</span>
              </div>
            </div>
            <div v-if="settingsAddingContext" class="flex gap-2 mt-2">
              <input
                ref="settingsContextInput"
                v-model="settingsNewContextName"
                type="text"
                placeholder="e.g. 🏋️ @gym"
                @keydown.enter="settingsCommitContext"
                @keydown.esc="settingsAddingContext = false"
                class="flex-1 rounded-lg border border-border bg-background px-3 py-1.5 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
              />
              <button @click="settingsCommitContext" :disabled="!settingsNewContextName.trim()" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-40 transition-colors">Add</button>
            </div>
            <button
              v-else
              @click="settingsAddingContext = true; settingsNewContextName = ''; nextTick(() => settingsContextInput?.focus())"
              class="text-xs text-muted-foreground hover:text-foreground transition-colors mt-2"
            >+ New context</button>
          </div>

          <!-- Email Integration -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-3">Email Integration</p>
            <div class="flex gap-2 mb-3">
              <input
                v-model="emailAddressSetting"
                type="email"
                placeholder="you@gmail.com"
                @keydown.enter="saveEmailAddress"
                class="flex-1 rounded-lg border border-border bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
                data-testid="email-address-input"
              />
              <button
                @click="saveEmailAddress"
                :disabled="emailAddressSetting === savedEmailAddress"
                class="rounded-lg bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-40 transition-colors"
                data-testid="email-address-save"
              >{{ emailAddressSetting === savedEmailAddress && savedEmailAddress ? 'Saved' : 'Save' }}</button>
            </div>

            <!-- Setup info -->
            <div class="rounded-lg border border-border bg-muted/30 p-3 space-y-2.5">
              <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full bg-green-500 shrink-0"></div>
                <p class="text-[11px] font-semibold text-muted-foreground">SMTP server running on this host</p>
              </div>

              <p class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Forwarding address</p>
              <div class="flex items-center gap-2">
                <code class="flex-1 text-[11px] bg-background border border-border rounded px-2 py-1.5 text-foreground select-all truncate">inbox@{{ emailDomain }}</code>
                <button @click="copyText('inbox@' + emailDomain, 'fwd')" class="text-[10px] text-muted-foreground hover:text-foreground transition-colors shrink-0 px-2 py-1">{{ copiedField === 'fwd' ? 'Copied!' : 'Copy' }}</button>
              </div>

              <details class="group">
                <summary class="text-[11px] font-semibold text-primary cursor-pointer hover:underline">Setup instructions</summary>
                <div class="mt-2 space-y-2 text-[11px] text-muted-foreground leading-relaxed">
                  <p class="font-semibold text-foreground">1. Add DNS records</p>
                  <p>Add two records in your DNS provider (e.g. Cloudflare):</p>
                  <div class="bg-background border border-border rounded p-2 text-[10px] font-mono space-y-1">
                    <p class="font-semibold text-foreground/70">A record (points your domain to this server)</p>
                    <p>Type: <span class="text-foreground">A</span></p>
                    <p>Name: <span class="text-foreground">{{ emailDomain }}</span></p>
                    <p>Value: <span class="text-foreground">your VPS IP address</span></p>
                    <p>Proxy: <span class="text-foreground">DNS only (not proxied)</span></p>
                  </div>
                  <div class="bg-background border border-border rounded p-2 text-[10px] font-mono space-y-1 mt-1">
                    <p class="font-semibold text-foreground/70">MX record (routes mail to this server)</p>
                    <p>Type: <span class="text-foreground">MX</span></p>
                    <p>Name: <span class="text-foreground">{{ emailDomain }}</span></p>
                    <p>Mail server: <span class="text-foreground">{{ emailDomain }}</span></p>
                    <p>Priority: <span class="text-foreground">10</span></p>
                  </div>
                  <p class="text-[10px]">If using Cloudflare, the A record <span class="text-foreground font-semibold">must be DNS only</span> (grey cloud) — Cloudflare's proxy doesn't pass port 25 (SMTP).</p>

                  <p class="font-semibold text-foreground">2. Forward from Gmail</p>
                  <p>In Gmail: <span class="text-foreground">Settings &rarr; Forwarding &rarr; Add forwarding address</span></p>
                  <p>Enter: <code class="bg-background border border-border rounded px-1 py-0.5">inbox@{{ emailDomain }}</code></p>
                  <p>Gmail will send a confirmation email. The SMTP server will receive it and it'll appear in your inbox as a task. Open it, find the confirmation code, and confirm in Gmail.</p>
                  <p>Then choose: <span class="text-foreground">Forward a copy of incoming mail to inbox@{{ emailDomain }}</span></p>

                  <p class="font-semibold text-foreground">3. (Optional) Use a filter instead</p>
                  <p>If you only want certain emails forwarded, create a Gmail filter instead of forwarding all mail. Go to <span class="text-foreground">Settings &rarr; Filters &rarr; Create filter</span> and set the action to forward to <code class="bg-background border border-border rounded px-1 py-0.5">inbox@{{ emailDomain }}</code>.</p>

                  <p class="font-semibold text-foreground">Done!</p>
                  <p>Forwarded emails arrive in real-time as inbox items. Gmail keeps its own copy untouched.</p>
                </div>
              </details>
            </div>
          </div>

          <!-- Export -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-3">Data</p>
            <button
              @click="settingsOpen = false; exportModalOpen = true"
              :disabled="doneItems.length === 0"
              class="w-full rounded-lg px-3 py-2.5 text-sm font-medium text-left transition-colors hover:bg-accent disabled:opacity-40 disabled:pointer-events-none"
            >
              {{ themeIcons.done }} Export Completed Tasks
              <span class="text-xs text-muted-foreground ml-1">({{ doneItems.length }})</span>
            </button>
          </div>

          <!-- Two-Factor Authentication -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-3">Security</p>
            <div class="rounded-lg border border-border bg-muted/30 p-3 space-y-3">
              <div v-if="!twoFactorSetup && !twoFactorEnabled" class="space-y-2">
                <p class="text-[12px] text-muted-foreground">Two-factor authentication is not enabled.</p>
                <button @click="setupTwoFactor" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground hover:bg-primary/90 transition-colors">
                  Enable 2FA
                </button>
              </div>
              <div v-else-if="twoFactorSetup && !twoFactorEnabled" class="space-y-3">
                <p class="text-[12px] text-muted-foreground">Scan this QR code with your authenticator app:</p>
                <div class="flex justify-center">
                  <img :src="twoFactorQrDataUrl" alt="2FA QR Code" class="w-48 h-48 rounded-lg border border-border" v-if="twoFactorQrDataUrl" />
                </div>
                <div class="text-center">
                  <p class="text-[11px] text-muted-foreground mb-1">Or enter this key manually:</p>
                  <code class="bg-background border border-border rounded px-2 py-1 text-xs font-mono text-foreground select-all">{{ twoFactorSecret }}</code>
                </div>
                <div class="flex gap-2">
                  <input
                    v-model="twoFactorCode"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    placeholder="6-digit code"
                    class="flex-1 rounded-lg border border-border bg-background px-3 py-1.5 text-sm text-center tracking-widest"
                  />
                  <button @click="confirmTwoFactor" :disabled="twoFactorCode.length !== 6" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground hover:bg-primary/90 transition-colors disabled:opacity-40">
                    Verify
                  </button>
                </div>
                <p v-if="twoFactorError" class="text-[11px] text-red-500">{{ twoFactorError }}</p>
                <button @click="twoFactorSetup = false; twoFactorSecret = ''; twoFactorQrDataUrl = ''" class="text-[11px] text-muted-foreground hover:text-foreground transition-colors">
                  Cancel
                </button>
              </div>
              <div v-else class="space-y-2">
                <p class="text-[12px] text-green-500 flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-green-500"></span>
                  Two-factor authentication is enabled.
                </p>
                <div class="flex gap-2 items-center">
                  <input
                    v-model="twoFactorDisablePassword"
                    type="password"
                    placeholder="Enter password to disable"
                    class="flex-1 rounded-lg border border-border bg-background px-3 py-1.5 text-sm"
                  />
                  <button @click="disableTwoFactor" :disabled="!twoFactorDisablePassword" class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-red-700 transition-colors disabled:opacity-40">
                    Disable
                  </button>
                </div>
                <p v-if="twoFactorError" class="text-[11px] text-red-500">{{ twoFactorError }}</p>
              </div>
            </div>
          </div>

          <!-- App Version -->
          <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-3">App</p>
            <div class="rounded-lg border border-border bg-muted/30 p-3 space-y-2">
              <p class="text-[12px] text-muted-foreground">
                Running version <code class="bg-background border border-border rounded px-1 py-0.5 text-[11px] font-mono text-foreground">{{ deployedCommit }}</code>
              </p>
              <p v-if="buildReady" class="text-[11px] text-primary">
                New version ready — use the banner above to apply.
              </p>
              <p v-else class="text-[11px] text-green-500">Up to date</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Hotkeys modal -->
    <div
      v-if="hotkeysOpen"
      class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50"
      @click.self="hotkeysOpen = false"
    >
      <div class="bg-card border border-border rounded-xl w-full max-w-lg shadow-xl overflow-hidden max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between px-5 pt-5 pb-3">
          <div class="flex items-center gap-2">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M6 8h.01M10 8h.01M14 8h.01M18 8h.01M6 12h.01M10 12h.01M14 12h.01M18 12h.01M8 16h8"/></svg>
            <p class="text-sm font-semibold text-foreground">Keyboard Shortcuts</p>
          </div>
          <button @click="hotkeysOpen = false" class="text-muted-foreground hover:text-foreground transition-colors p-1">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>

        <div class="px-5 pb-5">
          <!-- Global -->
          <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-2">Global</p>
          <div class="space-y-1 mb-4">
            <div class="hotkey-row"><span>Tasks view</span><kbd>{{ isMac ? '⌘' : 'Ctrl+' }}1</kbd></div>
            <div class="hotkey-row"><span>Notes view</span><kbd>{{ isMac ? '⌘' : 'Ctrl+' }}2</kbd></div>
            <div class="hotkey-row"><span>Calendar view</span><kbd>{{ isMac ? '⌘' : 'Ctrl+' }}3</kbd></div>
            <div class="hotkey-row"><span>Search</span><kbd>{{ isMac ? '⌘' : 'Ctrl+' }}F</kbd></div>
            <div class="hotkey-row"><span>Capture to inbox</span><kbd>I</kbd></div>
            <div class="hotkey-row"><span>Close / Back</span><kbd>Esc</kbd></div>
            <div class="hotkey-row"><span>This dialog</span><kbd>?</kbd></div>
          </div>

          <!-- Tasks -->
          <template v-if="currentView === 'tasks'">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-2">Tasks</p>
            <div class="space-y-1 mb-4">
              <div class="hotkey-row"><span>New next action</span><kbd>N</kbd></div>
              <div class="hotkey-row"><span>New waiting for</span><kbd>W</kbd></div>
            </div>
          </template>

          <!-- Notes -->
          <template v-if="currentView === 'notes'">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-2">Notes</p>
            <div class="space-y-1 mb-4">
              <div class="hotkey-row"><span>New note</span><kbd>N</kbd></div>
              <div class="hotkey-row"><span>Edit title</span><kbd>E</kbd></div>
              <div class="hotkey-row"><span>Add tag</span><kbd>T</kbd></div>
              <div class="hotkey-row"><span>Toggle pin</span><kbd>P</kbd></div>
              <div class="hotkey-row"><span>Trash note</span><kbd>Backspace</kbd></div>
              <div class="hotkey-row"><span>Toggle sidebar</span><kbd>Shift+B</kbd></div>
              <div class="hotkey-row"><span>Edit / Preview</span><kbd>{{ isMac ? '⌘' : 'Ctrl+' }}E</kbd></div>
              <div class="hotkey-row"><span>Lock / Unlock</span><kbd>{{ isMac ? '⌘' : 'Ctrl+' }}L</kbd></div>
              <div class="hotkey-row"><span>Fullscreen</span><kbd>{{ isMac ? '⌘' : 'Ctrl+' }}⇧F</kbd></div>
            </div>
          </template>

          <!-- Calendar -->
          <template v-if="currentView === 'calendar'">
            <p class="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-2">Calendar</p>
            <div class="space-y-1 mb-4">
              <div class="hotkey-row"><span>Save event</span><kbd>Enter</kbd></div>
              <div class="hotkey-row"><span>Cancel editing</span><kbd>Esc</kbd></div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Quick capture modal -->
    <div
      v-if="quickCapture"
      class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50"
      @click.self="quickCapture = false"
    >
      <div class="bg-card border border-border rounded-xl p-5 w-full max-w-lg shadow-xl">
        <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-3">Quick Capture <span class="ml-1 opacity-50">— press I</span></p>
        <div class="flex gap-2">
          <input
            ref="quickInput"
            v-model="quickTitle"
            @keydown.enter="quickCaptureSubmit"
            @keydown.esc="quickCapture = false"
            type="text"
            placeholder="What's on your mind?"
            class="flex-1 rounded-xl border border-input bg-background px-3 py-2 text-base outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
          />
          <button
            @click="quickCaptureSubmit"
            :disabled="!quickTitle.trim()"
            class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 disabled:pointer-events-none transition-colors"
          >Capture</button>
        </div>
      </div>
    </div>

    <!-- Quick Next Action modal -->
    <div
      v-if="quickNextAction"
      class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50"
      @click.self="quickNextAction = false"
      @keydown.enter="quickNextActionSubmit"
      @keydown.esc="quickNextAction = false"
    >
      <div class="bg-card border border-border rounded-xl p-5 w-full max-w-lg shadow-xl">
        <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-3">New Next Action <span class="ml-1 opacity-50">— press N</span></p>
        <div class="flex gap-2 mb-3">
          <input
            ref="quickNextInput"
            v-model="quickNextTitle"
            @keydown.enter="quickNextActionSubmit"
            @keydown.esc="quickNextAction = false"
            type="text"
            placeholder="What's the next physical action?"
            class="flex-1 rounded-xl border border-input bg-background px-3 py-2 text-base outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
          />
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs text-muted-foreground shrink-0">Context:</span>
          <div class="flex flex-wrap gap-1.5 flex-1">
            <button
              v-for="ctx in allContexts"
              :key="ctx"
              @click="quickNextContext = quickNextContext === ctx ? '' : ctx"
              class="rounded-full px-3 py-1 text-[12px] font-medium transition-colors"
              :class="quickNextContext === ctx ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground'"
            >{{ ctx }}</button>
          </div>
          <button
            @click="quickNextActionSubmit"
            :disabled="!quickNextTitle.trim()"
            class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 disabled:pointer-events-none transition-colors shrink-0"
          >Add</button>
        </div>
      </div>
    </div>

    <!-- Quick Waiting For modal -->
    <div
      v-if="quickWaiting"
      class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50"
      @click.self="quickWaiting = false"
    >
      <div class="bg-card border border-border rounded-xl p-5 w-full max-w-lg shadow-xl">
        <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-3">New Waiting For <span class="ml-1 opacity-50">— press W</span></p>
        <div class="space-y-3">
          <input
            ref="quickWaitingInput"
            v-model="quickWaitingTitle"
            @keydown.enter="quickWaitingSubmit"
            @keydown.esc="quickWaiting = false"
            type="text"
            placeholder="What are you waiting for?"
            class="w-full rounded-xl border border-input bg-background px-3 py-2 text-base outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
          />
          <div class="flex gap-2">
            <input
              v-model="quickWaitingFor"
              @keydown.enter="quickWaitingSubmit"
              @keydown.esc="quickWaiting = false"
              type="text"
              placeholder="Who? (person or org)"
              class="flex-1 rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
            />
            <input
              v-model="quickWaitingDate"
              type="date"
              class="rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring text-foreground"
            />
          </div>
          <div class="flex justify-end">
            <button
              @click="quickWaitingSubmit"
              :disabled="!quickWaitingTitle.trim() || !quickWaitingFor.trim()"
              class="rounded-xl bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50 disabled:pointer-events-none transition-colors"
            >Add</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Process modal -->
    <div
      v-if="processingInbox"
      class="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50"
      @click.self="processingInbox = false"
    >
      <div class="bg-card border border-border rounded-xl p-4 md:p-5 w-full max-w-lg shadow-xl max-h-[85vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-4">
          <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">
            Processing <span v-if="currentProcessItem" class="opacity-50">{{ processIndex + 1 }} / {{ inboxCountAtProcessStart }}</span>
          </p>
          <div class="flex items-center gap-2">
            <button
              v-if="currentProcessItem"
              @click="toggleProcessFlag"
              class="rounded-full px-2.5 py-0.5 text-xs font-medium transition-colors"
              :class="currentProcessItem.flagged ? 'bg-red-500/15 text-red-500 ring-1 ring-red-500/30' : 'text-muted-foreground/50 hover:text-red-500 hover:bg-red-500/10'"
            >{{ themeIcons.flag }} {{ currentProcessItem.flagged ? 'Flagged' : 'Flag' }}</button>
            <button @click="processingInbox = false" class="text-xs text-muted-foreground hover:text-foreground transition-colors">Close</button>
          </div>
        </div>

        <!-- Flash confirmation -->
        <div v-if="processFlash" class="text-center py-8" data-testid="process-flash">
          <p class="text-3xl mb-2 animate-bounce">✓</p>
          <p class="text-sm text-green-400 font-semibold">{{ processFlashLabel }}</p>
        </div>

        <!-- Context sub-step -->
        <div v-else-if="processStep === 'context' && currentProcessItem" class="space-y-4">
          <input v-model="processEditTitle" type="text" class="w-full bg-transparent text-xl font-semibold outline-none placeholder:text-muted-foreground/40 text-foreground rounded-lg border border-border/40 px-3 py-2" />
          <div class="flex items-center gap-3 mb-2">
            <button @click="processStep = 'main'" class="w-7 h-7 rounded-full bg-muted hover:bg-accent flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors text-sm">←</button>
            <div>
              <p class="text-sm font-semibold">Pick a context</p>
              <p class="text-xs text-muted-foreground">Where will you do this?</p>
            </div>
          </div>
          <div class="grid grid-cols-3 gap-2">
            <button
              v-for="ctx in allContexts"
              :key="ctx"
              @click="processWithContext(ctx)"
              class="flex flex-col items-center gap-1.5 rounded-xl border border-border/60 bg-card hover:bg-accent py-3 text-sm font-medium transition-all hover:scale-105 active:scale-95"
            >
              <span class="text-xs">{{ ctx }}</span>
            </button>
          </div>
          <button @click="processAs('next-action')" class="w-full text-xs text-muted-foreground hover:text-foreground transition-colors">Skip — no context</button>
        </div>

        <!-- Waiting sub-step -->
        <div v-else-if="processStep === 'waiting' && currentProcessItem" class="space-y-4">
          <input v-model="processEditTitle" type="text" class="w-full bg-transparent text-xl font-semibold outline-none placeholder:text-muted-foreground/40 text-foreground rounded-lg border border-border/40 px-3 py-2" />
          <div class="flex items-center gap-3 mb-2">
            <button @click="processStep = 'main'" class="w-7 h-7 rounded-full bg-muted hover:bg-accent flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors text-sm">←</button>
            <div>
              <p class="text-sm font-semibold">Who are you waiting for?</p>
              <p class="text-xs text-muted-foreground">Person or team blocking this</p>
            </div>
          </div>
          <input
            ref="processWaitingInput"
            v-model="processWaitingFor"
            type="text"
            placeholder="e.g. John, Design team…"
            @keydown.enter="processConfirmWaiting"
            class="w-full rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-amber-500/40 text-amber-300 font-medium"
          />
          <div>
            <label class="text-xs text-muted-foreground block mb-1">Date</label>
            <input v-model="processWaitingDate" type="date" class="w-full rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-amber-500/40 text-amber-300 font-medium" />
          </div>
          <div class="flex gap-2 justify-end">
            <button @click="processAs('waiting')" class="text-xs text-muted-foreground hover:text-foreground transition-colors px-3 py-2">Skip</button>
            <button @click="processConfirmWaiting" class="rounded-xl bg-amber-600 hover:bg-amber-500 text-white px-5 py-2 text-sm font-semibold transition-colors">Save</button>
          </div>
        </div>

        <!-- Tickler sub-step -->
        <div v-else-if="processStep === 'tickler' && currentProcessItem" class="space-y-4">
          <input v-model="processEditTitle" type="text" class="w-full bg-transparent text-xl font-semibold outline-none placeholder:text-muted-foreground/40 text-foreground rounded-lg border border-border/40 px-3 py-2" />
          <div class="flex items-center gap-3 mb-2">
            <button @click="processStep = 'main'" class="w-7 h-7 rounded-full bg-muted hover:bg-accent flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors text-sm">←</button>
            <div>
              <p class="text-sm font-semibold">Tickler — defer to when?</p>
              <p class="text-xs text-muted-foreground">Task reappears on this date</p>
            </div>
          </div>
          <div class="flex justify-center">
            <Calendar v-model="processTicklerDate" :min-value="getToday(getLocalTimeZone())" class="rounded-xl border border-violet-500/20 bg-violet-500/5" />
          </div>
          <div class="flex gap-2 justify-end">
            <button @click="processStep = 'main'" class="text-xs text-muted-foreground hover:text-foreground transition-colors px-3 py-2">Cancel</button>
            <button @click="processConfirmTickler" :disabled="!processTicklerDate" class="rounded-xl bg-violet-600 hover:bg-violet-500 disabled:opacity-40 disabled:pointer-events-none text-white px-5 py-2 text-sm font-semibold transition-colors">Defer</button>
          </div>
        </div>

        <!-- Event sub-step -->
        <div v-else-if="processStep === 'event' && currentProcessItem" class="space-y-4">
          <input v-model="processEditTitle" type="text" class="w-full bg-transparent text-xl font-semibold outline-none placeholder:text-muted-foreground/40 text-foreground rounded-lg border border-border/40 px-3 py-2" />
          <div class="flex items-center gap-3 mb-2">
            <button @click="processStep = 'main'" class="w-7 h-7 rounded-full bg-muted hover:bg-accent flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors text-sm">←</button>
            <div>
              <p class="text-sm font-semibold">Schedule as Event</p>
              <p class="text-xs text-muted-foreground">Pick a date and time for the calendar</p>
            </div>
          </div>
          <div class="flex justify-center">
            <Calendar v-model="processEventDate" :min-value="getToday(getLocalTimeZone())" class="rounded-xl border border-teal-500/20 bg-teal-500/5" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs text-muted-foreground block mb-1">Start time</label>
              <TimePicker v-model="processEventTime" />
            </div>
            <div>
              <label class="text-xs text-muted-foreground block mb-1">End time</label>
              <TimePicker v-model="processEventEndTime" />
            </div>
          </div>
          <div>
            <label class="text-xs text-muted-foreground block mb-1.5">Color</label>
            <div class="flex gap-2">
              <button v-for="c in ['blue', 'red', 'green', 'yellow', 'purple']" :key="c" @click="processEventColor = c" class="w-7 h-7 rounded-full border-2 transition-all" :class="[processEventColor === c ? 'scale-110 ring-2 ring-offset-2 ring-offset-background' : 'hover:scale-105', { 'bg-blue-500 border-blue-500 ring-blue-500': c === 'blue', 'bg-red-500 border-red-500 ring-red-500': c === 'red', 'bg-green-500 border-green-500 ring-green-500': c === 'green', 'bg-yellow-500 border-yellow-500 ring-yellow-500': c === 'yellow', 'bg-purple-500 border-purple-500 ring-purple-500': c === 'purple' }]"></button>
            </div>
          </div>
          <div>
            <label class="text-xs text-muted-foreground block mb-1">Recurrence</label>
            <select v-model="processEventRecurrence" class="w-full rounded-xl border border-teal-500/30 bg-teal-500/10 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-teal-500/40 font-medium">
              <option value="">None</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
              <option value="yearly">Yearly</option>
            </select>
          </div>
          <div class="flex gap-2 justify-end">
            <button @click="processStep = 'main'" class="text-xs text-muted-foreground hover:text-foreground transition-colors px-3 py-2">Cancel</button>
            <button @click="processConfirmEvent" :disabled="!processEventDate" class="rounded-xl bg-teal-600 hover:bg-teal-500 disabled:opacity-40 disabled:pointer-events-none text-white px-5 py-2 text-sm font-semibold transition-colors">Schedule</button>
          </div>
        </div>

        <!-- Project sub-step -->
        <div v-else-if="processStep === 'project' && currentProcessItem" class="space-y-4">
          <input v-model="processEditTitle" type="text" class="w-full bg-transparent text-xl font-semibold outline-none placeholder:text-muted-foreground/40 text-foreground rounded-lg border border-border/40 px-3 py-2" />
          <div class="flex items-center gap-3 mb-2">
            <button @click="processStep = 'main'" class="w-7 h-7 rounded-full bg-muted hover:bg-accent flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors text-sm">←</button>
            <p class="text-sm font-semibold">Set up project</p>
          </div>

          <!-- First next action -->
          <div>
            <p class="text-xs font-semibold text-muted-foreground mb-2">First next action <span class="font-normal">(optional)</span></p>
            <input
              v-model="processProjectNextAction"
              type="text"
              placeholder="e.g. Draft project brief"
              class="w-full rounded-xl border border-primary/30 bg-primary/5 px-4 py-2.5 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-primary/40 font-medium"
            />
          </div>

          <!-- Link existing tasks -->
          <div>
            <button
              v-if="!processProjectLinkExisting"
              @click="processProjectLinkExisting = true; processProjectSearchItems = ''"
              class="text-[12px] text-muted-foreground hover:text-foreground transition-colors"
            >+ Link existing tasks</button>
            <div v-else class="space-y-2">
              <p class="text-xs font-semibold text-muted-foreground">Link existing tasks</p>
              <div class="relative">
                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground/50" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input
                  v-model="processProjectSearchItems"
                  type="text"
                  placeholder="Search tasks..."
                  class="w-full rounded-lg border border-border bg-background pl-8 pr-3 py-1.5 text-[12px] outline-none placeholder:text-muted-foreground/50 focus:ring-2 focus:ring-ring"
                />
              </div>
              <div v-if="processProjectSearchResults.length > 0" class="max-h-32 overflow-y-auto space-y-0.5 rounded-lg border border-border/60 bg-card p-1">
                <button
                  v-for="t in processProjectSearchResults"
                  :key="t.id"
                  @click="processProjectLinkedIds.push(t.id)"
                  class="w-full text-left flex items-center gap-2 px-2.5 py-1.5 rounded-md text-[12px] hover:bg-accent transition-colors"
                >
                  <span class="w-2 h-2 rounded-full shrink-0" :class="{ 'bg-primary': t.status === 'next-action', 'bg-amber-500': t.status === 'waiting', 'bg-muted-foreground/40': t.status !== 'next-action' && t.status !== 'waiting' }"></span>
                  <span class="truncate flex-1">{{ t.title }}</span>
                  <span class="text-[10px] text-muted-foreground shrink-0">{{ bucketLabel(t.status) }}</span>
                </button>
              </div>
              <div v-if="processProjectLinkedIds.length > 0" class="flex flex-wrap gap-1.5">
                <span
                  v-for="id in processProjectLinkedIds"
                  :key="id"
                  class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-primary/10 text-primary"
                >
                  {{ items.find(i => i.id === id)?.title?.slice(0, 25) }}{{ (items.find(i => i.id === id)?.title?.length || 0) > 25 ? '...' : '' }}
                  <button @click="processProjectLinkedIds = processProjectLinkedIds.filter(x => x !== id)" class="hover:text-destructive ml-0.5">&times;</button>
                </span>
              </div>
            </div>
          </div>

          <div class="flex gap-2 justify-end">
            <button @click="processConfirmProject" class="rounded-xl bg-primary hover:bg-primary/90 text-primary-foreground px-5 py-2 text-sm font-semibold transition-colors">Save as Project</button>
          </div>
        </div>

        <!-- Main bucket picker -->
        <div v-else-if="currentProcessItem">
          <input v-model="processEditTitle" type="text" class="w-full bg-transparent text-xl font-semibold outline-none placeholder:text-muted-foreground/40 text-foreground rounded-lg border border-border/40 px-3 py-2 mb-4" />
          <p class="text-xs text-muted-foreground mb-3">Where does this belong?</p>
          <div class="space-y-2">
            <button @click="processStep = 'context'" class="w-full text-left rounded-xl border-2 border-primary/30 bg-primary/5 hover:bg-primary/15 px-4 py-3 transition-all">
              <span class="text-sm font-bold text-primary">{{ themeIcons.nextAction }} Next Action</span>
            </button>
            <button @click="processStartWaiting" class="w-full text-left rounded-xl border-2 border-amber-500/30 bg-amber-500/5 hover:bg-amber-500/15 px-4 py-3 transition-all">
              <span class="text-sm font-bold text-amber-400">⏳ Waiting For</span>
            </button>
            <button @click="processStartTickler" class="w-full text-left rounded-xl border-2 border-violet-500/30 bg-violet-500/5 hover:bg-violet-500/15 px-4 py-3 transition-all">
              <span class="text-sm font-bold text-violet-400">🗓️ Tickler</span>
            </button>
            <button @click="processStartEvent" class="w-full text-left rounded-xl border-2 border-teal-500/30 bg-teal-500/5 hover:bg-teal-500/15 px-4 py-3 transition-all">
              <span class="text-sm font-bold text-teal-400">{{ themeIcons.calendar }} Event</span>
            </button>
<button @click="processStartProject" class="w-full text-left rounded-xl border-2 border-border/60 bg-card hover:bg-accent px-4 py-3 transition-all">
              <span class="text-sm font-bold">📁 Project</span>
            </button>
            <button @click="processAs('someday')" class="w-full text-left rounded-xl border-2 border-border/60 bg-card hover:bg-accent px-4 py-3 transition-all">
              <span class="text-sm font-bold">🌱 Someday/Maybe</span>
            </button>
            <button @click="processAs('done')" class="w-full text-left rounded-xl border-2 border-green-500/30 bg-green-500/5 hover:bg-green-500/15 px-4 py-3 transition-all">
              <span class="text-sm font-bold text-green-400">{{ themeIcons.done }} Done</span>
            </button>
            <button
              v-if="!confirmingProcessDelete"
              @click="confirmingProcessDelete = true"
              class="w-full text-left rounded-xl border-2 border-destructive/30 bg-destructive/5 hover:bg-destructive/15 px-4 py-3 transition-all"
            >
              <span class="text-sm font-bold text-destructive">🗑️ Delete</span>
            </button>
            <div v-else class="rounded-xl border-2 border-destructive/30 bg-destructive/10 p-3 flex items-center justify-between gap-3">
              <p class="text-sm text-destructive font-medium">Delete permanently?</p>
              <div class="flex gap-2">
                <button @click="confirmingProcessDelete = false" class="text-xs text-muted-foreground hover:text-foreground px-3 py-1.5 transition-colors">Cancel</button>
                <button @click="deleteProcessItem" class="rounded-lg bg-destructive hover:bg-destructive/90 text-white px-4 py-2 text-sm font-semibold transition-colors">Delete</button>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-muted-foreground">
          <p class="text-3xl mb-2">✓</p>
          <p class="text-sm">All items processed!</p>
        </div>
      </div>
    </div>

    <!-- Search modal (command palette style) -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="searchOpen"
          class="fixed inset-0 bg-black/50 backdrop-blur-[2px] flex items-start justify-center pt-[15vh] px-4 z-50"
          @click.self="searchOpen = false"
        >
          <div class="bg-card border border-border/60 rounded-lg w-full max-w-2xl shadow-2xl overflow-hidden ring-1 ring-white/5" data-testid="search-modal">
            <!-- Search input -->
            <div class="px-6 py-5 flex items-center gap-4">
              <svg class="w-5 h-5 text-muted-foreground/60 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
              <input
                ref="searchInput"
                v-model="searchQuery"
                type="text"
                placeholder="Search tasks…"
                @keydown.esc="searchOpen = false"
                @keydown.enter="selectSearchResult"
                @keydown.down.prevent="searchSelectedIdx = Math.min(searchSelectedIdx + 1, searchResults.length - 1)"
                @keydown.up.prevent="searchSelectedIdx = Math.max(searchSelectedIdx - 1, 0)"
                class="w-full bg-transparent text-lg outline-none placeholder:text-muted-foreground/50 text-foreground pl-1"
              />
              <kbd class="hidden sm:inline-flex text-[10px] text-muted-foreground/40 border border-border/60 rounded px-1.5 py-0.5 shrink-0 font-mono">Esc</kbd>
            </div>
            <div class="h-px bg-border/50" />
            <!-- Results -->
            <div v-if="searchResults.length > 0" class="max-h-96 overflow-y-auto py-2">
              <button
                v-for="(result, idx) in searchResults"
                :key="result.item.id"
                @click="openItem(result.item); searchOpen = false"
                class="w-full text-left px-4 flex items-center transition-colors"
                :class="idx === searchSelectedIdx ? 'bg-accent' : 'hover:bg-accent/40'"
              >
                <div class="flex items-center gap-3 flex-1 min-w-0 px-3 py-3.5">
                  <span v-if="result.item.flagged" class="text-red-500 text-sm shrink-0">{{ themeIcons.flag }}</span>
                  <span v-else class="w-2 h-2 rounded-full shrink-0" :class="{
                    'bg-primary': result.item.status === 'next-action',
                    'bg-amber-500': result.item.status === 'waiting',
                    'bg-violet-500': result.item.status === 'tickler',
                    'bg-green-500': result.item.status === 'done',
                    'bg-muted-foreground/40': result.item.status === 'inbox' || result.item.status === 'someday' || result.item.status === 'project',
                  }"></span>
                  <p class="text-[15px] font-medium flex-1 truncate min-w-0">{{ result.item.title }}</p>
                  <span v-if="result.item.context" class="text-xs text-muted-foreground/60 shrink-0">{{ result.item.context }}</span>
                  <span class="text-[11px] text-muted-foreground/40 shrink-0 font-medium">{{ bucketLabel(result.item.status) }}</span>
                </div>
              </button>
            </div>
            <div v-else-if="searchQuery.length > 0" class="px-6 py-12 text-center">
              <p class="text-sm text-muted-foreground">No matching tasks</p>
            </div>
            <div v-else class="px-6 py-10 text-center">
              <p class="text-sm text-muted-foreground/40">Type to search across all tasks</p>
            </div>
            <!-- Footer -->
            <div class="h-px bg-border/50" />
            <div class="px-6 py-3 flex items-center gap-5 text-[10px] text-muted-foreground/30">
              <span class="flex items-center gap-1"><kbd class="font-mono border border-border/40 rounded px-1 py-px">↑↓</kbd> navigate</span>
              <span class="flex items-center gap-1"><kbd class="font-mono border border-border/40 rounded px-1 py-px">↵</kbd> open</span>
              <span class="flex items-center gap-1"><kbd class="font-mono border border-border/40 rounded px-1 py-px">esc</kbd> close</span>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>


    <!-- Clarify dialog -->
    <Dialog v-model:open="dialogOpen">
      <DialogContent :class="emailViewerOpen ? 'sm:max-w-2xl p-0 gap-0 max-h-[90vh] overflow-y-auto' : 'sm:max-w-xl p-0 gap-0 max-h-[85vh] overflow-y-auto'" :show-close-button="false" :trap-focus="!pickingProject && !emailViewerOpen" @escape-key-down="guardDialogDismiss" @interact-outside="guardDialogDismiss">

      <template v-if="!emailViewerOpen">
        <!-- Title editor -->
        <div class="px-6 pt-6 pb-4">
          <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Task</p>
            <button
              v-if="processing"
              @click="toggleFlag"
              class="rounded-full px-2.5 py-0.5 text-xs font-medium transition-colors"
              :class="processing.flagged ? 'bg-red-500/15 text-red-500 ring-1 ring-red-500/30' : 'text-muted-foreground/50 hover:text-red-500 hover:bg-red-500/10'"
            >{{ themeIcons.flag }} {{ processing.flagged ? 'Flagged' : 'Flag' }}</button>
          </div>
          <input
            v-if="editItem"
            v-model="editItem.title"
            type="text"
            @keydown.enter="saveEdits"
            @keydown.esc="!emailViewerOpen && (dialogOpen = false)"
            class="w-full bg-transparent text-xl font-semibold outline-none placeholder:text-muted-foreground/40 text-foreground rounded-lg border border-border/40 px-3 py-2"
          />
        </div>

        <!-- Tag management -->
        <div v-if="processing && !pickingContext && !pickingWaiting && !pickingTickler && !pickingEvent && !pickingProjectGoal" class="px-6 pb-2">
          <div class="flex flex-wrap items-center gap-1.5">
            <span v-for="t in (processing.tags || [])" :key="t.id" class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-primary/10 text-primary">
              #{{ t.tag }}
              <button @click="removeItemTag(t.tag)" class="hover:text-destructive transition-colors ml-0.5">&times;</button>
            </span>
            <button v-if="!addingTag" @click="startAddingTag" class="text-[11px] text-muted-foreground hover:text-foreground transition-colors px-1.5 py-0.5">+ tag</button>
            <div v-else class="relative">
              <input
                ref="tagInput"
                v-model="newTagValue"
                type="text"
                placeholder="tag name"
                @keydown.enter.prevent="commitTag"
                @keydown.esc="addingTag = false; newTagValue = ''"
                @keydown.down.prevent="tagSuggestIdx = Math.min(tagSuggestIdx + 1, tagSuggestions.length - 1)"
                @keydown.up.prevent="tagSuggestIdx = Math.max(tagSuggestIdx - 1, 0)"
                @input="tagSuggestIdx = 0"
                class="rounded-lg border border-border bg-background px-2 py-0.5 text-[11px] outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring w-32"
              />
              <div v-if="tagSuggestions.length > 0" class="absolute left-0 top-full mt-1 w-40 bg-card border border-border rounded-lg shadow-lg z-10 py-1 max-h-32 overflow-y-auto">
                <button
                  v-for="(s, idx) in tagSuggestions"
                  :key="s"
                  @click="newTagValue = s; commitTag()"
                  class="w-full text-left px-3 py-1 text-[11px] transition-colors"
                  :class="idx === tagSuggestIdx ? 'bg-accent text-foreground' : 'text-muted-foreground hover:bg-accent'"
                >#{{ s }}</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Checklist -->
        <div v-if="processing && !pickingContext && !pickingWaiting && !pickingTickler && !pickingEvent && !pickingProjectGoal" class="px-6 pb-2">
          <div class="flex items-center gap-2 mb-2">
            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Checklist</p>
            <span v-if="checklistProgress(processing)" class="text-[11px] font-medium text-muted-foreground">{{ checklistProgress(processing)!.done }}/{{ checklistProgress(processing)!.total }}</span>
          </div>
          <div v-if="processing.checklist_items && processing.checklist_items.length > 0" class="space-y-1 mb-2">
            <div
              v-for="ci in processing.checklist_items"
              :key="ci.id"
              class="flex items-center gap-2 group rounded-lg px-2 py-1.5 hover:bg-muted/50 transition-colors"
            >
              <button
                @click="toggleChecklistItem(ci)"
                class="w-4 h-4 rounded border-2 flex items-center justify-center shrink-0 transition-all"
                :class="ci.completed ? 'bg-primary border-primary text-primary-foreground' : 'border-muted-foreground/40 hover:border-primary'"
              ><svg v-if="ci.completed" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></button>
              <span class="text-sm flex-1 min-w-0 truncate" :class="ci.completed ? 'line-through text-muted-foreground/50' : 'text-foreground'">{{ ci.title }}</span>
              <button @click="removeChecklistItem(ci)" class="text-muted-foreground/30 hover:text-destructive transition-colors opacity-0 group-hover:opacity-100 shrink-0 text-xs">&times;</button>
            </div>
          </div>
          <div v-if="addingChecklist" class="flex items-center gap-2">
            <input
              ref="checklistInput"
              v-model="newChecklistTitle"
              type="text"
              placeholder="Add a step…"
              @keydown.enter.prevent="addChecklistItem"
              @keydown.esc="addingChecklist = false; newChecklistTitle = ''"
              class="flex-1 rounded-lg border border-border bg-background px-2.5 py-1.5 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
            />
            <button @click="addChecklistItem" class="text-xs text-primary font-medium hover:underline shrink-0">Add</button>
          </div>
          <button v-else @click="startAddingChecklist" class="text-[11px] text-muted-foreground hover:text-foreground transition-colors px-1.5 py-0.5">+ step</button>
        </div>

        <!-- View Email button -->
        <div v-if="processing?.email && !pickingContext && !pickingWaiting && !pickingTickler && !pickingEvent && !pickingProjectGoal" class="px-6 pb-2">
          <button
            @click="emailViewerOpen = true"
            class="w-full flex items-center gap-3 rounded-xl border-2 border-blue-500/30 bg-blue-500/5 hover:bg-blue-500/15 hover:border-blue-500/50 px-4 py-3 transition-all text-left"
          >
            <svg class="shrink-0 text-blue-500" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            <div class="min-w-0 flex-1">
              <p class="text-sm font-bold text-blue-400">View Email</p>
              <p class="text-xs text-muted-foreground truncate">from {{ processing.email.from_name || processing.email.from_address }}</p>
            </div>
            <span class="text-muted-foreground text-xs">→</span>
          </button>
        </div>

        <div class="px-6 py-5 space-y-5">

          <!-- Context sub-step -->
          <div v-if="pickingContext" class="space-y-5" @keydown.enter="confirmNextAction">
            <div class="flex items-center gap-3">
              <button @click="pickingContext = false" class="w-7 h-7 rounded-full bg-muted hover:bg-accent flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors text-sm">←</button>
              <p class="text-sm font-semibold">Next Action</p>
            </div>

            <!-- Context -->
            <div class="space-y-2.5">
              <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Context</p>
              <div class="flex flex-wrap gap-2">
                <button
                  @click="selectedContext = null"
                  class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                  :class="selectedContext === null ? 'bg-primary text-primary-foreground' : 'border border-border/60 bg-card hover:bg-accent text-muted-foreground'"
                >None</button>
                <button
                  v-for="ctx in allContexts"
                  :key="ctx"
                  @click="selectedContext = ctx"
                  class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                  :class="selectedContext === ctx ? 'bg-primary text-primary-foreground' : 'border border-border/60 bg-card hover:bg-accent'"
                >{{ ctx }}</button>
              </div>
            </div>

            <!-- Project -->
            <div class="space-y-2.5">
              <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Project</p>
              <div class="flex flex-wrap gap-2">
                <button v-if="processing?.project_id" @click="pickingProject = true; projectSearchQuery = ''" class="rounded-lg bg-primary/10 text-primary border border-primary/20 px-4 py-2 text-sm font-medium transition-colors hover:bg-primary/20 flex items-center gap-2">
                  📁 {{ projectName(processing.project_id) }}
                  <span @click.stop="assignProjectToItem(null)" class="hover:text-destructive transition-colors text-primary/60 ml-0.5">&times;</span>
                </button>
                <button v-else @click="pickingProject = true; projectSearchQuery = ''" class="rounded-lg border border-border/60 bg-card hover:bg-accent px-4 py-2 text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">None</button>
              </div>
            </div>

            <!-- Save -->
            <button @click="confirmNextAction" class="w-full rounded-xl bg-primary hover:bg-primary/90 text-primary-foreground px-5 py-2.5 text-sm font-semibold transition-colors">Save</button>
          </div>

          <!-- Waiting For sub-step -->
          <div v-else-if="pickingWaiting" class="space-y-4">
            <div class="flex items-center gap-3">
              <button @click="pickingWaiting = false" class="w-7 h-7 rounded-full bg-muted hover:bg-accent flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors text-sm">←</button>
              <div>
                <p class="text-sm font-semibold">Who are you waiting for?</p>
                <p class="text-xs text-muted-foreground">Person or team blocking this</p>
              </div>
            </div>
            <input
              ref="waitingInput"
              v-model="waitingFor"
              type="text"
              placeholder="e.g. John, Design team…"
              @keydown.enter="clarifyWaiting"
              class="w-full rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-amber-500/40 text-amber-300 font-medium"
            />
            <div>
              <label class="text-xs text-muted-foreground block mb-1">Date</label>
              <input
                v-model="waitingDateInput"
                type="date"
                class="w-full rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-amber-500/40 text-amber-300 font-medium"
                data-testid="waiting-date"
              />
            </div>
            <div class="flex gap-2 justify-end">
              <button @click="clarify('waiting')" class="text-xs text-muted-foreground hover:text-foreground transition-colors px-3 py-2">Skip</button>
              <button @click="clarifyWaiting" class="rounded-xl bg-amber-600 hover:bg-amber-500 text-white px-5 py-2 text-sm font-semibold transition-colors">Save</button>
            </div>
          </div>

          <!-- Tickler sub-step -->
          <div v-else-if="pickingTickler" class="space-y-4">
            <div class="flex items-center gap-3">
              <button @click="pickingTickler = false" class="w-7 h-7 rounded-full bg-muted hover:bg-accent flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors text-sm">←</button>
              <div>
                <p class="text-sm font-semibold">Tickler — defer to when?</p>
                <p class="text-xs text-muted-foreground">Task reappears on this date</p>
              </div>
            </div>
            <div class="flex justify-center">
              <Calendar v-model="ticklerDate" :min-value="getToday(getLocalTimeZone())" class="rounded-xl border border-violet-500/20 bg-violet-500/5" />
            </div>
            <div class="flex gap-2 justify-end">
              <button @click="pickingTickler = false" class="text-xs text-muted-foreground hover:text-foreground transition-colors px-3 py-2">Cancel</button>
              <button @click="clarifyTickler" :disabled="!ticklerDate" class="rounded-xl bg-violet-600 hover:bg-violet-500 disabled:opacity-40 disabled:pointer-events-none text-white px-5 py-2 text-sm font-semibold transition-colors">Defer</button>
            </div>
          </div>

          <!-- Event sub-step -->
          <div v-else-if="pickingEvent" class="space-y-4">
            <div class="flex items-center gap-3">
              <button @click="pickingEvent = false" class="w-7 h-7 rounded-full bg-muted hover:bg-accent flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors text-sm">←</button>
              <div>
                <p class="text-sm font-semibold">Schedule as Event</p>
                <p class="text-xs text-muted-foreground">Pick a date and time for the calendar</p>
              </div>
            </div>
            <div class="flex justify-center">
              <Calendar v-model="eventDate" :min-value="getToday(getLocalTimeZone())" class="rounded-xl border border-teal-500/20 bg-teal-500/5" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="text-xs text-muted-foreground block mb-1">Start time</label>
                <TimePicker v-model="eventTime" />
              </div>
              <div>
                <label class="text-xs text-muted-foreground block mb-1">End time</label>
                <TimePicker v-model="eventEndTime" />
              </div>
            </div>
            <div>
              <label class="text-xs text-muted-foreground block mb-1.5">Color</label>
              <div class="flex gap-2">
                <button v-for="c in ['blue', 'red', 'green', 'yellow', 'purple']" :key="c" @click="eventColor = c" class="w-7 h-7 rounded-full border-2 transition-all" :class="[eventColor === c ? 'scale-110 ring-2 ring-offset-2 ring-offset-background' : 'hover:scale-105', { 'bg-blue-500 border-blue-500 ring-blue-500': c === 'blue', 'bg-red-500 border-red-500 ring-red-500': c === 'red', 'bg-green-500 border-green-500 ring-green-500': c === 'green', 'bg-yellow-500 border-yellow-500 ring-yellow-500': c === 'yellow', 'bg-purple-500 border-purple-500 ring-purple-500': c === 'purple' }]"></button>
              </div>
            </div>
            <div>
              <label class="text-xs text-muted-foreground block mb-1">Recurrence</label>
              <select v-model="eventRecurrence" class="w-full rounded-xl border border-teal-500/30 bg-teal-500/10 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-teal-500/40 font-medium">
                <option value="">None</option>
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="yearly">Yearly</option>
              </select>
            </div>
            <div class="flex gap-2 justify-end">
              <button @click="pickingEvent = false" class="text-xs text-muted-foreground hover:text-foreground transition-colors px-3 py-2">Cancel</button>
              <button @click="clarifyAsEvent" :disabled="!eventDate" class="rounded-xl bg-teal-600 hover:bg-teal-500 disabled:opacity-40 disabled:pointer-events-none text-white px-5 py-2 text-sm font-semibold transition-colors">Schedule</button>
            </div>
          </div>

          <!-- Project sub-step -->
          <div v-else-if="pickingProjectGoal" class="space-y-4">
            <div class="flex items-center gap-3">
              <button @click="pickingProjectGoal = false" class="w-7 h-7 rounded-full bg-muted hover:bg-accent flex items-center justify-center text-muted-foreground hover:text-foreground transition-colors text-sm">←</button>
              <p class="text-sm font-semibold">Set up project</p>
            </div>

            <!-- First next action -->
            <div>
              <label class="text-xs text-muted-foreground block mb-1">First next action <span class="opacity-50">(optional)</span></label>
              <input
                v-model="editProjectNextAction"
                placeholder="What's the very first step?"
                class="w-full rounded-xl border border-border/60 bg-card px-4 py-3 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
              />
            </div>

            <!-- Link existing tasks -->
            <div>
              <button
                v-if="!editProjectLinkExisting"
                @click="editProjectLinkExisting = true; editProjectSearchItems = ''"
                class="text-xs text-muted-foreground hover:text-foreground transition-colors"
              >+ Link existing tasks</button>
              <div v-else class="space-y-2">
                <label class="text-xs text-muted-foreground block">Search tasks to link</label>
                <input
                  v-model="editProjectSearchItems"
                  placeholder="Search by title…"
                  class="w-full rounded-xl border border-border/60 bg-card px-4 py-2.5 text-sm outline-none placeholder:text-muted-foreground focus:ring-2 focus:ring-ring"
                />
                <div v-if="editProjectSearchResults.length > 0" class="max-h-32 overflow-y-auto space-y-0.5 rounded-lg border border-border/60 bg-card p-1">
                  <button
                    v-for="t in editProjectSearchResults"
                    :key="t.id"
                    @click="editProjectLinkedIds.push(t.id)"
                    class="w-full text-left px-3 py-1.5 text-sm rounded-md hover:bg-accent transition-colors truncate"
                  >{{ t.title }}</button>
                </div>
                <div v-if="editProjectLinkedIds.length > 0" class="flex flex-wrap gap-1.5">
                  <span
                    v-for="id in editProjectLinkedIds"
                    :key="id"
                    class="inline-flex items-center gap-1 rounded-full bg-primary/15 text-primary px-2.5 py-0.5 text-xs font-medium"
                  >{{ items.find(i => i.id === id)?.title || id }}
                    <button @click="editProjectLinkedIds = editProjectLinkedIds.filter(x => x !== id)" class="hover:text-destructive ml-0.5">&times;</button>
                  </span>
                </div>
              </div>
            </div>

            <div class="flex gap-2 justify-end">
              <button @click="clarifyAsProject" class="rounded-xl bg-primary hover:bg-primary/90 text-primary-foreground px-5 py-2 text-sm font-semibold transition-colors">Save as Project</button>
            </div>
          </div>

          <!-- Main bucket picker -->
          <div v-else class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-widest text-muted-foreground">Where does this belong?</p>

            <!-- Action required -->
            <div class="space-y-2">
              <button @click="pickingContext = true; selectedContext = processing?.context || null" class="w-full group text-left rounded-xl border-2 px-4 py-3.5 transition-all" :class="processing?.status === 'next-action' ? 'border-primary bg-primary/15 ring-2 ring-primary/40' : 'border-primary/30 bg-primary/5 hover:bg-primary/15 hover:border-primary/50'">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <span class="text-xl">{{ themeIcons.nextAction }}</span>
                    <div>
                      <p class="text-sm font-bold text-primary">Next Action <span v-if="processing?.status === 'next-action'" class="text-[10px] font-medium text-muted-foreground ml-1">(current)</span></p>
                      <p class="text-xs text-muted-foreground">Single physical step</p>
                    </div>
                  </div>
                  <span class="text-muted-foreground text-xs group-hover:translate-x-0.5 transition-transform">→</span>
                </div>
              </button>
              <button @click="openWaiting" class="w-full group text-left rounded-xl border-2 px-4 py-3.5 transition-all" :class="processing?.status === 'waiting' ? 'border-amber-500 bg-amber-500/15 ring-2 ring-amber-500/40' : 'border-amber-500/30 bg-amber-500/5 hover:bg-amber-500/15 hover:border-amber-500/50'">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <span class="text-xl">⏳</span>
                    <div>
                      <p class="text-sm font-bold text-amber-400">Waiting For <span v-if="processing?.status === 'waiting'" class="text-[10px] font-medium text-muted-foreground ml-1">(current)</span></p>
                      <p class="text-xs text-muted-foreground">Delegated to someone</p>
                    </div>
                  </div>
                  <span class="text-muted-foreground text-xs group-hover:translate-x-0.5 transition-transform">→</span>
                </div>
              </button>
              <button @click="openTickler" class="w-full group text-left rounded-xl border-2 px-4 py-3.5 transition-all" :class="processing?.status === 'tickler' ? 'border-violet-500 bg-violet-500/15 ring-2 ring-violet-500/40' : 'border-violet-500/30 bg-violet-500/5 hover:bg-violet-500/15 hover:border-violet-500/50'">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <span class="text-xl">🗓️</span>
                    <div>
                      <p class="text-sm font-bold text-violet-400">Tickler <span v-if="processing?.status === 'tickler'" class="text-[10px] font-medium text-muted-foreground ml-1">(current)</span></p>
                      <p class="text-xs text-muted-foreground">Defer to a specific date</p>
                    </div>
                  </div>
                  <span class="text-muted-foreground text-xs group-hover:translate-x-0.5 transition-transform">→</span>
                </div>
              </button>
              <button @click="openEvent" class="w-full group text-left rounded-xl border-2 px-4 py-3.5 transition-all border-teal-500/30 bg-teal-500/5 hover:bg-teal-500/15 hover:border-teal-500/50">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <span class="text-xl">{{ themeIcons.calendar }}</span>
                    <div>
                      <p class="text-sm font-bold text-teal-400">Event</p>
                      <p class="text-xs text-muted-foreground">Schedule on the calendar</p>
                    </div>
                  </div>
                  <span class="text-muted-foreground text-xs group-hover:translate-x-0.5 transition-transform">→</span>
                </div>
              </button>
            </div>

            <!-- No action needed -->
            <div class="space-y-2">
              <button
                v-for="b in smallBuckets"
                :key="b.key"
                @click="b.key === 'project' ? openProjectGoal() : clarify(b.key)"
                class="w-full group text-left rounded-xl border-2 px-4 py-3.5 transition-all"
                :class="processing?.status === b.key
                  ? (b.key === 'done' ? 'border-green-500 bg-green-500/15 ring-2 ring-green-500/40' : 'border-primary bg-primary/10 ring-2 ring-primary/40')
                  : (b.key === 'done'
                    ? 'border-green-500/30 bg-green-500/5 hover:bg-green-500/15 hover:border-green-500/50'
                    : 'border-border/60 bg-card hover:bg-accent hover:border-border')"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3">
                    <span class="text-xl">{{ bucketIcon(b.key) }}</span>
                    <div>
                      <p class="text-sm font-bold" :class="b.key === 'done' ? 'text-green-400' : ''">{{ b.label }} <span v-if="processing?.status === b.key" class="text-[10px] font-medium text-muted-foreground ml-1">(current)</span></p>
                      <p v-if="b.key !== 'done'" class="text-xs text-muted-foreground">{{ b.description }}</p>
                    </div>
                  </div>
                </div>
              </button>
            </div>
          </div>
        </div>

        <!-- Move to Inbox / Delete -->
        <div class="px-6 pb-5 space-y-1">
          <button
            v-if="processing && processing.status !== 'inbox'"
            @click="moveToInbox()"
            class="w-full rounded-xl py-1.5 text-xs text-muted-foreground/50 hover:text-muted-foreground hover:bg-muted/50 transition-colors"
            data-testid="move-to-inbox-btn"
          >Move to Inbox</button>
          <div v-if="confirmingDelete" class="rounded-xl border border-destructive/30 bg-destructive/10 p-3 flex items-center justify-between gap-3">
            <p class="text-sm text-destructive font-medium">Delete permanently?</p>
            <div class="flex gap-2">
              <button @click="confirmingDelete = false" class="text-xs text-muted-foreground hover:text-foreground px-3 py-1.5 transition-colors">Cancel</button>
              <button @click="deleteItem" class="rounded-lg bg-destructive hover:bg-destructive/90 text-white px-4 py-2 text-sm font-semibold transition-colors">Delete</button>
            </div>
          </div>
          <button v-else @click="confirmingDelete = true" class="w-full rounded-xl py-2.5 text-sm font-medium text-destructive/60 hover:text-destructive hover:bg-destructive/10 transition-colors">
            Delete task
          </button>
        </div>

      </template>

      <!-- Email Viewer (replaces dialog content when open) -->
      <template v-if="emailViewerOpen && processing?.email">
        <div class="px-6 pt-6 pb-3 flex items-center justify-between">
          <button @click="emailViewerOpen = false" class="text-muted-foreground hover:text-foreground transition-colors p-1 shrink-0">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          <p class="text-base font-semibold text-foreground truncate flex-1 mx-3 select-text cursor-text">{{ processing.email.subject }}</p>
        </div>
        <div class="px-6 pb-4 space-y-1 text-sm select-text cursor-text">
          <p><span class="text-muted-foreground">From:</span> <span class="font-medium">{{ processing.email.from_name ? `${processing.email.from_name} <${processing.email.from_address}>` : processing.email.from_address }}</span></p>
          <p><span class="text-muted-foreground">To:</span> {{ processing.email.to_address }}</p>
          <p><span class="text-muted-foreground">Date:</span> {{ new Date(processing.email.received_at).toLocaleString() }}</p>
        </div>
        <Separator />
        <div class="px-6 py-5 flex-1 overflow-y-auto select-text cursor-text">
          <pre class="text-sm text-foreground whitespace-pre-wrap font-sans select-text">{{ processing.email.body_text }}</pre>
        </div>
        <Separator />
        <div class="px-6 py-4 flex justify-end">
          <a
            :href="`mailto:${processing.email.from_address}?subject=Re: ${encodeURIComponent(processing.email.subject)}`"
            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 text-sm font-medium transition-colors"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
            Reply in email client
          </a>
        </div>
      </template>

      </DialogContent>
    </Dialog>

    <!-- Project Search Modal -->
    <Teleport to="body">
    <div
      v-if="pickingProject"
      class="fixed inset-0 bg-black/60 flex items-start justify-center pt-[15vh] p-4 z-[100]"
      @click.self="pickingProject = false"
      @keydown.esc.stop="pickingProject = false"
      @pointerdown.stop
    >
      <div class="bg-card border border-border rounded-xl w-full max-w-md shadow-xl overflow-hidden" @pointerdown.stop>
        <div class="px-5 pt-5 pb-3 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold">Assign to project</p>
            <button @click="pickingProject = false" class="text-muted-foreground hover:text-foreground p-1 transition-colors">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground/50" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input
              ref="projectSearchInput"
              v-model="projectSearchQuery"
              type="text"
              placeholder="Search projects..."
              class="w-full rounded-lg border border-border bg-background pl-9 pr-3 py-2.5 text-sm outline-none placeholder:text-muted-foreground/50 focus:ring-2 focus:ring-ring"
              @keydown.esc="pickingProject = false"
            />
          </div>
        </div>
        <div class="px-5 pb-5 max-h-[300px] overflow-y-auto">
          <div class="space-y-0.5">
            <button
              v-if="!projectSearchQuery"
              @click="assignProjectToItem(null)"
              class="w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-accent transition-colors text-muted-foreground"
            >
              <span class="text-base">—</span>
              <span class="text-sm font-medium">No project</span>
            </button>
            <button
              v-for="p in filteredProjects.slice(0, 15)"
              :key="p.id"
              @click="assignProjectToItem(p.id)"
              class="w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-accent transition-colors"
            >
              <span class="text-base">📁</span>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium truncate">{{ p.title }}</p>
              </div>
              <span class="text-[10px] text-muted-foreground shrink-0">{{ (projectTasksMap.get(p.id) || []).length }} tasks</span>
            </button>
            <p v-if="projectSearchQuery && filteredProjects.length === 0" class="text-sm text-muted-foreground text-center py-6">No projects match</p>
          </div>
        </div>
      </div>
    </div>
    </Teleport>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

// Network connectivity check
const isOnline = ref(true)
let healthPollInterval: ReturnType<typeof setInterval> | null = null

async function checkHealth() {
  try {
    const res = await fetch('/health', { method: 'GET', cache: 'no-store' })
    isOnline.value = res.ok
  } catch {
    isOnline.value = false
  }
}

// Fire immediately during setup
checkHealth()

// Guarded router — blocks POST/PUT/DELETE when offline
const guardedRouter = {
  post(...args: Parameters<typeof router.post>) {
    if (!isOnline.value) return
    return router.post(...args)
  },
  put(...args: Parameters<typeof router.put>) {
    if (!isOnline.value) return
    return router.put(...args)
  },
  delete(...args: Parameters<typeof router.delete>) {
    if (!isOnline.value) return
    return router.delete(...args)
  },
  get(...args: Parameters<typeof router.get>) {
    return router.get(...args)
  },
  reload(...args: Parameters<typeof router.reload>) {
    return router.reload(...args)
  },
}

// Shared Inertia options: only refetch items (not notes/events) on item mutations
const itemOnly = { preserveScroll: true, only: ['items'] } as const
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Calendar } from '@/components/ui/calendar'
import { CalendarDate, getLocalTimeZone, today as getToday } from '@internationalized/date'
import NotesView from './NotesView.vue'
import CalendarView from './CalendarView.vue'
import { getEcho } from '@/echo'
import ReviewView from './ReviewView.vue'
import TimePicker from './TimePicker.vue'

type Status = 'inbox' | 'next-action' | 'project' | 'waiting' | 'someday' | 'tickler' | 'done' | 'trash'

interface ItemTagRecord { id: number; item_id: string; tag: string }
interface Email { id: string; item_id: string; from_address: string; from_name?: string | null; to_address: string; subject: string; body_text: string; received_at: string; message_id?: string | null }
interface ChecklistItemRecord { id: string; item_id: string; title: string; completed: boolean; sort_order: number }
interface Item { id: string; title: string; status: Status; context?: string; waiting_for?: string; waiting_date?: string; tickler_date?: string; notes?: string; sort_order?: number; flagged?: boolean; completed_at?: string; original_status?: string; tags?: ItemTagRecord[]; email?: Email | null; goal?: string | null; project_id?: string | null; updated_at?: string; checklist_items?: ChecklistItemRecord[] }

// View navigation
type ViewKey = 'tasks' | 'notes' | 'calendar'
const currentView = ref<ViewKey>('tasks')
const views = computed(() => [
  { key: 'tasks' as ViewKey, label: 'Tasks', icon: '✓' },
  { key: 'notes' as ViewKey, label: 'Notes', icon: themeIcons.value.notes },
  { key: 'calendar' as ViewKey, label: 'Calendar', icon: themeIcons.value.calendar },
])

// Reload lazy props when switching views
watch(currentView, (v) => {
  if (v === 'notes') {
    router.reload({ only: ['notes'] })
  } else if (v === 'calendar') {
    router.reload({ only: ['events'] })
  }
})

const buckets: { key: Status; label: string; description: string }[] = [
  { key: 'next-action', label: 'Next Action',   description: 'Single physical step' },
  { key: 'project',     label: 'Project',        description: 'Multiple steps needed' },
  { key: 'waiting',     label: 'Waiting For',    description: 'Delegated to someone' },
  { key: 'someday',     label: 'Someday/Maybe',  description: 'Not now, keep it' },
  { key: 'tickler',     label: 'Tickler',        description: 'Defer to a date' },
  { key: 'done',        label: 'Done',           description: '2-min rule applied' },
  { key: 'trash',       label: 'Trash',          description: 'Delete it' },
]
const smallBuckets = buckets.filter(b => !['next-action', 'waiting', 'trash', 'tickler'].includes(b.key))

// Themes
const themes = [
  { key: 'default', name: 'Stone', swatch: '#d4cfc8', description: 'Clean and minimal' },
  { key: 'theme-ocean', name: 'Ocean', swatch: '#8dafc8', description: 'Crisp nautical with frosted glass' },
  { key: 'theme-forest', name: 'Forest', swatch: '#8dba9c', description: 'Warm library with serif headings' },
  { key: 'theme-midnight', name: 'Midnight', swatch: '#2a2040', description: 'Dark neon cyberpunk terminal' },
  { key: 'theme-sunset', name: 'Sunset', swatch: '#e8a87c', description: 'Playful peach with handwritten labels' },
  { key: 'theme-slate', name: 'Slate', swatch: '#7a8a9e', description: 'Sharp steel-blue, industrial light' },
  { key: 'theme-obsidian', name: 'Obsidian', swatch: '#2a3040', description: 'Bold dark charcoal with steel accents' },
  { key: 'theme-gruvbox', name: 'Gruvbox', swatch: '#d65d0e', description: 'Warm retro with earthy orange and brown' },
  { key: 'theme-everforest', name: 'Everforest', swatch: '#a7c080', description: 'Soft green woodland with gentle contrast' },
  { key: 'theme-rosepine', name: 'Rosé Pine', swatch: '#c4a7e7', description: 'Muted dark with soft lavender accents' },
]
const currentTheme = ref('default')
const settingsOpen = ref(false)
const hotkeysOpen = ref(false)
const isMac = computed(() => typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.userAgent))

const themeIcons = computed(() => {
  return { nextAction: '⚡', flag: '🚩', inbox: '📥', done: '✅', calendar: '📅', project: '📋', notes: '📝', review: '⚡',
    reviewSteps: { collect: '📥', inbox: '⚡', nextActions: '✓', projects: '📋', stuck: '🧴', waiting: '⏳', someday: '💭', calendar: '📅', goals: '🎯', complete: '🎉' } }
})

const noteFonts = [
  { key: 'system', name: 'System Default', family: "-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif", sample: 'Aa' },
  { key: 'inter', name: 'Inter', family: "'Inter', sans-serif", sample: 'Aa' },
  { key: 'dm-sans', name: 'DM Sans', family: "'DM Sans', sans-serif", sample: 'Aa' },
  { key: 'nunito', name: 'Nunito', family: "'Nunito', sans-serif", sample: 'Aa' },
  { key: 'lora', name: 'Lora', family: "'Lora', serif", sample: 'Aa' },
  { key: 'merriweather', name: 'Merriweather', family: "'Merriweather', serif", sample: 'Aa' },
  { key: 'libre-baskerville', name: 'Libre Baskerville', family: "'Libre Baskerville', serif", sample: 'Aa' },
  { key: 'jetbrains-mono', name: 'JetBrains Mono', family: "'JetBrains Mono', monospace", sample: 'Aa' },
  { key: 'ibm-plex-mono', name: 'IBM Plex Mono', family: "'IBM Plex Mono', monospace", sample: 'Aa' },
  { key: 'inconsolata', name: 'Inconsolata', family: "'Inconsolata', monospace", sample: 'Aa' },
]
const selectedNoteFont = ref('system')

function setNoteFont(key: string) {
  selectedNoteFont.value = key
  const font = noteFonts.find(f => f.key === key)
  if (font) {
    document.documentElement.style.setProperty('--note-font', font.family)
  }
  guardedRouter.put('/settings/note_font', { value: key }, { preserveScroll: true, preserveState: true, only: [] })
}

function setTheme(key: string) {
  const html = document.documentElement
  html.classList.remove('dark', 'theme-ocean', 'theme-forest', 'theme-midnight', 'theme-sunset', 'theme-slate', 'theme-obsidian', 'theme-gruvbox', 'theme-everforest', 'theme-rosepine')
  if (key !== 'default') html.classList.add(key)
  currentTheme.value = key
  guardedRouter.put('/settings/theme', { value: key }, { preserveScroll: true, preserveState: true, only: [] })
}

// User-creatable contexts
const page = usePage()

// Email integration settings
const emailDomain = computed(() => window.location.hostname)
const savedEmailAddress = ref((page.props.email_address as string) || '')
const emailAddressSetting = ref(savedEmailAddress.value)
const copiedField = ref<string | null>(null)

// SMTP status polling
const smtpStatus = ref<'up' | 'down' | 'checking'>('checking')
let smtpPollInterval: ReturnType<typeof setInterval> | null = null

async function checkSmtpStatus() {
  try {
    const res = await fetch('/api/smtp-status', { credentials: 'same-origin' })
    if (!res.ok) { smtpStatus.value = 'down'; return }
    const data = await res.json()
    smtpStatus.value = data.up ? 'up' : 'down'
  } catch {
    smtpStatus.value = 'down'
  }
}

// Fire immediately during setup (don't wait for onMounted)
checkSmtpStatus()

function saveEmailAddress() {
  const val = emailAddressSetting.value.trim()
  guardedRouter.put('/settings/email_address', { value: val || null }, { preserveScroll: true, preserveState: true, only: [] })
  savedEmailAddress.value = val
}

// --- Two-Factor Authentication ---
const twoFactorEnabled = ref(false)
const twoFactorSetup = ref(false)
const twoFactorSecret = ref('')
const twoFactorQrDataUrl = ref('')
const twoFactorCode = ref('')
const twoFactorError = ref('')
const twoFactorDisablePassword = ref('')

async function checkTwoFactorStatus() {
  try {
    const res = await fetch('/api/2fa/status')
    if (res.ok) {
      const data = await res.json()
      twoFactorEnabled.value = data.enabled
    }
  } catch {}
}

async function setupTwoFactor() {
  twoFactorError.value = ''
  try {
    const res = await fetch('/api/2fa/setup', {
      method: 'POST',
      headers: { 'X-XSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
    })
    if (res.ok) {
      const data = await res.json()
      twoFactorSecret.value = data.secret
      twoFactorQrDataUrl.value = data.qr_svg
      twoFactorSetup.value = true
    }
  } catch {}
}

async function confirmTwoFactor() {
  twoFactorError.value = ''
  try {
    const res = await fetch('/api/2fa/confirm', {
      method: 'POST',
      headers: { 'X-XSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: twoFactorCode.value }),
    })
    if (res.ok) {
      const data = await res.json()
      if (data.confirmed) {
        twoFactorEnabled.value = true
        twoFactorSetup.value = false
        twoFactorSecret.value = ''
        twoFactorQrDataUrl.value = ''
        twoFactorCode.value = ''
      }
    } else {
      const data = await res.json()
      twoFactorError.value = data.error || 'Invalid code'
    }
  } catch {
    twoFactorError.value = 'Failed to verify code'
  }
}

async function disableTwoFactor() {
  twoFactorError.value = ''
  try {
    const res = await fetch('/api/2fa/disable', {
      method: 'POST',
      headers: { 'X-XSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ password: twoFactorDisablePassword.value }),
    })
    if (res.ok) {
      const data = await res.json()
      if (data.disabled) {
        twoFactorEnabled.value = false
        twoFactorDisablePassword.value = ''
      }
    } else {
      const data = await res.json()
      twoFactorError.value = data.error || 'Failed to disable'
    }
  } catch {
    twoFactorError.value = 'Failed to disable 2FA'
  }
}

checkTwoFactorStatus()

// --- App Updates (silent background build) ---
const deployedCommit = computed(() => (page.props.commit_hash as string) || 'unknown')
const buildReady = ref(false)
const pendingCommit = ref<string | null>(null)
const updateApplying = ref(false)

function getCsrfToken(): string {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : ''
}

async function checkBuildStatus() {
  if (!isOnline.value) return
  try {
    const res = await fetch('/api/update-status')
    if (res.ok) {
      const data = await res.json()
      buildReady.value = data.build_ready || false
      pendingCommit.value = data.pending_commit || null
      if (data.applying) {
        updateApplying.value = true
        startApplyPolling()
      }
    }
  } catch {}
}

async function applyUpdate() {
  try {
    const res = await fetch('/api/update-apply', { method: 'POST', headers: { 'X-XSRF-TOKEN': getCsrfToken(), 'Accept': 'application/json', 'Content-Type': 'application/json' } })
    if (res.ok) {
      const data = await res.json()
      if (data.applied) {
        updateApplying.value = true
        buildReady.value = false
        startApplyPolling()
      }
    }
  } catch {}
}

let applyPollTimer: ReturnType<typeof setInterval> | null = null
function startApplyPolling() {
  if (applyPollTimer) clearInterval(applyPollTimer)
  applyPollTimer = setInterval(async () => {
    try {
      const res = await fetch('/health')
      if (res.ok) {
        // Server is back — check if commit changed
        const statusRes = await fetch('/api/update-status')
        if (statusRes.ok) {
          const data = await statusRes.json()
          if (!data.applying && data.commit !== deployedCommit.value) {
            clearInterval(applyPollTimer!)
            applyPollTimer = null
            window.location.reload()
          }
        }
      }
    } catch {
      // Server down during restart — expected
    }
  }, 3000)
}

checkBuildStatus()
setInterval(checkBuildStatus, 30000)

function copyText(text: string, field = 'url') {
  navigator.clipboard.writeText(text)
  copiedField.value = field
  setTimeout(() => copiedField.value = null, 2000)
}

const contexts = computed(() => (page.props.contexts || []) as { id: number; name: string; built_in: boolean; sort_order: number }[])
const allContexts = computed(() => contexts.value.map(c => c.name))
const addingContext = ref(false)
const newContextName = ref('')
const newContextInput = ref<HTMLInputElement | null>(null)
const settingsAddingContext = ref(false)
const settingsNewContextName = ref('')
const settingsContextInput = ref<HTMLInputElement | null>(null)

function settingsCommitContext() {
  const name = settingsNewContextName.value.trim()
  if (!name) return
  const ctx = name.startsWith('@') ? name : '@' + name
  guardedRouter.post('/contexts', { name: ctx }, { preserveScroll: true, preserveState: true })
  settingsAddingContext.value = false
  settingsNewContextName.value = ''
}

function deleteContext(id: number) {
  guardedRouter.delete(`/contexts/${id}`, { preserveScroll: true, preserveState: true })
}

function startAddingContext() {
  addingContext.value = true
  newContextName.value = ''
  nextTick(() => newContextInput.value?.focus())
}

function commitNewContext() {
  const name = newContextName.value.trim()
  if (!name) return
  const ctx = name.startsWith('@') ? name : '@' + name
  guardedRouter.post('/contexts', { name: ctx })
  clarifyWithContext(ctx)
  addingContext.value = false
  newContextName.value = ''
}

const items = computed(() => (page.props.items || []) as Item[])
const activeContextFilter = ref<string | null>(null)
const activeTagFilter = ref<string | null>(null)

function toggleContextFilter(ctx: string) {
  activeContextFilter.value = activeContextFilter.value === ctx ? null : ctx
}
type PillKey = 'next-actions' | 'waiting' | 'projects' | 'inbox' | 'someday' | 'tickler' | 'done' | 'flagged'
const activePill = ref<PillKey>('next-actions')

function setActivePill(pill: PillKey) {
  activePill.value = activePill.value === pill ? 'next-actions' : pill
}
const nextActions = computed(() => items.value.filter(i => i.status === 'next-action'))
const waitingItems = computed(() => items.value.filter(i => i.status === 'waiting'))
const hasStaleWaiting = computed(() => waitingItems.value.some(i => isWaitingStale(i)))
const somedayItems = computed(() => items.value.filter(i => i.status === 'someday'))
const ticklerItems = computed(() => items.value.filter(i => i.status === 'tickler').sort((a, b) => (a.tickler_date || '').localeCompare(b.tickler_date || '')))
const doneItems = computed(() => items.value.filter(i => i.status === 'done').sort((a, b) => (b.completed_at || '').localeCompare(a.completed_at || '')))
const flaggedItems = computed(() => items.value.filter(i => i.flagged && i.status !== 'done'))

// Project computeds
const projects = computed(() => items.value.filter(i => i.status === 'project'))

const projectTasksMap = computed(() => {
  const map = new Map<string, Item[]>()
  for (const item of items.value) {
    if (item.project_id) {
      if (!map.has(item.project_id)) map.set(item.project_id, [])
      map.get(item.project_id)!.push(item)
    }
  }
  return map
})

const stuckProjects = computed(() => {
  const set = new Set<string>()
  for (const p of projects.value) {
    const tasks = projectTasksMap.value.get(p.id) || []
    if (!tasks.some(t => t.status === 'next-action')) set.add(p.id)
  }
  return set
})


function projectName(id: string): string {
  return items.value.find(i => i.id === id)?.title || 'Unknown'
}

function projectStatus(id: string): Status | undefined {
  return items.value.find(i => i.id === id)?.status
}

// Expanded projects in clarified view
const expandedProjects = ref(new Set<string>())

function toggleProjectExpand(id: string) {
  if (expandedProjects.value.has(id)) {
    expandedProjects.value.delete(id)
  } else {
    expandedProjects.value.add(id)
  }
  expandedProjects.value = new Set(expandedProjects.value)
}

// Render limits for large lists (performance)
const RENDER_BATCH = 50
const renderLimits = ref<Record<string, number>>({
  'next-actions': RENDER_BATCH,
  waiting: RENDER_BATCH,
  projects: RENDER_BATCH,
  inbox: RENDER_BATCH,
  someday: RENDER_BATCH,
  tickler: RENDER_BATCH,
  done: RENDER_BATCH,
  flagged: RENDER_BATCH,
})

function showMore(key: string) {
  renderLimits.value[key] = (renderLimits.value[key] || RENDER_BATCH) + RENDER_BATCH
}

// Reset render limits when switching pills or filters
function resetRenderLimits() {
  for (const key of Object.keys(renderLimits.value)) {
    renderLimits.value[key] = RENDER_BATCH
  }
}

watch(activePill, resetRenderLimits)
watch([activeContextFilter, activeTagFilter], () => {
  renderLimits.value['next-actions'] = RENDER_BATCH
})

const quickCapture = ref(false)
const quickTitle = ref('')
const quickInput = ref<HTMLInputElement | null>(null)

const quickNextAction = ref(false)
const quickNextTitle = ref('')
const quickNextContext = ref('')
const quickNextInput = ref<HTMLInputElement | null>(null)

const quickWaiting = ref(false)
const quickWaitingTitle = ref('')
const quickWaitingFor = ref('')
const quickWaitingDate = ref('')
const quickWaitingInput = ref<HTMLInputElement | null>(null)

// Process inbox
const processingInbox = ref(false)
const processIndex = ref(0)
const processStep = ref<'main' | 'context' | 'waiting' | 'tickler' | 'event' | 'project'>('main')
const processGoal = ref('')
const processProjectId = ref<string | null>(null)
const processFlash = ref(false)
const processFlashLabel = ref('')
const processWaitingFor = ref('')
const processWaitingDate = ref('')
const processWaitingInput = ref<HTMLInputElement | null>(null)
const processTicklerDate = ref<CalendarDate | undefined>(undefined)
const inboxCountAtProcessStart = ref(0)
const currentProcessItem = computed(() => inbox.value[0] ?? null)
const processEditTitle = ref('')

function openProcess() {
  processIndex.value = 0
  processStep.value = 'main'
  processFlash.value = false
  processProjectId.value = null
  inboxCountAtProcessStart.value = inbox.value.length
  processEditTitle.value = currentProcessItem.value?.title ?? ''
  processingInbox.value = true
}

function showFlashAndAdvance(label: string) {
  processFlash.value = true
  processFlashLabel.value = label
  processIndex.value++
  setTimeout(() => {
    processFlash.value = false
    processStep.value = 'main'
    processProjectId.value = null
    confirmingProcessDelete.value = false
    processEditTitle.value = currentProcessItem.value?.title ?? ''
  }, 600)
}

const confirmingProcessDelete = ref(false)

function deleteProcessItem() {
  const item = currentProcessItem.value
  if (!item) return
  guardedRouter.delete(`/items/${item.id}`, itemOnly)
  confirmingProcessDelete.value = false
  showFlashAndAdvance('Deleted')
}

function processEditedTitle(): string | undefined {
  const trimmed = processEditTitle.value.trim()
  const original = currentProcessItem.value?.title
  return trimmed && trimmed !== original ? trimmed : undefined
}

function processAs(status: Status) {
  const item = currentProcessItem.value
  if (!item) return
  guardedRouter.post(`/items/${item.id}/process`, { status, title: processEditedTitle(), project_id: processProjectId.value }, itemOnly)
  showFlashAndAdvance(bucketLabel(status))
}

function processWithContext(ctx: string) {
  const item = currentProcessItem.value
  if (!item) return
  guardedRouter.post(`/items/${item.id}/process`, { status: 'next-action', context: ctx, title: processEditedTitle(), project_id: processProjectId.value }, itemOnly)
  showFlashAndAdvance('Next Action — ' + ctx)
}

function processStartWaiting() {
  processStep.value = 'waiting'
  processWaitingFor.value = ''
  processWaitingDate.value = new Date().toISOString().split('T')[0]
  nextTick(() => processWaitingInput.value?.focus())
}

function processConfirmWaiting() {
  const item = currentProcessItem.value
  if (!item) return
  guardedRouter.post(`/items/${item.id}/process`, { status: 'waiting', title: processEditedTitle(), waiting_for: processWaitingFor.value.trim() || undefined, waiting_date: processWaitingDate.value || undefined, project_id: processProjectId.value }, itemOnly)
  showFlashAndAdvance('Waiting For')
}

function processStartTickler() {
  processStep.value = 'tickler'
  processTicklerDate.value = undefined
}

function processConfirmTickler() {
  if (!processTicklerDate.value) return
  const item = currentProcessItem.value
  if (!item) return
  guardedRouter.post(`/items/${item.id}/process`, { status: 'tickler', title: processEditedTitle(), tickler_date: processTicklerDate.value?.toString() }, itemOnly)
  showFlashAndAdvance('Tickler')
}

const processProjectNextAction = ref('')
const processProjectLinkExisting = ref(false)
const processProjectSearchItems = ref('')
const processProjectLinkedIds = ref<string[]>([])

const processProjectSearchResults = computed(() => {
  const q = processProjectSearchItems.value.trim().toLowerCase()
  if (!q) return []
  return items.value
    .filter(i => i.status !== 'done' && i.status !== 'trash' && i.status !== 'inbox' && i.status !== 'project' && i.title.toLowerCase().includes(q) && !processProjectLinkedIds.value.includes(i.id))
    .slice(0, 8)
})

function processStartProject() {
  processStep.value = 'project'
  processGoal.value = ''
  processProjectNextAction.value = ''
  processProjectLinkExisting.value = false
  processProjectSearchItems.value = ''
  processProjectLinkedIds.value = []
}

function processConfirmProject() {
  const item = currentProcessItem.value
  if (!item) return
  guardedRouter.post(`/items/${item.id}/process`, { status: 'project', title: processEditedTitle() }, {
    ...itemOnly,
    onSuccess: () => {
      // Create new next action if provided
      const na = processProjectNextAction.value.trim()
      if (na) {
        guardedRouter.post('/items', { title: na, status: 'next-action', project_id: item.id }, itemOnly)
      }
      // Link existing items
      for (const id of processProjectLinkedIds.value) {
        guardedRouter.post(`/items/${id}/assign-project`, { project_id: item.id }, itemOnly)
      }
    },
  })
  showFlashAndAdvance('Project')
}

// Search
const searchOpen = ref(false)
const searchQuery = ref('')
const searchSelectedIdx = ref(0)
const searchInput = ref<HTMLInputElement | null>(null)

function levenshtein(a: string, b: string): number {
  const m = a.length, n = b.length
  const dp: number[][] = Array.from({ length: m + 1 }, (_, i) => {
    const row = new Array(n + 1).fill(0)
    row[0] = i
    return row
  })
  for (let j = 0; j <= n; j++) dp[0][j] = j
  for (let i = 1; i <= m; i++) {
    for (let j = 1; j <= n; j++) {
      dp[i][j] = a[i - 1] === b[j - 1]
        ? dp[i - 1][j - 1]
        : 1 + Math.min(dp[i - 1][j], dp[i][j - 1], dp[i - 1][j - 1])
    }
  }
  return dp[m][n]
}

function fuzzyScore(query: string, text: string): number {
  const q = query.toLowerCase()
  const t = text.toLowerCase()
  if (t.includes(q)) return 0
  // Check individual words
  const words = t.split(/\s+/)
  let bestWord = Infinity
  for (const w of words) {
    bestWord = Math.min(bestWord, levenshtein(q, w))
  }
  return bestWord
}

const searchResults = computed(() => {
  const q = searchQuery.value.trim()
  if (!q) return []
  const scored = items.value
    .filter(i => i.status !== 'trash')
    .map(item => {
      let score = fuzzyScore(q, item.title)
      if (item.context) score = Math.min(score, fuzzyScore(q, item.context))
      if (item.waiting_for) score = Math.min(score, fuzzyScore(q, item.waiting_for))
      if (item.notes) score = Math.min(score, fuzzyScore(q, item.notes))
      score = Math.min(score, fuzzyScore(q, bucketLabel(item.status)))
      return { item, score }
    })
    .filter(r => r.score <= 3)
    .sort((a, b) => a.score - b.score)
  return scored.slice(0, 10)
})

watch(searchQuery, () => { searchSelectedIdx.value = 0 })

function selectSearchResult() {
  const result = searchResults.value[searchSelectedIdx.value]
  if (result) {
    openItem(result.item)
    searchOpen.value = false
  }
}

// Review
const reviewOpen = ref(false)
const reviewJustCompleted = ref(false)
const hasReviewProgress = computed(() => !reviewJustCompleted.value && !!(page.props.review_progress as string | null))
watch(reviewOpen, (v) => { if (v) reviewJustCompleted.value = false })

// Weekly review reminder
const REVIEW_INTERVAL_MS = 7 * 24 * 60 * 60 * 1000 // 7 days
const lastReviewDate = ref<string | null>(null)

const reviewOverdue = computed(() => {
  if (!lastReviewDate.value) return true
  const last = new Date(lastReviewDate.value).getTime()
  return Date.now() - last >= REVIEW_INTERVAL_MS
})

const nextReviewLabel = computed(() => {
  if (!lastReviewDate.value) return 'No review completed yet'
  const last = new Date(lastReviewDate.value)
  const next = new Date(last.getTime() + REVIEW_INTERVAL_MS)
  const now = Date.now()
  if (next.getTime() <= now) return 'Review is overdue'
  const daysLeft = Math.ceil((next.getTime() - now) / (24 * 60 * 60 * 1000))
  return `Next review in ${daysLeft} day${daysLeft === 1 ? '' : 's'}`
})

function onReviewComplete() {
  reviewOpen.value = false
  reviewJustCompleted.value = true
  lastReviewDate.value = new Date().toISOString()
}

function openQuickCapture() {
  quickCapture.value = true
  quickTitle.value = ''
  nextTick(() => quickInput.value?.focus())
}

function quickCaptureSubmit() {
  if (!quickTitle.value.trim()) return
  guardedRouter.post('/items', { title: quickTitle.value.trim(), status: 'inbox' }, { ...itemOnly, onSuccess: () => { quickCapture.value = false } })
}

function openQuickNextAction() {
  quickNextAction.value = true
  quickNextTitle.value = ''
  quickNextContext.value = ''
  nextTick(() => quickNextInput.value?.focus())
}

function quickNextActionSubmit() {
  if (!quickNextTitle.value.trim()) return
  guardedRouter.post('/items', { title: quickNextTitle.value.trim(), status: 'next-action', context: quickNextContext.value || undefined }, { ...itemOnly, onSuccess: () => { quickNextAction.value = false } })
}

function openQuickWaiting() {
  quickWaiting.value = true
  quickWaitingTitle.value = ''
  quickWaitingFor.value = ''
  quickWaitingDate.value = new Date().toISOString().split('T')[0]
  nextTick(() => quickWaitingInput.value?.focus())
}

function quickWaitingSubmit() {
  if (!quickWaitingTitle.value.trim() || !quickWaitingFor.value.trim()) return
  guardedRouter.post('/items', { title: quickWaitingTitle.value.trim(), status: 'waiting', waiting_for: quickWaitingFor.value.trim(), waiting_date: quickWaitingDate.value || undefined }, { ...itemOnly, onSuccess: () => { quickWaiting.value = false } })
}

function onKeydown(e: KeyboardEvent) {
  // Escape closes any open modal (priority order: innermost first)
  if (e.key === 'Escape') {
    if (emailViewerOpen.value) { e.preventDefault(); e.stopImmediatePropagation(); emailViewerOpen.value = false; return }
    if (selectedIds.value.size > 0) { e.preventDefault(); selectedIds.value = new Set(); return }
    if (exportModalOpen.value) { e.preventDefault(); exportModalOpen.value = false; return }
    if (processingInbox.value) { e.preventDefault(); processingInbox.value = false; return }
    if (quickCapture.value) { e.preventDefault(); quickCapture.value = false; return }
    if (quickNextAction.value) { e.preventDefault(); quickNextAction.value = false; return }
    if (quickWaiting.value) { e.preventDefault(); quickWaiting.value = false; return }
    if (searchOpen.value) { e.preventDefault(); searchOpen.value = false; return
    }
    if (settingsOpen.value) { e.preventDefault(); settingsOpen.value = false; return }
    if (hotkeysOpen.value) { e.preventDefault(); hotkeysOpen.value = false; return }
    if (dialogOpen.value) { return }
    if (activePill.value !== 'next-actions' || activeContextFilter.value !== null || activeTagFilter.value !== null) {
      e.preventDefault()
      activePill.value = 'next-actions'
      activeContextFilter.value = null
      activeTagFilter.value = null
      return
    }
    return
  }
  // Ctrl+F: view-specific search
  if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
    e.preventDefault()
    if (currentView.value === 'notes') {
      window.dispatchEvent(new CustomEvent('notes-open-search'))
    } else if (currentView.value === 'tasks') {
      searchOpen.value = true
      searchQuery.value = ''
      searchSelectedIdx.value = 0
      nextTick(() => searchInput.value?.focus())
    }
    return
  }
  // Ctrl+1/2/3 to switch views
  if ((e.ctrlKey || e.metaKey) && ['1', '2', '3'].includes(e.key)) {
    e.preventDefault()
    const viewMap: Record<string, ViewKey> = { '1': 'tasks', '2': 'notes', '3': 'calendar' }
    currentView.value = viewMap[e.key]
    return
  }
  const tag = (e.target as HTMLElement).tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA') return
  if (e.key === '?') { e.preventDefault(); hotkeysOpen.value = true; return }
  if (e.key === 'i') { e.preventDefault(); openQuickCapture() }
  if (e.key === 'n' && currentView.value === 'tasks') { e.preventDefault(); openQuickNextAction() }
  if (e.key === 'w' && currentView.value === 'tasks') { e.preventDefault(); openQuickWaiting() }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  lastReviewDate.value = (page.props.last_review as string) || null
  const savedFont = page.props.note_font as string
  if (savedFont) setNoteFont(savedFont)
  const savedTheme = page.props.theme as string
  if (savedTheme) {
    const html = document.documentElement
    html.classList.remove('dark', 'theme-ocean', 'theme-forest', 'theme-midnight', 'theme-sunset', 'theme-slate', 'theme-obsidian', 'theme-gruvbox', 'theme-everforest', 'theme-rosepine')
    if (savedTheme !== 'default') html.classList.add(savedTheme)
    currentTheme.value = savedTheme
  }
  checkSmtpStatus()
  smtpPollInterval = setInterval(checkSmtpStatus, 30000)
  checkHealth()
  healthPollInterval = setInterval(checkHealth, 15000)
  setupEcho()
})
onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  if (smtpPollInterval) clearInterval(smtpPollInterval)
  if (healthPollInterval) clearInterval(healthPollInterval)
  if (itemsPollInterval) clearInterval(itemsPollInterval)
  teardownEcho()
})

// Real-time sync via Echo (WebSocket) with polling fallback
let itemsPollInterval: ReturnType<typeof setInterval> | null = null
let echoChannel: any = null
let syncDebounceTimer: ReturnType<typeof setTimeout> | null = null

function setupEcho() {
  const echo = getEcho()
  if (!echo) {
    // Fallback to polling if Echo not configured
    itemsPollInterval = setInterval(pollForNewItems, 30000)
    return
  }

  echoChannel = echo.private('sync').listen('.SyncUpdated', () => {
    // Debounce rapid-fire events (500ms)
    if (syncDebounceTimer) clearTimeout(syncDebounceTimer)
    syncDebounceTimer = setTimeout(() => {
      pollForNewItems()
    }, 500)
  })

  // Keep a slower fallback poll for resilience
  itemsPollInterval = setInterval(pollForNewItems, 60000)
}

function teardownEcho() {
  if (echoChannel) {
    const echo = getEcho()
    echo?.leave('sync')
    echoChannel = null
  }
  if (syncDebounceTimer) clearTimeout(syncDebounceTimer)
}

function pollForNewItems() {
  if (!isOnline.value) return
  if (processing.value) return // don't refresh while clarifying
  router.reload({ only: ['items'], preserveScroll: true, preserveState: true })
}

const processing = ref<Item | null>(null)
const editItem = ref<{ title: string } | null>(null)

const pickingContext = ref(false)
const selectedContext = ref<string | null>(null)
const pickingWaiting = ref(false)
const pickingTickler = ref(false)
const ticklerDate = ref<CalendarDate | undefined>(undefined)
const pickingEvent = ref(false)
const eventDate = ref<CalendarDate | undefined>(undefined)
const eventEndDate = ref<CalendarDate | undefined>(undefined)
const eventTime = ref('')
const eventEndTime = ref('')
const eventColor = ref('blue')
const eventRecurrence = ref('')
const processEventDate = ref<CalendarDate | undefined>(undefined)
const processEventEndDate = ref<CalendarDate | undefined>(undefined)
const processEventTime = ref('')
const processEventEndTime = ref('')
const processEventColor = ref('blue')
const processEventRecurrence = ref('')
const confirmingDelete = ref(false)
const pickingProject = ref(false)
const pickingProjectGoal = ref(false)
const editGoal = ref('')
const editProjectNextAction = ref('')
const editProjectLinkExisting = ref(false)
const editProjectSearchItems = ref('')
const editProjectLinkedIds = ref<string[]>([])

const editProjectSearchResults = computed(() => {
  const q = editProjectSearchItems.value.trim().toLowerCase()
  if (!q) return []
  return items.value
    .filter(i => i.status !== 'done' && i.status !== 'trash' && i.status !== 'inbox' && i.status !== 'project' && i.title.toLowerCase().includes(q) && !editProjectLinkedIds.value.includes(i.id))
    .slice(0, 10)
})

const projectActionInput = ref('')
const projectSearchQuery = ref('')
const projectSearchInput = ref<HTMLInputElement | null>(null)

const filteredProjects = computed(() => {
  const q = projectSearchQuery.value.trim().toLowerCase()
  if (!q) return projects.value
  return projects.value.filter(p => p.title.toLowerCase().includes(q))
})

watch(pickingProject, (v) => {
  if (v) nextTick(() => projectSearchInput.value?.focus())
})
const waitingFor = ref('')
const waitingDateInput = ref('')
const waitingInput = ref<HTMLInputElement | null>(null)

function guardDialogDismiss(e: Event) {
  if (emailViewerOpen.value) e.preventDefault()
}

const dialogOpen = computed({
  get: () => processing.value !== null,
  set: (v) => { if (!v) { processing.value = null; editItem.value = null; pickingContext.value = false; pickingWaiting.value = false; pickingTickler.value = false; ticklerDate.value = undefined; pickingEvent.value = false; pickingProject.value = false; pickingProjectGoal.value = false; eventDate.value = undefined; eventEndDate.value = undefined; eventTime.value = ''; eventEndTime.value = ''; eventColor.value = 'blue'; eventRecurrence.value = ''; confirmingDelete.value = false; addingContext.value = false; addingTag.value = false; newTagValue.value = ''; addingChecklist.value = false; newChecklistTitle.value = ''; emailViewerOpen.value = false } },
})

const inbox = computed(() => items.value.filter(i => i.status === 'inbox'))

// Clarify button urgency styling
const clarifyShaking = ref(false)
let clarifyShakeInterval: ReturnType<typeof setInterval> | null = null

const clarifyBtnClass = computed(() => {
  const n = inbox.value.length
  if (n === 0) return 'bg-muted text-muted-foreground hover:bg-accent hover:text-foreground'
  if (n <= 3) return 'bg-green-500/15 text-green-700 hover:bg-green-500/25'
  if (n <= 7) return 'bg-yellow-500/15 text-yellow-700 hover:bg-yellow-500/25'
  return (clarifyShaking.value ? 'clarify-shake ' : '') + 'bg-red-500/15 text-red-700 hover:bg-red-500/25'
})

onMounted(() => {
  clarifyShakeInterval = setInterval(() => {
    if (inbox.value.length >= 10) {
      clarifyShaking.value = true
      setTimeout(() => { clarifyShaking.value = false }, 500)
    }
  }, 10000)
})

onUnmounted(() => {
  if (clarifyShakeInterval) clearInterval(clarifyShakeInterval)
})
const usedContexts = computed(() => {
  const ctxSet = new Set<string>()
  for (const item of nextActions.value) {
    if (item.context) ctxSet.add(item.context)
  }
  return [...ctxSet].sort()
})
const usedTags = computed(() => {
  const tagSet = new Set<string>()
  for (const item of nextActions.value) {
    for (const t of (item.tags || [])) {
      tagSet.add(t.tag)
    }
  }
  return [...tagSet].sort()
})
const filteredNextActions = computed(() => {
  let result = nextActions.value
  if (activeContextFilter.value) result = result.filter(i => i.context === activeContextFilter.value)
  if (activeTagFilter.value) result = result.filter(i => (i.tags || []).some(t => t.tag === activeTagFilter.value))
  return result
})

const activeListCount = computed(() => {
  switch (activePill.value) {
    case 'next-actions': return filteredNextActions.value.length
    case 'waiting': return waitingItems.value.length
    case 'projects': return projects.value.length
    case 'inbox': return inbox.value.length
    case 'someday': return somedayItems.value.length
    case 'tickler': return ticklerItems.value.length
    case 'done': return doneItems.value.length
    case 'flagged': return flaggedItems.value.length
    default: return 0
  }
})

// Multi-select
const selectedIds = ref(new Set<string>())

function onCardClick(item: Item, e: MouseEvent) {
  if (e.ctrlKey || e.metaKey) {
    e.preventDefault()
    if (selectedIds.value.has(item.id)) {
      selectedIds.value.delete(item.id)
    } else {
      selectedIds.value.add(item.id)
    }
    // Force reactivity on Set
    selectedIds.value = new Set(selectedIds.value)
    return
  }
  // If in selection mode, toggle without Ctrl
  if (selectedIds.value.size > 0) {
    if (selectedIds.value.has(item.id)) {
      selectedIds.value.delete(item.id)
    } else {
      selectedIds.value.add(item.id)
    }
    selectedIds.value = new Set(selectedIds.value)
    return
  }
  openItem(item)
}

function bulkAction(status: Status) {
  const ids = [...selectedIds.value]
  if (ids.length === 0) return
  guardedRouter.post('/items/bulk-process', { ids, status }, {
    ...itemOnly,
    onSuccess: () => { selectedIds.value = new Set() },
  })
}

function bulkDelete() {
  const ids = [...selectedIds.value]
  if (ids.length === 0) return
  guardedRouter.post('/items/bulk-delete', { ids }, {
    ...itemOnly,
    onSuccess: () => { selectedIds.value = new Set() },
  })
}

function bulkToggleFlag() {
  const ids = [...selectedIds.value]
  if (ids.length === 0) return
  // If any selected item is unflagged, flag all; otherwise unflag all
  const anyUnflagged = items.value.filter(i => ids.includes(i.id)).some(i => !i.flagged)
  ids.forEach(id => {
    guardedRouter.put(`/items/${id}`, { flagged: anyUnflagged }, itemOnly)
  })
  selectedIds.value = new Set()
}

const confirmingClearDone = ref(false)

function clearAllDone() {
  const ids = doneItems.value.map(i => i.id)
  if (ids.length === 0) return
  guardedRouter.post('/items/bulk-delete', { ids }, {
    ...itemOnly,
    onSuccess: () => { confirmingClearDone.value = false },
  })
}

function toggleProcessFlag() {
  if (!currentProcessItem.value) return
  const newVal = !currentProcessItem.value.flagged
  guardedRouter.put(`/items/${currentProcessItem.value.id}`, { flagged: newVal }, {
    ...itemOnly,
    onSuccess: () => { if (currentProcessItem.value) currentProcessItem.value.flagged = newVal },
  })
}

function toggleFlag() {
  if (!processing.value) return
  const newVal = !processing.value.flagged
  guardedRouter.put(`/items/${processing.value.id}`, { flagged: newVal }, {
    ...itemOnly,
    onSuccess: () => { if (processing.value) processing.value.flagged = newVal },
  })
}

function openProjectGoal() {
  pickingProjectGoal.value = true
  editProjectNextAction.value = ''
  editProjectLinkExisting.value = false
  editProjectSearchItems.value = ''
  editProjectLinkedIds.value = []
}

function clarifyAsProject() {
  if (!processing.value) return
  const itemId = processing.value.id
  guardedRouter.post(`/items/${itemId}/process`, {
    status: 'project',
    title: editItem.value?.title?.trim() || undefined,
  }, {
    ...itemOnly,
    onSuccess: () => {
      const na = editProjectNextAction.value.trim()
      if (na) {
        guardedRouter.post('/items', { title: na, status: 'next-action', project_id: itemId }, itemOnly)
      }
      for (const id of editProjectLinkedIds.value) {
        guardedRouter.post(`/items/${id}/assign-project`, { project_id: itemId }, itemOnly)
      }
      processing.value = null; editItem.value = null; pickingProjectGoal.value = false
    },
  })
}

function addActionToProject() {
  if (!processing.value || !projectActionInput.value.trim()) return
  guardedRouter.post('/items', {
    title: projectActionInput.value.trim(),
    status: 'next-action',
    project_id: processing.value.id,
  }, itemOnly)
  projectActionInput.value = ''
}

function assignProjectToItem(projectId: string | null) {
  if (!processing.value) return
  guardedRouter.post(`/items/${processing.value.id}/assign-project`, { project_id: projectId }, itemOnly)
  processing.value.project_id = projectId
  pickingProject.value = false
}

function openItem(item: Item) {
  processing.value = item
  editItem.value = { title: item.title }
  pickingContext.value = false
  pickingWaiting.value = false
  pickingTickler.value = false
  pickingEvent.value = false
  pickingProject.value = false
  pickingProjectGoal.value = false
  confirmingDelete.value = false
  addingContext.value = false
  waitingFor.value = ''
  waitingDateInput.value = ''
  ticklerDate.value = undefined
  eventDate.value = undefined
  eventEndDate.value = undefined
  eventTime.value = ''
  eventEndTime.value = ''
  eventColor.value = 'blue'
  eventRecurrence.value = ''
}

// Checklist management
const newChecklistTitle = ref('')
const addingChecklist = ref(false)
const checklistInput = ref<HTMLInputElement | null>(null)

function checklistProgress(item: Item): { done: number; total: number } | null {
  const cl = item.checklist_items
  if (!cl || cl.length === 0) return null
  return { done: cl.filter(c => c.completed).length, total: cl.length }
}

function toggleChecklistItem(ci: ChecklistItemRecord) {
  guardedRouter.post(`/checklist-items/${ci.id}/toggle`, {}, itemOnly)
  ci.completed = !ci.completed
}

function addChecklistItem() {
  const title = newChecklistTitle.value.trim()
  if (!title || !processing.value) return
  const tempId = Date.now().toString()
  const maxOrder = (processing.value.checklist_items || []).reduce((m, c) => Math.max(m, c.sort_order), -1)
  if (!processing.value.checklist_items) processing.value.checklist_items = []
  processing.value.checklist_items.push({ id: tempId, item_id: processing.value.id, title, completed: false, sort_order: maxOrder + 1 })
  guardedRouter.post(`/items/${processing.value.id}/checklist`, { title }, itemOnly)
  newChecklistTitle.value = ''
  nextTick(() => checklistInput.value?.focus())
}

function removeChecklistItem(ci: ChecklistItemRecord) {
  if (!processing.value) return
  guardedRouter.delete(`/checklist-items/${ci.id}`, itemOnly)
  if (processing.value.checklist_items) {
    processing.value.checklist_items = processing.value.checklist_items.filter(c => c.id !== ci.id)
  }
}

function startAddingChecklist() {
  addingChecklist.value = true
  newChecklistTitle.value = ''
  nextTick(() => checklistInput.value?.focus())
}

// Tag management
const addingTag = ref(false)
const newTagValue = ref('')
const tagInput = ref<HTMLInputElement | null>(null)
const tagSuggestIdx = ref(0)

const allItemTags = computed(() => {
  const tagSet = new Set<string>()
  for (const item of items.value) {
    for (const t of (item.tags || [])) {
      tagSet.add(t.tag)
    }
  }
  return [...tagSet].sort()
})

const tagSuggestions = computed(() => {
  const q = newTagValue.value.trim().toLowerCase()
  if (!q) return []
  const currentTags = new Set((processing.value?.tags || []).map(t => t.tag))
  return allItemTags.value.filter(t => t.toLowerCase().includes(q) && !currentTags.has(t))
})

function startAddingTag() {
  addingTag.value = true
  newTagValue.value = ''
  tagSuggestIdx.value = 0
  nextTick(() => tagInput.value?.focus())
}

function commitTag() {
  if (tagSuggestions.value.length > 0 && tagSuggestIdx.value >= 0) {
    newTagValue.value = tagSuggestions.value[tagSuggestIdx.value]
  }
  const tag = newTagValue.value.trim().toLowerCase()
  if (!tag || !processing.value) return
  guardedRouter.post(`/items/${processing.value.id}/tags`, { tag }, itemOnly)
  // Optimistically add to local state
  if (processing.value.tags) {
    if (!processing.value.tags.some(t => t.tag === tag)) {
      processing.value.tags.push({ id: Date.now(), item_id: processing.value.id, tag })
    }
  } else {
    processing.value.tags = [{ id: Date.now(), item_id: processing.value.id, tag }]
  }
  newTagValue.value = ''
  tagSuggestIdx.value = 0
}

function removeItemTag(tag: string) {
  if (!processing.value) return
  guardedRouter.delete(`/items/${processing.value.id}/tags/${encodeURIComponent(tag)}`, itemOnly)
  if (processing.value.tags) {
    processing.value.tags = processing.value.tags.filter(t => t.tag !== tag)
  }
}

function openWaiting() {
  pickingWaiting.value = true
  waitingFor.value = processing.value?.waiting_for || ''
  waitingDateInput.value = processing.value?.waiting_date || new Date().toISOString().split('T')[0]
  nextTick(() => waitingInput.value?.focus())
}

function clarifyWaiting() {
  if (!processing.value) return
  guardedRouter.post(`/items/${processing.value.id}/process`, { status: 'waiting', title: editItem.value?.title?.trim() || undefined, waiting_for: waitingFor.value.trim() || undefined, waiting_date: waitingDateInput.value || undefined }, { ...itemOnly, onSuccess: () => { processing.value = null; editItem.value = null; pickingWaiting.value = false } })
}

function openTickler() {
  pickingTickler.value = true
  ticklerDate.value = undefined
}

function clarifyTickler() {
  if (!ticklerDate.value || !processing.value) return
  guardedRouter.post(`/items/${processing.value.id}/process`, { status: 'tickler', title: editItem.value?.title?.trim() || undefined, tickler_date: ticklerDate.value?.toString() }, { ...itemOnly, onSuccess: () => { processing.value = null; editItem.value = null; pickingTickler.value = false; ticklerDate.value = undefined } })
}

function openEvent() {
  pickingEvent.value = true
  eventDate.value = undefined
  eventEndDate.value = undefined
  eventTime.value = ''
  eventEndTime.value = ''
  eventColor.value = 'blue'
  eventRecurrence.value = ''
}

function clarifyAsEvent() {
  if (!eventDate.value || !processing.value) return
  guardedRouter.post(`/items/${processing.value.id}/schedule-event`, {
    event_date: eventDate.value.toString(),
    end_date: eventEndDate.value?.toString() || undefined,
    event_time: eventTime.value || undefined,
    end_time: eventEndTime.value || undefined,
    color: eventColor.value || 'blue',
    recurrence: eventRecurrence.value || undefined,
  }, { preserveScroll: true, only: ['items', 'events'], onSuccess: () => { processing.value = null; editItem.value = null; pickingEvent.value = false; eventDate.value = undefined } })
}

function processStartEvent() {
  processStep.value = 'event'
  processEventDate.value = undefined
  processEventEndDate.value = undefined
  processEventTime.value = ''
  processEventEndTime.value = ''
  processEventColor.value = 'blue'
  processEventRecurrence.value = ''
}

function processConfirmEvent() {
  if (!processEventDate.value) return
  const item = currentProcessItem.value
  if (!item) return
  guardedRouter.post(`/items/${item.id}/schedule-event`, {
    event_date: processEventDate.value.toString(),
    end_date: processEventEndDate.value?.toString() || undefined,
    event_time: processEventTime.value || undefined,
    end_time: processEventEndTime.value || undefined,
    color: processEventColor.value || 'blue',
    recurrence: processEventRecurrence.value || undefined,
  }, { preserveScroll: true, only: ['items', 'events'] })
  showFlashAndAdvance('Event')
}

function saveEdits() {
  if (processing.value && editItem.value) {
    guardedRouter.put(`/items/${processing.value.id}`, { title: editItem.value.title.trim() }, { ...itemOnly, onSuccess: () => { processing.value = null; editItem.value = null } })
  }
}

function deleteItem() {
  if (processing.value) {
    guardedRouter.delete(`/items/${processing.value.id}`, { ...itemOnly, onSuccess: () => { processing.value = null; editItem.value = null } })
  }
}

function remove(id: string) {
  guardedRouter.delete(`/items/${id}`, itemOnly)
}

function moveToInbox() {
  if (!processing.value) return
  guardedRouter.post(`/items/${processing.value.id}/move-to-inbox`, {}, { ...itemOnly, onSuccess: () => { processing.value = null; editItem.value = null } })
}

function clarify(status: Status, context?: string, waitingForName?: string) {
  if (!processing.value) return
  guardedRouter.post(`/items/${processing.value.id}/process`, { status, title: editItem.value?.title?.trim() || undefined, context, waiting_for: waitingForName }, { ...itemOnly, onSuccess: () => { processing.value = null; editItem.value = null; pickingContext.value = false; pickingWaiting.value = false } })
}

function clarifyWithContext(ctx: string) {
  clarify('next-action', ctx)
}

function confirmNextAction() {
  clarify('next-action', selectedContext.value || undefined)
}

// Email viewer
const emailViewerOpen = ref(false)

// Export done items
const exportModalOpen = ref(false)
const exportCopied = ref(false)

const exportMarkdown = computed(() => {
  return doneItems.value.map(item => {
    let line = `- ${item.title}`
    if (item.original_status) line += ` [${bucketLabel(item.original_status as Status)}]`
    if (item.completed_at) line += ` (done ${formatCompletedAt(item.completed_at)})`
    return line
  }).join('\n')
})

function copyExport() {
  navigator.clipboard.writeText(exportMarkdown.value)
  exportCopied.value = true
  setTimeout(() => { exportCopied.value = false }, 2000)
}

function formatCompletedAt(d: string) {
  return new Date(d).toLocaleDateString('en', { month: 'short', day: 'numeric', year: 'numeric' })
}

function bucketLabel(status: Status) {
  return buckets.find(b => b.key === status)?.label ?? status
}

function formatDate(d: string) {
  return new Date(d + 'T00:00:00').toLocaleDateString('en', { month: 'short', day: 'numeric' })
}

function isDateOverdue(d: string | null | undefined): boolean {
  if (!d) return false
  const date = new Date(d + 'T00:00:00')
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return date < today
}

function isWaitingStale(item: Item): boolean {
  // If has a waiting_date, check if it's past
  if (item.waiting_date) return isDateOverdue(item.waiting_date)
  // Otherwise, check if item hasn't been updated in 7+ days
  if (item.updated_at) {
    const updated = new Date(item.updated_at)
    const now = new Date()
    return (now.getTime() - updated.getTime()) > 7 * 24 * 60 * 60 * 1000
  }
  return false
}

function bucketIcon(key: Status) {
  const icons = themeIcons.value
  const map: Partial<Record<Status, string>> = {
    'project':   '📁',
    'someday':   '🌱',
    'tickler':   '🗓️',
    'done':      icons.done,
    'trash':     '🗑️',
  }
  return map[key] ?? ''
}

function bucketVariant(status: Status): 'default' | 'secondary' | 'outline' | 'destructive' {
  const map: Partial<Record<Status, 'default' | 'secondary' | 'outline' | 'destructive'>> = {
    'next-action': 'default',
    'project':     'secondary',
    'trash':       'destructive',
  }
  return map[status] ?? 'outline'
}
</script>

<style scoped>
.hotkey-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 13px;
  color: var(--foreground);
}
.hotkey-row span {
  color: color-mix(in oklch, var(--foreground), transparent 30%);
}
.hotkey-row kbd {
  padding: 2px 7px;
  border-radius: 5px;
  background: var(--muted);
  color: var(--muted-foreground);
  font-family: ui-monospace, monospace;
  font-size: 11px;
  font-weight: 500;
  border: 1px solid color-mix(in oklch, var(--border), transparent 40%);
  box-shadow: 0 1px 0 color-mix(in oklch, var(--border), transparent 50%);
}

/* Hide scrollbar for pill rows on mobile */
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.no-scrollbar::-webkit-scrollbar {
  display: none;
}

/* Subtle pulse for overdue review button */
.review-pulse {
  animation: review-glow 2s ease-in-out infinite;
}
@keyframes review-glow {
  0%, 100% { box-shadow: 0 0 0 0 transparent; }
  50% { box-shadow: 0 0 8px 2px oklch(0.65 0.15 250 / 40%); }
}

/* Safe area for bottom nav on notched phones */
.safe-bottom {
  padding-bottom: env(safe-area-inset-bottom, 0px);
}

@keyframes clarify-shake {
  0%, 100% { transform: translateX(0); }
  15% { transform: translateX(-2px); }
  30% { transform: translateX(2px); }
  45% { transform: translateX(-2px); }
  60% { transform: translateX(2px); }
  75% { transform: translateX(-1px); }
  90% { transform: translateX(1px); }
}
.clarify-shake {
  animation: clarify-shake 0.5s ease-in-out;
}

.bulk-btn {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  font-size: 16px;
  transition: background 100ms, transform 100ms;
}
.bulk-btn:hover {
  background: var(--accent);
  transform: scale(1.1);
}
.bulk-btn:active {
  transform: scale(0.95);
}

.bulk-toolbar-enter-active {
  transition: all 200ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
.bulk-toolbar-leave-active {
  transition: all 150ms ease-in;
}
.bulk-toolbar-enter-from {
  opacity: 0;
  transform: translate(-50%, 20px);
}
.bulk-toolbar-leave-to {
  opacity: 0;
  transform: translate(-50%, 20px);
}
</style>
