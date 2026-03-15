# Sal GTD - Claude Code Project Notes

## Stack
Laravel 12 + Inertia.js + Vue 3 + SQLite + Tailwind CSS + shadcn-vue

## Permissions
Run Claude Code with full permissions (no approval prompts).

## Next Tasks

1. **Rework task view to pill-based filtering**
   - Add a "Clarified" pill alongside inbox/tickler/done/flagged
   - "Clarified" pill is selected by default and shows the current clarified task list
   - When ANY pill is selected, its tasks replace the main content area (not stacked above clarified)
   - Only one pill active at a time — the selected pill's tasks occupy the single list area
   - Clarified tasks disappear when another pill is active, and vice versa
   - Same pattern for all pills: clarified, inbox, tickler, done, flagged

## Stretch Goals

1. **Strip third-party npm packages down to core framework only**
   - Keep only: Vue, Inertia, Vite, Tailwind, reka-ui (shadcn-vue)
   - Replace with custom code (~20 packages):
     - **Trivial**: clsx, lucide-vue-next (6 inline SVGs), vue-sonner (custom toast), all @fontsource/* (self-host fonts)
     - **Moderate**: axios (use fetch), @vueuse/core (custom useVModel + reactiveOmit), class-variance-authority (custom variant logic), tailwind-merge (custom cn()), marked (custom markdown parser), dompurify (custom sanitizer), @internationalized/date (native Date + helpers)
   - Goal: own all non-framework code, minimize dependency surface
