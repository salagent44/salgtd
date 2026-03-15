<template>
  <div class="bear-notes flex rounded-xl overflow-hidden" :class="[fullscreen ? 'bear-fullscreen' : '', isMobile ? 'h-[calc(100vh-8.5rem)]' : 'h-[calc(100vh-7rem)]']" data-testid="notes-view">

    <!-- ===== Notes Sidebar (desktop only) ===== -->
    <div v-if="!fullscreen && !sidebarCollapsed && !isMobile" class="bear-sidebar w-80 shrink-0 flex flex-col border-r border-border">
      <!-- Sidebar header -->
      <div class="px-3 py-2.5 flex items-center justify-between border-b border-border/50">
        <p class="text-[11px] font-semibold uppercase tracking-wider bear-muted-dim">Notes <span class="opacity-50">{{ displayedSidebarNotes.length }}</span></p>
        <div class="flex items-center gap-1">
          <button
            @click="openSearch"
            class="bear-toolbar-btn p-1.5"
            title="Search (Ctrl+F)"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          </button>
          <button
            @click="createNote"
            class="bear-toolbar-btn p-1.5"
            title="New note (N)"
            data-testid="notes-sidebar-new"
          >
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </button>
        </div>
      </div>

      <!-- Sidebar filter tabs -->
      <div class="px-3 py-2 flex items-center gap-1 border-b border-border/30">
        <button
          @click="sidebarFilter = 'all'"
          class="text-[11px] font-medium px-2 py-1 rounded-md transition-colors"
          :class="sidebarFilter === 'all' ? 'bg-primary/10 text-primary' : 'bear-muted-dim hover:text-foreground'"
        >All</button>
        <button
          v-for="tag in allTags.slice(0, 4)"
          :key="tag"
          @click="sidebarFilter = sidebarFilter === tag ? 'all' : tag"
          class="text-[11px] font-medium px-2 py-1 rounded-md transition-colors truncate max-w-[5rem]"
          :class="sidebarFilter === tag ? 'bg-primary/10 text-primary' : 'bear-muted-dim hover:text-foreground'"
        >#{{ tag }}</button>
        <button
          v-if="trashedNotes.length > 0"
          @click="sidebarFilter = sidebarFilter === 'trash' ? 'all' : 'trash'"
          class="text-[11px] font-medium px-2 py-1 rounded-md transition-colors"
          :class="sidebarFilter === 'trash' ? 'bg-primary/10 text-primary' : 'bear-muted-dim hover:text-foreground'"
        >🗑</button>
      </div>

      <!-- Sidebar note list -->
      <div class="flex-1 overflow-y-auto">
        <div v-if="displayedSidebarNotes.length === 0" class="p-4 text-center">
          <p class="text-[12px] bear-muted-dim">No notes</p>
        </div>
        <button
          v-for="note in displayedSidebarNotes"
          :key="note.id"
          @click="selectNote(note.id)"
          class="bear-sidebar-item w-full text-left"
          :class="{ 'bear-sidebar-item-active': selectedNoteId === note.id }"
        >
          <div class="flex items-center gap-1.5 mb-0.5">
            <span v-if="note.pinned" class="text-[9px]">📌</span>
            <svg v-if="note.locked" class="shrink-0 bear-muted-dim" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <p class="text-[16px] font-medium truncate" :class="selectedNoteId === note.id ? 'text-foreground' : 'text-foreground/80'">{{ noteDisplayTitle(note) }}</p>
          </div>
          <p class="text-[11px] bear-muted-dim truncate">{{ notePreview(note) || 'No content' }}</p>
          <div class="flex items-center gap-1.5 mt-1">
            <span class="text-[10px] bear-muted-dim">{{ formatDate(note.updated_at) }}</span>
            <span v-for="tag in getTagNames(note).slice(0, 2)" :key="tag" class="text-[9px] bear-muted-dim">#{{ tag }}</span>
          </div>
        </button>
        <button
          v-if="allSidebarNotes.length > sidebarRenderLimit"
          @click="showMoreNotes"
          class="w-full py-2.5 text-[11px] font-medium bear-muted-dim hover:text-foreground transition-colors"
        >Show more ({{ allSidebarNotes.length - sidebarRenderLimit }} remaining)</button>
      </div>
    </div>

    <!-- ===== Mobile Note List (shown when no note selected on mobile) ===== -->
    <div v-if="isMobile && !selectedNote && !fullscreen" class="flex-1 flex flex-col min-w-0 min-h-0 bear-editor-area bear-mobile-list-enter">
      <!-- Mobile list header -->
      <div class="px-4 pt-3 pb-2.5 flex items-center justify-between">
        <p class="text-lg font-bold text-foreground">Notes</p>
        <button
          @click="openSearch"
          class="bear-toolbar-btn p-2.5 min-w-[44px] min-h-[44px] flex items-center justify-center"
          title="Search"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
      </div>

      <!-- Mobile filter tabs -->
      <div class="px-3 pb-2 flex items-center gap-1.5 overflow-x-auto bear-hide-scrollbar">
        <button
          @click="sidebarFilter = 'all'"
          class="bear-mobile-filter-pill"
          :class="sidebarFilter === 'all' ? 'bear-mobile-filter-active' : ''"
        >All <span class="opacity-50">{{ notes.filter(n => !n.trashed).length }}</span></button>
        <button
          v-for="tag in allTags.slice(0, 5)"
          :key="tag"
          @click="sidebarFilter = sidebarFilter === tag ? 'all' : tag"
          class="bear-mobile-filter-pill"
          :class="sidebarFilter === tag ? 'bear-mobile-filter-active' : ''"
        >#{{ tag }}</button>
        <button
          v-if="trashedNotes.length > 0"
          @click="sidebarFilter = sidebarFilter === 'trash' ? 'all' : 'trash'"
          class="bear-mobile-filter-pill"
          :class="sidebarFilter === 'trash' ? 'bear-mobile-filter-active' : ''"
        >Trash</button>
      </div>

      <!-- Mobile note list -->
      <div class="flex-1 overflow-y-auto">
        <div v-if="displayedSidebarNotes.length === 0" class="flex flex-col items-center justify-center h-full px-6">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="bear-muted-dim mb-4 opacity-40"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
          <p class="text-sm bear-muted-dim">{{ sidebarFilter === 'trash' ? 'Trash is empty' : 'No notes yet' }}</p>
          <p v-if="sidebarFilter !== 'trash'" class="text-xs bear-muted-dim mt-1">Tap + to create your first note</p>
        </div>
        <button
          v-for="note in displayedSidebarNotes"
          :key="note.id"
          @click="selectNote(note.id)"
          class="bear-mobile-note-item w-full text-left"
        >
          <div class="flex items-center gap-1.5 mb-1">
            <span v-if="note.pinned" class="text-xs">📌</span>
            <svg v-if="note.locked" class="shrink-0 bear-muted-dim" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <p class="text-[15px] font-semibold truncate text-foreground">{{ noteDisplayTitle(note) }}</p>
          </div>
          <p class="text-[13px] bear-muted truncate leading-snug">{{ notePreview(note) || 'No content' }}</p>
          <div class="flex items-center gap-2 mt-1.5">
            <span class="text-[11px] bear-muted-dim">{{ formatDate(note.updated_at) }}</span>
            <span v-for="tag in getTagNames(note).slice(0, 2)" :key="tag" class="bear-mobile-note-tag">#{{ tag }}</span>
          </div>
        </button>
        <button
          v-if="allSidebarNotes.length > sidebarRenderLimit"
          @click="showMoreNotes"
          class="w-full py-3 text-xs font-medium bear-muted-dim hover:text-foreground transition-colors"
        >Show more ({{ allSidebarNotes.length - sidebarRenderLimit }} remaining)</button>
      </div>

      <!-- Mobile floating new-note button -->
      <button
        @click="createNote"
        class="bear-mobile-fab"
        title="New note"
      >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      </button>
    </div>

    <!-- ===== Editor Area ===== -->
    <div v-if="!isMobile || selectedNote || fullscreen" class="flex-1 flex flex-col min-w-0 min-h-0">

    <!-- Editor area (note selected) -->
    <div v-if="selectedNote" class="flex-1 flex flex-col min-w-0 min-h-0 bear-editor-area">

      <!-- Title bar -->
      <div class="bear-title-bar flex items-center gap-3" :class="isMobile ? 'px-3 py-2' : 'px-5 py-3'">
        <!-- Mobile back button -->
        <button
          v-if="isMobile"
          @click="mobileBackToList"
          class="shrink-0 flex items-center gap-0.5 text-primary font-semibold text-sm min-w-[44px] min-h-[44px] -ml-1 px-1"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <div class="flex-1 min-w-0 flex items-center gap-2">
          <input
            ref="titleInputEl"
            v-model="selectedNote.title"
            @input="onNoteInput"
            @keydown.enter="focusEditor"
            type="text"
            placeholder="Untitled Note"
            class="bear-title-input flex-1"
            :readonly="selectedNote.locked"
            :class="[selectedNote.locked ? 'opacity-70' : '', isMobile ? 'text-lg' : '']"
            data-testid="notes-title"
          />
          <!-- Save indicator -->
          <transition name="save-fade">
            <svg v-if="saveStatus === 'saving'" class="bear-save-spinner shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 2a10 10 0 0 1 10 10" /></svg>
            <svg v-else-if="saveStatus === 'saved' && showSavedCheck" key="check" class="bear-save-check shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          </transition>
          <span v-if="selectedNote.locked" class="bear-lock-badge" data-testid="notes-locked-badge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Locked
          </span>
        </div>
        <!-- Mobile: compact action menu -->
        <div v-if="isMobile" class="flex items-center gap-0 shrink-0">
          <button v-if="!selectedNote.trashed" @click="showMobileMenu = !showMobileMenu" class="bear-toolbar-btn min-w-[44px] min-h-[44px] flex items-center justify-center">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
          </button>
          <button v-if="selectedNote.trashed" @click="restoreNote" class="bear-toolbar-btn min-w-[44px] min-h-[44px] flex items-center justify-center text-primary text-xs font-semibold">Restore</button>
        </div>
        <!-- Desktop: full toolbar -->
        <div v-if="!isMobile" class="flex items-center gap-1 shrink-0">
          <button @click="createNote" class="bear-toolbar-btn" title="New note (N)" data-testid="notes-new-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </button>
          <button v-if="!selectedNote.trashed" @click="openVersionHistory" class="bear-toolbar-btn" title="Version history" data-testid="notes-history-btn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </button>
          <span class="w-px h-4 bg-border mx-0.5"></span>
          <button v-if="!selectedNote.trashed" @click="toggleLock" class="bear-toolbar-btn" :class="selectedNote.locked ? 'bear-accent' : ''" :title="selectedNote.locked ? 'Unlock (Ctrl+L)' : 'Lock (Ctrl+L)'" data-testid="notes-lock-btn">
            <svg v-if="selectedNote.locked" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 8.7-2.7"/></svg>
          </button>
          <button v-if="!selectedNote.trashed" @click="toggleFullscreen" class="bear-toolbar-btn" :class="fullscreen ? 'bear-accent' : ''" :title="fullscreen ? 'Exit fullscreen (Esc)' : 'Fullscreen'" data-testid="notes-fullscreen-btn">
            <svg v-if="!fullscreen" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
            <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="14" y1="10" x2="21" y2="3"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
          </button>
          <button v-if="!selectedNote.trashed" @click="togglePin" class="bear-toolbar-btn" :class="selectedNote.pinned ? 'bear-accent' : ''" title="Pin note">📌</button>
          <button v-if="selectedNote.trashed" @click="restoreNote" class="bear-toolbar-btn" title="Restore" data-testid="notes-restore-btn">↩ Restore</button>
          <button v-if="selectedNote.trashed" @click="permanentlyDeleteNote" class="bear-toolbar-btn bear-destructive" title="Delete forever">Delete Forever</button>
          <button v-if="!selectedNote.trashed" @click="trashNote" class="bear-toolbar-btn bear-muted-dim hover:bear-destructive" title="Move to trash">🗑</button>
        </div>
      </div>

      <!-- Mobile dropdown menu -->
      <div v-if="isMobile && showMobileMenu && !selectedNote?.trashed" class="bear-mobile-dropdown">
        <button @click="togglePin(); showMobileMenu = false" class="bear-mobile-dropdown-item">
          <span>{{ selectedNote?.pinned ? 'Unpin' : 'Pin' }}</span>
          <span v-if="selectedNote?.pinned" class="text-primary text-xs">📌</span>
        </button>
        <button @click="toggleLock(); showMobileMenu = false" class="bear-mobile-dropdown-item">
          <span>{{ selectedNote?.locked ? 'Unlock' : 'Lock' }}</span>
        </button>
        <button @click="switchToPreview(); showMobileMenu = false" class="bear-mobile-dropdown-item">
          <span>{{ viewMode === 'preview' ? 'Edit' : 'Preview' }}</span>
        </button>
        <button @click="openVersionHistory(); showMobileMenu = false" class="bear-mobile-dropdown-item">
          <span>History</span>
        </button>
        <button @click="createNote(); showMobileMenu = false" class="bear-mobile-dropdown-item">
          <span>New Note</span>
        </button>
        <div class="border-t border-border/30 my-1"></div>
        <button @click="trashNote(); showMobileMenu = false" class="bear-mobile-dropdown-item text-destructive">
          <span>Delete</span>
        </button>
      </div>

      <!-- Tags bar + mode toggle -->
      <div class="bear-tags-bar px-5 py-1.5 flex items-center gap-2">
        <div class="flex flex-wrap items-center gap-1.5 flex-1">
          <span
            v-for="(tag, idx) in getTagNames(selectedNote)"
            :key="tag"
            class="bear-tag-pill"
          >
            #{{ tag }}
            <button @click="removeTag(idx)" class="bear-tag-remove">&times;</button>
          </span>
          <div v-if="addingTag" class="relative inline-flex items-center">
            <input
              ref="tagInput"
              v-model="newTagName"
              @keydown.enter.prevent="commitTag"
              @keydown.esc="cancelAddingTag"
              @keydown.down.prevent="tagPickerIdx = Math.min(tagPickerIdx + 1, tagSuggestions.length - 1)"
              @keydown.up.prevent="tagPickerIdx = Math.max(tagPickerIdx - 1, 0)"
              type="text"
              placeholder="type or pick tag…"
              class="bear-tag-input"
              data-testid="notes-tag-input"
            />
            <!-- Tag picker dropdown -->
            <div v-if="tagSuggestions.length > 0" class="bear-tag-picker">
              <button
                v-for="(tag, idx) in tagSuggestions"
                :key="tag"
                @mousedown.prevent="pickTag(tag)"
                class="bear-tag-picker-item"
                :class="{ 'bear-tag-picker-active': idx === tagPickerIdx }"
              >
                <span class="bear-tag-picker-hash">#</span>{{ tag }}
                <span class="bear-tag-picker-count">{{ tagCounts[tag] || 0 }}</span>
              </button>
            </div>
          </div>
          <button v-else @click="startAddingTag" class="bear-add-tag" data-testid="notes-add-tag">+ tag</button>
        </div>
        <!-- Edit / Preview toggle -->
        <div v-if="!selectedNote.locked" class="bear-mode-toggle shrink-0" data-testid="notes-mode-toggle">
          <button
            @click="viewMode = 'edit'"
            class="bear-mode-btn"
            :class="viewMode === 'edit' ? 'bear-mode-active' : ''"
            data-testid="notes-mode-edit"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
            Edit
          </button>
          <button
            @click="switchToPreview"
            class="bear-mode-btn"
            :class="viewMode === 'preview' ? 'bear-mode-active' : ''"
            data-testid="notes-mode-preview"
          >
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Preview
          </button>
        </div>
        <span v-else class="text-[11px] bear-muted-dim italic shrink-0">Read only</span>
      </div>

      <!-- Editor (edit mode) -->
      <div v-if="viewMode === 'edit'" class="flex-1 flex flex-col min-h-0">
        <textarea
          ref="editorEl"
          v-model="selectedNote.content"
          @input="onNoteInput"
          @keydown.tab.prevent="handleTab"
          placeholder="Start writing..."
          class="bear-textarea flex-1 w-full"
          :class="{ 'pb-2': isMobile }"
          @focus="showMobileMenu = false"
          data-testid="notes-editor"
        ></textarea>

        <!-- Bear-style mobile formatting toolbar -->
        <div v-if="isMobile && !selectedNote?.locked" class="bear-format-bar">
          <div class="bear-format-bar-scroll bear-hide-scrollbar">
            <button @click="insertFormat('heading')" class="bear-format-btn" title="Heading">
              <span class="text-xs font-bold">H</span>
            </button>
            <button @click="insertFormat('bold')" class="bear-format-btn" title="Bold">
              <span class="text-xs font-extrabold">B</span>
            </button>
            <button @click="insertFormat('italic')" class="bear-format-btn" title="Italic">
              <span class="text-xs font-semibold italic">I</span>
            </button>
            <button @click="insertFormat('strikethrough')" class="bear-format-btn" title="Strikethrough">
              <span class="text-xs font-semibold line-through">S</span>
            </button>
            <span class="bear-format-divider"></span>
            <button @click="insertFormat('ul')" class="bear-format-btn" title="Bullet list">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="4" cy="18" r="1.5" fill="currentColor" stroke="none"/></svg>
            </button>
            <button @click="insertFormat('ol')" class="bear-format-btn" title="Numbered list">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="10" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="10" y1="18" x2="20" y2="18"/><text x="2" y="8" font-size="8" fill="currentColor" stroke="none" font-weight="700">1</text><text x="2" y="14" font-size="8" fill="currentColor" stroke="none" font-weight="700">2</text><text x="2" y="20" font-size="8" fill="currentColor" stroke="none" font-weight="700">3</text></svg>
            </button>
            <button @click="insertFormat('checklist')" class="bear-format-btn" title="Checklist">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><polyline points="5 6 6.5 8 10 4"/><line x1="14" y1="6" x2="21" y2="6"/><rect x="3" y="14" width="7" height="7" rx="1"/><line x1="14" y1="17" x2="21" y2="17"/></svg>
            </button>
            <span class="bear-format-divider"></span>
            <button @click="insertFormat('link')" class="bear-format-btn" title="Link">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            </button>
            <button @click="insertFormat('code')" class="bear-format-btn" title="Code">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            </button>
            <button @click="insertFormat('quote')" class="bear-format-btn" title="Quote">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V21z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3z"/></svg>
            </button>
            <button @click="insertFormat('hr')" class="bear-format-btn" title="Divider">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="12" x2="21" y2="12"/></svg>
            </button>
            <button @click="insertFormat('tag')" class="bear-format-btn" title="Tag">
              <span class="text-xs font-bold text-primary">#</span>
            </button>
          </div>
        </div>

        <!-- Word count / status bar -->
        <div class="bear-word-count px-4 py-1.5 flex items-center justify-between">
          <div>
            <span>{{ wordCount }} words</span>
            <span class="mx-2">·</span>
            <span>{{ charCount }} characters</span>
          </div>
          <div class="flex items-center gap-2">
          </div>
        </div>
      </div>

      <!-- Preview (preview mode) -->
      <div v-else class="flex-1 flex flex-col min-h-0" :class="{ 'pb-20': isMobile }">
        <div
          class="flex-1 overflow-y-auto px-6 py-4 bear-preview-content"
          v-html="renderedContent"
          @click="onPreviewClick"
          data-testid="notes-preview"
        ></div>
        <div class="bear-word-count px-4 py-1.5">
          <span>{{ wordCount }} words</span>
          <span class="mx-2">·</span>
          <span>{{ charCount }} characters</span>
        </div>
      </div>

    </div>

    <!-- Empty state (no note selected) -->
    <div v-else class="flex-1 flex items-center justify-center bear-editor-area">
      <div class="text-center">
        <div class="bear-empty-icon mb-4">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="bear-muted-dim mx-auto"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
        </div>
        <p class="text-[15px] bear-muted font-medium mb-2">No Note Selected</p>
        <p class="text-[11px] bear-muted-dim">
          <kbd class="bear-kbd">N</kbd> new note
          <span class="mx-1.5 opacity-30">·</span>
          <kbd class="bear-kbd">Ctrl F</kbd> search
          <span class="mx-1.5 opacity-30">·</span>
          <kbd class="bear-kbd">Shift B</kbd> toggle sidebar
        </p>
      </div>
    </div>

    </div><!-- end editor area wrapper -->

    <!-- ===== Search Modal ===== -->
    <div
      v-if="searchOpen"
      class="fixed inset-0 bg-black/40 flex items-start justify-center z-50"
      :class="isMobile ? 'p-0' : 'pt-[12vh] p-4'"
      @click.self="closeSearch"
    >
      <div class="notes-search-modal" :class="isMobile ? 'notes-search-modal-mobile' : ''" data-testid="notes-search-modal">
        <!-- Search input -->
        <div class="notes-search-header">
          <svg class="notes-search-input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input
            ref="searchModalInput"
            v-model="searchQuery"
            type="text"
            placeholder="Search notes and tags..."
            @keydown.esc="closeSearch"
            @keydown.enter="selectSearchResult"
            @keydown.down.prevent="searchSelectedIdx = Math.min(searchSelectedIdx + 1, searchResults.length - 1)"
            @keydown.up.prevent="searchSelectedIdx = Math.max(searchSelectedIdx - 1, 0)"
            class="notes-search-field"
            data-testid="notes-search"
          />
          <kbd class="bear-kbd text-[10px] shrink-0">esc</kbd>
        </div>

        <!-- Tag quick filters -->
        <div v-if="allTags.length > 0 && !searchQuery" class="notes-search-tags">
          <span class="text-[10px] font-semibold uppercase tracking-wider bear-muted-dim mr-2">Tags</span>
          <button
            v-for="tag in allTags"
            :key="tag"
            @click="searchQuery = '#' + tag"
            class="notes-search-tag-btn"
            data-testid="notes-tag-filter"
          >
            <span class="notes-search-tag-hash">#</span>{{ tag }}
            <span class="notes-search-tag-count">{{ tagCounts[tag] || 0 }}</span>
          </button>
          <button
            v-if="trashedNotes.length > 0"
            @click="searchQuery = ':trash'"
            class="notes-search-tag-btn"
            data-testid="notes-trash-filter"
          >🗑 Trash <span class="notes-search-tag-count">{{ trashedNotes.length }}</span></button>
        </div>

        <!-- Recent notes (no query) -->
        <div v-if="!searchQuery && recentNotes.length > 0" class="notes-search-section">
          <div class="notes-search-section-label">Recent</div>
          <button
            v-for="(note, idx) in recentNotes"
            :key="note.id"
            @click="selectNote(note.id); closeSearch()"
            class="notes-search-result"
            :class="idx === searchSelectedIdx ? 'notes-search-result-active' : ''"
            data-testid="notes-list-item"
          >
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-1.5">
                <span v-if="note.pinned" class="text-[10px]">📌</span>
                <svg v-if="note.locked" class="shrink-0 bear-muted-dim" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <p class="text-[13px] font-medium truncate">{{ noteDisplayTitle(note) }}</p>
              </div>
              <p class="text-[11px] bear-muted truncate mt-0.5">{{ notePreview(note) }}</p>
            </div>
            <div class="shrink-0 flex items-center gap-2">
              <span v-for="tag in getTagNames(note).slice(0, 2)" :key="tag" class="notes-search-result-tag">#{{ tag }}</span>
              <span class="text-[10px] bear-muted-dim">{{ formatDate(note.updated_at) }}</span>
            </div>
          </button>
        </div>

        <!-- Search results -->
        <div v-if="searchQuery && searchResults.length > 0" class="notes-search-section">
          <div class="notes-search-section-label">{{ searchResults.length }} {{ searchResults.length === 1 ? 'result' : 'results' }}</div>
          <button
            v-for="(result, idx) in searchResults"
            :key="result.note.id"
            @click="selectNote(result.note.id); closeSearch()"
            class="notes-search-result"
            :class="idx === searchSelectedIdx ? 'notes-search-result-active' : ''"
            data-testid="notes-list-item"
          >
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-1.5">
                <span v-if="result.note.pinned" class="text-[10px]">📌</span>
                <p class="text-[13px] font-medium truncate">{{ noteDisplayTitle(result.note) }}</p>
              </div>
              <p class="text-[11px] bear-muted truncate mt-0.5">{{ notePreview(result.note) }}</p>
            </div>
            <div class="shrink-0 flex items-center gap-2">
              <span v-for="tag in getTagNames(result.note).slice(0, 2)" :key="tag" class="notes-search-result-tag">#{{ tag }}</span>
              <span class="text-[10px] bear-muted-dim">{{ formatDate(result.note.updated_at) }}</span>
            </div>
          </button>
        </div>

        <!-- No results — offer to create -->
        <div v-if="searchQuery && searchResults.length === 0 && !searchQuery.startsWith('#') && searchQuery !== ':trash'" class="p-6 text-center">
          <p class="text-[13px] bear-muted">No notes matching "{{ searchQuery }}"</p>
          <button
            @click="createNoteFromSearch"
            class="mt-3 rounded-lg bg-primary px-5 py-2.5 text-[15px] font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
            data-testid="notes-create-from-search"
          >Create "{{ searchQuery }}"</button>
          <p class="text-[11px] bear-muted-dim mt-2">Press <kbd class="bear-kbd">Enter</kbd> to create</p>
        </div>
        <div v-if="searchQuery && searchResults.length === 0 && (searchQuery.startsWith('#') || searchQuery === ':trash')" class="p-8 text-center">
          <p class="text-[13px] bear-muted">No notes found</p>
          <p class="text-[11px] bear-muted-dim mt-1">Try a different search or check for typos</p>
        </div>

        <!-- Empty state (no notes at all) -->
        <div v-if="!searchQuery && recentNotes.length === 0" class="p-8 text-center">
          <p class="text-[13px] bear-muted">No notes yet</p>
          <p class="text-[11px] bear-muted-dim mt-1">Press <kbd class="bear-kbd">N</kbd> to create your first note</p>
        </div>
      </div>
    </div>

    <!-- ===== Version History Modal ===== -->
    <div
      v-if="showingVersions"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      :class="isMobile ? 'p-0' : 'p-4'"
      @click.self="closeVersionHistory"
    >
      <div class="bg-card border border-border shadow-xl overflow-hidden flex flex-col" :class="isMobile ? 'w-full h-full' : 'rounded-xl w-full max-w-3xl max-h-[80vh]'">
        <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-border">
          <div class="flex items-center gap-2">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <p class="text-sm font-semibold text-foreground">Version History</p>
            <span class="text-[11px] text-muted-foreground hidden md:inline">{{ selectedNote?.title || 'Untitled' }}</span>
          </div>
          <button @click="closeVersionHistory" class="text-muted-foreground hover:text-foreground transition-colors p-2 min-w-[44px] min-h-[44px] flex items-center justify-center">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="flex-1 min-h-0" :class="isMobile ? 'flex flex-col overflow-y-auto' : 'flex'">
          <!-- Version list -->
          <div :class="isMobile ? 'shrink-0 border-b border-border max-h-[40vh] overflow-y-auto' : 'w-56 shrink-0 border-r border-border overflow-y-auto'">
            <div v-if="noteVersions.length === 0" class="p-4 text-center">
              <p class="text-[12px] bear-muted-dim">No versions yet</p>
              <p class="text-[11px] bear-muted-dim mt-1">Versions are saved automatically every 30 seconds while editing</p>
            </div>
            <button
              v-for="(v, idx) in noteVersions"
              :key="idx"
              @click="previewVersion(v)"
              class="w-full text-left px-4 py-3 border-b border-border/40 transition-colors"
              :class="previewingVersion === v ? 'bg-primary/10' : 'hover:bg-accent'"
            >
              <p class="text-[12px] font-medium text-foreground">{{ formatVersionDate(v.created_at) }}</p>
              <p class="text-[11px] bear-muted-dim mt-0.5">{{ formatVersionTime(v.created_at) }}</p>
              <p class="text-[10px] bear-muted-dim mt-1 truncate">{{ v.content.slice(0, 60) || 'Empty' }}</p>
            </button>
          </div>
          <!-- Version preview -->
          <div class="flex-1 overflow-y-auto p-5" :class="{ 'pb-20': isMobile }">
            <div v-if="!previewingVersion" class="h-full flex items-center justify-center">
              <p class="text-[13px] bear-muted-dim">Select a version to preview</p>
            </div>
            <div v-else>
              <div class="flex items-center justify-between mb-4">
                <p class="text-[12px] text-muted-foreground">{{ formatVersionDate(previewingVersion.created_at) }} at {{ formatVersionTime(previewingVersion.created_at) }}</p>
                <button
                  @click="restoreVersion(previewingVersion)"
                  class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 transition-colors"
                >Restore this version</button>
              </div>
              <div class="rounded-lg border border-border bg-background p-4">
                <p v-if="previewingVersion.title" class="text-lg font-bold text-foreground mb-3">{{ previewingVersion.title }}</p>
                <pre class="text-sm text-foreground whitespace-pre-wrap font-mono leading-relaxed">{{ previewingVersion.content || '(empty)' }}</pre>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import { marked } from 'marked'
