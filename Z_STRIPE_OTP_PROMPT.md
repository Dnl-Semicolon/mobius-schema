# Claude Code Session: Stripe Payment Gateway + OTP Phone Verification

> PASTE THIS ENTIRE FILE as your first message to a new Claude Code session.
> Use: /effort max, /using-superpowers

## WHO YOU ARE

You are continuing work on the Mobius Smart Recycling Bin Ecosystem. A previous CC session built the schema, APIs, detection pipeline, and bin client. You are picking up two remaining viva fixes.

## BEFORE YOU TOUCH ANY CODE

Read these files IN ORDER. Do not skip any:

1. `/Users/danieltan/mobius-schema/DOMAIN.md` — full system context, ground rules, business logic
2. `/Users/danieltan/mobius-schema/MASTER_PLAN.md` — rabbit holes, architecture, what's done vs not
3. `/Users/danieltan/mobius-schema/VIVA_FIXES.md` — the 12 lecturer complaints that must be fixed
4. `/Users/danieltan/mobius_smart_recycling_bin_ecosystem/backend/.env` — check what API keys exist
5. `/Users/danieltan/mobius_smart_recycling_bin_ecosystem/backend/routes/api.php` — current API routes
6. `/Users/danieltan/mobius_smart_recycling_bin_ecosystem/backend/routes/web.php` — current web routes
7. `/Users/danieltan/mobius_smart_recycling_bin_ecosystem/backend/database/migrations/2026_04_03_000000_rebuild_schema_from_clean_room.php` — THE schema

## GROUND RULES (FROM DOMAIN.md — NON-NEGOTIABLE)

1. **NEVER run git commands.** Daniel handles all git. Always remind him to commit.
2. **NEVER run `migrate:fresh` or `db:seed` on the main backend.** The database already has the new schema with seeded data. If you need to add data, use tinker or create a new migration.
3. Light mode only. No dark mode anywhere.
4. Functionality over form. Every button must work.
5. Run `vendor/bin/pint --dirty` after PHP changes.

## TASK 1: Stripe Payment Gateway (Viva Fix — lecturer wants sandbox payment)

### Context:
The schema has `plans`, `subscriptions`, and `payments` tables. Organizations subscribe to plans (Basic RM299/mo, Pro RM599/mo, Enterprise RM999/mo). The seeder already has 2 orgs with subscriptions and payment records.

What's MISSING is an actual Stripe Checkout flow where:
- An organization can select a plan on the web
- They're redirected to Stripe Checkout (sandbox/test mode)
- On success, a subscription + payment record is created in our database
- On the admin dashboard, payments show up

### What to build:

1. **Install Laravel Cashier** if not already installed: `composer require laravel/cashier`
   - BUT CHECK FIRST: the backend is Laravel 12, Cashier v16. Read the docs using search-docs tool.
   - Cashier might want its own subscriptions table — we already have one. You may need to either:
     - Use Cashier's built-in subscription management (let it handle the table), OR
     - Use Stripe's API directly via `Http::` facade (simpler, no table conflict)
   - **RECOMMENDATION: Use Stripe API directly.** Don't use Cashier. It's simpler for a demo and avoids table conflicts.

2. **Stripe Setup:**
   - Daniel needs to create a Stripe account at https://dashboard.stripe.com (if not done)
   - Get test keys from Stripe Dashboard → Developers → API Keys
   - Add to `.env`:
     ```
     STRIPE_KEY=pk_test_...
     STRIPE_SECRET=sk_test_...
     ```
   - Create Products/Prices in Stripe Dashboard matching our plans (or create them via API)

3. **Stripe Checkout Flow:**
   - `POST /api/v1/stripe/checkout` — takes `plan_id` and `organization_id`, creates a Stripe Checkout Session, returns the checkout URL
   - Stripe redirects to `GET /stripe/success?session_id=...` — verify payment, create subscription + payment records in our DB
   - Stripe redirects to `GET /stripe/cancel` — show cancellation message
   - Create a `StripeController` for this

4. **On the web/landing page:**
   - The pricing section's "Get Started" buttons should link to the checkout flow
   - After successful payment, the org's subscription status updates

5. **For the demo:**
   - Use Stripe TEST mode (pk_test_, sk_test_)
   - Test card: 4242 4242 4242 4242, any future expiry, any CVC
   - The checkout page is hosted by Stripe (no custom form needed)

