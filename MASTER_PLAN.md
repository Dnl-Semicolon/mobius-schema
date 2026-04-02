# Mobius — Master Implementation Plan

> **For any Claude Code instance:** Read DOMAIN.md first, then this plan. Pick a rabbit hole, go deep.
> **For Daniel:** This is your roadmap. Every rabbit hole is listed. Nothing is hallucinated.

**Goal:** Rebuild the Mobius system with a clean schema, working APIs, functional UIs, and demo-ready AI/CV + route optimization. Every feature from the viva fixes must work.

**Architecture:**
```
mobius_smart_recycling_bin_ecosystem/
├── backend/        ← Laravel 12 API (port 8000) — Sanctum auth, Eloquent, MySQL
├── web-admin/      ← React SPA (port 3000) — admin + brand owner + store owner dashboards
├── bin-client/     ← React kiosk app (port 5001+) — one instance per bin
├── mobile/         ← SwiftUI iOS — public user + collector apps
└── (external)
    ├── mobius-schema/     ← Clean room schema project (reference only, don't deploy)
    ├── Roboflow API       ← Waste type detection (cup/lid/straw)
    ├── OpenAI Vision API  ← Brand detection (Starbucks/ZUS/CHAGEE/Lucky Cup)
    └── Google Directions  ← Route optimization
```

**API Keys (all in backend/.env):**
- `GOOGLE_DIRECTIONS_API_KEY` — route optimization
- `GOOGLE_MAPS_API_KEY` — map display
- `OPENAI_API_KEY` — brand detection
- `ROBOFLOW_API_KEY` — waste type detection
- `ROBOFLOW_MODEL_ID` — mobius-v2/1
- `ROBOFLOW_API_URL` — https://serverless.roboflow.com

**Ports:**
- 8000 — Laravel API
- 3000 — Web admin (React)
- 5001+ — Bin clients (one port per bin instance)

---

## Rabbit Holes (in execution order)

### Rabbit Hole 1: Port Schema to Main Backend
**Status:** NOT STARTED
**Priority:** CRITICAL — everything depends on this
**Estimated effort:** 30 minutes

**What:** Take the migration from `mobius-schema` and port it to `backend/`. Create one new migration that drops all old tables and creates the new schema. Update the seeder. Update all Eloquent models to match.

**Key files:**
- `backend/database/migrations/` — one new migration file
- `backend/database/seeders/DatabaseSeeder.php` — replace entirely
- `backend/app/Models/*.php` — update ALL models to match new schema (relationships, casts, fillable)
- `backend/.env` — add all 6 API keys

**Dependencies:** None (this is first)
**Risks:** The main `mobius` MySQL database has existing data. We need to be careful — Daniel runs the migration himself. NEVER run migrate:fresh on the main DB without Daniel's explicit approval.

**What NOT to do:** Don't update controllers or routes yet. Just schema + models.

---

### Rabbit Hole 2: Update Laravel API Routes & Controllers
**Status:** NOT STARTED
**Priority:** HIGH — React apps need APIs to call
**Estimated effort:** 2-3 hours

**What:** The existing backend has 50+ API routes already working. Many are still valid but point to old models/columns. Update controllers to use new schema columns. Add new endpoints for new features (organizations, plans, subscriptions, payments, bin sessions, voucher system).

**Key areas:**
- `routes/api.php` — add new routes for: organizations, plans, subscriptions, payments, bin-sessions, detection pipeline, voucher templates/allocations/claims, pickup requests (with emergency type)
- `app/Http/Controllers/Api/` — update existing + add new controllers
- `app/Http/Requests/` — form requests for validation
- Auth flow stays the same (Sanctum) — just verify it works with new user model

**Existing API routes to KEEP (already working):**
- Auth: login, register, logout, user profile
- Bins: CRUD, heartbeat, QR resolve
- Detection events: create, classify
- Pickups: list, claim, complete
- Routes: list, show, generate, accept, complete
- Customer: stats, history, leaderboard, rewards, redemptions
- Notifications: CRUD

