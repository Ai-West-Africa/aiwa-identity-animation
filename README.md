<img width="1280" height="640" alt="sparxstar_photon" src="https://github.com/user-attachments/assets/92c4f1dd-8cea-4273-87fe-3415c5d83412" />


SPARXSTAR Photon VCard
======================

**Version:** 0.6.0
**Status:** Production / Master Edition\
**Scope:** WordPress plugin (PHP + client-side JavaScript)
**Author** Starisian Technolog (Max Barrett)
**License** Starisian Technologies Proprietary

Copyright (c) 2025 Starisian Technologies. All rights reserved.

[![CodeQL](https://github.com/Starisian-Technologies/sparxstar-photon-vcard/actions/workflows/github-code-scanning/codeql/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-photon-vcard/actions/workflows/github-code-scanning/codeql)  [![Copilot code review](https://github.com/Starisian-Technologies/sparxstar-photon-vcard/actions/workflows/copilot-pull-request-reviewer/copilot-pull-request-reviewer/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-photon-vcard/actions/workflows/copilot-pull-request-reviewer/copilot-pull-request-reviewer)  [![Copilot coding agent](https://github.com/Starisian-Technologies/sparxstar-photon-vcard/actions/workflows/copilot-swe-agent/copilot/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-photon-vcard/actions/workflows/copilot-swe-agent/copilot)

[![Build & Release](https://github.com/Starisian-Technologies/sparxstar-photon-vcard/actions/workflows/build-release.yml/badge.svg)](https://github.com/Starisian-Technologies/sparxstar-photon-vcard/actions/workflows/build-release.yml)

* * * * *

Overview
--------

The **SPARXSTAR Photon VCard** plugin delivers a secure, accessible, and resilient digital business card overlay for WordPress/WooCommerce sites. It pulls contact data from ACF, WooCommerce billing fields, WordPress core, and Gravatar, then renders a polished card overlay that visitors can share as a `.vcf` file or transmit via AirDrop / Nearby Share.

The card overlay can be triggered via:

-   **Shortcode button** — place anywhere in content with `[spx_photon_vcard]`

-   **Device shake** — 3 acceleration events > 18 m/s² within 1.2 s

-   **Face-down flip** — single face-down orientation event (beta ≥ 145°)

-   **Touch fallback** — long-press anywhere on the page

-   **Keyboard fallback** — `Shift + V`

The system is designed for **legacy hardware**, **high-latency networks**, **accessibility compliance**, and **enterprise deployment constraints**, including kiosk and private browsing environments.

* * * * *

Design Principles
-----------------

-   **Fail-fast execution** --- no work is done unless explicitly permitted

-   **Zero external dependencies**

-   **Inline delivery** for low-bandwidth environments

-   **Battery-aware sensor usage**

-   **Accessibility-first modal behavior**

-   **Graceful degradation on broken or unavailable sensors**

-   **Auditor-friendly security model**

* * * * *

Shortcode
---------

Place the card trigger button anywhere in post/page content using the `[spx_photon_vcard]` shortcode.

```
[spx_photon_vcard]
[spx_photon_vcard text="View My Card"]
[spx_photon_vcard text="Share Contact" class="my-class" id="hero-card-btn"]
[spx_photon_vcard user_id="42" text="Jane's Card"]
```

### Attributes

| Attribute | Default | Description |
|-----------|---------|-------------|
| `text` | `"View My Card"` | Button label |
| `class` | `""` | Extra CSS classes added to the button |
| `id` | `""` | HTML `id` attribute on the button |
| `user_id` | Current logged-in user | WordPress user ID whose card data to display |

### Behaviour

-   The shortcode renders a `<button>` that opens the business card overlay for the specified user when clicked.

-   If the target user's `spx_display_business_card` ACF toggle is **off**, or their role is not permitted, the shortcode returns **an empty string** — no button is rendered and no assets are enqueued.

-   When any shortcode trigger is present on the page, the floating auto-injected button hides itself automatically to avoid duplication.

-   Multiple shortcodes with different `user_id` values on the same page are fully supported — each user's card data is loaded independently.

* * * * *

Data Layer
----------

Card data is resolved per user using the following priority chain:

1.  **WooCommerce billing meta** — `billing_company`, `billing_phone`, `billing_address_1/2`, `billing_city`, `billing_state`, `billing_postcode`, `billing_country`

2.  **ACF `spx_*` fields** — `spx_title`, `spx_work_phone`, `spx_mobile`, `spx_fax`, `spx_whatsapp_phone`, `spx_company`, `spx_address_*`, `spx_website`

3.  **WordPress core** — `display_name`, `user_email`, `user_url`

4.  **Gravatar** — profile photo derived from the user's email hash (512 px, `d=404`)

The enriched payload is localized to JavaScript as `window.SPX_PHOTON_VCARD_USERS[uid]`. When the plugin auto-enqueues for the current user (non-shortcode path), the default uid is also stored in `window.SPX_PHOTON_VCARD_DEFAULT`.

Phone entries carry a `type` field (`CELL`, `WORK`, or `FAX`) that maps directly to the vCard `TEL;TYPE=` attribute and determines the icon displayed in the overlay.

* * * * *

ACF Field Groups
----------------

The plugin registers two ACF local field groups automatically — no manual field creation is required.

### Base Group (always registered)

| Field key | Label | Type |
|-----------|-------|------|
| `spx_title` | Job Title | Text |
| `spx_work_phone` | Work Phone | Text (max 25 chars) |
| `spx_mobile` | Mobile | Text (max 25 chars) |
| `spx_fax` | Fax | Text (max 25 chars) |
| `spx_whatsapp_phone` | WhatsApp | Text (max 25 chars) |
| `spx_display_business_card` | Display Business Card | True/False |

### Fallback Group (registered only when WooCommerce is absent)

| Field key | Label | Type |
|-----------|-------|------|
| `spx_company` | Company | Text |
| `spx_address_1` | Address Line 1 | Text |
| `spx_address_2` | Address Line 2 | Text |
| `spx_city` | City | Text |
| `spx_state` | State / County | Text |
| `spx_postcode` | Postcode / ZIP | Text |
| `spx_country` | Country | Text |
| `spx_website` | Website URL | URL |

All fields are attached to the **User** post type and appear under the user profile in WP Admin.

To show a user's card, set **Display Business Card** to `true` on their profile. When `false`, neither the floating button nor any shortcode button will render for that user.

* * * * *

Business Card UI
----------------

The overlay is styled as a dark-gradient business card:

-   **Background:** `linear-gradient(145deg, #1c1c1e, #2c2c2e)`

-   **Profile photo:** circular Gravatar (60 × 60 px) with CSP-safe `error` fallback

-   **Identity block:** name, job title, company

-   **Contact rows:** tap-to-call phone numbers, WhatsApp deep-link (`wa.me`), `mailto:` email, website, formatted address

-   **QR section:** QR code encoding the full vCard 3.0 payload

-   **Action buttons:**

    -   **Download .vcf** — saves the vCard file to the device

    -   **Send via AirDrop / Nearby Share** — invokes `navigator.share({ files: [vcf] })` (OS share sheet); falls back to download when the Web Share Files API is unavailable

    -   **Enable Motion Trigger** — requests `DeviceOrientationEvent` and `DeviceMotionEvent` permissions (iOS 13+) so shake and flip triggers work

* * * * *

Trigger Methods
---------------

### 1\. Motion Triggers (Shake + Flip)

**Shake** (new):

-   Three `devicemotion` acceleration-delta events > 18 m/s² within a 1.2 s window trigger the overlay

-   Requires motion permission on iOS 13+ (requested via the "Enable Motion Trigger" button)

**Flip** (refined):

-   A single face-down `deviceorientation` event with `beta ≥ 145°` triggers the overlay

-   Stabilisation delay reduced; threshold lowered from 155° to 145°

Both triggers share the same `requestSensorAccess()` / `enableSensors()` permission flow. On iOS 13+, both `DeviceOrientationEvent.requestPermission()` and `DeviceMotionEvent.requestPermission()` are called; each listener is registered only when its permission is granted.

-   Automatically disabled when:

    -   Reduced motion is enabled

    -   Sensors are disabled server-side

    -   The overlay is active

### 2\. Touch Fallback (Always Available)

-   Long-press anywhere on the page

-   Includes:

    -   Scroll-movement guard

    -   Active-overlay suppression

-   Designed for:

    -   Broken sensors

    -   Desktop touch screens

    -   Low-end Android devices

### 3\. Keyboard Fallback (Desktop / Kiosk)

-   `Shift + V`

-   Automatically disabled while the modal is open

-   Includes `Escape` key handling to close the modal

* * * * *

Accessibility Compliance
------------------------

The runtime implements a **WCAG-aligned modal pattern**:

-   `role="dialog"`

-   `aria-modal="true"`

-   `aria-live="assertive"`

-   Keyboard focus trapping

-   Visible focus outlines

-   Escape key dismissal

-   Respects `prefers-reduced-motion`

No animation or motion is required for operation.

* * * * *

Security Model
--------------

### Data Handling

-   Only the following fields are exposed client-side:

    -   Name, job title, company

    -   Phone numbers (CELL, WORK, FAX), WhatsApp

    -   Email, website

    -   Address (street, city, state, postcode, country)

    -   Gravatar photo URL

    -   Logo URL

-   Data is JSON-encoded with hex escaping

-   No cookies are used

-   No PII is persisted beyond runtime memory

### XSS Prevention

-   All text is escaped via DOM text nodes

-   URLs are strictly validated to allow only:

    -   `http`

    -   `https`

    -   `mailto`

    -   `tel`

-   Invalid or unsafe URLs are silently discarded

* * * * *

Storage Safety
--------------

The runtime uses a guarded local storage accessor:

-   Prevents crashes in:

    -   Private browsing mode

    -   Locked kiosk environments

    -   Restricted WebViews

-   Storage failures fail silently and do not block execution

* * * * *

Sensor Lifecycle & Battery Hygiene
----------------------------------

-   Sensors are:

    -   Bound only after permission is granted

    -   Unbound when the overlay is active

    -   Rebound only if motion is allowed on close

-   Orientation events are throttled to ~10Hz

-   Stabilization prevents false positives on noisy hardware

* * * * *

Analytics Contract
------------------

The runtime emits custom DOM events without requiring analytics vendors.

### Events Dispatched

-   `vip-card-event`

    -   type: `open`

    -   method: `motion | shake | shortcode | touch | keyboard`

    -   timestamp

-   `vip-card-event`

    -   type: `close`

    -   timestamp

These events can be consumed by any analytics or logging system without modifying the runtime.

* * * * *

Server-Side Control
-------------------

Sensor usage can be disabled **entirely** via a WordPress filter before JavaScript execution.

This allows compliance with:

-   Government environments

-   Education systems

-   Privacy-restricted deployments

Fallback triggers remain functional when sensors are disabled.

* * * * *

Browser & Device Support
------------------------

Tested and designed for:

-   Android (low-end and legacy devices)

-   iOS Safari

-   Desktop Chrome / Firefox

-   Kiosk and embedded WebViews

-   High-latency mobile networks

Graceful degradation is guaranteed when features are unavailable.

* * * * *

What This Runtime Does NOT Do
-----------------------------

-   No external network requests

-   No tracking pixels

-   No third-party scripts

-   No framework usage

-   No persistent UI elements

-   No automatic activation without user intent

* * * * *

Intended Usage
--------------

This plugin is intended to be:

-   Installed on WordPress / WooCommerce sites

-   Loaded only on authorized pages (per-user `spx_display_business_card` toggle)

-   Used by authenticated users

-   Governed by server-side role permissions (configurable via the `spx_photon_vcard_allowed_roles` filter)

-   Governed by server-side permissions

It is **not** designed as a standalone library.

* * * * *

License & Deployment
--------------------

This runtime is designed for **controlled distribution** and enterprise deployment.

Usage, redistribution, and modification should follow the licensing terms defined by the parent project.

* * * * *

Final Notes
-----------

This implementation prioritizes **real-world reliability** over abstraction.

If something fails:

-   It fails silently

-   It does not block the page

-   It does not degrade accessibility

-   It does not leak data

That behavior is intentional.
