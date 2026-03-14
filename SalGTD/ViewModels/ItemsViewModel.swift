import Foundation
import SwiftData

@Observable
final class ItemsViewModel {
    var selectedFilter: ItemFilter = .clarified

    enum ItemFilter: String, CaseIterable {
        case clarified = "Clarified"
        case inbox = "Inbox"
        case tickler = "Tickler"
        case done = "Done"
        case flagged = "Flagged"
    }

    func statusesForFilter(_ filter: ItemFilter) -> [String] {
        switch filter {
        case .clarified: return ["next-action", "project", "waiting", "someday"]
        case .inbox: return ["inbox"]
        case .tickler: return ["tickler"]
        case .done: return ["done"]
        case .flagged: return [] // Special case — uses flagged field
        }
    }

    func predicate(for filter: ItemFilter) -> Predicate<GTDItem> {
        switch filter {
        case .clarified:
            return #Predicate<GTDItem> {
                !$0.isDeleted &&
                ($0.status == "next-action" || $0.status == "project" || $0.status == "waiting" || $0.status == "someday")
            }
        case .inbox:
            return #Predicate<GTDItem> { !$0.isDeleted && $0.status == "inbox" }
        case .tickler:
            return #Predicate<GTDItem> { !$0.isDeleted && $0.status == "tickler" }
        case .done:
            return #Predicate<GTDItem> { !$0.isDeleted && $0.status == "done" }
        case .flagged:
            return #Predicate<GTDItem> { !$0.isDeleted && $0.flagged == true }
        }
    }
}