import DOMPurify from 'dompurify'

const page = usePage()

interface NoteTag {
  id: number
  note_id: string
  tag: string
}
interface Note {
  id: string
  title: string
  content: string
  tags: NoteTag[]
  pinned: boolean
  trashed: boolean
  locked: boolean
  created_at: string
  updated_at: string
}

function getTagNames(note: Note): string[] {
  return note.tags.map(t => t.tag)
}

const notes = computed(() => (page.props.notes || []) as Note[])
const selectedNoteId = ref<string | null>(null)
const searchQuery = ref('')
const activeTag = ref<string | null>(null)
const showTrash = ref(false)
const addingTag = ref(false)
const newTagName = ref('')
const sortMode = ref<'modified' | 'created' | 'title'>('modified')
const viewMode = ref<'edit' | 'preview'>('edit')
const fullscreen = ref(false)
const tagInput = ref<HTMLInputElement | null>(null)
const titleInputEl = ref<HTMLInputElement | null>(null)
const searchModalInput = ref<HTMLInputElement | null>(null)
const editorEl = ref<HTMLTextAreaElement | null>(null)
const searchOpen = ref(false)
const searchSelectedIdx = ref(0)
const sidebarFilter = ref<string>('all')
const sidebarCollapsed = ref(false)

// Mobile detection
const isMobile = ref(false)
const showMobileMenu = ref(false)
function checkMobile() {
  isMobile.value = window.innerWidth < 768
}

