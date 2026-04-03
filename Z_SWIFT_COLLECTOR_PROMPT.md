# Claude Code Session: Swift Collector App (mobile_v2)

> PASTE THIS ENTIRE FILE as your first message to a new Claude Code session.
> Use: /effort max

## WHO YOU ARE

You are building a FRESH SwiftUI iOS app for the collector role in the Mobius Smart Recycling Bin Ecosystem. Do NOT look at the old mobile app at `/Users/danieltan/mobius_smart_recycling_bin_ecosystem/mobile/`. It's buggy. Build from scratch.

## BEFORE YOU TOUCH ANY CODE

Read these files:
1. `/Users/danieltan/mobius-schema/DOMAIN.md` — full system context
2. `/Users/danieltan/mobius-schema/MASTER_PLAN.md` — architecture overview

## GROUND RULES

1. **NEVER run git commands.** Daniel handles all git.
2. **Do NOT look at or reference `/Users/danieltan/mobius_smart_recycling_bin_ecosystem/mobile/`** — that codebase is legacy and buggy. Build fresh.
3. iOS 18+, Swift 6.0, SwiftUI only. No UIKit unless absolutely necessary.
4. Use @MainActor @Observable pattern (NOT ObservableObject).
5. Every screen must work. No placeholder views. No "under construction."

## THE APP

Create a new Xcode project directory at:
```
/Users/danieltan/mobius_smart_recycling_bin_ecosystem/mobile_v2/
```

This is a collector-focused app. The collector logs in, sees pending routes, generates optimized routes from pickup requests, navigates stop by stop on a map, and marks stops complete.

## API

