# Mobius Smart Recycling Bin Ecosystem — Domain Knowledge

> This document is the single source of truth for any Claude Code instance working on this project.
> Read this BEFORE touching any code. Written 2026-04-03, the night before the third viva attempt.

## What Is Mobius?

Mobius is a smart recycling bin ecosystem for beverage cups. Companies (Starbucks, Mixue, etc.) register with Mobius, deploy smart bins at their outlets, and customers earn points by recycling properly. The system rewards good recycling behavior with points redeemable for vouchers.

The project is Daniel's Final Year Project (FYP). The viva has been attempted twice — first time a rogue CC instance ran unauthorized git commands and deleted the user schema during the presentation. Second time, too many bugs, core tech not working. Third attempt is the final chance.

## Ground Rules (NEVER VIOLATE)

1. **NEVER run git commands.** Daniel handles all git. Always remind him to commit.
2. **NEVER run `migrate:fresh` or `db:seed` on the MAIN backend project.** The main database has data from a Python script.
3. The `mobius-schema` project at `/Users/danieltan/mobius-schema` is the CLEAN ROOM for schema design. You CAN run migrations there.
4. **NEVER look at the legacy/old schema for inspiration.** It will contaminate your thinking. Design from the domain, not from what existed before.
5. **Every piece of UI must work.** No placeholder pages, no "under construction" buttons. If it doesn't work, don't build the UI for it.

## Architecture

- **Backend:** Laravel 12 at `/Users/danieltan/mobius_smart_recycling_bin_ecosystem/backend`
- **Mobile:** SwiftUI iOS at `/Users/danieltan/mobius_smart_recycling_bin_ecosystem/mobile`
- **AI/CV:** Python at `/Users/danieltan/mobius_smart_recycling_bin_ecosystem/backend/ai`
- **Schema project:** `/Users/danieltan/mobius-schema` (clean room, one migration, one seeder)
- **Bin client:** React web app (each bin = a terminal process + React UI on its own port)
- **Web admin + corporate site:** To be built with Skywork/Lovable/V0.dev, then wired to backend by CC

## User Roles

| Role | What they do | Where they log in |
|------|-------------|-------------------|
| Admin | Manages everything — orgs, users, plans, bins, zones | Web admin |
| Brand Owner | Regional manager for an org — manages brands, outlets, invites store owners, manages vouchers | Web admin |
| Store Owner | Manages one or more outlets — monitors bins, views stats, requests pickups | Mobile app |
| Collector | Collects full bins along optimized routes | Mobile app |
| Public User | Recycles at bins, earns points, claims vouchers | Mobile app |

## The Organization Hierarchy

```
Organization (Starbucks Malaysia Sdn Bhd)
├── Brand (Starbucks)
│   ├── Outlet (Gurney Plaza) — managed by Store Owner (Jenny)
│   │   ├── Bin MBS-SB-001
│   │   └── Bin MBS-SB-002
│   └── Outlet (Queensbay Mall) — managed by Store Owner (Wei Ming)
│       └── Bin MBS-SB-003
└── Users
    ├── Brand Owner (Sarah Lee)
    ├── Store Owner (Jenny Wong)
    └── Store Owner (Lim Wei Ming)
```

One org can have multiple brands. Brands have outlets. Outlets have bins. The bin's brand comes from its outlet's brand — no separate brand_id on bins.

## Registration & Onboarding Flow

1. Company submits registration request (public form) OR admin creates org directly
2. Admin reviews and approves → Organization created
3. Brand owner account created → credentials sent
4. Brand owner invites store owners → admin approves → account created
5. OTP verification required for all phone numbers (`phone_verified_at`)

## The Bin — Hardware Simulated as Software

