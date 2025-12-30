<?php
/**
 * SPARXSTAR Photon VCard
 * @version           0.5.0
 * @package           sparxstar-photon-vcard
 * @author            Starisian Technologies (Max Barrett) <support@starisian.com>
 * @copyright         2025 Starisian Technologies. All rights reserved.
 * @license           Starisian Technologies Proprietary
 *
 * @wordpress-plugin
 * Plugin Name:       SPARXSTAR Photon VCard
 * Plugin URI:        https://starisian.com/sparxstar/sparxstar-photon-vcard
 * Description:       roduction-grade digital business card with motion/touch triggers. Finalized for accessibility, security, and legacy hardware resilience.
 * Version:           0.5.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Starisian Technologies (Max Barrett) <support@starisian.com>
 * Author URI:        https://starisian.com
 * Text Domain:       sparxstar-photon-vcard
 * License:           Starisian Technologies Proprietary
 * License URI:       https://starisian.com/license/starisian-technology-proprietary
 * Update URI:        https://starisian.com/sparxstar/sparxstar-photon-vcard/update
 */

declare(strict_types=1);

namespace Starian\Sparxstar\Photon\;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SparxstarPhotonVCard {

	/**
	 * Initialize hooks.
	 * Priority 99 ensures non-blocking load order (Performance).
	 */
	public static function init(): void {
		add_action( 'wp_footer', [ __CLASS__, 'render_optimized_assets' ], 99 );
	}

	/**
	 * Logic Controller.
	 */
	public static function render_optimized_assets(): void {
		// 1. FAIL FAST
		if ( ! is_user_logged_in() || ! is_singular() ) {
			return;
		}

		$user_id   = get_current_user_id();
		$post_obj  = get_queried_object();

		// 2. AUTHORSHIP GUARD
		if ( ! $post_obj instanceof \WP_Post || (int) $post_obj->post_author !== $user_id ) {
			return;
		}

		$user = get_userdata( $user_id );
		
		// 3. TYPE SAFETY
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		// 4. PERMISSION CHECK
		$allowed_roles = [ 'administrator', 'vip_business_user', 'editor' ];
		if ( empty( array_intersect( $allowed_roles, (array) $user->roles ) ) ) {
			return;
		}

		// 5. ENTERPRISE CONFIGURATION (Filter Hook)
		$disable_sensors = apply_filters( 'vip_motion_disable_sensors', false, $user_id, $post_obj->ID );

		// 6. DATA PREPARATION
		$card_data = [
			'name'     => get_user_meta( $user_id, 'scf_full_name', true ) ?: $user->display_name,
			'title'    => get_user_meta( $user_id, 'scf_job_title', true ) ?: 'Business Associate',
			'phone'    => get_user_meta( $user_id, 'scf_phone_number', true ) ?: '',
			'email'    => $user->user_email,
			'logo'     => get_user_meta( $user_id, 'scf_business_logo_url', true ) ?: '',
			'noSensor' => (bool) $disable_sensors,
		];

		self::output_inline_code( $card_data );
	}

	/**
	 * Output optimized inline assets.
	 */
	private static function output_inline_code( array $data ): void {
		// Security: HEX flags prevent XSS in JSON context.
		$json_payload = wp_json_encode(
			$data,
			JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		);
		?>
		<style id="vip-motion-css">
			/* Base Overlay: Accessibility & Layout */
			#vip-card-overlay {
				display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
				background-color: rgba(0,0,0,0.96); color: #fff; z-index: 999999;
				flex-direction: column; align-items: center; justify-content: center;
				font-family: -apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
				text-align: center; padding: 20px; box-sizing: border-box;
				opacity: 0; transition: opacity 0.25s ease-out;
				touch-action: none; /* Prevent scroll-through */
			}
			
			/* Active States */
			body.vip-card-active #vip-card-overlay { display: flex; opacity: 1; }
			
			/* iOS Safe Scroll Lock */
			body.vip-card-active { position: fixed; width: 100%; overflow: hidden; }
			
