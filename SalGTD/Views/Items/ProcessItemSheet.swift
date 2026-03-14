import SwiftUI
import SwiftData

struct ProcessItemSheet: View {
    @Environment(\.dismiss) private var dismiss
    @Environment(\.modelContext) private var modelContext
    @Environment(SyncViewModel.self) private var syncViewModel

    @Bindable var item: GTDItem

    @State private var title: String = ""
    @State private var status: String = "next-action"
    @State private var context: String = ""
    @State private var waitingFor: String = ""
    @State private var waitingDate: Date = Date()
    @State private var ticklerDate: Date = Date()
    @State private var flagged: Bool = false
    @State private var goal: String = ""

    @Query(filter: #Predicate<GTDContext> { !$0.isDeleted })
    private var contexts: [GTDContext]

    var body: some View {
        NavigationStack {
            Form {
                Section("Title") {
                    TextField("Title", text: $title)
                }

                Section("Status") {
                    Picker("Status", selection: $status) {
                        Text("Next Action").tag("next-action")
                        Text("Project").tag("project")
                        Text("Waiting").tag("waiting")
                        Text("Someday").tag("someday")
                        Text("Tickler").tag("tickler")
                        Text("Done").tag("done")
                    }
                    .pickerStyle(.menu)
                }

                if status == "next-action" {
                    Section("Context") {
                        Picker("Context", selection: $context) {
                            Text("None").tag("")
                            ForEach(contexts, id: \.id) { ctx in
                                Text(ctx.name).tag(ctx.name)
                            }
                        }
                    }
                }

                if status == "waiting" {
                    Section("Waiting For") {
                        TextField("Who?", text: $waitingFor)
                        DatePicker("Expected by", selection: $waitingDate, displayedComponents: .date)
                    }
                }

                if status == "tickler" {
                    Section("Tickler Date") {
                        DatePicker("Show on", selection: $ticklerDate, displayedComponents: .date)
                    }
                }

                if status == "project" {
                    Section("Goal") {
                        TextField("What does done look like?", text: $goal)
                    }
                }

                Section {
                    Toggle("Flagged", isOn: $flagged)
                }

                if let email = item.email {
                    Section("Email") {
                        LabeledContent("From", value: email.fromName ?? email.fromAddress ?? "Unknown")
                        LabeledContent("Subject", value: email.subject ?? "No subject")
                        if let body = email.bodyText, !body.isEmpty {
                            Text(body)
                                .font(.caption)
                                .foregroundStyle(.secondary)
                                .lineLimit(5)
                        }
                    }
                }
            }
            .navigationTitle("Clarify")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Save") { saveProcess() }
                        .fontWeight(.semibold)
                }
            }
            .onAppear {
                title = item.title
                status = item.status == "inbox" ? "next-action" : item.status
                context = item.context ?? ""
                waitingFor = item.waitingFor ?? ""
                flagged = item.flagged
                goal = item.goal ?? ""
            }
        }
    }

    private func saveProcess() {
        item.title = title
        item.status = status
        item.flagged = flagged
        item.pendingSync = true

        switch status {
        case "next-action":
            item.context = context.isEmpty ? nil : context
            item.waitingFor = nil
            item.waitingDate = nil
            item.ticklerDate = nil
        case "waiting":
            item.waitingFor = waitingFor.isEmpty ? nil : waitingFor
            item.waitingDate = waitingDate
            item.context = nil
            item.ticklerDate = nil
        case "tickler":
            item.ticklerDate = ticklerDate
            item.context = nil
            item.waitingFor = nil
            item.waitingDate = nil
        case "project":
            item.goal = goal.isEmpty ? nil : goal
            item.projectId = nil
            item.context = nil
            item.waitingFor = nil
            item.waitingDate = nil
            item.ticklerDate = nil
        case "done":
            item.originalStatus = item.status
            item.completedAt = Date()
            item.context = nil
            item.waitingFor = nil
            item.waitingDate = nil
            item.ticklerDate = nil
        default:
            item.context = nil
            item.waitingFor = nil
            item.waitingDate = nil
            item.ticklerDate = nil
        }

        try? modelContext.save()

        var data: [String: Any] = [
            "status": status,
            "title": title,
            "flagged": flagged,
        ]
        if let ctx = item.context { data["context"] = ctx }
        if let wf = item.waitingFor { data["waiting_for"] = wf }
        if let g = item.goal { data["goal"] = g }

        Task {
            await syncViewModel.enqueueMutation(
                entity: "item",
                entityId: item.id,
                action: "upsert",
                baseVersion: item.syncVersion,
                data: data
            )
        }

        dismiss()
    }
}