### What a bin IS:
- A React web app (the bin's "screen")
- Running as a terminal process (each terminal tab = one bin)
- Uses the computer's webcam for detection + QR scanning
- Authenticates with the backend via `api_token` (generated at pairing)
- Reports sensor data (fill level, weight, IR sensors)

### Bin lifecycle:
`unpaired` → admin pairs it (assigns serial number + outlet + generates api_token) → `active` → `maintenance` → `offline`

### Bin hardware simulation:
- **IR sensors** at 4 heights (25%, 50%, 75%, 100%) — stored as `sensor_levels` JSON
- **Load cell** — stored as `weight_grams`
- **Camera** — computer's webcam, toggles between item detection and QR scanning
- **Fill level** — cached percentage derived from sensor readings, NOT the primary measurement
- **Capacity** — `capacity_liters` (default 20L)

### Bin input areas (the washing cups model):
The bin is NOT one hole. It's a station with designated inputs:
- **Cup slot** — for the clean cup only
- **Lid slot** — for the lid
- **Straw slot** — for the straw
- **Wash basin** — user dumps liquid and rinses the cup here
- **General intake** — for people who throw everything in together

Each slot maps to `input_method` on detection events.

## The McDonald's QR Model

The bin does NOT have a QR code. The USER has the QR code (generated in their mobile app). The bin has a QR SCANNER (the webcam). The user walks up, opens their app, shows their QR code to the bin's camera, and the session gets linked to their account.

Sessions start anonymous. The user can scan their QR at ANY point during the session. If they never scan, the recycling still happens but no points are earned.

## Bin Sessions & Detection Events

### Session flow:
1. Someone approaches the bin → session starts (anonymous)
2. Items are deposited through designated slots
3. For each item, the camera takes a snapshot → one detection event
4. User can scan their QR code at any point → session links to their account
5. Session ends (exit button, timeout, or premature termination)
6. If user is linked → points calculated → transaction created

### Detection events:
One camera snapshot = one item = one row. Each detection event records:
- `waste_type`: paper_cup, plastic_cup, lid, straw, napkin, liquid_waste
- `input_method`: cup_slot, lid_slot, straw_slot, general_intake
- `detected_brand_id`: the cup brand the AI saw (nullable — only cups have visible brands)
- `confidence`: 0-100 AI confidence score
- `image_path`: the actual photo
- `ai_output`: JSON blob with raw model response (bounding boxes, probabilities, etc.)

### Brand inheritance:
Lids and straws don't have visible branding. If a branded cup is detected in the same session, the lid and straw inherit that brand. This is business logic, not a schema constraint.

## The Points System — Dual Multiplier

`total_points = base_item_points × behavior_multiplier × brand_multiplier`

### Base points per item:
| Item | Points |
|------|--------|
| paper_cup | 15 |
| plastic_cup | 12 |
| lid | 5 |
| straw | 3 |
| napkin | 2 |
| liquid_waste | 8 |

### Behavior multiplier (recycling quality):
| Behavior | Multiplier | How detected |
|----------|-----------|-------------|
| Separated + rinsed | 2.0x | All items through proper slots + `cup_rinsed = true` |
| Separated, no rinse | 1.5x | All items through proper slots, `cup_rinsed = false` |
| Partial separation | 1.2x | Some items through slots, some through general |
| No separation | 1.0x | Everything through `general_intake` |

### Brand multiplier (brand loyalty):
- Cup brand matches bin's outlet brand → `brand.point_multiplier` (e.g., 1.5x for Starbucks)
- Cup brand doesn't match → 1.0x
- Cup brand not detected → 1.0x

### Example (gold standard):
```
Starbucks cup (15) + lid (5) + straw (3) = 23 base
× 2.0 (separated + rinsed)
× 1.5 (Starbucks cup at Starbucks bin)
= 69 points
```

### Example (lazy):
```
Cup only (15) via general intake
× 1.0 (no separation, no rinse)
× 1.5 (brand match still detected)
= 22 points
```

## Voucher System

1. **Brand HQ creates voucher templates** — "RM5 off any drink", costs 100 points, valid until X date
2. **Each outlet gets a quota allocation** — Gurney gets 50 vouchers, Queensbay gets 30
3. **Users claim against the quota** — spend points, get voucher, quota decreases
4. **Voucher has its own expiry** after claiming
5. **Budget runs out or expires** = no more claims from that outlet

## Pickup Requests (concept — tables deferred)

Two triggers:
1. **Automatic:** fill_level hits threshold (e.g., 75%) → system creates pickup request
2. **Emergency:** store owner reports contamination/issue → request to admin → admin handles it → pickup created regardless of fill level

Emergency requests need: `request_type`, `requested_by`, `reason` fields.

## Route Optimization (NOT YET DESIGNED)

Route tables are DEFERRED. The approach (VROOM, Google Routes API, OpenRouteService, or something else) needs to be researched first. Do NOT copy from the legacy schema. Design fresh after research.

Key requirements:
- Multi-stop optimization with real traffic data
- Dynamic re-routing (new bin fills up mid-route → route recalculates)
- Collector gets notified of route changes
- Picture proof at each stop
- GPS audit trail for verification
- Data should be good for generating reports

## Service Plans & Contracts

Tiered plans (Basic/Pro/Enterprise). Organizations subscribe. Subscriptions track:
- `starts_at`, `ends_at`, `renews_at`
- Status: active, past_due, cancelled, expired
- Payments table records actual money (amount, method, reference number)

This addresses viva fixes #5 (catalogue + payment), #6 (contract end), #7 (renewal date).

## Viva Fixes — The 12 Commandments

| # | Fix | Status |
|---|-----|--------|
| 1 | CV inaccurate | Retrain model, improve dataset |
| 2 | 45° camera angle | Training images at 45°, adjust camera |
| 3 | Width/height for cup vs lid | Add dimension features to model |
| 4 | More training images | Real images + AI augmentation |
| 5 | Catalogue + payment module | ✅ plans + subscriptions + payments |
| 6 | Detect contract end | ✅ subscriptions.ends_at |
| 7 | Renewal date | ✅ subscriptions.renews_at |
| 8 | Bin owner | ✅ bins → outlets → users → organizations |
| 9 | Brand owner OTP | ✅ users.phone_verified_at |
| 10 | Reward budget + expiry | ✅ voucher_allocations.quota + valid_until |
| 11 | Route optimization | DEFERRED — research best approach first |
| 12 | Store owner app ↔ real bin | Integration after schema port |

## UI Strategy

- **Web admin + corporate site:** Build with Skywork/Lovable/V0.dev (React SPA), then CC wires to backend
- **Mobile app (SwiftUI):** Redo from scratch, conforming to APIs and schema. Functionality over aesthetics.
- **Bin client (React):** Each bin = terminal process + React web app on its own port

## Schema Location

The clean schema lives at:
- Migration: `/Users/danieltan/mobius-schema/database/migrations/0001_01_01_000000_create_core_tables.php`
- Seeder: `/Users/danieltan/mobius-schema/database/seeders/DatabaseSeeder.php`
- Database: `mobius_schema` (MySQL, root@127.0.0.1:3306, no password)

19 domain tables. Route tables will be added after research. Porting to main backend is a separate decision.
