import SwiftUI

struct NoteEditorView: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(\.modelContext) private var modelContext
    @Environment(SyncViewModel.self) private var syncViewModel

    @Bindable var note: GTDNote

    @State private var title: String = ""
    @State private var content: String = ""

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                TextField("Title", text: $title)
                    .font(.title2)
                    .fontWeight(.bold)
                    .padding(.horizontal)
                    .padding(.top)

                Divider()
                    .padding(.horizontal)
                    .padding(.vertical, 8)

                TextEditor(text: $content)
                    .padding(.horizontal, 12)
                    .scrollContentBackground(.hidden)
            }
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .confirmationAction) {
                    Button("Done") { saveAndDismiss() }
                }
            }
            .onAppear {
                title = note.title
                content = note.content
            }
        }
    }

    private func saveAndDismiss() {
        note.title = title
        note.content = content
        note.pendingSync = true
        note.updatedAt = Date()
        try? modelContext.save()

        Task {
            await syncViewModel.enqueueMutation(
                entity: "note",
                entityId: note.id,
                action: "upsert",
                baseVersion: note.syncVersion,
                data: ["title": title, "content": content]
            )
        }

        dismiss()
    }
}