**New API routes needed:**
- Organizations: CRUD (admin), my-org (brand owner)
- Plans: CRUD (admin)
- Subscriptions: CRUD (admin), my-subscription (brand owner)
- Payments: list, create
- Invitations: create (brand owner), approve/reject (admin), accept (invitee)
- Registration Requests: create (public), list/approve/reject (admin)
- Bin Sessions: start, link-user, end, list
- Detection Pipeline: receive-frame → call Roboflow → call OpenAI → create detection event → calculate points
- Voucher Templates: CRUD (brand owner)
- Voucher Allocations: CRUD (brand owner)
- Voucher Claims: claim (public user), list, redeem

**Dependencies:** Rabbit Hole 1 (schema must be ported first)

---

### Rabbit Hole 3: Detection Pipeline Service
**Status:** NOT STARTED
**Priority:** HIGH — this is core tech #1 (viva fixes 1-4)
**Estimated effort:** 1-2 hours

**What:** Build a Laravel service class that handles the full detection pipeline:
1. Receive image from bin client
2. POST to Roboflow API → get waste type + confidence + bounding box
3. Crop bounding box area from image
4. POST cropped image to OpenAI Vision API → get brand name
5. Match brand slug to `brands` table
6. Create `detection_event` record
7. Update bin fill level (weight + sensors)
8. If session has user linked → calculate points (base × behavior × brand) → create transaction

**Key files:**
- `backend/app/Services/DetectionPipelineService.php` — new service
- `backend/app/Services/RoboflowService.php` — HTTP client for Roboflow API
- `backend/app/Services/OpenAIBrandService.php` — HTTP client for OpenAI Vision with brand prompt
- `backend/app/Services/PointsCalculatorService.php` — behavior multiplier × brand multiplier logic

**The OpenAI brand prompt (baked into the service):**
```
You are identifying the brand on a beverage cup. Look at the logo, text, and design.

Known brands:
- STARBUCKS: Green siren/mermaid logo, "STARBUCKS" text, clear plastic cups with measurement markings
- ZUS COFFEE: Zeus figure illustration (Greek god), "ZUS COFFEE" text, navy blue paper cups or clear plastic
- CHAGEE: Two designs — (1) navy blue constellation/floral with "CHAGEE" diagonal, (2) beige/nature with crane and green accents. Red CHAGEE stamp. Paper hot cups.
- LUCKY CUP: Red paper cup with "LUCKY CUP" text, 8-ball graphic. Plastic cup has red shield/crest with king illustration.

Respond with ONLY the brand slug: starbucks, zus, chagee, luckycup, or unknown.
```

**Dependencies:** Rabbit Hole 1 (models), Rabbit Hole 2 (API endpoint)

---

### Rabbit Hole 4: Route Optimization Service
**Status:** NOT STARTED
**Priority:** MEDIUM — viva fix #11
**Estimated effort:** 1-2 hours

**What:** Build a Laravel service that generates optimized collection routes using Google Directions API.

**Flow:**
1. Query bins with fill_level ≥ threshold in a geographic area
2. Build waypoints list from bin locations
3. Call Google Directions API with `optimizeWaypoints: true`
4. Parse response: optimized order, distance/duration per leg, polyline
5. Create `collection_route` + `route_stops` records
6. Assign to collector

**Dynamic re-routing:** When a new bin fills up while collector is on a route, call the API again with remaining stops + new stop. Update the route.

**Key files:**
- `backend/app/Services/RouteOptimizationService.php` — Google Directions integration
- `backend/app/Http/Controllers/Api/CollectorRouteController.php` — already exists, update

**Tested and working:** We already verified the Google Directions API returns correct results for Penang locations (30.8 km, 52 min route across Gurney → Queensbay → Komtar).

**Dependencies:** Rabbit Hole 1 (schema), Rabbit Hole 2 (API routes)

---

