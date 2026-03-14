import SwiftUI
import SwiftData

@main
struct SalGTDApp: App {
    let container: ModelContainer

    @State private var syncViewModel: SyncViewModel

    init() {
        let schema = Schema([
            GTDItem.self,
            GTDNote.self,
            GTDCalendarEvent.self,
            GTDContext.self,
            GTDEmail.self,
            PendingMutation.self,
            SyncState.self,
        ])
        let config = ModelConfiguration(schema: schema, isStoredInMemoryOnly: false)
        do {
            container = try ModelContainer(for: schema, configurations: [config])
        } catch {
            fatalError("Failed to create ModelContainer: \(error)")
        }

        let syncVM = SyncViewModel(modelContainer: container)
        _syncViewModel = State(initialValue: syncVM)
    }

    var body: some Scene {
        WindowGroup {
            ContentView()
                .environment(syncViewModel)
                .onAppear {
                    syncViewModel.startSync()
                }
        }
        .modelContainer(container)
    }
}
