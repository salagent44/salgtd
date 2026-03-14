import SwiftUI
import SwiftData

struct ItemsListView: View {
    @Environment(\.modelContext) private var modelContext
    @Environment(SyncViewModel.self) private var syncViewModel
    @State private var viewModel = ItemsViewModel()
    @State private var showingAddItem = false
    @State private var newItemTitle = ""
    @State private var processingItem: GTDItem?

    @Query(filter: #Predicate<GTDItem> { !$0.isDeleted && $0.status == "inbox" })
    private var inboxItems: [GTDItem]

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                PillFilterBar(
                    selected: $viewModel.selectedFilter,
                    inboxCount: inboxItems.count
                )
                .padding(.vertical, 8)

                FilteredItemsList(
                    filter: viewModel.selectedFilter,
                    onProcess: { item in processingItem = item },
                    onComplete: { item in completeItem(item) },
                    onDelete: { item in deleteItem(item) }
                )
            }
            .navigationTitle("Items")
            .toolbar {
                ToolbarItem(placement: .primaryAction) {
                    Button {
                        showingAddItem = true
                    } label: {
                        Image(systemName: "plus")
                    }
                }
            }
            .alert("New Item", isPresented: $showingAddItem) {
                TextField("What's on your mind?", text: $newItemTitle)
                Button("Add") { addItem() }
                Button("Cancel", role: .cancel) { newItemTitle = "" }
            }
            .sheet(item: $processingItem) { item in
                ProcessItemSheet(item: item)
            }
        }
    }

    private func addItem() {
        guard !newItemTitle.trimmingCharacters(in: .whitespaces).isEmpty else { return }
        let item = GTDItem(title: newItemTitle, status: "inbox")
        modelContext.insert(item)
        try? modelContext.save()

        Task {
            await syncViewModel.enqueueMutation(
                entity: "item",
                entityId: item.id,
                action: "upsert",
                baseVersion: 0,
                data: ["title": item.title, "status": "inbox"]
            )
        }

        newItemTitle = ""
    }

    private func completeItem(_ item: GTDItem) {
        item.originalStatus = item.status
        item.completedAt = Date()
        item.status = "done"
        item.pendingSync = true
        try? modelContext.save()

        Task {
            await syncViewModel.enqueueMutation(
                entity: "item",
                entityId: item.id,
                action: "upsert",
                baseVersion: item.syncVersion,
                data: ["status": "done"]
            )
        }
    }

    private func deleteItem(_ item: GTDItem) {
        item.isDeleted = true
        item.pendingSync = true
        try? modelContext.save()

        Task {
            await syncViewModel.enqueueMutation(
                entity: "item",
                entityId: item.id,
                action: "delete",
                baseVersion: item.syncVersion,
                data: nil
            )
        }
    }
}

struct FilteredItemsList: View {
    let filter: ItemsViewModel.ItemFilter
    var onProcess: (GTDItem) -> Void
    var onComplete: (GTDItem) -> Void
    var onDelete: (GTDItem) -> Void

    @Query private var items: [GTDItem]

    init(filter: ItemsViewModel.ItemFilter,
         onProcess: @escaping (GTDItem) -> Void,
         onComplete: @escaping (GTDItem) -> Void,
         onDelete: @escaping (GTDItem) -> Void) {
        self.filter = filter
        self.onProcess = onProcess
        self.onComplete = onComplete
        self.onDelete = onDelete

        let viewModel = ItemsViewModel()
        _items = Query(filter: viewModel.predicate(for: filter), sort: \.sortOrder)
    }

    var body: some View {
        if items.isEmpty {
            ContentUnavailableView {
                Label(emptyTitle, systemImage: emptyIcon)
            } description: {
                Text(emptyDescription)
            }
        } else {
            List {
                ForEach(items) { item in
                    ItemRowView(item: item)
                        .swipeActions(edge: .trailing) {
                            Button(role: .destructive) {
                                onDelete(item)
                            } label: {
                                Label("Delete", systemImage: "trash")
                            }
                        }
                        .swipeActions(edge: .leading) {
                            if item.status != "done" {
                                Button {
                                    onComplete(item)
                                } label: {
                                    Label("Done", systemImage: "checkmark")
                                }
                                .tint(.green)
                            }
                        }
                        .contentShape(Rectangle())
                        .onTapGesture {
                            if item.status == "inbox" {
                                onProcess(item)
                            }
                        }
                }
            }
            .listStyle(.plain)
        }
    }

    private var emptyTitle: String {
        switch filter {
        case .clarified: return "No clarified items"
        case .inbox: return "Inbox zero!"
        case .tickler: return "No tickler items"
        case .done: return "No completed items"
        case .flagged: return "No flagged items"
        }
    }

    private var emptyIcon: String {
        switch filter {
        case .clarified: return "checklist"
        case .inbox: return "tray"
        case .tickler: return "clock"
        case .done: return "checkmark.circle"
        case .flagged: return "flag"
        }
    }

    private var emptyDescription: String {
        switch filter {
        case .inbox: return "You've processed everything"
        default: return "Items will appear here"
        }
    }
}
