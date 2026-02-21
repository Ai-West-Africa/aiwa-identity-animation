(function () {
'use strict';

// 1) SINGLETON LOCK (Prevent multi-injection issues)
if (window.__SPAX_PHOTON_CARD_LOADED__) return;
window.__SPAX_PHOTON_CARD_LOADED__ = true;

// 2) WordPress-provided data (via wp_localize_script)
// Expected global: window.SPAX_PHOTON_VCARD_DATA = { name, title, phone, email, logo, noSensor? }
const userData = (window.SPAX_PHOTON_VCARD_DATA && typeof window.SPAX_PHOTON_VCARD_DATA === 'object')
? window.SPAX_PHOTON_VCARD_DATA
: {};

class SpaxPhotonVCard {
constructor() {
this.config = {
threshold: 155,        // beta threshold for "flat/face-down" trigger
resetTime: 2500,       // tap sequence reset
cooldown: 5000,        // lockout after close
stabilize: 150,        // stabilization window (ms)
longPress: 800,        // long press duration (ms)
sensorFps: 10,         // throttle orientation (fps)
sensorAutoDisable: 30000, // battery saver (ms)
gammaThreshold: 20,    // max gamma for face-down detection (degrees)
permissionTimeout: 2000   // iOS permission promise timeout (ms)
};

this.state = {
taps: 0,
lastTapTime: 0,
lastCloseTime: 0,
isFaceDown: false,
isActive: false,
sensorBound: false,
scrollPos: 0
};

this.timers = {
sensorTick: 0,
stabilizer: null,
longPress: null,
sensorAutoDisable: null
};

this.boundOrientationHandler = null;
this.wakeLock = null;
this.lastFocus = null;

this.init();
}

/* ---------------------------
   INIT
---------------------------- */

init() {
const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Motion Setup:
// - only if not reduced motion
// - only if not disabled by WP data (noSensor)
// - only if not low-end device
if (!reducedMotion && !userData.noSensor && !this.isLowEndDevice()) {
this.setupMotion();
}

// Fix 3: release wake lock on page hide to prevent leak on navigation/refresh
window.addEventListener('pagehide', () => this.releaseWakeLock());

// Fallback triggers always active
this.setupFallbacks();
}

isLowEndDevice() {
// Conservative: disable motion on low-end hardware for better UX
const hc = navigator.hardwareConcurrency;
const dm = navigator.deviceMemory;
return (typeof hc === 'number' && hc <= 4) || (typeof dm === 'number' && dm <= 2);
}

/* ---------------------------
   SAFE STORAGE (kiosk/private mode)
---------------------------- */

storage(key, val = null) {
try {
if (val !== null) localStorage.setItem(key, String(val));
else return localStorage.getItem(key);
} catch (e) {
return null;
}
}

/* ---------------------------
   MOTION SENSORS
---------------------------- */

setupMotion() {
// If previously enabled, request on first user gesture (needed on iOS anyway)
const isEnabled = this.storage('spax_photon_motion_enabled') === 'true';

if (isEnabled) {
document.addEventListener('touchstart', () => this.requestSensorAccess(), { once: true, passive: true });
} else {
this.renderSetupButton();
}
}

renderSetupButton() {
// Uses your existing CSS: #spax-photon-sensor-grant
// Don't show if sensors are already active
if (this.state.sensorBound) return;
if (document.getElementById('spax-photon-sensor-grant')) return;

const btn = document.createElement('button');
btn.id = 'spax-photon-sensor-grant';
btn.type = 'button';
btn.textContent = 'Activate Card';
btn.addEventListener('click', () => {
this.requestSensorAccess();
btn.remove();
}, { passive: true });

document.body.appendChild(btn);
}

async requestSensorAccess() {
if (this.state.sensorBound) return;

try {
// iOS 13+ permission model
if (typeof DeviceOrientationEvent !== 'undefined' &&
typeof DeviceOrientationEvent.requestPermission === 'function') {
// Fix 10: timeout fallback in case permission promise never resolves
const timeout = setTimeout(() => this.renderSetupButton(), this.config.permissionTimeout);
const response = await DeviceOrientationEvent.requestPermission();
clearTimeout(timeout);
if (response === 'granted') this.enableSensors();
} else {
// Android/others
this.enableSensors();
}
} catch (e) {
// Fallbacks remain active
}
}

enableSensors() {
if (this.state.sensorBound) return;

this.boundOrientationHandler = this.handleOrientation.bind(this);
window.addEventListener('deviceorientation', this.boundOrientationHandler, { passive: true });

this.state.sensorBound = true;
this.storage('spax_photon_motion_enabled', 'true');

// Remove stale activation button (may exist if the permission timeout fired first)
const grantBtn = document.getElementById('spax-photon-sensor-grant');
if (grantBtn) grantBtn.remove();

// Battery saver: disable after inactivity window
this.resetSensorAutoDisable();

this.vibrate(40);
}

disableSensors(clearStorageFlag = true) {
if (!this.state.sensorBound) return;
window.removeEventListener('deviceorientation', this.boundOrientationHandler);
this.state.sensorBound = false;
// Only clear the permission flag for user-initiated disables (openCard/closeCard paths).
// Auto-disable (battery saver) passes false so the flag stays 'true' and the next
// page load can auto-request on touchstart without showing the activation button.
if (clearStorageFlag) {
this.storage('spax_photon_motion_enabled', 'false');
}

if (this.timers.sensorAutoDisable) {
clearTimeout(this.timers.sensorAutoDisable);
this.timers.sensorAutoDisable = null;
}
}

resetSensorAutoDisable() {
if (this.timers.sensorAutoDisable) clearTimeout(this.timers.sensorAutoDisable);
this.timers.sensorAutoDisable = setTimeout(() => {
// Battery-saver auto-disable: preserve the permission flag so next page load
// does not show the activation button unnecessarily.
this.disableSensors(false);
}, this.config.sensorAutoDisable);
}

handleOrientation(event) {
if (this.state.isActive) return;
if (!this.state.sensorBound) return;

// Reset battery saver when there's sensor activity
this.resetSensorAutoDisable();

// THROTTLE to reduce CPU on older devices
const now = Date.now();
const minDelta = Math.floor(1000 / this.config.sensorFps);
if (now - this.timers.sensorTick < minDelta) return;
this.timers.sensorTick = now;

const beta  = Math.abs(event.beta || 0);
// Fix 2: add gamma stability check to reduce false positives (pocket/walking/table)
const gamma = Math.abs(event.gamma || 0);
const isFlat = beta > this.config.threshold && gamma < this.config.gammaThreshold;

// Stabilization
if (isFlat && !this.state.isFaceDown) {
if (!this.timers.stabilizer) {
this.timers.stabilizer = setTimeout(() => {
this.state.isFaceDown = true;
this.registerGesture();
this.timers.stabilizer = null;
}, this.config.stabilize);
}
} else if (!isFlat) {
if (this.timers.stabilizer) {
clearTimeout(this.timers.stabilizer);
this.timers.stabilizer = null;
}
this.state.isFaceDown = false;
}
}

registerGesture() {
const now = Date.now();

if (now - this.state.lastTapTime > this.config.resetTime) {
this.state.taps = 0;
}

this.state.taps++;
this.state.lastTapTime = now;

this.vibrate(30);

if (this.state.taps >= 2) {
this.openCard('motion');
this.state.taps = 0;
}
}

/* ---------------------------
   FALLBACKS (Touch & Keyboard)
---------------------------- */

setupFallbacks() {
// Keyboard: Shift+V
document.addEventListener('keydown', (e) => {
if (this.state.isActive) return;
if (e.shiftKey && (e.key === 'V' || e.key === 'v')) {
this.openCard('keyboard');
}
});

// Touch: long press with scroll guard
let startY = 0;

const startPress = () => {
if (this.state.isActive) return;
startY = window.scrollY;

this.timers.longPress = setTimeout(() => {
// SCROLL GUARD
if (Math.abs(window.scrollY - startY) < 10) {
this.openCard('touch');
}
}, this.config.longPress);
};

const cancelPress = () => {
if (this.timers.longPress) {
clearTimeout(this.timers.longPress);
this.timers.longPress = null;
}
};

document.addEventListener('touchstart', startPress, { passive: true });
document.addEventListener('touchend', cancelPress);
document.addEventListener('touchmove', cancelPress, { passive: true });
}

/* ---------------------------
   UI & LIFECYCLE
---------------------------- */

openCard(triggerMethod) {
// Cooldown & duplicate check
if (Date.now() - this.state.lastCloseTime < this.config.cooldown) return;
if (this.state.isActive || document.getElementById('spax-photon-card-overlay')) return;

// Fix 9: save focus for accessibility restoration on close
this.lastFocus = document.activeElement;

this.state.isActive = true;
this.disableSensors();

// iOS safe scroll lock (CSS expects body.spax-photon-card-active fixed)
this.state.scrollPos = window.scrollY;
document.body.style.top = `-${this.state.scrollPos}px`;
document.body.classList.add('spax-photon-card-active');

// Analytics hook
document.dispatchEvent(new CustomEvent('spax-photon-card-event', {
detail: { type: 'open', method: triggerMethod, timestamp: Date.now() }
}));

this.vibrate([80, 50, 80]);

this.injectOverlay();
this.keepScreenAwake();
}

injectOverlay() {
const overlay = document.createElement('div');
overlay.id = 'spax-photon-card-overlay';

// Accessibility attributes
overlay.setAttribute('role', 'dialog');
overlay.setAttribute('aria-modal', 'true');
overlay.setAttribute('aria-label', 'Digital Business Card');

overlay.innerHTML = `
${userData.logo ? `<img src="${this.safeURL(userData.logo)}" class="spax-photon-logo" alt="Business Logo" loading="lazy" decoding="async">` : ''}
<div class="spax-photon-name">${this.esc(userData.name)}</div>
<div class="spax-photon-title">${this.esc(userData.title)}</div>

<div id="spax-photon-qr" class="spax-photon-qr" aria-label="Scan to save contact"></div>

<div class="spax-photon-actions" role="group" aria-label="Business card actions">
${this._canShare() ? `<button type="button" class="spax-photon-btn" id="spax-photon-share-btn">Share</button>` : ''}
<button type="button" class="spax-photon-btn" id="spax-photon-save-btn">Save Contact</button>
</div>

<button type="button" class="spax-photon-close" id="spax-photon-close-btn" aria-label="Close Card">Close</button>
`;

document.body.appendChild(overlay);

// Focus management
const focusable = overlay.querySelectorAll('button, a, [tabindex]:not([tabindex="-1"])');
const closeBtn = document.getElementById('spax-photon-close-btn');

// Render QR (vCard encoded)
this.renderVCardQR();

// Attach actions
this.attachOverlayActions();

// Initial focus
setTimeout(() => closeBtn && closeBtn.focus(), 50);

// Key handling: Escape + focus trap
overlay.addEventListener('keydown', (e) => {
if (e.key === 'Escape') {
this.closeCard();
return;
}
if (e.key === 'Tab' && focusable.length) {
const first = focusable[0];
const last = focusable[focusable.length - 1];

if (e.shiftKey) {
if (document.activeElement === first) {
e.preventDefault();
last.focus();
}
} else {
if (document.activeElement === last) {
e.preventDefault();
first.focus();
}
}
}
});
}

attachOverlayActions() {
const shareBtn = document.getElementById('spax-photon-share-btn');
const saveBtn  = document.getElementById('spax-photon-save-btn');
const closeBtn = document.getElementById('spax-photon-close-btn');
const overlay  = document.getElementById('spax-photon-card-overlay');

if (shareBtn) {
shareBtn.addEventListener('click', () => this.shareCard(), { passive: true });
}
if (saveBtn) {
saveBtn.addEventListener('click', () => this.downloadVCard(), { passive: true });
}
if (closeBtn) {
closeBtn.addEventListener('click', () => this.closeCard(), { passive: true });
}

// Close when tapping backdrop (but not when tapping inside content)
overlay.addEventListener('click', (e) => {
if (e.target === overlay) this.closeCard();
});
}

closeCard() {
const overlay = document.getElementById('spax-photon-card-overlay');
if (!overlay) return;

document.body.classList.remove('spax-photon-card-active');

// Restore scroll
document.body.style.top = '';
window.scrollTo(0, this.state.scrollPos);

this.state.isActive = false;
this.state.lastCloseTime = Date.now();
// Fix 7: reset motion state to prevent immediate re-open
this.state.isFaceDown = false;
this.state.taps = 0;

// Fix 8: clear pending timers to avoid memory leaks
this.timers.stabilizer && clearTimeout(this.timers.stabilizer);
this.timers.stabilizer = null;
this.timers.longPress && clearTimeout(this.timers.longPress);
this.timers.longPress = null;

// Analytics hook
document.dispatchEvent(new CustomEvent('spax-photon-card-event', {
detail: { type: 'close', timestamp: Date.now() }
}));

overlay.remove();

// Fix 9: restore focus for accessibility
if (this.lastFocus) {
this.lastFocus.focus();
this.lastFocus = null;
}

// Fix 1: re-request sensor access (iOS requires user-gesture re-grant after disable)
const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
if (!reducedMotion && !userData.noSensor && !this.isLowEndDevice()) {
this.requestSensorAccess();
}

this.releaseWakeLock();
}

/* ---------------------------
   SHARE + VCARD + QR
---------------------------- */

shareCard() {
// Fix 6: guard against browsers that expose share but reject calls
if (!this._canShare()) return;
navigator.share({
title: userData.name || 'Business Card',
text: `${userData.name || ''}${userData.title ? ' - ' + userData.title : ''}`.trim(),
url: window.location.href
}).catch(() => {});
}

generateVCard() {
// Keep it compatible across scanners/importers
const name  = (userData.name  || '').replace(/\n/g, ' ').trim();
const title = (userData.title || '').replace(/\n/g, ' ').trim();
const tel   = (userData.phone || '').replace(/\s+/g, ' ').trim();
const email = (userData.email || '').replace(/\s+/g, '').trim();
const url   = window.location.href;

return [
'BEGIN:VCARD',
'VERSION:3.0',
`FN:${name}`,
title ? `TITLE:${title}` : '',
tel   ? `TEL;TYPE=CELL:${tel}` : '',
email ? `EMAIL:${email}` : '',
url   ? `URL:${url}` : '',
'END:VCARD'
].filter(Boolean).join('\n');
}

downloadVCard() {
const vcard = this.generateVCard();
const blob  = new Blob([vcard], { type: 'text/vcard;charset=utf-8' });
const url   = URL.createObjectURL(blob);

const a = document.createElement('a');
a.href     = url;
a.download = `${(userData.name || 'contact').replace(/[^\w\-]+/g, '_')}.vcf`;
document.body.appendChild(a);
a.click();
document.body.removeChild(a);

URL.revokeObjectURL(url);
}

renderVCardQR() {
const container = document.getElementById('spax-photon-qr');
if (!container) return;

// Lazy-load QR library (tiny). If you prefer self-hosting, change this URL.
this.ensureQRCodeLib(() => {
// Clear previous if any
container.innerHTML = '';
// Encode vCard content directly (best: scan -> save contact)
// eslint-disable-next-line no-undef
new QRCode(container, {
text: this.generateVCard(),
width: 220,
height: 220,
correctLevel: QRCode.CorrectLevel.M
});
});
}

ensureQRCodeLib(cb) {
if (window.QRCode) {
cb();
return;
}
// Avoid duplicate loads
if (document.getElementById('spax-photon-qrcode-lib')) {
// If script is loading, poll lightly
const t = setInterval(() => {
if (window.QRCode) {
clearInterval(t);
cb();
}
}, 50);
setTimeout(() => clearInterval(t), 3000);
return;
}

const s = document.createElement('script');
s.id    = 'spax-photon-qrcode-lib';
s.src   = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
s.async = true;
s.onload = () => cb();
document.head.appendChild(s);
}

/* ---------------------------
   WAKE LOCK
---------------------------- */

async keepScreenAwake() {
// Great for trade shows (prevents dim/sleep)
if (!('wakeLock' in navigator)) return;

try {
// eslint-disable-next-line no-undef
this.wakeLock = await navigator.wakeLock.request('screen');

// If page visibility changes, re-request when visible again
this._onVisChange = async () => {
if (!this.state.isActive) return;
if (document.visibilityState === 'visible' && !this.wakeLock) {
try {
// eslint-disable-next-line no-undef
this.wakeLock = await navigator.wakeLock.request('screen');
} catch (e) {}
}
};

document.addEventListener('visibilitychange', this._onVisChange);
} catch (e) {
// ignore
}
}

releaseWakeLock() {
try {
if (this.wakeLock) {
this.wakeLock.release();
this.wakeLock = null;
}
} catch (e) {}
if (this._onVisChange) {
document.removeEventListener('visibilitychange', this._onVisChange);
this._onVisChange = null;
}
}

/* ---------------------------
   UTILITIES
---------------------------- */

vibrate(pattern) {
if (navigator.vibrate && typeof navigator.vibrate === 'function') {
try { navigator.vibrate(pattern); } catch (e) {}
}
}

_canShare() {
return !!(navigator.canShare && navigator.canShare({ text: 'x' }));
}

esc(str) {
if (!str) return '';
const div = document.createElement('div');
div.textContent = String(str);
return div.innerHTML;
}

safeURL(url) {
if (!url) return '';
try {
const u = new URL(url, window.location.href);
// allow only safe protocols
if (['http:', 'https:'].includes(u.protocol)) return u.href;
} catch (e) {}
return '';
}
}

// Boot safely
if (document.readyState === 'loading') {
document.addEventListener('DOMContentLoaded', () => new SpaxPhotonVCard());
} else {
new SpaxPhotonVCard();
}
})();
