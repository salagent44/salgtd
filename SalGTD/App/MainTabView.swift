import SwiftUI

struct MainTabView: View {
    @State private var selectedTab = 0
    @Environment(SyncViewModel.self) private var syncViewModel

    var body: some View {
        TabView(selection: $selectedTab) {
            Tab("Items", systemImage: "checklist", value: 0) {
                ItemsListView()
            }

            Tab("Notes", systemImage: "note.text", value: 1) {
                NotesListView()
            }

            Tab("Calendar", systemImage: "calendar", value: 2) {
                CalendarView()
            }

            Tab("Settings", systemImage: "gear", value: 3) {
                SettingsView()
            }
        }
        .overlay(alignment: .topTrailing) {
            if syncViewModel.isSyncing {
                ProgressView()
                    .padding(8)
                    .background(.ultraThinMaterial, in: Circle())
                    .padding()
            }
        }
    }
}
