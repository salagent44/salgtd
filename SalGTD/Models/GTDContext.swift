import Foundation
import SwiftData

@Model
final class GTDContext {
    @Attribute(.unique) var id: Int
    var name: String
    var builtIn: Bool
    var sortOrder: Int
    var syncVersion: Int
    var isDeleted: Bool

    init(
        id: Int = 0,
        name: String,
        builtIn: Bool = false,
        sortOrder: Int = 0,
        syncVersion: Int = 0,
        isDeleted: Bool = false
    ) {
        self.id = id
        self.name = name
        self.builtIn = builtIn
        self.sortOrder = sortOrder
        self.syncVersion = syncVersion
        self.isDeleted = isDeleted
    }
}