function mobileBackToList() {
  selectedNoteId.value = null
  showMobileMenu.value = false
}

// Bear-style formatting toolbar insert
function insertFormat(type: string) {
  const el = editorEl.value
  if (!el || !selectedNote.value || selectedNote.value.locked) return
  const start = el.selectionStart
  const end = el.selectionEnd
  const val = selectedNote.value.content
  const selected = val.substring(start, end)
  const before = val.substring(0, start)
  const after = val.substring(end)
  // Find start of current line
  const lineStart = before.lastIndexOf('\n') + 1
  const linePrefix = before.substring(lineStart)

  let insert = ''
  let cursorOffset = 0

  switch (type) {
    case 'heading': {
      // Cycle through #, ##, ### or add # to line start
      const fullLine = linePrefix + selected + (after.split('\n')[0] || '')
      const match = fullLine.match(/^(#{1,3})\s/)
      if (match) {
        const level = match[1].length
        if (level < 3) {
          // Add one more #
          selectedNote.value.content = before.substring(0, lineStart) + '#' + before.substring(lineStart) + selected + after
          cursorOffset = 1
        } else {
          // Remove all ###
          selectedNote.value.content = before.substring(0, lineStart) + before.substring(lineStart).replace(/^#{1,3}\s/, '') + selected + after
          cursorOffset = -(level + 1)
        }
      } else {
        selectedNote.value.content = before.substring(0, lineStart) + '# ' + before.substring(lineStart) + selected + after
        cursorOffset = 2
      }
      nextTick(() => { el.selectionStart = el.selectionEnd = start + cursorOffset })
      break
    }
    case 'bold':
      insert = selected ? `**${selected}**` : '****'
      selectedNote.value.content = before + insert + after
      nextTick(() => {
        if (selected) { el.selectionStart = start; el.selectionEnd = start + insert.length }
        else { el.selectionStart = el.selectionEnd = start + 2 }
      })
      break
    case 'italic':
      insert = selected ? `_${selected}_` : '__'
      selectedNote.value.content = before + insert + after
      nextTick(() => {
        if (selected) { el.selectionStart = start; el.selectionEnd = start + insert.length }
        else { el.selectionStart = el.selectionEnd = start + 1 }
      })
      break
    case 'strikethrough':
      insert = selected ? `~~${selected}~~` : '~~~~'
      selectedNote.value.content = before + insert + after
      nextTick(() => {
        if (selected) { el.selectionStart = start; el.selectionEnd = start + insert.length }
        else { el.selectionStart = el.selectionEnd = start + 2 }
      })
      break
    case 'ul':
      insert = linePrefix ? `\n- ` : '- '
      selectedNote.value.content = before + insert + selected + after
      nextTick(() => { el.selectionStart = el.selectionEnd = start + insert.length + selected.length })
      break
    case 'ol':
      insert = linePrefix ? `\n1. ` : '1. '
      selectedNote.value.content = before + insert + selected + after
      nextTick(() => { el.selectionStart = el.selectionEnd = start + insert.length + selected.length })
      break
    case 'checklist':
      insert = linePrefix ? `\n- [ ] ` : '- [ ] '
      selectedNote.value.content = before + insert + selected + after
      nextTick(() => { el.selectionStart = el.selectionEnd = start + insert.length + selected.length })
      break
    case 'link':
      if (selected) {
        insert = `[${selected}](url)`
        selectedNote.value.content = before + insert + after
        nextTick(() => { el.selectionStart = start + selected.length + 3; el.selectionEnd = start + selected.length + 6 })
      } else {
        insert = '[](url)'
        selectedNote.value.content = before + insert + after
        nextTick(() => { el.selectionStart = el.selectionEnd = start + 1 })
      }
      break
    case 'code':
      if (selected && selected.includes('\n')) {
        insert = `\`\`\`\n${selected}\n\`\`\``
      } else {
        insert = selected ? `\`${selected}\`` : '``'
      }
      selectedNote.value.content = before + insert + after
      nextTick(() => {
        if (!selected) { el.selectionStart = el.selectionEnd = start + 1 }
        else { el.selectionStart = start; el.selectionEnd = start + insert.length }
      })
      break
    case 'quote':
      insert = linePrefix ? `\n> ` : '> '
      selectedNote.value.content = before + insert + selected + after
      nextTick(() => { el.selectionStart = el.selectionEnd = start + insert.length + selected.length })
      break
    case 'hr':
      insert = linePrefix ? `\n\n---\n\n` : '---\n\n'
      selectedNote.value.content = before + insert + after
      nextTick(() => { el.selectionStart = el.selectionEnd = start + insert.length })
      break
    case 'tag':
      insert = '#'
      selectedNote.value.content = before + insert + after
      nextTick(() => { el.selectionStart = el.selectionEnd = start + 1 })
      break
  }
  onNoteInput()
  el.focus()
}

// Persistence + auto-save
const saveStatus = ref<'saved' | 'saving' | 'unsaved'>('saved')
const showSavedCheck = ref(false)
let saveTimeout: ReturnType<typeof setTimeout> | null = null
let savedCheckTimeout: ReturnType<typeof setTimeout> | null = null

watch(saveStatus, (status) => {
  if (savedCheckTimeout) clearTimeout(savedCheckTimeout)
  if (status === 'saved') {
    showSavedCheck.value = true
    savedCheckTimeout = setTimeout(() => { showSavedCheck.value = false }, 2000)
  } else {
    showSavedCheck.value = false
  }
})
const MAX_VERSIONS_PER_NOTE = 20


// ===== Version history (API-based) =====
interface NoteVersion {
  id: number
  note_id: string
  title: string
  content: string
  created_at: string
}

async function loadVersionsFromApi(noteId: string): Promise<NoteVersion[]> {
  try {
    const response = await fetch(`/notes/${noteId}/versions`)
    return await response.json()
  } catch { return [] }
}

function saveVersion(noteId: string) {
  fetch(`/notes/${noteId}/versions`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      'Accept': 'application/json',
    },
  }).catch(() => {})
}

function formatVersionDate(ts: string): string {
  const d = new Date(ts)
  const now = new Date()
  const diff = now.getTime() - d.getTime()
  const days = Math.floor(diff / 86400000)
  if (days === 0) return 'Today'
  if (days === 1) return 'Yesterday'
  if (days < 7) return d.toLocaleDateString('en', { weekday: 'long' })
  return d.toLocaleDateString('en', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatVersionTime(ts: string): string {
  return new Date(ts).toLocaleTimeString('en', { hour: 'numeric', minute: '2-digit' })
}

// Snapshot version every 30 seconds of active editing
let versionInterval: ReturnType<typeof setInterval> | null = null
const showingVersions = ref(false)
const noteVersions = ref<NoteVersion[]>([])
const previewingVersion = ref<NoteVersion | null>(null)

function startVersionTimer() {
  stopVersionTimer()
  versionInterval = setInterval(() => {
    if (selectedNote.value && !selectedNote.value.locked) {
      saveVersion(selectedNote.value.id)
    }
  }, 30000)
}

function stopVersionTimer() {
  if (versionInterval) { clearInterval(versionInterval); versionInterval = null }
}

async function openVersionHistory() {
  if (!selectedNote.value) return
  // Save a snapshot before viewing history
  saveVersion(selectedNote.value.id)
  noteVersions.value = await loadVersionsFromApi(selectedNote.value.id)
  showingVersions.value = true
  previewingVersion.value = null
}

function previewVersion(v: NoteVersion) {
  previewingVersion.value = v
}

function restoreVersion(v: NoteVersion) {
  if (!selectedNote.value) return
  router.post(`/notes/${selectedNote.value.id}/versions/${v.id}/restore`, {}, {
    preserveScroll: true, only: ['notes'],
    onSuccess: () => { showingVersions.value = false; previewingVersion.value = null }
  })
}

function closeVersionHistory() {
  showingVersions.value = false
  previewingVersion.value = null
}

const allTags = computed(() => {
  const tags = new Set<string>()
  for (const note of notes.value) {
    if (note.trashed) continue
    for (const t of note.tags) tags.add(t.tag)
    // Also pick up inline tags from content
    for (const tag of extractInlineTags(note.content)) tags.add(tag)
  }
  return [...tags].sort()
})

const tagCounts = computed(() => {
  const counts: Record<string, number> = {}
  for (const note of notes.value) {
    if (note.trashed) continue
    const allNoteTags = new Set([...getTagNames(note), ...extractInlineTags(note.content)])
    for (const tag of allNoteTags) {
      counts[tag] = (counts[tag] || 0) + 1
    }
  }
  return counts
})

const trashedNotes = computed(() => notes.value.filter(n => n.trashed))

const filteredNotes = computed(() => {
  let result = notes.value.filter(n => !n.trashed)
  if (activeTag.value) {
    result = result.filter(n => getTagNames(n).includes(activeTag.value!))
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    result = result.filter(n =>
      n.title.toLowerCase().includes(q) ||
      n.content.toLowerCase().includes(q) ||
      getTagNames(n).some(t => t.toLowerCase().includes(q))
    )
  }
  // Sort
  if (sortMode.value === 'modified') {
    result.sort((a, b) => new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime())
  } else if (sortMode.value === 'created') {
    result.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
  } else {
    result.sort((a, b) => noteDisplayTitle(a).localeCompare(noteDisplayTitle(b)))
  }
  // Pinned first
  const pinned = result.filter(n => n.pinned)
  const unpinned = result.filter(n => !n.pinned)
  return [...pinned, ...unpinned]
})

const displayedNotes = computed(() => {
  if (showTrash.value) return trashedNotes.value
  return filteredNotes.value
})

const SIDEBAR_BATCH = 50
const sidebarRenderLimit = ref(SIDEBAR_BATCH)

const allSidebarNotes = computed(() => {
  if (sidebarFilter.value === 'trash') return trashedNotes.value
  let result = notes.value.filter(n => !n.trashed)
  if (sidebarFilter.value !== 'all') {
    result = result.filter(n =>
      getTagNames(n).includes(sidebarFilter.value) ||
      extractInlineTags(n.content).includes(sidebarFilter.value)
    )
  }
  // Sort: pinned first, then by updated
  result.sort((a, b) => new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime())
  const pinned = result.filter(n => n.pinned)
  const unpinned = result.filter(n => !n.pinned)
  return [...pinned, ...unpinned]
})

const displayedSidebarNotes = computed(() => allSidebarNotes.value.slice(0, sidebarRenderLimit.value))

function showMoreNotes() {
  sidebarRenderLimit.value += SIDEBAR_BATCH
}

watch(sidebarFilter, () => { sidebarRenderLimit.value = SIDEBAR_BATCH })

const selectedNote = computed(() => {
  if (!selectedNoteId.value) return null
  return notes.value.find(n => n.id === selectedNoteId.value) ?? null
})

const renderedContent = ref('')

const copyIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>'
const checkIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>'

function addCopyButtons(html: string): string {
  return html.replace(/<pre>/g, `<pre><button class="copy-btn" data-copy-code title="Copy">${copyIcon}</button>`)
}

function renderInlineTags(html: string): string {
  // Don't touch tags inside <pre> or <code> blocks
  // Process in segments: split by <pre>...</pre> and <code>...</code>
  const parts = html.split(/(<pre[\s\S]*?<\/pre>|<code[\s\S]*?<\/code>)/g)
  return parts.map((part, i) => {
    // Odd indices are code/pre blocks — leave them alone
    if (i % 2 === 1) return part
    // Multi-word: #multi word#
    part = part.replace(/#([^#<\n]{2,})#/g, '<span class="bear-inline-tag" data-tag="$1">#$1#</span>')
    // Single-word: #word (not ##heading, not pure numbers)
    part = part.replace(/(^|[\s(>])#([a-zA-Z][a-zA-Z0-9_/\-]*)(?=[\s,.)!?:;<]|$)/gm,
      '$1<span class="bear-inline-tag" data-tag="$2">#$2</span>')
    return part
  }).join('')
}

function switchToPreview() {
  if (selectedNote.value) {
    const raw = marked(selectedNote.value.content, { async: false }) as string
    renderedContent.value = renderInlineTags(addCopyButtons(DOMPurify.sanitize(raw)))
  }
  viewMode.value = 'preview'
}

function onPreviewClick(e: MouseEvent) {
  // Handle inline tag clicks — filter sidebar by tag
  const tagEl = (e.target as HTMLElement).closest('[data-tag]') as HTMLElement | null
  if (tagEl) {
    const tag = tagEl.getAttribute('data-tag')
    if (tag) {
      sidebarFilter.value = sidebarFilter.value === tag ? 'all' : tag
      sidebarCollapsed.value = false
    }
    return
  }
  const btn = (e.target as HTMLElement).closest('[data-copy-code]') as HTMLElement | null
  if (!btn) return
  const pre = btn.closest('pre')
  if (!pre) return
  const code = pre.querySelector('code')
  const text = code ? code.textContent || '' : pre.textContent || ''
  // Strip the button text from copied content
  navigator.clipboard.writeText(text.trim()).then(() => {
    btn.innerHTML = checkIcon
    btn.classList.add('copied')
    setTimeout(() => {
      btn.innerHTML = copyIcon
      btn.classList.remove('copied')
    }, 1500)
  })
}

const wordCount = computed(() => {
  if (!selectedNote.value) return 0
  const text = selectedNote.value.content.trim()
  if (!text) return 0
  return text.split(/\s+/).length
})

const charCount = computed(() => {
  return selectedNote.value?.content.length ?? 0
})

function noteDisplayTitle(note: Note): string {
  if (note.title) return note.title
  const firstLine = note.content.split('\n')[0].replace(/^#+\s*/, '').trim()
  return firstLine || 'Untitled'
}

function notePreview(note: Note): string {
  const lines = note.content.split('\n')
  const second = lines.slice(1).find(l => l.trim())
  return second?.trim().replace(/^#+\s*/, '').slice(0, 80) || ''
}

function formatDate(d: string): string {
  const date = new Date(d)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  const days = Math.floor(diff / 86400000)
  if (days === 0) {
    return date.toLocaleTimeString('en', { hour: 'numeric', minute: '2-digit' })
  } else if (days === 1) {
    return 'Yesterday'
  } else if (days < 7) {
    return date.toLocaleDateString('en', { weekday: 'long' })
  }
  return date.toLocaleDateString('en', { month: 'short', day: 'numeric' })
}

// ===== Levenshtein fuzzy search =====
function levenshtein(a: string, b: string): number {
  const m = a.length, n = b.length
  const dp = Array.from({ length: m + 1 }, (_, i) => {
    const row = new Array(n + 1).fill(0); row[0] = i; return row
  })
  for (let j = 0; j <= n; j++) dp[0][j] = j
  for (let i = 1; i <= m; i++) {
    for (let j = 1; j <= n; j++) {
      dp[i][j] = a[i-1] === b[j-1] ? dp[i-1][j-1] : 1 + Math.min(dp[i-1][j], dp[i][j-1], dp[i-1][j-1])
    }
  }
  return dp[m][n]
}

function fuzzyScore(query: string, text: string): number {
  const q = query.toLowerCase(), t = text.toLowerCase()
  if (t.includes(q)) return 0
  const words = t.split(/\s+/)
  let bestWord = Infinity
  for (const w of words) bestWord = Math.min(bestWord, levenshtein(q, w))
  return bestWord
}

const searchResults = computed(() => {
  const q = searchQuery.value.trim()
  if (!q) return []

  // Special filter: ":trash"
  if (q === ':trash') {
    return trashedNotes.value.map(note => ({ note, score: 0 }))
  }

  // Tag filter: "#tagname"
  if (q.startsWith('#')) {
    const tagQ = q.slice(1).toLowerCase()
    return notes.value
      .filter(n => !n.trashed && getTagNames(n).some(t => t.toLowerCase().includes(tagQ)))
      .sort((a, b) => new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime())
      .map(note => ({ note, score: 0 }))
  }

  // Fuzzy search across title, content, tags
  // Tight threshold: prefer exact/close matches so "no results → create" is fast
  const threshold = Math.max(1, Math.floor(q.length * 0.3))
  const scored: { note: Note; score: number }[] = []
  for (const note of notes.value) {
    if (note.trashed) continue
    const titleScore = fuzzyScore(q, note.title || 'Untitled')
    const contentScore = fuzzyScore(q, note.content.slice(0, 300))
    const noteTagNames = getTagNames(note)
    const tagScore = noteTagNames.length > 0
      ? Math.min(...noteTagNames.map(t => fuzzyScore(q, t)))
      : Infinity
    const best = Math.min(titleScore, contentScore, tagScore)
    if (best <= threshold) {
      scored.push({ note, score: best })
    }
  }
  scored.sort((a, b) => a.score - b.score)
  return scored.slice(0, 10)
})

const recentNotes = computed(() => {
  return notes.value
    .filter(n => !n.trashed)
    .sort((a, b) => new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime())
    .slice(0, 8)
})

function openSearch() {
  searchOpen.value = true
  searchQuery.value = ''
  searchSelectedIdx.value = 0
  nextTick(() => searchModalInput.value?.focus())
}

function closeSearch() {
  searchOpen.value = false
  searchQuery.value = ''
}

function selectSearchResult() {
  const q = searchQuery.value.trim()
  if (q) {
    const results = searchResults.value
    if (results.length > 0 && searchSelectedIdx.value < results.length) {
      selectNote(results[searchSelectedIdx.value].note.id)
      closeSearch()
    } else if (results.length === 0 && !q.startsWith('#') && q !== ':trash') {
      createNoteFromSearch()
    }
  } else {
    const recent = recentNotes.value
    if (recent.length > 0 && searchSelectedIdx.value < recent.length) {
      selectNote(recent[searchSelectedIdx.value].id)
      closeSearch()
    }
  }
}

function createNoteFromSearch() {
  const title = searchQuery.value.trim()
  if (!title) return
  router.post('/notes', { title }, { preserveScroll: true, only: ['notes'], onSuccess: (page) => {
    const newNotes = (page as any).props.notes as Note[]
    if (newNotes.length > 0) selectedNoteId.value = newNotes[0].id
    closeSearch()
    viewMode.value = 'edit'
    nextTick(() => editorEl.value?.focus())
  }})
}

watch(searchQuery, () => { searchSelectedIdx.value = 0 })

function createNote() {
  showTrash.value = false
  router.post('/notes', {}, { preserveScroll: true, only: ['notes'], onSuccess: (page) => {
    const newNotes = (page as any).props.notes as Note[]
    if (newNotes.length > 0) selectedNoteId.value = newNotes[0].id
    nextTick(() => titleInputEl.value?.focus())
  }})
}

function selectNote(id: string) {
  selectedNoteId.value = id
  viewMode.value = 'edit'
  showMobileMenu.value = false
}

function extractInlineTags(content: string): string[] {
  const tags = new Set<string>()
  // Multi-word tags: #multi word# (Bear style)
  const multiWordRe = /#([^#\n]+)#/g
  let m
  while ((m = multiWordRe.exec(content)) !== null) {
    const tag = m[1].trim()
    if (tag) tags.add(tag)
  }
  // Single-word tags: #word (not inside code fences or after http links)
  // Match #word but not ##heading or #123 (pure numbers) or URLs
  const singleWordRe = /(?:^|[\s(])#([a-zA-Z][a-zA-Z0-9_/\-]*)/gm
  while ((m = singleWordRe.exec(content)) !== null) {
    tags.add(m[1])
  }
  return [...tags]
}

function debouncedSaveNote(note: Note) {
  saveStatus.value = 'unsaved'
  if (saveTimeout) clearTimeout(saveTimeout)
  saveTimeout = setTimeout(() => {
    saveStatus.value = 'saving'
    fetch(`/notes/${note.id}`, {
      method: 'PUT',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ title: note.title, content: note.content }),
    })
    .then(res => {
      if (res.ok) {
        saveStatus.value = 'saved'
        note.updated_at = new Date().toISOString()
      } else {
        saveStatus.value = 'unsaved'
      }
    })
    .catch(() => { saveStatus.value = 'unsaved' })
  }, 2000)
}

function onNoteInput() {
  if (selectedNote.value) {
    debouncedSaveNote(selectedNote.value)
  }
}

function trashNote() {
  if (!selectedNote.value) return
  const noteId = selectedNote.value.id
  router.put(`/notes/${noteId}/trash`, {}, { preserveScroll: true, only: ['notes'], onSuccess: () => {
    selectedNoteId.value = null
  }})
}

function restoreNote() {
  if (!selectedNote.value) return
  router.put(`/notes/${selectedNote.value.id}/restore`, {}, { preserveScroll: true, only: ['notes'], onSuccess: () => {
    selectedNoteId.value = null
  }})
}

function permanentlyDeleteNote() {
  if (!selectedNoteId.value) return
  router.delete(`/notes/${selectedNoteId.value}`, { preserveScroll: true, only: ['notes'], onSuccess: () => {
    selectedNoteId.value = null
  }})
}

function togglePin() {
  if (!selectedNote.value) return
  router.put(`/notes/${selectedNote.value.id}/toggle-pin`, {}, { preserveScroll: true, only: ['notes'] })
}

function toggleLock() {
  if (!selectedNote.value) return
  router.put(`/notes/${selectedNote.value.id}/toggle-lock`, {}, { preserveScroll: true, only: ['notes'] })
  if (!selectedNote.value.locked && viewMode.value === 'edit') {
    switchToPreview()
  }
}

function toggleFullscreen() {
  fullscreen.value = !fullscreen.value
  if (fullscreen.value && viewMode.value === 'edit') {
    nextTick(() => editorEl.value?.focus())
  }
}

const tagPickerIdx = ref(0)

const tagSuggestions = computed(() => {
  const q = newTagName.value.trim().replace(/^#/, '').toLowerCase()
  const currentTagNames = selectedNote.value ? getTagNames(selectedNote.value) : []
  return allTags.value.filter(t =>
    !currentTagNames.includes(t) &&
    (q === '' || t.toLowerCase().includes(q))
  ).slice(0, 8)
})

watch(newTagName, () => { tagPickerIdx.value = 0 })

function startAddingTag() {
  addingTag.value = true
  newTagName.value = ''
  tagPickerIdx.value = 0
  nextTick(() => tagInput.value?.focus())
}

function cancelAddingTag() {
  addingTag.value = false
  newTagName.value = ''
}

function pickTag(tag: string) {
  if (selectedNote.value) {
    router.post(`/notes/${selectedNote.value.id}/tags`, { tag }, { preserveScroll: true, only: ['notes'] })
  }
  addingTag.value = false
  newTagName.value = ''
}

function commitTag() {
  // If a suggestion is highlighted, pick it
  if (tagSuggestions.value.length > 0 && tagPickerIdx.value < tagSuggestions.value.length) {
    pickTag(tagSuggestions.value[tagPickerIdx.value])
    return
  }
  // Otherwise create a new tag from typed text
  const name = newTagName.value.trim().replace(/^#/, '')
  if (name && selectedNote.value) {
    pickTag(name)
    return
  }
  addingTag.value = false
  newTagName.value = ''
}

function removeTag(idx: number) {
  if (selectedNote.value) {
    const tagName = getTagNames(selectedNote.value)[idx]
    if (tagName) {
      router.delete(`/notes/${selectedNote.value.id}/tags/${encodeURIComponent(tagName)}`, { preserveScroll: true, only: ['notes'] })
    }
  }
}

function cycleSortMode() {
  const modes: typeof sortMode.value[] = ['modified', 'created', 'title']
  const idx = modes.indexOf(sortMode.value)
  sortMode.value = modes[(idx + 1) % modes.length]
}

function focusEditor() {
  nextTick(() => editorEl.value?.focus())
}

function handleTab(e: Event) {
  // Insert 2 spaces for tab in editor
  const el = editorEl.value
  if (!el || !selectedNote.value) return
  const start = el.selectionStart
  const end = el.selectionEnd
  const val = selectedNote.value.content
  selectedNote.value.content = val.substring(0, start) + '  ' + val.substring(end)
  nextTick(() => {
    el.selectionStart = el.selectionEnd = start + 2
  })
  onNoteInput()
}

function onKeydown(e: KeyboardEvent) {
  // Ctrl/Cmd+E toggles edit/preview from anywhere (even inside textarea)
  if ((e.ctrlKey || e.metaKey) && e.key === 'e' && selectedNote.value) {
    e.preventDefault()
    if (selectedNote.value.locked) return
    if (viewMode.value === 'edit') switchToPreview()
    else { viewMode.value = 'edit'; nextTick(() => editorEl.value?.focus()) }
    return
  }
  // Ctrl/Cmd+L toggles lock
  if ((e.ctrlKey || e.metaKey) && e.key === 'l' && selectedNote.value) {
    e.preventDefault()
    toggleLock()
    return
  }
  // Escape closes any open modal / state
  if (e.key === 'Escape') {
    if (showingVersions.value) { e.preventDefault(); showingVersions.value = false; previewingVersion.value = null; return }
    if (searchOpen.value) { e.preventDefault(); closeSearch(); return }
    if (addingTag.value) { e.preventDefault(); cancelAddingTag(); return }
    if (fullscreen.value) { e.preventDefault(); fullscreen.value = false; return }
    return
  }
  // Shift+B toggles sidebar collapse
  if (e.shiftKey && e.key === 'B') {
    e.preventDefault()
    sidebarCollapsed.value = !sidebarCollapsed.value
    return
  }
  // Ctrl/Cmd+Shift+F toggles fullscreen
  if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'F' && selectedNote.value) {
    e.preventDefault()
    toggleFullscreen()
    return
  }
  // Ctrl/Cmd+F opens search from anywhere
  if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
    e.preventDefault()
    openSearch()
    return
  }
  const tag = (e.target as HTMLElement).tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA') return
  if (e.key === 'n') {
    e.preventDefault()
    createNote()
  }
  if (e.key === 'e' && selectedNote.value) {
    e.preventDefault()
    nextTick(() => titleInputEl.value?.focus())
  }
  if (e.key === 't' && selectedNote.value) {
    e.preventDefault()
    startAddingTag()
  }
  if (e.key === 'Backspace' && selectedNote.value) {
    e.preventDefault()
    if (selectedNote.value.trashed) permanentlyDeleteNote()
    else trashNote()
  }
  if (e.key === 'p' && selectedNote.value) {
    e.preventDefault()
    togglePin()
  }
}

function onNotesOpenSearch() { openSearch() }

// Save version when switching away from a note
watch(selectedNoteId, (newId, oldId) => {
  if (oldId) {
    saveVersion(oldId)
  }
  startVersionTimer()
})

onMounted(() => {
  document.addEventListener('keydown', onKeydown)
  window.addEventListener('notes-open-search', onNotesOpenSearch)
  window.addEventListener('resize', checkMobile)
  checkMobile()
  startVersionTimer()
})
onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown)
  window.removeEventListener('notes-open-search', onNotesOpenSearch)
  window.removeEventListener('resize', checkMobile)
  stopVersionTimer()
})

</script>

<style scoped>
/* ===== Bear-inspired styling ===== */
.bear-notes {
  background: var(--background);
  border: 1px solid var(--border);
}

/* Sidebar */
.bear-sidebar {
  background: color-mix(in oklch, var(--card), var(--background) 60%);
}

.bear-sidebar-item {
  display: block;
  padding: 8px 12px;
  border-bottom: 1px solid color-mix(in oklch, var(--border), transparent 60%);
  transition: background 100ms;
}
.bear-sidebar-item:hover {
  background: color-mix(in oklch, var(--accent), transparent 40%);
}
.bear-sidebar-item-active {
  background: color-mix(in oklch, var(--primary), transparent 88%);
  border-left: 3px solid var(--primary);
}
.bear-sidebar-item-active:hover {
  background: color-mix(in oklch, var(--primary), transparent 84%);
  font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', sans-serif;
}

/* ===== Search Modal ===== */
.notes-search-modal {
  width: 100%;
  max-width: 560px;
  max-height: 70vh;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 14px;
  box-shadow:
    0 20px 60px rgba(0,0,0,0.15),
    0 4px 16px rgba(0,0,0,0.08);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  animation: searchModalIn 150ms ease-out;
}
@keyframes searchModalIn {
  from { opacity: 0; transform: translateY(-8px) scale(0.98); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}

/* Mobile search modal - full screen */
.notes-search-modal-mobile {
  max-width: 100%;
  max-height: 100%;
  height: 100%;
  border-radius: 0;
  border: none;
  animation: none;
}
.notes-search-modal-mobile .notes-search-header {
  padding: 16px;
}
.notes-search-modal-mobile .notes-search-field {
  font-size: 17px;
}
.notes-search-modal-mobile .notes-search-result {
  padding: 14px 16px;
}
.notes-search-modal-mobile .notes-search-tag-btn {
  padding: 6px 14px;
  font-size: 14px;
}

.notes-search-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
}
.notes-search-input-icon {
  color: color-mix(in oklch, var(--foreground), transparent 55%);
  flex-shrink: 0;
}
.notes-search-field {
  flex: 1;
  background: transparent;
  border: none;
  outline: none;
  font-size: 15px;
  color: var(--foreground);
}
.notes-search-field::placeholder {
  color: color-mix(in oklch, var(--foreground), transparent 60%);
}

.notes-search-tags {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border-bottom: 1px solid color-mix(in oklch, var(--border), transparent 50%);
}
.notes-search-tag-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 10px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 500;
  background: color-mix(in oklch, var(--primary), transparent 88%);
  color: color-mix(in oklch, var(--foreground), transparent 15%);
  transition: all 150ms;
}
.notes-search-tag-btn:hover {
  background: color-mix(in oklch, var(--primary), transparent 78%);
  color: var(--primary);
}
.notes-search-tag-hash {
  color: var(--primary);
  font-weight: 600;
}
.notes-search-tag-count {
  font-size: 10px;
  opacity: 0.5;
  margin-left: 2px;
}

.notes-search-section {
  overflow-y: auto;
  flex: 1;
}
.notes-search-section-label {
  padding: 8px 16px 4px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: color-mix(in oklch, var(--foreground), transparent 55%);
}

.notes-search-result {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  text-align: left;
  padding: 10px 16px;
  transition: background 100ms;
  cursor: pointer;
}
.notes-search-result:hover,
.notes-search-result-active {
  background: color-mix(in oklch, var(--primary), transparent 90%);
}

.notes-search-result-tag {
  font-size: 10px;
  font-weight: 500;
  padding: 1px 6px;
  border-radius: 6px;
  background: color-mix(in oklch, var(--primary), transparent 88%);
  color: var(--primary);
}

/* Text colors */
.bear-text { color: var(--foreground); }
.bear-muted { color: color-mix(in oklch, var(--foreground), transparent 40%); }
.bear-muted-dim { color: color-mix(in oklch, var(--foreground), transparent 60%); }
.bear-accent { color: var(--primary); }
.bear-destructive { color: var(--destructive); }

.bear-tag-inline {
  color: var(--primary);
  font-weight: 500;
}

/* Bear-style inline tags in preview */
.bear-preview-content :deep(.bear-inline-tag) {
  color: var(--primary);
  font-weight: 500;
  cursor: pointer;
  transition: color 150ms, opacity 150ms;
  border-radius: 2px;
}
.bear-preview-content :deep(.bear-inline-tag:hover) {
  opacity: 0.75;
  text-decoration: underline;
  text-decoration-color: color-mix(in oklch, var(--primary), transparent 50%);
  text-underline-offset: 2px;
}

/* Editor area */
.bear-editor-area {
  background: var(--background);
}

.bear-title-bar {
  border-bottom: none;
}

.bear-title-input {
  background: transparent;
  border: none;
  outline: none;
  font-size: 20px;
  font-weight: 700;
  color: var(--foreground);
  letter-spacing: -0.01em;
  font-family: var(--note-font);
}
.bear-title-input::placeholder {
  color: color-mix(in oklch, var(--foreground), transparent 70%);
}

/* Save indicator */
.bear-save-spinner {
  color: var(--primary);
  animation: spin 0.8s linear infinite;
}
.bear-save-check {
  color: color-mix(in oklch, var(--foreground), transparent 60%);
}
@keyframes spin {
  to { transform: rotate(360deg); }
}

.save-fade-enter-active { transition: opacity 0.15s ease; }
.save-fade-leave-active { transition: opacity 0.4s ease; }
.save-fade-enter-from,
.save-fade-leave-to { opacity: 0; }

.bear-toolbar-btn {
  padding: 5px 10px;
  border-radius: 7px;
  font-size: 13px;
  transition: all 150ms;
  color: color-mix(in oklch, var(--foreground), transparent 40%);
}
.bear-toolbar-btn:hover {
  background: color-mix(in oklch, var(--foreground), transparent 90%);
  color: var(--foreground);
}

/* Tags bar */
.bear-tags-bar {
  border-bottom: 1px solid color-mix(in oklch, var(--border), transparent 50%);
}

.bear-tag-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 4px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: -0.01em;
  background: color-mix(in oklch, var(--primary), transparent 82%);
  color: var(--primary);
  transition: background 150ms;
}
.bear-tag-pill:hover {
  background: color-mix(in oklch, var(--primary), transparent 72%);
}
.bear-tag-remove {
  opacity: 0.4;
  transition: opacity 150ms;
  font-size: 15px;
  line-height: 1;
  margin-left: -1px;
}
.bear-tag-remove:hover {
  opacity: 1;
}

.bear-tag-input {
  width: 130px;
  background: transparent;
  border: none;
  border-bottom: 2px solid var(--primary);
  color: var(--primary);
  font-size: 13px;
  font-weight: 500;
  outline: none;
  padding: 4px 2px;
}
.bear-tag-input::placeholder {
  color: color-mix(in oklch, var(--foreground), transparent 55%);
  font-weight: 400;
}

/* Tag picker dropdown */
.bear-tag-picker {
  position: absolute;
  top: 100%;
  left: 0;
  margin-top: 4px;
  min-width: 180px;
  max-height: 220px;
  overflow-y: auto;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: 0 8px 24px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06);
  z-index: 30;
  padding: 4px;
  animation: tagPickerIn 100ms ease-out;
}
@keyframes tagPickerIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.bear-tag-picker-item {
  display: flex;
  align-items: center;
  gap: 3px;
  width: 100%;
  text-align: left;
  padding: 6px 10px;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 500;
  color: var(--foreground);
  transition: background 80ms;
  cursor: pointer;
}
.bear-tag-picker-item:hover,
.bear-tag-picker-active {
  background: color-mix(in oklch, var(--primary), transparent 88%);
}
.bear-tag-picker-hash {
  color: var(--primary);
  font-weight: 700;
}
.bear-tag-picker-count {
  margin-left: auto;
  font-size: 11px;
  opacity: 0.4;
}