### Rabbit Hole 5: Web Admin (React SPA)
**Status:** NOT STARTED — Skywork generating skeleton
**Priority:** HIGH — admin needs to manage everything
**Estimated effort:** 3-4 hours (wiring to APIs)

**What:** Take the Skywork-generated React app and wire it to the Laravel API backend. Every page must call real APIs, not mock data.

**Setup:**
- Place at `mobius_smart_recycling_bin_ecosystem/web-admin/`
- Runs on port 3000
- Calls Laravel API at `http://localhost:8000/api/v1/`
- Sanctum token auth (login → get token → send in Authorization header)

**Key wiring tasks:**
- Auth: login form → POST /api/v1/auth/login → store token → redirect to dashboard
- Each CRUD page: list → GET endpoint, create → POST, edit → PUT, delete → DELETE
- Role-based views: admin sees everything, brand owner sees their org, store owner sees their outlets

**Existing Skywork code:**
- Landing page: `/Users/danieltan/Downloads/skywork_smart_recycling_landing/` — KEEP THIS, merge into web-admin as the `/` route
- Admin dashboard: pending from Skywork generation
- Both use Vite + React Router + shadcn/ui — compatible

**Dependencies:** Rabbit Hole 2 (APIs must exist to wire to)

---

### Rabbit Hole 6: Bin Client (React Kiosk)
**Status:** NOT STARTED — Skywork generating skeleton
**Priority:** HIGH — this is the core demo
**Estimated effort:** 3-4 hours (webcam + API wiring)

**What:** React app that simulates a physical bin. Each instance runs on its own port, authenticates with its API token, uses the webcam for detection.

**Setup:**
- Place at `mobius_smart_recycling_bin_ecosystem/bin-client/`
- Start with: `PORT=5001 SERIAL=MBS-SB-001 npm run dev`
- Each terminal tab = one bin instance on its own port

**Key implementation:**
- Webcam access via `navigator.mediaDevices.getUserMedia()`
- Capture frame → send to Laravel API → API calls Roboflow + OpenAI → returns result
- Session management: start session on first interaction, end on timeout/button
- QR scanning: use `jsQR` library on webcam frames (toggle mode or continuous background scan)
- Input method selection: buttons for cup_slot/lid_slot/straw_slot/general_intake
- Wash basin toggle
- Points display with behavior × brand calculation
- Admin panel behind PIN for pairing/config

**Dependencies:** Rabbit Hole 3 (detection pipeline must work)

---

### Rabbit Hole 7: Mobile App (SwiftUI)
**Status:** NOT STARTED — existing app needs redo
**Priority:** MEDIUM — needed for QR scan demo
**Estimated effort:** 3-4 hours

**What:** Minimal SwiftUI app that works. Focus: login, QR code display (for bin scanning), points balance, transaction history, voucher claiming.

**Existing mobile app:** `/Users/danieltan/mobius_smart_recycling_bin_ecosystem/mobile/` — has auth, role switching, route UI. Much of it broken or pointing at old schema.

**Approach:** Start from existing app structure but strip broken features. Rebuild screens one by one, each wired to real API endpoints. Every screen must work — no placeholder views.

**Key screens (public user):**
- Login (Sanctum)
- Home: points balance, streak, recent activity
- QR Code: generate user-specific QR code for bin scanning
- History: transaction list
- Vouchers: browse available, claim, my vouchers
- Profile: name, email, phone verified status

**Key screens (collector):**
- Pending routes
- Active route with map
- Stop completion with photo proof

**Dependencies:** Rabbit Hole 2 (APIs), existing mobile codebase for reference

---

### Rabbit Hole 8: Seeder Data — Make It Demo-Ready
**Status:** PARTIALLY DONE (schema project has seeder)
**Priority:** HIGH — the viva needs believable data
**Estimated effort:** 30 minutes

