import SwiftUI

struct PillFilterBar: View {
    @Binding var selected: ItemsViewModel.ItemFilter
    var inboxCount: Int = 0

    var body: some View {
        ScrollView(.horizontal, showsIndicators: false) {
            HStack(spacing: 8) {
                ForEach(ItemsViewModel.ItemFilter.allCases, id: \.self) { filter in
                    Button {
                        selected = filter
                    } label: {
                        HStack(spacing: 4) {
                            Text(filter.rawValue)
                                .font(.subheadline)
                                .fontWeight(selected == filter ? .semibold : .regular)

                            if filter == .inbox && inboxCount > 0 {
                                Text("\(inboxCount)")
                                    .font(.caption2)
                                    .fontWeight(.bold)
                                    .padding(.horizontal, 5)
                                    .padding(.vertical, 1)
                                    .background(selected == filter ? .white.opacity(0.3) : .red)
                                    .foregroundStyle(selected == filter ? .primary : .white)
                                    .clipShape(Capsule())
                            }
                        }
                        .padding(.horizontal, 14)
                        .padding(.vertical, 8)
                        .background(selected == filter ? Color.accentColor : Color(.systemGray5))
                        .foregroundStyle(selected == filter ? .white : .primary)
                        .clipShape(Capsule())
                    }
                    .buttonStyle(.plain)
                }
            }
            .padding(.horizontal)
        }
    }
}