.bear-add-tag {
  font-size: 13px;
  font-weight: 500;
  padding: 3px 8px;
  border-radius: 6px;
  color: color-mix(in oklch, var(--foreground), transparent 45%);
  transition: all 150ms;
}
.bear-add-tag:hover {
  color: var(--primary);
  background: color-mix(in oklch, var(--primary), transparent 90%);
}

/* Mode toggle (edit / preview) */
.bear-mode-toggle {
  display: flex;
  gap: 2px;
  padding: 2px;
  border-radius: 8px;
  background: color-mix(in oklch, var(--foreground), transparent 92%);
}
.bear-mode-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 5px 14px;
  border-radius: 7px;
  font-size: 13px;
  font-weight: 500;
  color: color-mix(in oklch, var(--foreground), transparent 50%);
  transition: all 150ms;
}
.bear-mode-btn:hover {
  color: color-mix(in oklch, var(--foreground), transparent 20%);
}
.bear-mode-active {
  background: color-mix(in oklch, var(--foreground), transparent 85%);
  color: var(--foreground);
  box-shadow: 0 1px 2px rgba(0,0,0,0.15);
}

/* Editor + preview panes */
.bear-editor-pane {
  border-right: 1px solid color-mix(in oklch, var(--border), transparent 50%);
}

