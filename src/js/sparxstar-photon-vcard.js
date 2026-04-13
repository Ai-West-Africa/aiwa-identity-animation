/**
 * SPARXSTAR Photon VCard — front-end runtime.
 *
 * Bootstrapped by WordPress via wp_enqueue_script.  Card data is injected
 * via wp_add_inline_script as window.SPX_PHOTON_VCARD_USERS / window.SPX_PHOTON_VCARD_DEFAULT.
 *
 * Features:
 *  – Motion triggers: single face-down flip (deviceorientation) OR shake (devicemotion)
 *  – Keyboard trigger: Shift+V
 *  – Long-press touch trigger
 *  – [spx_photon_vcard] shortcode button support (data-spx-vcard-trigger)
 *  – Business-card-styled overlay with Gravatar photo, full contact details
 *  – QR code (vCard data encoded)
 *  – Save Contact (.vcf download)
 *  – Web Share API URL share
 *  – Send to Device: Web Share API with .vcf file (AirDrop / Nearby Share)
 *  – Wake lock while card is visible
 *  – WCAG 2.1 focus trap + keyboard navigation
 *
 * @package Starisian\Sparxstar\Photon
 */
(function (window, document) {
'use strict';

// 1) SINGLETON LOCK (prevent multi-injection on back/forward cache hits)
if (window.__SPX_PHOTON_CARD_LOADED__) return;
window.__SPX_PHOTON_CARD_LOADED__ = true;

// 2) WordPress-provided per-user card data map (via wp_add_inline_script).
// window.SPX_PHOTON_VCARD_USERS  — map of { [uid]: cardData }
// window.SPX_PHOTON_VCARD_DEFAULT — UID of the default card (post author / first shortcode user)
const usersMap  = (window.SPX_PHOTON_VCARD_USERS && typeof window.SPX_PHOTON_VCARD_USERS === 'object')
? window.SPX_PHOTON_VCARD_USERS
: {};
const defaultUid = Number(window.SPX_PHOTON_VCARD_DEFAULT) || 0;

class SpxPhotonVCard {
constructor() {
this.config = {
threshold: 145,           // beta (abs) for face-down flip trigger
cooldown: 5000,           // lockout after close (ms)
stabilize: 150,           // stabilization window before registering flip (ms)
longPress: 800,           // long-press duration (ms)
sensorFps: 10,            // throttle orientation events (fps)
sensorAutoDisable: 30000, // battery saver — disable sensors after inactivity (ms)
gammaThreshold: 25,       // max |gamma| for face-down detection (degrees)
permissionTimeout: 2000,  // iOS permission promise timeout (ms)
shakeThreshold: 324,      // squared m/s² magnitude delta triggering a shake count (18² — avoids sqrt)
shakeRequired: 3,         // shake events required within the window
shakeWindow: 1200,        // ms window for shake sequence
motionThrottle: 120       // minimum ms between processed motion events (~8 Hz, saves CPU/battery)
};

this.state = {
lastCloseTime: 0,
isFaceDown: false,
isActive: false,
sensorBound: false,
scrollPos: 0,
shakeCount: 0,
firstShakeTime: 0,
lastMagSq: 0,
lastMotionTime: 0
};

this.timers = {
sensorTick: 0,
stabilizer: null,
longPress: null,
sensorAutoDisable: null
};

this.boundOrientationHandler = null;
this.boundMotionHandler      = null;
this.wakeLock  = null;
this.lastFocus = null;
// Active user for the current card display.
this.activeUid = defaultUid;

this.init();
}

/* ---------------------------
   INIT
---------------------------- */

init() {
const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const defaultData   = usersMap[defaultUid] || {};

if (!reducedMotion && !defaultData.noSensor && !this.isLowEndDevice()) {
this.setupMotion();
}

window.addEventListener('pagehide', () => this.releaseWakeLock());

this.setupFallbacks();

// Attach click handlers to [spx_photon_vcard] shortcode buttons.
this.setupShortcodeTriggers();

// Render the floating open-button only when no shortcode triggers are present.
if (!document.querySelector('[data-spx-vcard-trigger]')) {
this.renderOpenButton();
}
}

/** Returns the card data for the currently active user. */
get d() {
return usersMap[this.activeUid] || {};
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
this.requestSensorAccess();
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
btn.textContent = 'Enable Motion Trigger';
btn.setAttribute('aria-label', 'Enable motion-based card trigger (flip or shake)');
btn.addEventListener('click', () => {
this.requestSensorAccess();
btn.remove();
}, { passive: true });

document.body.appendChild(btn);
}

renderOpenButton() {
if (document.getElementById('spax-photon-open-btn')) return;

const btn = document.createElement('button');
btn.id = 'spax-photon-open-btn';
btn.type = 'button';
btn.textContent = 'View Business Card';
btn.setAttribute('aria-label', 'Open digital business card');
btn.dataset.spxVcardUid = String(defaultUid);
btn.addEventListener('click', () => this.openCard('button', defaultUid), { passive: true });

document.body.appendChild(btn);
}

setupShortcodeTriggers() {
const triggers = document.querySelectorAll('[data-spx-vcard-trigger]');
triggers.forEach(el => {
const uid = Number(el.dataset.spxVcardUid) || defaultUid;
el.addEventListener('click', () => this.openCard('shortcode', uid), { passive: true });
});
}

async requestSensorAccess() {
if (this.state.sensorBound) return;

try {
const needsOrientPerm = typeof DeviceOrientationEvent !== 'undefined' &&
typeof DeviceOrientationEvent.requestPermission === 'function';
const needsMotionPerm = typeof DeviceMotionEvent !== 'undefined' &&
typeof DeviceMotionEvent.requestPermission === 'function';

if (needsOrientPerm || needsMotionPerm) {
// iOS 13+ requires explicit permission for both sensor types.
const timeout = setTimeout(() => this.renderSetupButton(), this.config.permissionTimeout);

let orientGranted = !needsOrientPerm;
let motionGranted  = !needsMotionPerm;

if (needsOrientPerm) {
const r = await DeviceOrientationEvent.requestPermission();
orientGranted = (r === 'granted');
}
if (needsMotionPerm) {
const r = await DeviceMotionEvent.requestPermission();
motionGranted = (r === 'granted');
}

clearTimeout(timeout);

if (orientGranted || motionGranted) {
this.enableSensors(orientGranted, motionGranted);
} else {
this.renderSetupButton();
}
} else {
// Android and all other browsers — sensors available implicitly.
this.enableSensors(true, true);
}
} catch (e) {
// Fallbacks remain active on permission errors.
}
}

enableSensors(orientGranted = true, motionGranted = true) {
if (this.state.sensorBound) return;

// Flip / face-down detection (orientation permission).
if (orientGranted) {
this.boundOrientationHandler = this.handleOrientation.bind(this);
window.addEventListener('deviceorientation', this.boundOrientationHandler, { passive: true });
}

// Shake detection (motion permission).
if (motionGranted) {
this.boundMotionHandler = this.handleMotion.bind(this);
window.addEventListener('devicemotion', this.boundMotionHandler, { passive: true });
}

if (!orientGranted && !motionGranted) return;

this.state.sensorBound = true;
this.storage('spx_photon_motion_enabled', 'true');

const grantBtn = document.getElementById('spax-photon-sensor-grant');
if (grantBtn) grantBtn.remove();

this.resetSensorAutoDisable();
this.vibrate(40);
}

disableSensors(clearStorageFlag = true) {
if (!this.state.sensorBound) return;
window.removeEventListener('deviceorientation', this.boundOrientationHandler);
if (this.boundMotionHandler) {
window.removeEventListener('devicemotion', this.boundMotionHandler);
}
this.state.sensorBound = false;

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

/* Orientation — single face-down flip */
handleOrientation(event) {
if (this.state.isActive) return;
if (!this.state.sensorBound) return;

this.resetSensorAutoDisable();

const now = Date.now();
const minDelta = Math.floor(1000 / this.config.sensorFps);
if (now - this.timers.sensorTick < minDelta) return;
this.timers.sensorTick = now;

const beta  = Math.abs(event.beta  || 0);
const gamma = Math.abs(event.gamma || 0);
const isFlat = beta > this.config.threshold && gamma < this.config.gammaThreshold;

if (isFlat && !this.state.isFaceDown) {
if (!this.timers.stabilizer) {
this.timers.stabilizer = setTimeout(() => {
this.state.isFaceDown = true;
this.openCard('flip');
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

/* Motion — shake detection (throttled + sqrt-free for low-end/battery-constrained devices) */
handleMotion(event) {
if (this.state.isActive) return;
if (!this.state.sensorBound) return;

// Throttle: process at most ~8 Hz to avoid excessive CPU/battery use on low-end hardware.
const now = Date.now();
if (now - this.state.lastMotionTime < this.config.motionThrottle) return;
this.state.lastMotionTime = now;

this.resetSensorAutoDisable();
const acc = event.accelerationIncludingGravity;
if (!acc) return;

// Compare squared magnitudes — avoids Math.sqrt() entirely.
// shakeThreshold is expressed in squared m/s² units (default 324 = 18²).
const magSq  = (acc.x || 0) ** 2 + (acc.y || 0) ** 2 + (acc.z || 0) ** 2;
const delta  = Math.abs(magSq - this.state.lastMagSq);
this.state.lastMagSq = magSq;

if (delta > this.config.shakeThreshold) {
if (!this.state.firstShakeTime || (now - this.state.firstShakeTime > this.config.shakeWindow)) {
this.state.shakeCount    = 1;
this.state.firstShakeTime = now;
} else {
this.state.shakeCount++;
if (this.state.shakeCount >= this.config.shakeRequired) {
this.state.shakeCount     = 0;
this.state.firstShakeTime = 0;
this.openCard('shake');
}
}
}
}

/* ---------------------------
   FALLBACKS (Touch & Keyboard)
---------------------------- */

setupFallbacks() {
document.addEventListener('keydown', (e) => {
if (this.state.isActive) return;
if (e.shiftKey && (e.key === 'V' || e.key === 'v')) {
this.openCard('keyboard');
}
});

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

openCard(triggerMethod, uid) {
if (Date.now() - this.state.lastCloseTime < this.config.cooldown) return;
if (this.state.isActive || document.getElementById('spax-photon-card-overlay')) return;

// Set the active user for this card display.
const resolvedUid = (uid !== undefined && usersMap[uid]) ? Number(uid) : defaultUid;
this.activeUid = resolvedUid;

this.lastFocus = document.activeElement;
this.state.isActive = true;
this.disableSensors();

const openBtn = document.getElementById('spax-photon-open-btn');
if (openBtn) openBtn.hidden = true;

this.state.scrollPos = window.scrollY;
document.body.style.top = `-${this.state.scrollPos}px`;
document.body.classList.add('spax-photon-card-active');

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

const name    = this.esc(this.d.name    || '');
const title   = this.esc(this.d.title   || '');
const company = this.esc(this.d.company || '');

// First phone (for the prominent link on the card face)
const phones = Array.isArray(this.d.phones) ? this.d.phones : [];
const toTelHrefValue = (value) => {
const raw = String(value || '').trim();
if (!raw) return '';
const extMatch = raw.match(/(?:ext\.?|x)\s*[:.]?\s*(\d+)$/i);
const extension = extMatch ? extMatch[1] : '';
const mainPart = extMatch ? raw.slice(0, extMatch.index).trim() : raw;
const hasLeadingPlus = /^\s*\+/.test(mainPart);
const digits = mainPart.replace(/\D/g, '');
if (!digits) return '';
return `${hasLeadingPlus ? '+' : ''}${digits}${extension ? `;ext=${extension}` : ''}`;
};

let primaryPhoneHtml = '';
if (phones.length) {
const firstPhone = phones[0];
const telHref = toTelHrefValue(firstPhone.number);
if (telHref) {
primaryPhoneHtml = `<a class="spx_phone_link" href="tel:${this.escAttr(telHref)}">TEL: + ${this.esc(firstPhone.number)}</a>`;
}
}

const photoSrc = this.d.photo ? this.safeURL(this.d.photo) : '';

const canSendFile = this._canSendFile();
const canShare    = this._canShare();

// SVG icons for action bar
const iconSave  = `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>`;
const iconShare = `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>`;
const iconSend  = `<svg viewBox="0 0 24 24" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>`;

const sendLabel  = this.esc(this._sendButtonLabel());

overlay.innerHTML = `
<button type="button" class="spax-photon-close" id="spax-photon-close-btn" aria-label="Close business card">&#x2715;</button>

<div class="spx_vcard_page_wrapper">
  <div class="spx_card_wrapper">
    <div class="spx_card_content">
      ${photoSrc ? `<img src="${this.escAttr(photoSrc)}" class="spx_user_photo" alt="${this.escAttr(this.d.name || '')}" width="56" height="56" loading="eager" decoding="async">` : ''}
      <div id="spax-photon-qr" class="spx_qr_code" aria-label="Scan QR code to save contact"></div>
      <span class="spx_scan_label" aria-hidden="true">Scan to save contact</span>
      ${company ? `<p class="spx_company">${company}</p>` : ''}
      ${name    ? `<p class="spx_name">${name}</p>` : ''}
      ${title   ? `<p class="spx_title">${title}</p>` : ''}
      ${primaryPhoneHtml}
    </div>
  </div>


<div class="spx_action_bar" role="group" aria-label="Business card actions">
  <div class="spx_action_grid">
    ${canSendFile
      ? `<button type="button" class="spx_action_item" id="spax-photon-send-btn" aria-label="${sendLabel}">${iconSend}<span class="spx_action_label">${sendLabel}</span></button>`
      : `<span class="spx_action_placeholder" aria-hidden="true"></span>`}
    ${canShare
      ? `<button type="button" class="spx_action_item" id="spax-photon-share-btn" aria-label="Share Link">${iconShare}<span class="spx_action_label">Share</span></button>`
      : `<span class="spx_action_placeholder" aria-hidden="true"></span>`}
    <button type="button" class="spx_action_item" id="spax-photon-save-btn" aria-label="Save Contact">${iconSave}<span class="spx_action_label">Save</span></button>
  </div>
</div>
</div>
`;

document.body.appendChild(overlay);

const focusable = overlay.querySelectorAll('button, a, [tabindex]:not([tabindex="-1"])');
const closeBtn  = document.getElementById('spax-photon-close-btn');

this.renderVCardQR();
this.attachOverlayActions();

setTimeout(() => closeBtn && closeBtn.focus(), 50);

overlay.addEventListener('keydown', (e) => {
if (e.key === 'Escape') {
this.closeCard();
return;
}
if (e.key === 'Tab' && focusable.length) {
const first = focusable[0];
const last  = focusable[focusable.length - 1];
if (e.shiftKey) {
if (document.activeElement === first) { e.preventDefault(); last.focus(); }
} else {
if (document.activeElement === last)  { e.preventDefault(); first.focus(); }
}
}
});
}

attachOverlayActions() {
const sendBtn  = document.getElementById('spax-photon-send-btn');
const shareBtn = document.getElementById('spax-photon-share-btn');
const saveBtn  = document.getElementById('spax-photon-save-btn');
const closeBtn = document.getElementById('spax-photon-close-btn');
const overlay  = document.getElementById('spax-photon-card-overlay');

if (sendBtn)  sendBtn.addEventListener( 'click', () => this.sendToDevice(),  { passive: true });
if (shareBtn) shareBtn.addEventListener('click', () => this.shareCard(),     { passive: true });
if (saveBtn)  saveBtn.addEventListener( 'click', () => this.downloadVCard(), { passive: true });
if (closeBtn) closeBtn.addEventListener('click', () => this.closeCard(),     { passive: true });

// Hide broken profile image without an inline onerror (CSP-safe).
const photoEl = overlay.querySelector('.spx_user_photo');
if (photoEl) {
photoEl.addEventListener('error', () => { photoEl.style.display = 'none'; }, { once: true });
}

overlay.addEventListener('click', (e) => {
if (e.target === overlay) this.closeCard();
});
}

closeCard() {
const overlay = document.getElementById('spax-photon-card-overlay');
if (!overlay) return;

document.body.classList.remove('spax-photon-card-active');
document.body.style.top = '';
window.scrollTo(0, this.state.scrollPos);

this.state.isActive      = false;
this.state.lastCloseTime = Date.now();
this.state.isFaceDown    = false;

if (this.timers.stabilizer) { clearTimeout(this.timers.stabilizer); this.timers.stabilizer = null; }
if (this.timers.longPress)  { clearTimeout(this.timers.longPress);  this.timers.longPress  = null; }

document.dispatchEvent(new CustomEvent('spx-photon-card-event', {
detail: { type: 'close', timestamp: Date.now() }
}));

overlay.remove();

const openBtn = document.getElementById('spax-photon-open-btn');
if (openBtn) openBtn.hidden = false;

if (this.lastFocus) {
this.lastFocus.focus();
this.lastFocus = null;
}

const reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const defaultData   = usersMap[defaultUid] || {};
if (!reducedMotion && !defaultData.noSensor && !this.isLowEndDevice()) {
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
title: this.d.name || 'Business Card',
text:  `${this.d.name || ''}${this.d.title ? ' — ' + this.d.title : ''}`.trim(),
url:   window.location.href
}).catch(() => {});
}

async sendToDevice() {
const vcard = this.generateVCard();
const blob  = new Blob([vcard], { type: 'text/vcard' });
const fname = (this.d.name || 'contact').replace(/[^\w\-]+/g, '_');
const file  = new File([blob], `${fname}.vcf`, { type: 'text/vcard' });

try {
await navigator.share({
files: [file],
title: this.d.name || 'Contact Card'
});
} catch (e) {
// AbortError = user cancelled — no fallback needed.
if (e && e.name !== 'AbortError') {
this.downloadVCard();
}
}
}

generateVCard() {
const clean   = s => String(s || '').replace(/[\r\n]/g, ' ').trim();
const ve      = s => this.vcardEsc(s);
const name    = clean(this.d.name);
const title   = clean(this.d.title);
const company = clean(this.d.company);
const email   = String(this.d.email   || '').replace(/\s/g, '').trim();
const website = String(this.d.website || '').trim();
const photo   = String(this.d.photo   || '').trim();
const phones  = Array.isArray(this.d.phones)   ? this.d.phones   : [];
const channels = Array.isArray(this.d.channels) ? this.d.channels : [];
const social   = Array.isArray(this.d.social)   ? this.d.social   : [];
const addr     = this.d.address || {};

const lines = [
'BEGIN:VCARD',
'VERSION:3.0',
`FN:${ve(name)}`
];

// N field: only emit for exactly "First Last" (two-space-separated tokens).
const parts = name.split(' ');
if (parts.length === 2) {
lines.push(`N:${ve(parts[1])};${ve(parts[0])};;;`);
}

if (title)   lines.push(`TITLE:${ve(title)}`);
if (company) lines.push(`ORG:${ve(company)}`);

// Phone numbers from the repeater (phone-type channels)
phones.forEach(p => {
if (p && p.number) {
const phoneValue = ve(String(p.number).replace(/\s+/g, ''));
lines.push(`TEL;TYPE=${(p.type || 'VOICE').toUpperCase()}:${phoneValue}`);
}
});

// Messaging / communications channels
channels.forEach(c => {
const cleanVal = ve(String(c.val || '').replace(/\s+/g, ''));
if (!cleanVal) return;
switch (c.key) {
case 'whatsapp':
lines.push(`TEL;TYPE=CELL,VOICE:${cleanVal}`);
lines.push(`X-WHATSAPP:${cleanVal}`);
break;
case 'telegram':
lines.push(`X-TELEGRAM:${cleanVal}`);
break;
case 'signal':
lines.push(`X-SIGNAL:${cleanVal}`);
break;
case 'wechat':
lines.push(`X-WECHAT:${cleanVal}`);
break;
case 'viber':
lines.push(`X-VIBER:${cleanVal}`);
break;
default:
lines.push(`X-${c.key.toUpperCase().replace(/[^A-Z0-9]/g, '')}:${cleanVal}`);
}
});

if (email)   lines.push(`EMAIL:${email}`);
if (website) lines.push(`URL:${website}`);

// ADR vCard 3.0 field order: PO-Box;Extended-Addr;Street;City;State;Postal;Country
const s1 = ve(String(addr.street1  || '').trim());
const s2 = ve(String(addr.street2  || '').trim());
const ct = ve(String(addr.city     || '').trim());
const st = ve(String(addr.state    || '').trim());
const pc = ve(String(addr.postcode || '').trim());
const co = ve(String(addr.country  || '').trim());

if (s1 || s2 || ct || st || pc || co) {
lines.push(`ADR;TYPE=WORK:;${s2};${s1};${ct};${st};${pc};${co}`);
}

// Social media profiles
// Map ACF slug values (snake_case) → valid vCard token values.
const socialMap = {
'amazon_music':   'AMAZON-MUSIC',
'apple_music':    'APPLE-MUSIC',
'audiomack':      'AUDIOMACK',
'baidu_tieba':    'BAIDU-TIEBA',
'bandcamp':       'BANDCAMP',
'behance':        'BEHANCE',
'bereal':         'BEREAL',
'bilibili':       'BILIBILI',
'bluesky':        'BLUESKY',
'caffeine':       'CAFFEINE',
'deezer':         'DEEZER',
'discord':        'DISCORD',
'douyin':         'DOUYIN',
'dribbble':       'DRIBBBLE',
'facebook':       'FACEBOOK',
'flickr':         'FLICKR',
'github':         'GITHUB',
'instagram':      'INSTAGRAM',
'kick':           'KICK',
'kuaishou':       'KUAISHOU',
'lemmy':          'LEMMY',
'line':           'LINE',
'linkedin':       'LINKEDIN',
'mastodon':       'MASTODON',
'medium':         'MEDIUM',
'pandora':        'PANDORA',
'pinterest':      'PINTEREST',
'pixiv':          'PIXIV',
'qq':             'QQ',
'quora':          'QUORA',
'reddit':         'REDDIT',
'rumble':         'RUMBLE',
'snapchat':       'SNAPCHAT',
'soundcloud':     'SOUNDCLOUD',
'spotify':        'SPOTIFY',
'stack_overflow': 'STACKOVERFLOW',
'telegram':       'TELEGRAM',
'threads':        'THREADS',
'tidal':          'TIDAL',
'tiktok':         'TIKTOK',
'truth_social':   'TRUTH-SOCIAL',
'tumblr':         'TUMBLR',
'twitch':         'TWITCH',
'viber':          'VIBER',
'vk':             'VK',
'vsco':           'VSCO',
'whatsapp':       'WHATSAPP',
'wechat':         'WECHAT',
'weibo':          'WEIBO',
'xiaohongshu':    'XIAOHONGSHU',
'x':              'X',
'youtube':        'YOUTUBE',
'youtube_music':  'YOUTUBE-MUSIC',
'zhihu':          'ZHIHU',
};
social.forEach(s => {
if (s.val) {
const safeVal = this.safeURL(s.val);
if (safeVal) {
const rawKey    = String(clean(s.key) || '').trim().toLowerCase();
const socialType = socialMap[rawKey]
|| rawKey.toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g, '')
|| 'SOCIAL';
lines.push(`X-SOCIALPROFILE;TYPE=${socialType}:${safeVal}`);
lines.push(`URL:${safeVal}`);
}
}
});

if (photo) {
const safePhoto = this.safeURL(photo);
if (safePhoto) lines.push(`PHOTO;VALUE=URI:${safePhoto}`);
}

lines.push(`REV:${new Date().toISOString().replace(/[-:]/g, '').split('.')[0]}Z`);
lines.push('END:VCARD');

return lines.join('\r\n');
}

downloadVCard() {
const vcard = this.generateVCard();
const blob  = new Blob([vcard], { type: 'text/vcard;charset=utf-8' });
const url   = URL.createObjectURL(blob);

const a    = document.createElement('a');
a.href     = url;
a.download = `${(this.d.name || 'contact').replace(/[^\w\-]+/g, '_')}.vcf`;
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
width: 176,
height: 176,
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
this.wakeLock = await navigator.wakeLock.request('screen');

this._onVisChange = async () => {
if (!this.state.isActive) return;
if (document.visibilityState === 'visible' && !this.wakeLock) {
try {
this.wakeLock = await navigator.wakeLock.request('screen');
} catch (e) {}
}
};

document.addEventListener('visibilitychange', this._onVisChange);
} catch (e) {}
}

releaseWakeLock() {
try {
if (this.wakeLock) { this.wakeLock.release(); this.wakeLock = null; }
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
try {
return !!(navigator.canShare && navigator.canShare({ text: 'x' }));
} catch (e) {
return false;
}
}

_canSendFile() {
if (!navigator.canShare) return false;
try {
const b = new Blob(['test'], { type: 'text/vcard' });
const f = new File([b], 'test.vcf', { type: 'text/vcard' });
return navigator.canShare({ files: [f] });
} catch (e) {
return false;
}
}

_sendButtonLabel() {
const ua = navigator.userAgent || '';
if (/iphone|ipad|ipod/i.test(ua)) return 'Send via AirDrop';
if (/android/i.test(ua))          return 'Nearby Share';
return 'Send Contact';
}

/** Escape a string for safe insertion as HTML text content. */
esc(str) {
if (!str) return '';
const d = document.createElement('div');
d.textContent = String(str);
return d.innerHTML;
}

/**
 * Escape a plain-text string for embedding in a vCard TEXT value.
 * Per RFC 2426 §4, backslash, semicolon, comma, and newlines must be escaped.
 */
vcardEsc(str) {
if (!str) return '';
return String(str)
.replace(/\\/g, '\\\\')
.replace(/;/g, '\\;')
.replace(/,/g, '\\,')
.replace(/\r\n|\r|\n/g, '\\n');
}

/** Escape a string for safe use in an HTML attribute value. */
escAttr(str) {
if (!str) return '';
return String(str)
.replace(/&/g, '&amp;')
.replace(/"/g, '&quot;')
.replace(/'/g, '&#39;')
.replace(/</g, '&lt;')
.replace(/>/g, '&gt;');
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

function spx_photon_boot() {
// Progressive enhancement: enable Houdini animation only when
// CSS.registerProperty is available and the user is not data-saving.
if (
    typeof CSS !== 'undefined' &&
    typeof CSS.registerProperty === 'function' &&
    !( navigator.connection && navigator.connection.saveData )
) {
    document.documentElement.classList.add('spx-houdini');
}

window.spxPhotonVCard = new SpxPhotonVCard();
}

if (document.readyState === 'loading') {
document.addEventListener('DOMContentLoaded', spx_photon_boot);
} else {
spx_photon_boot();
}

})(window, document);