Base URL: `http://localhost:8000/api/v1` (change to machine's local IP for device testing)

Auth: POST `/auth/login` with `{email, password}` → returns `{token, user}`. Send token as `Authorization: Bearer {token}` on all subsequent requests.

Test credentials: `kumar@mobius.my` / `password` (collector role, user_id 7)

### Collector API Endpoints:

| Method | Path | What it does |
|--------|------|-------------|
| GET | `/collector/routes` | List collector's routes |
| GET | `/collector/routes/{id}` | Route detail with stops, polyline, bin info |
| POST | `/collector/routes/generate` | Generate optimized route from pending pickups |
| POST | `/collector/routes/{id}/accept` | Accept a pending route |
| POST | `/collector/routes/{id}/start` | Start navigating |
| POST | `/collector/routes/{id}/stops/{order}/complete` | Complete a stop (body: `{latitude, longitude}`) |
| POST | `/collector/routes/{id}/stops/{order}/skip` | Skip a stop (body: `{reason}`) |
| POST | `/collector/routes/{id}/complete` | Mark entire route complete |
| POST | `/collector/routes/{id}/reject` | Reject a route |

### Response shapes:

**GET /collector/routes:**
```json
{
  "routes": [
    {
      "id": 1,
      "status": "pending",
      "depot_name": "Gurney Plaza",
      "total_distance_km": 30.8,
      "total_duration_min": 52,
      "stops_count": 3,
      "started_at": null,
      "completed_at": null,
      "created_at": "2026-04-03T..."
    }
  ]
}
```

**GET /collector/routes/{id}:**
```json
{
  "route": {
    "id": 1,
    "status": "in_progress",
    "depot_latitude": 5.4141,
    "depot_longitude": 100.3288,
    "depot_name": "Komtar Depot",
    "total_distance_km": 30.8,
    "total_duration_min": 52,
    "route_polyline": "encoded_polyline_string",
    "stops": [
      {
        "id": 1,
        "stop_order": 1,
        "bin_serial": "MBS-SB-002",
        "outlet": "Gurney Plaza",
        "brand": "Starbucks",
        "address": "168A, Persiaran Gurney, 10350 George Town",
        "distance_km": 4.0,
        "duration_min": 9,
        "status": "pending",
        "latitude": 5.4371,
        "longitude": 100.3101
      }
    ]
  }
}
```

## SCREENS TO BUILD

### 1. LoginView
- Email + password fields
- "Sign In" button → POST /auth/login
- Store token in UserDefaults (Keychain is better but UserDefaults is fine for demo)
- On success → navigate to CollectorHome
- Clean, simple, Mobius branding (teal accent color #0D9488)

### 2. CollectorHomeView
- Welcome message with collector name
- Stats: pending routes count, completed today
- "Generate New Route" button → POST /collector/routes/generate → refresh list
- List of routes (pull to refresh)
- Tap route → RouteDetailView

### 3. RouteListView (can be part of CollectorHome)
- Each route card shows: status badge, depot name, distance, duration, stops count
- Color-coded status: pending=amber, accepted=blue, in_progress=teal, completed=green

### 4. RouteDetailView
- **Apple MapKit** showing:
  - Depot pin (start/end)
  - Stop pins numbered 1, 2, 3...
  - Route polyline decoded from Google's encoded polyline
- Route info: total distance, total duration, status
- List of stops below the map with address, bin serial, brand
- Action buttons based on status:
  - pending → "Accept" or "Reject"
  - accepted → "Start Navigation"
  - in_progress → shows ActiveRouteView

### 5. ActiveRouteView
- Map with current position + remaining stops
- Current stop highlighted: bin serial, outlet, address
- "Complete Stop" button → POST with GPS coordinates
- "Skip Stop" button → with reason text field
- After all stops → "Complete Route" button
- Progress indicator: "Stop 2 of 4"

## POLYLINE DECODING

Google Directions returns an encoded polyline string. Decode it to `[CLLocationCoordinate2D]` for MapKit:

```swift
func decodePolyline(_ encoded: String) -> [CLLocationCoordinate2D] {
    var coordinates: [CLLocationCoordinate2D] = []
    var index = encoded.startIndex
    var lat: Int32 = 0
    var lng: Int32 = 0

    while index < encoded.endIndex {
        var shift: Int32 = 0
        var result: Int32 = 0
        var byte: Int32

        repeat {
            byte = Int32(encoded[index].asciiValue! - 63)
            index = encoded.index(after: index)
            result |= (byte & 0x1F) << shift
            shift += 5
        } while byte >= 0x20

        lat += (result & 1) != 0 ? ~(result >> 1) : (result >> 1)

        shift = 0
        result = 0

        repeat {
            byte = Int32(encoded[index].asciiValue! - 63)
            index = encoded.index(after: index)
            result |= (byte & 0x1F) << shift
            shift += 5
        } while byte >= 0x20

        lng += (result & 1) != 0 ? ~(result >> 1) : (result >> 1)

        coordinates.append(CLLocationCoordinate2D(
            latitude: Double(lat) / 1e5,
            longitude: Double(lng) / 1e5
        ))
    }

    return coordinates
}
```

## PROJECT STRUCTURE

```
mobile_v2/
├── MobiusCollector/
│   ├── MobiusCollectorApp.swift     ← @main entry
│   ├── Models/
│   │   ├── Route.swift              ← Route, RouteStop structs (Codable)
│   │   └── User.swift               ← User struct
│   ├── Services/
│   │   ├── APIService.swift          ← HTTP client, auth token management
│   │   └── PolylineDecoder.swift     ← Google polyline → CLLocationCoordinate2D
│   ├── ViewModels/
│   │   ├── AuthViewModel.swift       ← @Observable, login logic
│   │   └── RouteViewModel.swift      ← @Observable, route CRUD + generation
│   └── Views/
│       ├── LoginView.swift
│       ├── CollectorHomeView.swift
│       ├── RouteDetailView.swift
│       ├── ActiveRouteView.swift
│       └── RouteMapView.swift        ← MapKit wrapper
```

## DESIGN

- Light mode only
- Teal accent (#0D9488)
- Clean, functional, no frills
- Large tap targets for buttons
- Status badges with color coding
- The map should take up the top 60% of RouteDetailView

## WHAT SUCCESS LOOKS LIKE

1. Open app → login as kumar@mobius.my
2. See home screen → tap "Generate Route"
3. Route appears with stops on the map
4. Accept → Start → navigate stop by stop
5. Complete each stop → complete route
6. Route shows as completed in list