### Files to create/modify:
- `app/Http/Controllers/Api/StripeController.php` — NEW
- `config/services.php` — add stripe config
- `routes/api.php` — add stripe routes
- `routes/web.php` — add success/cancel callback routes
- `.env` — add STRIPE_KEY and STRIPE_SECRET (ask Daniel for his test keys)

---

## TASK 2: OTP Phone Verification (Viva Fix #9)

### What the schema has:
- `users.phone` — phone number field
- `users.phone_verified_at` — nullable timestamp (null = not verified)

### What needs to be built:
- An OTP verification flow: user enters phone → system sends 6-digit OTP → user enters OTP → phone_verified_at set
- For the demo/FYP, use a **SIMULATED OTP approach** — don't spend money on Twilio:
  - **Option A (recommended):** Log the OTP to Laravel's log file (`storage/logs/laravel.log`) and display a flash message saying "OTP sent to your phone" — the "OTP" is in the log for demo purposes
  - **Option B:** Use Mailtrap (already configured in .env) to send the OTP via email instead of SMS — explain to lecturer that "in production this would be SMS via Twilio, we're using email for the demo"
  - **Option C:** Show the OTP directly on screen in a dismissable alert (fastest for demo)
- Needs: a controller, a simple Blade form (enter phone → enter OTP), rate limiting, and the database update
- Should be accessible from the user's profile page
- Brand owners specifically need phone_verified_at to be set (per DOMAIN.md)

### Implementation:
1. Create `app/Http/Controllers/OtpController.php`
2. Methods:
   - `showForm()` — Blade view with phone input
   - `sendOtp(Request $request)` — generate 6-digit code, store in cache (Cache::put("otp:{$phone}", $code, 300)), log it, redirect to verify form
   - `showVerifyForm()` — Blade view with OTP input
   - `verify(Request $request)` — check code against cache, if match → set phone_verified_at, redirect with success
3. Routes in `routes/web.php` (auth middleware):
   - `GET /phone/verify` → showForm
   - `POST /phone/send-otp` → sendOtp
   - `GET /phone/verify-code` → showVerifyForm
   - `POST /phone/verify-code` → verify
4. Rate limit: max 3 OTP sends per 10 minutes

### Files to create/modify:
- `app/Http/Controllers/OtpController.php` — NEW
- `resources/views/phone/verify.blade.php` — NEW (phone input form)
- `resources/views/phone/verify-code.blade.php` — NEW (OTP input form)
- `routes/web.php` — add routes

---

## TASK 3: Seed More Realistic Data for Admin Dashboard

### Problem:
The current seeder creates minimal data (2 orgs, 2 brands, 4 bins). The admin dashboard looks empty. We need more records that tell a demo story.

### What to add (via a new seeder class or tinker):
- Add CHAGEE and ZUS Coffee as brands (with new organizations: "Chagee Malaysia Sdn Bhd" and "ZUS Coffee Sdn Bhd")
- Add Lucky Cup as a brand (org: "Lucky Cup Malaysia Sdn Bhd")
- Add outlets for each new brand (at least 1 each, Penang locations)
- Add bins at each outlet
- Add more users (public users with recycling history)
- Add more detection events showing different waste types, brands, behavior multipliers
- Add more recycling transactions showing the points system working
- Add voucher claims (at least one user claiming a voucher)
- The data should demonstrate the ENTIRE flow: registration → org → brand → outlet → bin → session → detection → points → voucher claim

### Important: Do NOT run migrate:fresh. Create a supplementary seeder:
```bash
php artisan make:seeder DemoDataSeeder
```
Then run: `php artisan db:seed --class=DemoDataSeeder`

---

## APPROACH

1. Read ALL the files listed at the top first
2. Check the current state of the codebase — what routes exist, what views exist
3. **Plan using superpowers before touching any code** — use the writing-plans skill
4. For Stripe: ask Daniel for his Stripe test keys before starting
5. For OTP: use Option A (log to file) for fastest implementation
6. For demo data: create a DemoDataSeeder, run it once
7. After each task, run `vendor/bin/pint --dirty`
8. Tell Daniel to commit after each working checkpoint
9. Test everything works by hitting the routes in the browser/API

## WHAT SUCCESS LOOKS LIKE

- Admin logs in → sees populated dashboard with orgs, bins, detections, payments
- An org can go through Stripe Checkout → payment recorded in our DB
- A user can verify their phone via OTP → phone_verified_at is set
- All 12 viva fixes are addressed (the previous session handled 1-8, 10-12; you handle 5 + 9)
