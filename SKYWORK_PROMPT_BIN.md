Build a React + TypeScript kiosk-style web application for a smart recycling bin. Vite + Tailwind CSS. Light mode. This app is the "brain" of one recycling bin — it runs in a browser, uses the computer's webcam, and guides users through recycling cups, lids, and straws.

The domain knowledge, database schema, and seeder data are attached. This bin client talks to a Laravel API backend.

## How the bin works:

The bin has separate input slots: cup slot, lid slot, straw slot, wash basin, and a general intake for people who throw everything in at once. Each slot has a webcam that detects what was placed in it. Users can scan their phone's QR code on the bin's camera to earn points.

## Key screens/states:

**Idle** — Webcam feed live, waiting for interaction. Shows bin info (serial number, fill level, items today). Inviting prompt to recycle.

**Input Slot Selection** — User selects which slot they're using (cup, lid, straw, general intake). Wash basin toggle for rinsing.

**Detection** — Camera captures frame, sends to AI API, shows result with bounding box overlay. Shows detected waste type and confidence. If it's a cup, shows detected brand.

**Points Preview** — Shows calculation: base points × behavior multiplier × brand multiplier = total. Visual feedback for "Gold Standard" (separated + rinsed) vs "Basic" (general intake).

**QR Scan** — Camera mode switches to scan user's QR code from their mobile app. When scanned, shows welcome message with user's name.

**Session Summary** — When session ends: items recycled, behavior quality, brand matches, total points earned. Thank you message. Returns to idle.

**Admin/Setup Panel** — Behind a settings icon with PIN. Configure serial number, API endpoint, camera selection. Pair this bin to an outlet. View sensor states and connection status.

The webcam feed should be prominent. Large touch-friendly buttons. Clean, professional, institutional feel — like a self-checkout machine or an airport kiosk. Smooth transitions between states.
