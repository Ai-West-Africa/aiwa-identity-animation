/**
 * AiWA Identity Animation – Frontend View Script
 *
 * Progressively enhances the static block markup with cycling animation.
 *
 * Design goals
 * ────────────
 * • Zero runtime dependencies – compiled to browser-compatible JS by Babel so
 *   it executes on Android 4.4 / Chrome 33 (circa 2014) after transpilation.
 * • Feature detection before every DOM operation – never throws on old browsers.
 * • Respects prefers-reduced-motion on devices that support it.
 * • Pauses cycling on hover / focus for keyboard and screen-reader users.
 *
 * Graceful degradation tiers
 * ──────────────────────────
 * 1. Full animation  — CSS keyframes + JS word cycling (modern browsers).
 * 2. Static display  — animationStyle="none" or no JS / no animation support.
 */

( function () {
	'use strict';

	// ── Minimum capability check ─────────────────────────────────────────────
	// querySelectorAll + addEventListener are available on Android 4.0+.
	if ( ! document.querySelectorAll || ! window.addEventListener ) {
		return;
	}

	// ── Respect reduced-motion preference ───────────────────────────────────
	// matchMedia is available from Chrome 9 / Android 4.0.
	if (
		window.matchMedia &&
		window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
	) {
		return;
	}

	const CYCLE_MS = 3000; // ms between word changes
	const ANIM_MS = 600; // ms for the enter animation (matches CSS)

	/**
	 * Initialise cycling animation for a single block element.
	 *
	 * @param {Element} block The .wp-block-aiwa-identity-animation element.
	 */
	function initBlock( block ) {
		const style = block.getAttribute( 'data-animation-style' ) || 'fade';
		if ( style === 'none' ) {
			return;
		}

		const wordsAttr = block.getAttribute( 'data-cycle-words' );
		if ( ! wordsAttr ) {
			return;
		}

		let words;
		try {
			words = JSON.parse( wordsAttr );
		} catch ( e ) {
			return;
		}

		if ( ! Array.isArray( words ) || words.length < 2 ) {
			return;
		}

		const cycleEl = block.querySelector( '.aiwa-animation__cycle' );
		if ( ! cycleEl ) {
			return;
		}

		let currentIndex = 0;
		let timer = null;

		/**
		 * Advance to the next word, applying the CSS enter animation.
		 */
		function nextWord() {
			currentIndex = ( currentIndex + 1 ) % words.length;

			// Remove animation class, update text, force reflow, re-add class.
			cycleEl.className = cycleEl.className.replace(
				/\s*is-entering\b/g,
				''
			);
			cycleEl.textContent = words[ currentIndex ];
			// Force reflow so removing + re-adding the class triggers animation.
			void cycleEl.offsetWidth; // eslint-disable-line no-void
			cycleEl.className += ' is-entering';

			// Clean up class after animation completes.
			setTimeout( function () {
				cycleEl.className = cycleEl.className.replace(
					/\s*is-entering\b/g,
					''
				);
			}, ANIM_MS );
		}

		function startCycle() {
			if ( timer === null ) {
				timer = setInterval( nextWord, CYCLE_MS );
			}
		}

		function stopCycle() {
			if ( timer !== null ) {
				clearInterval( timer );
				timer = null;
			}
		}

		// Pause cycling on hover / focus (accessibility).
		block.addEventListener( 'mouseenter', stopCycle );
		block.addEventListener( 'focusin', stopCycle );
		block.addEventListener( 'mouseleave', startCycle );
		block.addEventListener( 'focusout', startCycle );

		startCycle();
	}

	// ── Initialise all blocks on the page ───────────────────────────────────
	function init() {
		const blocks = document.querySelectorAll(
			'.wp-block-aiwa-identity-animation'
		);
		for ( let i = 0; i < blocks.length; i++ ) {
			initBlock( blocks[ i ] );
		}
	}

	// Run after DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
