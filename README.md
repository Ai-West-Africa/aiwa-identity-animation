SPARXSTAR Photon VCard
=====================+

**Version:** 3.1.0\
**Status:** Production / Master Edition\
**Scope:** Client-side runtime (inline JavaScript)
**Author** Starisian Technolog (Max Barrett)
**License** Starisian Technologies Proprietary

Copyright (c) 2025 Starisian Technologies. All rights reserved.

* * * * *

Overview
--------

The **SPARXSTAR Photon VCard** runtime provides a secure, accessible, and resilient digital business card overlay that can be triggered via:

-   Device motion (face-down double-flip)

-   Touch fallback (long-press)

-   Keyboard fallback (Shift + V)

The system is designed for **legacy hardware**, **high-latency networks**, **accessibility compliance**, and **enterprise deployment constraints**, including kiosk and private browsing environments.

This JavaScript runtime is injected inline by WordPress PHP and intentionally avoids external dependencies, libraries, or build tooling.

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

Trigger Methods
---------------

### 1\. Motion Trigger (Primary)

-   Activated by a **double face-down gesture**

-   Uses `deviceorientation` with:

    -   Noise stabilization

    -   Angle thresholding

    -   Time-window validation

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

    -   Name

    -   Title

    -   Phone

    -   Email

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

    -   method: `motion | touch | keyboard`

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

This JavaScript runtime is intended to be:

-   Injected inline by WordPress

-   Loaded only on authorized pages

-   Used by authenticated users

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
