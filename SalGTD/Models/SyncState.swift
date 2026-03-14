import Foundation
import SwiftData

@Model
final class SyncState {
    var lastSyncVersion: Int
    var lastSyncDate: Date?

    init(lastSyncVersion: Int = 0, lastSyncDate: Date? = nil) {
        self.lastSyncVersion = lastSyncVersion
        self.lastSyncDate = lastSyncDate
    }
}
