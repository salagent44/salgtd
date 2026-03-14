import SwiftUI
import SwiftData

struct NotesListView: View {
    @Environment(\.modelContext) private var modelContext
    @Environment(SyncViewModel.self) private var syncViewModel

    @Query(filter: #Predicate<GTDNote> { !$0.isDeleted && !$0.trashed },
           sort: [SortDescriptor(\GTDNote.pinned, order: .reverse),
                  SortDescriptor(\GTDNote.updatedAt, order: .reverse)])
    private var notes: [GTDNote]

    @State private var selectedNote: GTDNote?
    @State private var showingEditor = false

    var body: some View {
        NavigationStack {
            Group {
                if notes.isEmpty {
                    ContentUnavailableView {
                        Label("No Notes", systemImage: "note.text")
                    } description: {
                        Text("Tap + to create your first note")
                    }
                } else {
                    List {
                        ForEach(notes) { note in
                            NoteRowView(note: note)
                                .contentShape(Rectangle())
                                .onTapGesture {
                                    selectedNote = note
                                    showingEditor = true
                                }
                                .swipeActions(edge: .trailing) {
                                    Button(role: .destructive) {
                                        trashNote(note)
                                    } label: {
                                        Label("Trash", systemImage: "trash")
                                    }
                                }
                                .swipeActions(edge: .leading) {
                                    Button {
                                        togglePin(note)
                                    } label: {
                                        Label(note.pinned ? "Unpin" : "Pin",
                                              systemImage: note.pinned ? "pin.slash" : "pin")
                                    }
                                    .tint(.orange)
                                }
                        }
                    }
                    .listStyle(.plain)
                }
            }
            .navigationTitle("Notes")
            .toolbar {
                ToolbarItem(placement: .primaryAction) {
                    Button {
                        createNote()
                    } label: {
                        Image(systemName: "plus")
                    }
                }
            }
            .sheet(isPresented: $showingEditor) {
                if let note = selectedNote {
                    NoteEditorView(note: note)
                }
            }
        }
    }

    private func createNote() {
        let note = GTDNote()
        modelContext.insert(note)
        try? modelContext.save()

        Task {
            await syncViewModel.enqueueMutation(
                entity: "note",
                entityId: note.id,
                action: "upsert",
                baseVersion: 0,
                data: ["title": "", "content": ""]
            )
        }

        selectedNote = note
        showingEditor = true
    }

    private func trashNote(_ note: GTDNote) {
        note.trashed = true
        note.pendingSync = true
        try? modelContext.save()

        Task {
            await syncViewModel.enqueueMutation(
                entity: "note",
                entityId: note.id,
                action: "upsert",
                baseVersion: note.syncVersion,
                data: ["trashed": true]
            )
        }
    }

    private func togglePin(_ note: GTDNote) {
        note.pinned.toggle()
        note.pendingSync = true
        try? modelContext.save()

        Task {
            await syncViewModel.enqueueMutation(
                entity: "note",
                entityId: note.id,
                action: "upsert",
                baseVersion: note.syncVersion,
                data: ["pinned": note.pinned]
            )
        }
    }
}

struct NoteRowView: View {
    let note: GTDNote

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            HStack {
                if note.pinned {
                    Image(systemName: "pin.fill")
                        .font(.caption)
                        .foregroundStyle(.orange)
                }
                Text(note.title.isEmpty ? "Untitled" : note.title)
                    .font(.headline)
                    .lineLimit(1)

                Spacer()

                if note.locked {
                    Image(systemName: "lock.fill")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                }

                if note.pendingSync {
                    Image(systemName: "arrow.triangle.2.circlepath")
                        .font(.caption2)
                        .foregroundStyle(.secondary)
                }
            }

            if !note.content.isEmpty {
                Text(note.content)
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
                    .lineLimit(2)
            }

            if !note.tags.isEmpty {
                HStack(spacing: 4) {
                    ForEach(note.tags, id: \.self) { tag in
                        Text("#\(tag)")
                            .font(.caption2)
                            .foregroundStyle(.blue)
                    }
                }
            }
        }
        .padding(.vertical, 2)
    }
}
