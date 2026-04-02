Build a React + TypeScript admin dashboard SPA for "Mobius" — a smart recycling bin management platform for beverage cups. Vite + React Router + Tailwind CSS + shadcn/ui. Light mode only. Every button must work, every route must be functional. No placeholder pages.

The domain knowledge, database schema, and seeder data are attached. Read them carefully — every table, every column, every relationship maps to a page or feature in this dashboard.

## Pages needed:

**Dashboard** — Overview stats and recent activity.

**Organizations** — List all, view detail, create. Registration Requests queue (approve/reject with notes).

**Users** — List all with role/org filters. Invitations queue (approve/reject).

**Brands** — List all under orgs. View/edit with logo, multiplier.

**Outlets** — List by brand or store owner. View bins at each outlet.

**Bins** — List all with status/fill level. Pairing flow: enter serial number, select outlet, activate. Show sensor data.

**Bin Sessions** — Table showing session history. User linked or anonymous, items detected, behavior quality, points earned.

**Detection Events** — Table with waste type, input method, brand detected, confidence, image.

**Pickup Requests** — Queue of automatic (threshold) and emergency (store owner) requests. Assign to collector.

**Collection Routes** — Routes with stops, distance, duration, status. Route detail shows ordered stops.

**Plans & Subscriptions** — CRUD for service plans. View org subscriptions with contract dates.

**Payments** — Payment history table.

**Vouchers** — Templates (create by brand), Allocations (quota per outlet), Claims (user redemption history).

Login page with email/password. Sidebar navigation. Role-based: this dashboard is for admins, but brand owners and store owners should be able to log in and see filtered views of their own data.