			/* Visuals */
			.vc-logo { max-width: 100px; margin-bottom: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(255,255,255,0.1); }
			.vc-name { font-size: 2rem; font-weight: 800; margin-bottom: 5px; color: #fff; }
			.vc-title { font-size: 1.1rem; color: #bbb; margin-bottom: 35px; text-transform: uppercase; letter-spacing: 1.5px; }
			
			/* Interaction */
			.vc-btn {
				display: block; width: 100%; max-width: 320px; padding: 16px; margin: 10px 0;
				background: #1a1a1a; color: #fff; text-decoration: none;
				border: 1px solid #333; border-radius: 12px; font-size: 1.1rem; font-weight: 600;
				transition: transform 0.1s, background 0.2s;
			}
			.vc-btn:active { transform: scale(0.98); background: #333; }
			
			/* Close Button */
			.vc-close { margin-top: 45px; background: transparent; border: 1px solid #666; color: #999; padding: 12px 35px; border-radius: 30px; cursor: pointer; }
			.vc-close:focus, .vc-btn:focus { outline: 2px solid #fff; outline-offset: 4px; }

			/* Setup Trigger */
			#vip-sensor-grant {
				position: fixed; bottom: 25px; right: 25px; z-index: 9999;
				padding: 14px 24px; background: #fff; color: #000;
				border: none; border-radius: 50px; font-weight: bold;
				box-shadow: 0 8px 20px rgba(0,0,0,0.4);
				animation: vipSlideUp 0.6s ease-out;
			}
			@keyframes vipSlideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
		</style>

		<script>
		(function() {
			'use strict';
			
			// 1. SINGLETON LOCK (Prevent multi-injection issues)
			if (window.__VIP_CARD_LOADED__) return;
			window.__VIP_CARD_LOADED__ = true;
			
			const userData = <?php echo $json_payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;

			class VIPBusinessCard {
				constructor() {
					this.config = {
						threshold: 155,
						resetTime: 2500,
						cooldown: 5000,
						stabilize: 150,
						longPress: 800
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

					this.timers = { sensor: 0, stabilizer: null, longPress: null };
					
					this.init();
				}

				init() {
					const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

					// Motion Setup: Only if allowed, not disabled by PHP, and not reduced motion
					if (!reducedMotion && !userData.noSensor) {
						this.setupMotion();
					}

					// Fallbacks (Always active)
					this.setupFallbacks();
				}

				// --- STORAGE GUARD (Kiosk/Private Mode Safety) ---
				storage(key, val = null) {
					try {
						if (val) localStorage.setItem(key, val);
						else return localStorage.getItem(key);
					} catch (e) { return null; }
				}

				// --- MOTION SENSORS ---

				setupMotion() {
					const isEnabled = this.storage('vip_motion_enabled') === 'true';
					if (isEnabled) {
						document.addEventListener('touchstart', () => this.requestSensorAccess(), { once: true });
					} else {
						this.renderSetupButton();
					}
				}

				renderSetupButton() {
					const btn = document.createElement('button');
					btn.id = 'vip-sensor-grant';
					btn.innerText = 'Activate Card 📳';
					btn.onclick = () => {
						this.requestSensorAccess();
						btn.remove();
					};
					document.body.appendChild(btn);
				}

				async requestSensorAccess() {
					if (this.state.sensorBound) return;

					try {
						if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
							const response = await DeviceOrientationEvent.requestPermission();
							if (response === 'granted') this.enableSensors();
						} else {
							this.enableSensors();
						}
					} catch (e) { /* Fallback remains active */ }
				}

				enableSensors() {
					if (this.state.sensorBound) return;
					this.boundHandler = this.handleOrientation.bind(this);
					window.addEventListener('deviceorientation', this.boundHandler);
					this.state.sensorBound = true;
					this.storage('vip_motion_enabled', 'true');
					if (navigator.vibrate) navigator.vibrate(40);
				}

				disableSensors() {
					if (!this.state.sensorBound) return;
					window.removeEventListener('deviceorientation', this.boundHandler);
					this.state.sensorBound = false;
				}

				handleOrientation(event) {
					if (this.state.isActive) return;

					// THROTTLE: 10fps limit for legacy CPUs
					const now = Date.now();
					if (now - this.timers.sensor < 100) return;
					this.timers.sensor = now;

					const beta = Math.abs(event.beta || 0);
					const isFlat = beta > this.config.threshold;

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
					if (now - this.state.lastTapTime > this.config.resetTime) this.state.taps = 0;

					this.state.taps++;
					this.state.lastTapTime = now;
					
					if (navigator.vibrate) navigator.vibrate(30);

					if (this.state.taps >= 2) {
						this.openCard('motion');
						this.state.taps = 0;
					}
				}

				// --- FALLBACKS (Touch & Keyboard) ---

				setupFallbacks() {
					// 1. Keyboard (Guard: Disable when card is open)
					document.addEventListener('keydown', (e) => {
						if (this.state.isActive) return; 
						if (e.shiftKey && (e.key === 'V' || e.key === 'v')) this.openCard('keyboard');
					});

					// 2. Touch (Long Press with Scroll Guard)
					let startY = 0;
					
					const startPress = (e) => {
						if (this.state.isActive) return;
						startY = window.scrollY;
						
						this.timers.longPress = setTimeout(() => {
							// SCROLL GUARD: If scrolled > 10px, abort.
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

				// --- UI & LIFECYCLE ---

				openCard(triggerMethod) {
					// Cooldown & Duplicate Check
					if (Date.now() - this.state.lastCloseTime < this.config.cooldown) return;
					if (this.state.isActive || document.getElementById('vip-card-overlay')) return;

					this.state.isActive = true;
					this.disableSensors();

					// 1. SCROLL LOCK (iOS Safe)
					this.state.scrollPos = window.scrollY;
					document.body.style.top = `-${this.state.scrollPos}px`;
					
					// 2. ANALYTICS
					document.dispatchEvent(new CustomEvent('vip-card-event', { 
						detail: { type: 'open', method: triggerMethod, timestamp: Date.now() } 
					}));

					if (navigator.vibrate) navigator.vibrate([80, 50, 80]);

					this.injectOverlay();
				}

				injectOverlay() {
					const overlay = document.createElement('div');
					overlay.id = 'vip-card-overlay';
					
					// ACCESSIBILITY: WCAG Modal & Live Region
					overlay.setAttribute('role', 'dialog');
					overlay.setAttribute('aria-modal', 'true');
					overlay.setAttribute('aria-label', 'Digital Business Card');
					overlay.setAttribute('aria-live', 'assertive');

					overlay.innerHTML = `
						${userData.logo ? `<img src="${this.safeURL(userData.logo)}" class="vc-logo" alt="Business Logo">` : ''}
						<div class="vc-name">${this.esc(userData.name)}</div>
						<div class="vc-title">${this.esc(userData.title)}</div>
						
						${userData.phone ? `<a href="${this.safeURL('tel:'+userData.phone)}" class="vc-btn">📞 Call Me</a>` : ''}
						${userData.email ? `<a href="${this.safeURL('mailto:'+userData.email)}" class="vc-btn">✉️ Email Me</a>` : ''}
						
						<button class="vc-close" id="vip-close-btn" aria-label="Close Card">Dismiss</button>
					`;

					document.body.appendChild(overlay);
					document.body.classList.add('vip-card-active');

					// FOCUS TRAP (Simple Cycle)
					const focusable = overlay.querySelectorAll('button, a');
					const firstFocus = focusable[0];
					const lastFocus = focusable[focusable.length - 1];
					const closeBtn = document.getElementById('vip-close-btn');

					// Set initial focus
					setTimeout(() => closeBtn && closeBtn.focus(), 50);

					overlay.addEventListener('keydown', (e) => {
						// Fix: Escape to close
						if (e.key === 'Escape') {
							this.closeCard(overlay);
							return;
						}
						
						// Fix: Tab Cycle
						if (e.key === 'Tab') {
							if (e.shiftKey) { // Shift+Tab
								if (document.activeElement === firstFocus) {
									e.preventDefault();
									lastFocus.focus();
								}
							} else { // Tab
								if (document.activeElement === lastFocus) {
									e.preventDefault();
									firstFocus.focus();
								}
							}
						}
					});

					closeBtn.onclick = () => this.closeCard(overlay);
				}

				closeCard(overlay) {
					document.body.classList.remove('vip-card-active');
					
					// RESTORE SCROLL
					document.body.style.top = '';
					window.scrollTo(0, this.state.scrollPos);
					
					this.state.isActive = false;
					this.state.lastCloseTime = Date.now();

					// ANALYTICS CLOSE EVENT
					document.dispatchEvent(new CustomEvent('vip-card-event', { 
						detail: { type: 'close', timestamp: Date.now() } 
					}));

					setTimeout(() => {
						overlay.remove();
						if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && !userData.noSensor) {
							this.enableSensors();
						}
					}, 250);
				}

				// SECURITY: Text Escaping
				esc(str) {
					if (!str) return '';
					const div = document.createElement('div');
					div.textContent = str;
					return div.innerHTML;
				}

				// SECURITY: URL Sanitization
				safeURL(url) {
					if (!url) return '';
					try {
						const u = new URL(url, window.location.href);
						if (['http:', 'https:', 'mailto:', 'tel:'].includes(u.protocol)) {
							return u.href;
						}
					} catch (e) {
						if (url.startsWith('tel:') || url.startsWith('mailto:') || url.startsWith('/')) return url;
					}
					return '';
				}
			}

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', () => new VIPBusinessCard());
			} else {
				new VIPBusinessCard();
			}
		})();
		</script>
		<?php
	}
}

Business_Card_Motion::init();