.bear-pane-header {
  border-bottom: 1px solid color-mix(in oklch, var(--border), transparent 60%);
}


.bear-textarea {
  resize: none;
  background: transparent;
  padding: 16px 20px;
  font-family: var(--note-font);
  font-size: 15px;
  line-height: 1.7;
  color: var(--foreground);
  outline: none;
  tab-size: 2;
  overflow-y: auto;
}
.bear-textarea::placeholder {
  color: color-mix(in oklch, var(--foreground), transparent 70%);
}

.bear-word-count {
  border-top: 1px solid color-mix(in oklch, var(--border), transparent 60%);
  font-size: 11px;
  color: color-mix(in oklch, var(--foreground), transparent 60%);
}

.bear-preview-pane {
  background: color-mix(in oklch, var(--background), black 3%);
}

/* Bear-styled markdown preview */
.bear-preview-content {
  color: var(--foreground);
  font-size: 15px;
  line-height: 1.7;
  font-family: var(--note-font);
}
.bear-preview-content :deep(h1) {
  font-size: 1.6em;
  font-weight: 700;
  margin: 0.8em 0 0.3em;
  color: var(--foreground);
  letter-spacing: -0.02em;
}
.bear-preview-content :deep(h2) {
  font-size: 1.3em;
  font-weight: 700;
  margin: 0.7em 0 0.25em;
  color: var(--foreground);
}
.bear-preview-content :deep(h3) {
  font-size: 1.1em;
  font-weight: 600;
  margin: 0.6em 0 0.2em;
  color: var(--foreground);
}
.bear-preview-content :deep(p) {
  margin: 0.5em 0;
}
.bear-preview-content :deep(ul),
.bear-preview-content :deep(ol) {
  padding-left: 1.5em;
  margin: 0.5em 0;
}
.bear-preview-content :deep(ul) { list-style: disc; }
.bear-preview-content :deep(ol) { list-style: decimal; }
.bear-preview-content :deep(li) { margin: 0.2em 0; }
.bear-preview-content :deep(strong) { font-weight: 700; }
.bear-preview-content :deep(em) { font-style: italic; }
.bear-preview-content :deep(a) {
  color: var(--primary);
  text-decoration: underline;
  text-decoration-color: color-mix(in oklch, var(--primary), transparent 60%);
  text-underline-offset: 2px;
}
.bear-preview-content :deep(a:hover) {
  text-decoration-color: var(--primary);
}
.bear-preview-content :deep(code) {
  background: color-mix(in oklch, var(--foreground), transparent 88%);
  padding: 0.15em 0.4em;
  border-radius: 4px;
  font-size: 0.88em;
  font-family: 'SF Mono', 'Fira Code', monospace;
}
.bear-preview-content :deep(pre) {
  position: relative;
  background: color-mix(in oklch, var(--muted), var(--background) 50%);
  padding: 14px 18px;
  padding-right: 48px;
  border-radius: 8px;
  overflow-x: auto;
  margin: 0.6em 0;
  border: 1px solid color-mix(in oklch, var(--border), transparent 30%);
}
.bear-preview-content :deep(pre code) {
  background: none;
  padding: 0;
  font-size: 13px;
  line-height: 1.5;
}
.bear-preview-content :deep(pre .copy-btn) {
  position: absolute;
  top: 6px;
  right: 6px;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  background: color-mix(in oklch, var(--foreground), transparent 88%);
  color: color-mix(in oklch, var(--foreground), transparent 50%);
  border: none;
  cursor: pointer;
  opacity: 0;
  transition: opacity 150ms, background 150ms;
}
.bear-preview-content :deep(pre:hover .copy-btn) {
  opacity: 1;
}
.bear-preview-content :deep(pre .copy-btn:hover) {
  background: color-mix(in oklch, var(--foreground), transparent 80%);
  color: var(--foreground);
}
.bear-preview-content :deep(pre .copy-btn.copied) {
  opacity: 1;
  color: var(--primary);
}
.bear-preview-content :deep(blockquote) {
  border-left: 3px solid var(--primary);
  padding-left: 14px;
  margin: 0.6em 0;
  color: color-mix(in oklch, var(--foreground), transparent 20%);
  font-style: italic;
}
.bear-preview-content :deep(hr) {
  border: none;
  border-top: 1px solid var(--border);
  margin: 1.5em 0;
}
.bear-preview-content :deep(img) {
  max-width: 100%;
  border-radius: 8px;
}

