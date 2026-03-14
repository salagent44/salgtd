import SwiftUI
import SwiftData

struct SettingsView: View {
    @Environment(SyncViewModel.self) private var syncViewModel
    @State private var showingLogoutConfirmation = false

    var body: some View {
        NavigationStack {
            Form {
                Section("Account") {
                    if let url = syncViewModel.authService.serverURL {
                        LabeledContent("Server", value: url.host ?? url.absoluteString)
                    }

                    Button("Logout", role: .destructive) {
                        showingLogoutConfirmation = true
                    }
                }

                Section("Sync") {
                    HStack {
                        Text("Status")
                        Spacer()
                        if syncViewModel.isSyncing {
                            HStack(spacing: 6) {
                                ProgressView()
                                    .controlSize(.small)
                                Text("Syncing...")
                                    .foregroundStyle(.secondary)
                            }
                        } else if let error = syncViewModel.syncError {
                            Text(error)
                                .foregroundStyle(.red)
                                .font(.caption)
                        } else if syncViewModel.networkMonitor.isConnected {
                            Label("Connected", systemImage: "checkmark.circle.fill")
                                .foregroundStyle(.green)
                                .font(.caption)
                        } else {
                            Label("Offline", systemImage: "wifi.slash")
                                .foregroundStyle(.orange)
                                .font(.caption)
                        }
                    }

                    if let lastSync = syncViewModel.lastSyncDate {
                        LabeledContent("Last sync", value: lastSync.formatted(.relative(presentation: .named)))
                    }

                    Button("Force Full Sync") {
                        Task { await syncViewModel.forceFullSync() }
                    }
                    .disabled(syncViewModel.isSyncing || !syncViewModel.networkMonitor.isConnected)
                }

                Section("About") {
                    LabeledContent("App", value: "Sal GTD")
                    LabeledContent("Version", value: "1.0.0")
                }
            }
            .navigationTitle("Settings")
            .confirmationDialog("Logout?", isPresented: $showingLogoutConfirmation, titleVisibility: .visible) {
                Button("Logout", role: .destructive) {
                    Task { await syncViewModel.logout() }
                }
                Button("Cancel", role: .cancel) {}
            } message: {
                Text("This will remove all local data.")
            }
        }
    }
}