**What:** When the schema is ported, the seeder should create a complete demo scenario:
- 2 organizations (Starbucks MY, Mixue MY) with subscriptions and payments
- 4 brands (+ CHAGEE, ZUS to be added)
- Multiple outlets across Penang
- 4+ bins paired to outlets
- A public user (Mei Ling) with recycling history showing the points system working
- A collector (Kumar) with a completed route
- Voucher templates with allocations and at least one claim
- Both automatic and emergency pickup requests

This data must tell a STORY that the lecturer can follow during the demo.

**Dependencies:** Rabbit Hole 1 (schema ported)

---

### Rabbit Hole 9: Viva Demo Script
**Status:** NOT STARTED
**Priority:** HIGH — this is what gets the A
**Estimated effort:** 30 minutes to write, practice time on top

**What:** A step-by-step script for the viva demo that walks through every feature:
1. Show the landing page (corporate site)
2. Log in as admin → show dashboard
3. Show organizations, plans, subscriptions
4. Show bins with fill levels
5. Switch to bin client → demonstrate recycling flow
6. Show AI detection working (Roboflow + OpenAI)
7. Show points awarded with behavior + brand multipliers
8. Show voucher claiming
9. Show collector route optimization
10. Show the 12 viva fixes addressed

**Dependencies:** Everything above working

---

## Execution Order

```
Phase 1 (FOUNDATION):
  Rabbit Hole 1 → Schema Port
  Rabbit Hole 8 → Demo-Ready Seeder

Phase 2 (BACKEND):
  Rabbit Hole 2 → API Routes & Controllers
  Rabbit Hole 3 → Detection Pipeline Service
  Rabbit Hole 4 → Route Optimization Service

Phase 3 (FRONTEND — parallel):
  Rabbit Hole 5 → Web Admin (Daniel generates Skywork, CC wires)
  Rabbit Hole 6 → Bin Client (Daniel generates Skywork, CC wires)
  Rabbit Hole 7 → Mobile App (CC rebuilds)

Phase 4 (POLISH):
  Rabbit Hole 9 → Viva Demo Script
```

Phases 1-2 are sequential. Phase 3 tasks can run in parallel. Phase 4 is last.

---

## Decision Log

| Decision | Why | Date |
|----------|-----|------|
| Schema designed from scratch in clean room | Legacy schema was contaminated, organic growth | 2026-04-03 |
| Google Directions API over VROOM/OSRM | Simpler, real traffic, no Docker, free tier sufficient | 2026-04-03 |
| Roboflow hosted API over local inference | No GPU needed, just HTTP calls, premium subscription | 2026-04-03 |
| OpenAI Vision for brand detection | GPT-4o already knows brand logos, no training needed | 2026-04-03 |
| Skywork for React UI generation | Best domain understanding, best taste in testing | 2026-04-03 |
| Laravel stays as API backend | Already configured, Sanctum works, 50+ routes exist | 2026-04-03 |
| React SPAs replace Blade views | Full SPA reactivity, Skywork generates fast | 2026-04-03 |
| Functionality over form | Working CRUD > pretty broken UI | 2026-04-03 |
| Light mode only everywhere | User preference, high visibility, consistency | 2026-04-03 |

---

## Files Quick Reference

**Schema (source of truth):**
- Migration: `/Users/danieltan/mobius-schema/database/migrations/0001_01_01_000000_create_core_tables.php`
- Seeder: `/Users/danieltan/mobius-schema/database/seeders/DatabaseSeeder.php`
- Domain doc: `/Users/danieltan/mobius-schema/DOMAIN.md`

**Backend (to modify):**
- Routes: `backend/routes/api.php`
- Models: `backend/app/Models/*.php`
- Controllers: `backend/app/Http/Controllers/Api/*.php`
- Services: `backend/app/Services/*.php` (new)

**Frontends (to create):**
- Web admin: `web-admin/` (from Skywork)
- Bin client: `bin-client/` (from Skywork)

**Mobile (to update):**
- `mobile/Mobius/Sources/`

**Config:**
- `backend/.env` — all API keys
- `mobius-schema/.env` — reference copy of keys