/* Empty state */
.bear-empty-icon {
  opacity: 0.3;
}

.bear-kbd {
  display: inline-block;
  padding: 2px 7px;
  border-radius: 5px;
  font-size: 11px;
  font-family: 'SF Mono', monospace;
  background: color-mix(in oklch, var(--foreground), transparent 90%);
  color: color-mix(in oklch, var(--foreground), transparent 30%);
  border: 1px solid color-mix(in oklch, var(--border), transparent 20%);
}

/* Fullscreen mode */
.bear-fullscreen {
  position: fixed !important;
  inset: 0 !important;
  z-index: 50 !important;
  height: 100vh !important;
  border-radius: 0 !important;
  border: none !important;
}

/* Lock badge */
.bear-lock-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 8px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 500;
  background: color-mix(in oklch, var(--primary), transparent 85%);
  color: var(--primary);
  white-space: nowrap;
}

/* ===== Mobile-specific styles ===== */

/* Hide scrollbar for filter pills */
.bear-hide-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
.bear-hide-scrollbar::-webkit-scrollbar {
  display: none;
}

/* Mobile filter pills */
.bear-mobile-filter-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 14px;
  border-radius: 20px;
  font-size: 13px;
  font-weight: 500;
  white-space: nowrap;
  color: color-mix(in oklch, var(--foreground), transparent 40%);
  background: color-mix(in oklch, var(--foreground), transparent 93%);
  transition: all 150ms;
}
.bear-mobile-filter-active {
  background: color-mix(in oklch, var(--primary), transparent 82%);
  color: var(--primary);
  font-weight: 600;
}

