/**
 * SPARXSTAR Photon VCard — front-end runtime.
 *
 * Bootstrapped by WordPress via wp_enqueue_script.  Card data is injected
 * by wp_localize_script as window.SPX_PHOTON_VCARD.
 *
 * The module pattern passes window and document explicitly so that:
 *  – local references are resolvable at parse time (minifier-friendly),
 *  – the singleton instance is reachable at window.spxPhotonVCard for
 *    external scripts and browser-console debugging.
 *
 * @package Starisian\Sparxstar\Photon
 */
(function (window, document) {
'use strict';

// 1) SINGLETON LOCK (prevent multi-injection on back/forward cache hits)
if (window.__SPX_PHOTON_CARD_LOADED__) return;
window.__SPX_PHOTON_CARD_LOADED__ = true;

// 2) WordPress-provided data (via wp_localize_script → SPX_PHOTON_VCARD)
// Expected shape: { name, title, phone, email, logo, noSensor? }
const userData = (window.SPX_PHOTON_VCARD && typeof window.SPX_PHOTON_VCARD === 'object')
? window.SPX_PHOTON_VCARD
: {};

class SpxPhotonVCard {
constructor() {
this.config = {
threshold: 155,           // beta threshold for "flat/face-down" trigger
resetTime: 2500,          // tap sequence reset window (ms)
cooldown: 5000,           // lockout after close (ms)
stabilize: 150,           // stabilization window (ms)
longPress: 800,           // long-press duration (ms)
sensorFps: 10,            // throttle orientation events (fps)
sensorAutoDisable: 30000, // battery saver — disable after inactivity (ms)
gammaThreshold: 20,       // max gamma for face-down detection (degrees)
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

// Enable motion triggers only when:
// – user has not opted in to reduced motion,
// – the WP data layer has not disabled sensors, and
// – the device is not flagged as low-end.
if (!reducedMotion && !userData.noSensor && !this.isLowEndDevice()) {
this.setupMotion();
}

// Release wake lock on page hide to prevent leaks on navigation/refresh.
window.addEventListener('pagehide', () => this.releaseWakeLock());

// Fallback triggers are always active.
this.setupFallbacks();
}

isLowEndDevice() {
const hc = navigator.hardwareConcurrency;
const dm = navigator.deviceMemory;
return (typeof hc === 'number' && hc <= 4) || (typeof dm === 'number' && dm <= 2);
}

/* ---------------------------
   SAFE STORAGE (kiosk / private mode)
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
const isEnabled = this.storage('spx_photon_motion_enabled') === 'true';

if (isEnabled) {
document.addEventListener('touchstart', () => this.requestSensorAccess(), { once: true, passive: true });
} else {
this.renderSetupButton();
}
}

renderSetupButton() {
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
if (typeof DeviceOrientationEvent !== 'undefined' &&
typeof DeviceOrientationEvent.requestPermission === 'function') {
// iOS 13+ permission model — apply a timeout in case the promise
// never resolves (e.g. the dialog is dismissed without a choice).
const timeout = setTimeout(() => this.renderSetupButton(), this.config.permissionTimeout);
const response = await DeviceOrientationEvent.requestPermission();
clearTimeout(timeout);
if (response === 'granted') this.enableSensors();
} else {
// Android and all other browsers grant access implicitly.
this.enableSensors();
}
} catch (e) {
// Fallbacks remain active on permission errors.
}
}

enableSensors() {
if (this.state.sensorBound) return;

this.boundOrientationHandler = this.handleOrientation.bind(this);
window.addEventListener('deviceorientation', this.boundOrientationHandler, { passive: true });

this.state.sensorBound = true;
this.storage('spx_photon_motion_enabled', 'true');

// Remove stale activation button (may exist if the permission timeout fired first).
const grantBtn = document.getElementById('spax-photon-sensor-grant');
if (grantBtn) grantBtn.remove();

// Battery saver: auto-disable after the inactivity window.
this.resetSensorAutoDisable();

this.vibrate(40);
}

disableSensors(clearStorageFlag = true) {
if (!this.state.sensorBound) return;
window.removeEventListener('deviceorientation', this.boundOrientationHandler);
this.state.sensorBound = false;

// Only clear the permission flag for user-initiated disables (openCard/closeCard).
// Auto-disable (battery saver) passes false so the flag stays 'true' and the next
// page load can auto-request on touchstart without showing the activation button.
if (clearStorageFlag) {
this.storage('spx_photon_motion_enabled', 'false');
}

if (this.timers.sensorAutoDisable) {
clearTimeout(this.timers.sensorAutoDisable);
this.timers.sensorAutoDisable = null;
}
}

resetSensorAutoDisable() {
if (this.timers.sensorAutoDisable) clearTimeout(this.timers.sensorAutoDisable);
this.timers.sensorAutoDisable = setTimeout(() => {
this.disableSensors(false);
}, this.config.sensorAutoDisable);
}

handleOrientation(event) {
if (this.state.isActive) return;
if (!this.state.sensorBound) return;

this.resetSensorAutoDisable();

// Throttle to reduce CPU load on older devices.
const now = Date.now();
const minDelta = Math.floor(1000 / this.config.sensorFps);
if (now - this.timers.sensorTick < minDelta) return;
this.timers.sensorTick = now;

const beta  = Math.abs(event.beta  || 0);
const gamma = Math.abs(event.gamma || 0);
// Require low gamma to reduce false positives (pocket/walking/table).
const isFlat = beta > this.config.threshold && gamma < this.config.gammaThreshold;

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

// Touch: long press with scroll guard.
let startY = 0;

const startPress = () => {
if (this.state.isActive) return;
startY = window.scrollY;

this.timers.longPress = setTimeout(() => {
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
document.addEventListener('touchend',   cancelPress);
document.addEventListener('touchmove',  cancelPress, { passive: true });
}

/* ---------------------------
   UI & LIFECYCLE
---------------------------- */

openCard(triggerMethod) {
if (Date.now() - this.state.lastCloseTime < this.config.cooldown) return;
if (this.state.isActive || document.getElementById('spax-photon-card-overlay')) return;

// Save current focus for accessibility restoration on close.
this.lastFocus = document.activeElement;

this.state.isActive = true;
this.disableSensors();

// iOS safe scroll lock (CSS expects body.spax-photon-card-active + fixed).
this.state.scrollPos = window.scrollY;
document.body.style.top = `-${this.state.scrollPos}px`;
document.body.classList.add('spax-photon-card-active');

// Analytics hook — external listeners can subscribe via document.
document.dispatchEvent(new CustomEvent('spx-photon-card-event', {
detail: { type: 'open', method: triggerMethod, timestamp: Date.now() }
}));

this.vibrate([80, 50, 80]);

this.injectOverlay();
this.keepScreenAwake();
}

injectOverlay() {
const overlay = document.createElement('div');
overlay.id = 'spax-photon-card-overlay';

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

const focusable = overlay.querySelectorAll('button, a, [tabindex]:not([tabindex="-1"])');
const closeBtn  = document.getElementById('spax-photon-close-btn');

this.renderVCardQR();
this.attachOverlayActions();

// Set initial focus to close button.
setTimeout(() => closeBtn && closeBtn.focus(), 50);

// Key handling: Escape + focus trap.
overlay.addEventListener('keydown', (e) => {
if (e.key === 'Escape') {
this.closeCard();
return;
}
if (e.key === 'Tab' && focusable.length) {
const first = focusable[0];
const last  = focusable[focusable.length - 1];

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

if (shareBtn) shareBtn.addEventListener('click', () => this.shareCard(),     { passive: true });
if (saveBtn)  saveBtn.addEventListener( 'click', () => this.downloadVCard(), { passive: true });
if (closeBtn) closeBtn.addEventListener('click', () => this.closeCard(),     { passive: true });

// Close when tapping the backdrop (not when tapping inside content).
overlay.addEventListener('click', (e) => {
if (e.target === overlay) this.closeCard();
});
}

closeCard() {
const overlay = document.getElementById('spax-photon-card-overlay');
if (!overlay) return;

document.body.classList.remove('spax-photon-card-active');

// Restore scroll position.
document.body.style.top = '';
window.scrollTo(0, this.state.scrollPos);

this.state.isActive      = false;
this.state.lastCloseTime = Date.now();
// Reset motion state to prevent immediate re-open.
this.state.isFaceDown = false;
this.state.taps       = 0;

// Clear pending timers to avoid memory leaks.
if (this.timers.stabilizer) { clearTimeout(this.timers.stabilizer); this.timers.stabilizer = null; }
if (this.timers.longPress)  { clearTimeout(this.timers.longPress);  this.timers.longPress  = null; }

document.dispatchEvent(new CustomEvent('spx-photon-card-event', {
detail: { type: 'close', timestamp: Date.now() }
}));

overlay.remove();

// Restore focus for accessibility.
if (this.lastFocus) {
this.lastFocus.focus();
this.lastFocus = null;
}

// Re-request sensor access (iOS requires a user-gesture re-grant after disable).
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
if (!this._canShare()) return;
navigator.share({
title: userData.name || 'Business Card',
text:  `${userData.name || ''}${userData.title ? ' - ' + userData.title : ''}`.trim(),
url:   window.location.href
}).catch(() => {});
}

generateVCard() {
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

const a      = document.createElement('a');
a.href       = url;
a.download   = `${(userData.name || 'contact').replace(/[^\w\-]+/g, '_')}.vcf`;
document.body.appendChild(a);
a.click();
document.body.removeChild(a);

URL.revokeObjectURL(url);
}

renderVCardQR() {
const container = document.getElementById('spax-photon-qr');
if (!container) return;

this.ensureQRCodeLib(() => {
container.innerHTML = '';
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
} else if (typeof console !== 'undefined') {
console.warn('SpxPhotonVCard: QRCode library not found. Ensure qrcode.min.js is enqueued.');
}
}

/* ---------------------------
   WAKE LOCK
---------------------------- */

async keepScreenAwake() {
if (!('wakeLock' in navigator)) return;

try {
// eslint-disable-next-line no-undef
this.wakeLock = await navigator.wakeLock.request('screen');

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
// Ignore — wake lock is a best-effort optimisation.
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
if (['http:', 'https:'].includes(u.protocol)) return u.href;
} catch (e) {}
return '';
}
}

// Boot safely: defer until DOM is ready when the script is in <head>,
// or execute immediately when the DOM is already interactive/complete.
function spx_photon_boot() {
window.spxPhotonVCard = new SpxPhotonVCard();
}

if (document.readyState === 'loading') {
document.addEventListener('DOMContentLoaded', spx_photon_boot);
} else {
spx_photon_boot();
}

})(window, document);
