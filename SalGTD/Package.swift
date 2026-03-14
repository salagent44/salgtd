// swift-tools-version: 5.9
// This is a placeholder — the actual project should be opened in Xcode.
// Create the Xcode project:
//   1. Open Xcode → File → New → Project → iOS App
//   2. Name: SalGTD, Interface: SwiftUI, Storage: SwiftData
//   3. Drag the contents of this SalGTD/ directory into the project
//   4. Build target: iOS 17.0+

import PackageDescription

let package = Package(
    name: "SalGTD",
    platforms: [.iOS(.v17)],
    targets: [
        .executableTarget(
            name: "SalGTD",
            path: "."
        ),
    ]
)