/* Mobile note list items */
.bear-mobile-note-item {
  display: block;
  padding: 14px 16px;
  border-bottom: 1px solid color-mix(in oklch, var(--border), transparent 50%);
  transition: background 100ms;
}
.bear-mobile-note-item:active {
  background: color-mix(in oklch, var(--accent), transparent 20%);
}

.bear-mobile-note-tag {
  font-size: 11px;
  font-weight: 500;
  padding: 1px 6px;
  border-radius: 6px;
  background: color-mix(in oklch, var(--primary), transparent 88%);
  color: var(--primary);
}

/* Floating action button for new note */
.bear-mobile-fab {
  position: absolute;
  bottom: 16px;
  right: 16px;
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: var(--primary);
  color: var(--primary-foreground);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow:
    0 4px 14px color-mix(in oklch, var(--primary), transparent 50%),
    0 2px 6px rgba(0,0,0,0.15);
  transition: transform 150ms, box-shadow 150ms;
  z-index: 10;
}
.bear-mobile-fab:active {
  transform: scale(0.92);
}

/* Mobile dropdown menu */
.bear-mobile-dropdown {
  border-bottom: 1px solid var(--border);
  background: color-mix(in oklch, var(--card), var(--background) 50%);
  padding: 4px 8px;
  animation: dropdownIn 120ms ease-out;
}
@keyframes dropdownIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}
.bear-mobile-dropdown-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  text-align: left;
  padding: 12px 12px;
  border-radius: 8px;
  font-size: 15px;
  font-weight: 500;
  color: var(--foreground);
  transition: background 80ms;
}
.bear-mobile-dropdown-item:active {
  background: color-mix(in oklch, var(--accent), transparent 30%);
}

