/**
 * AiWA Identity Animation – Block Save Component
 *
 * Outputs the static HTML that WordPress stores in post content.
 * The view script (view.js) progressively enhances this markup with
 * cycling animation on capable browsers.
 */

import { useBlockProps } from '@wordpress/block-editor';

/**
 * Block save function.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @return {JSX.Element} Saved block markup.
 */
export default function save( { attributes } ) {
	const {
		mainText,
		cycleWords,
		fontSize,
		textColor,
		accentColor,
		backgroundColor,
		animationStyle,
	} = attributes;

	const blockProps = useBlockProps.save( {
		style: {
			backgroundColor,
			'--aiwa-text-color': textColor,
			'--aiwa-accent-color': accentColor,
			'--aiwa-font-size': fontSize,
		},
		'data-animation-style': animationStyle,
		// Encode cycling words as a JSON array on the element so view.js can
		// read them without a server round-trip (no REST API call needed).
		'data-cycle-words': JSON.stringify(
			Array.isArray( cycleWords ) ? cycleWords : []
		),
	} );

	// Show the first cycling word as the initial static text.
	const initialWord =
		Array.isArray( cycleWords ) && cycleWords.length > 0
			? cycleWords[ 0 ]
			: '';

	return (
		<div { ...blockProps }>
			<div className="aiwa-animation__container">
				<div
					className="aiwa-animation__main"
					style={ { fontSize, color: textColor } }
				>
					{ mainText }
				</div>
				<div
					className="aiwa-animation__cycle"
					style={ { color: accentColor } }
				>
					{ initialWord }
				</div>
			</div>
		</div>
	);
}
