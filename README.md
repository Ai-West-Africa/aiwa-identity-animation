AiWA Identity Animation
=======================

**Version:** 1.0.0
**Status:** Active Development
**Scope:** WordPress Gutenberg block plugin
**Author:** AiWA – Ai West Africa
**License:** GPL-2.0-or-later

---

Overview
--------

**AiWA Identity Animation** is a WordPress Gutenberg block plugin that renders
an animated brand identity display for AiWA – Ai West Africa.

The block outputs bold brand text with a cycling subtitle that fades or slides
between configurable words (e.g. "Ai West Africa", "Intelligence for Africa").
All animation is progressively enhanced — the block degrades gracefully to
styled static text on low-end or older mobile devices (Android 4.4+, circa
2014), respects `prefers-reduced-motion`, and requires no JavaScript to display
content.

Block Features
--------------

Add the **AiWA Identity Animation** block from the Gutenberg inserter and
configure it in the inspector panel:

| Control | Description |
|---|---|
| **Main Text** | Primary brand text displayed prominently (default: `AiWA`) |
| **Cycling Words** | Words that cycle below the main text on the frontend |
| **Animation Style** | `Fade`, `Slide Up`, or `None (Static)` |
| **Font Size** | Small (2em) / Medium (3em) / Large (4.5em) / Extra Large (6em) |
| **Main Text Colour** | HEX colour for the primary text |
| **Accent Colour** | HEX colour for the cycling subtitle words |
| **Background Colour** | Block background colour |

The block also supports WordPress spacing (padding/margin) and wide/full
alignment out of the box.

Installation
------------

### From Release ZIP

1. Download the latest release ZIP from the [Releases](../../releases) page.
2. Go to **Plugins → Add New → Upload Plugin** in your WordPress admin.
3. Upload and activate the plugin.

### From Source

```bash
git clone https://github.com/AiWA-Ai-West-Africa/aiwa-identity-animation.git
cd aiwa-identity-animation
npm install
npm run build
```

Then upload the folder to `wp-content/plugins/` or symlink it there.

Requirements
------------

- WordPress 6.3+
- PHP 7.4+
- Node.js 20+ (for building from source only)

Graceful Degradation
--------------------

| Capability | Behaviour |
|---|---|
| Modern browser + JS | Full cycling animation with CSS keyframe transitions |
| `prefers-reduced-motion` | Animation disabled; first word shown statically |
| No JS / very old browser | Styled static text — main text + first cycling word |
| Extremely old device | CSS layout still renders readable text |

Development
-----------

```bash
npm run build       # Compile block assets to build/
npm run start       # Watch mode
npm run lint        # ESLint + Stylelint
npm run format      # Prettier auto-format
```

License
-------

GPL-2.0-or-later — see [LICENSE.md](LICENSE.md)