/* Bear-style mobile formatting toolbar */
.bear-format-bar {
  border-top: 1px solid color-mix(in oklch, var(--border), transparent 40%);
  background: color-mix(in oklch, var(--card), var(--background) 40%);
  padding: 6px 4px;
}
.bear-format-bar-scroll {
  display: flex;
  align-items: center;
  gap: 2px;
  overflow-x: auto;
  padding: 0 4px;
}
.bear-format-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 40px;
  height: 36px;
  border-radius: 8px;
  color: color-mix(in oklch, var(--foreground), transparent 30%);
  transition: all 100ms;
  flex-shrink: 0;
}
.bear-format-btn:active {
  background: color-mix(in oklch, var(--primary), transparent 80%);
  color: var(--primary);
  transform: scale(0.92);
}
.bear-format-divider {
  width: 1px;
  height: 20px;
  background: color-mix(in oklch, var(--border), transparent 30%);
  margin: 0 4px;
  flex-shrink: 0;
}

/* Mobile list enter animation */
.bear-mobile-list-enter {
  animation: mobileListIn 200ms ease-out;
}
@keyframes mobileListIn {
  from { opacity: 0.6; transform: translateX(-10px); }
  to { opacity: 1; transform: translateX(0); }
}

/* Mobile note tags bar adjustments */
@media (max-width: 767px) {
  .bear-tags-bar {
    padding: 6px 12px;
    overflow-x: auto;
    flex-wrap: nowrap;
  }
  .bear-tag-pill {
    font-size: 12px;
    padding: 3px 10px;
    white-space: nowrap;
  }
  .bear-textarea {
    font-size: 16px; /* prevent iOS zoom */
    padding: 12px 16px;
  }
  .bear-preview-content {
    font-size: 16px;
    padding: 12px 16px;
  }
  .bear-mode-toggle {
    display: none; /* handled via mobile menu */
  }
  .bear-add-tag {
    white-space: nowrap;
  }
}
</style>
