import SwiftUI

struct ContentView: View {
    @Environment(SyncViewModel.self) private var syncViewModel

    var body: some View {
        Group {
            if syncViewModel.isAuthenticated {
                MainTabView()
            } else {
                LoginView()
            }
        }
    }
}
