/**
 * AiWA Identity Animation – Block Editor Component
 *
 * Provides the inspector panel (sidebar controls) and the live editor
 * preview with text/colour/animation customisations.
 */

import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	TextControl,
	ColorPicker,
	BaseControl,
	Button,
} from '@wordpress/components';

const ANIMATION_OPTIONS = [
	{ label: __( 'Fade', 'aiwa-identity-animation' ), value: 'fade' },
	{ label: __( 'Slide Up', 'aiwa-identity-animation' ), value: 'slide' },
	{ label: __( 'None (Static)', 'aiwa-identity-animation' ), value: 'none' },
];

const FONT_SIZE_OPTIONS = [
	{ label: __( 'Small (2em)', 'aiwa-identity-animation' ), value: '2em' },
	{ label: __( 'Medium (3em)', 'aiwa-identity-animation' ), value: '3em' },
	{ label: __( 'Large (4.5em)', 'aiwa-identity-animation' ), value: '4.5em' },
	{
		label: __( 'Extra Large (6em)', 'aiwa-identity-animation' ),
		value: '6em',
	},
];

/**
 * Block edit component.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {JSX.Element} The editor UI.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		mainText,
		cycleWords,
		fontSize,
		textColor,
		accentColor,
		backgroundColor,
		animationStyle,
	} = attributes;

	const blockProps = useBlockProps( {
		style: {
			backgroundColor,
			'--aiwa-text-color': textColor,
			'--aiwa-accent-color': accentColor,
			'--aiwa-font-size': fontSize,
		},
	} );

	const addWord = () =>
		setAttributes( { cycleWords: [ ...cycleWords, '' ] } );

	const updateWord = ( index, value ) => {
		const next = [ ...cycleWords ];
		next[ index ] = value;
		setAttributes( { cycleWords: next } );
	};

	const removeWord = ( index ) =>
		setAttributes( {
			cycleWords: cycleWords.filter( ( _, i ) => i !== index ),
		} );

	return (
		<>
			<InspectorControls>
				{ /* ── Text ── */ }
				<PanelBody
					title={ __( 'Text', 'aiwa-identity-animation' ) }
					initialOpen
				>
					<TextControl
						label={ __( 'Main Text', 'aiwa-identity-animation' ) }
						value={ mainText }
						onChange={ ( val ) =>
							setAttributes( { mainText: val } )
						}
						help={ __(
							'Primary brand text shown prominently.',
							'aiwa-identity-animation'
						) }
						__nextHasNoMarginBottom
					/>
					<BaseControl
						id="aiwa-cycle-words-control"
						label={ __(
							'Cycling Words',
							'aiwa-identity-animation'
						) }
						help={ __(
							'These words cycle below the main text on the frontend.',
							'aiwa-identity-animation'
						) }
						__nextHasNoMarginBottom
					>
						{ cycleWords.map( ( word, idx ) => (
							<div
								key={ idx }
								style={ {
									display: 'flex',
									gap: '6px',
									marginBottom: '6px',
									alignItems: 'center',
								} }
							>
								<TextControl
									value={ word }
									onChange={ ( val ) =>
										updateWord( idx, val )
									}
									style={ { flex: 1, margin: 0 } }
									__nextHasNoMarginBottom
								/>
								<Button
									isSmall
									isDestructive
									onClick={ () => removeWord( idx ) }
									disabled={ cycleWords.length <= 1 }
									aria-label={ __(
										'Remove word',
										'aiwa-identity-animation'
									) }
								>
									✕
								</Button>
							</div>
						) ) }
						<Button variant="secondary" isSmall onClick={ addWord }>
							{ __( '+ Add Word', 'aiwa-identity-animation' ) }
						</Button>
					</BaseControl>
				</PanelBody>

				{ /* ── Animation ── */ }
				<PanelBody
					title={ __( 'Animation', 'aiwa-identity-animation' ) }
				>
					<SelectControl
						label={ __(
							'Animation Style',
							'aiwa-identity-animation'
						) }
						value={ animationStyle }
						options={ ANIMATION_OPTIONS }
						onChange={ ( val ) =>
							setAttributes( { animationStyle: val } )
						}
						help={ __(
							'Animation plays on the frontend. The editor always shows a static preview.',
							'aiwa-identity-animation'
						) }
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				{ /* ── Typography ── */ }
				<PanelBody
					title={ __( 'Typography', 'aiwa-identity-animation' ) }
				>
					<SelectControl
						label={ __( 'Font Size', 'aiwa-identity-animation' ) }
						value={ fontSize }
						options={ FONT_SIZE_OPTIONS }
						onChange={ ( val ) =>
							setAttributes( { fontSize: val } )
						}
						__nextHasNoMarginBottom
					/>
				</PanelBody>

				{ /* ── Colours ── */ }
				<PanelBody title={ __( 'Colours', 'aiwa-identity-animation' ) }>
					<BaseControl
						id="aiwa-text-color-control"
						label={ __(
							'Main Text Colour',
							'aiwa-identity-animation'
						) }
						__nextHasNoMarginBottom
					>
						<ColorPicker
							color={ textColor }
							onChange={ ( val ) =>
								setAttributes( { textColor: val } )
							}
							enableAlpha={ false }
						/>
					</BaseControl>
					<BaseControl
						id="aiwa-accent-color-control"
						label={ __(
							'Accent / Cycling Word Colour',
							'aiwa-identity-animation'
						) }
						__nextHasNoMarginBottom
					>
						<ColorPicker
							color={ accentColor }
							onChange={ ( val ) =>
								setAttributes( { accentColor: val } )
							}
							enableAlpha={ false }
						/>
					</BaseControl>
					<BaseControl
						id="aiwa-bg-color-control"
						label={ __(
							'Background Colour',
							'aiwa-identity-animation'
						) }
						__nextHasNoMarginBottom
					>
						<ColorPicker
							color={ backgroundColor }
							onChange={ ( val ) =>
								setAttributes( { backgroundColor: val } )
							}
							enableAlpha={ false }
						/>
					</BaseControl>
				</PanelBody>
			</InspectorControls>

			{ /* ── Editor preview ── */ }
			<div { ...blockProps }>
				<div className="aiwa-animation__container">
					<div
						className="aiwa-animation__main"
						style={ { fontSize, color: textColor } }
					>
						{ mainText || 'AiWA' }
					</div>
					<div
						className="aiwa-animation__cycle"
						style={ { color: accentColor } }
					>
						{ cycleWords[ 0 ] || 'Ai West Africa' }
					</div>
					{ animationStyle !== 'none' && (
						<p className="aiwa-animation__editor-hint">
							{ __(
								'↻ Animation plays on the frontend',
								'aiwa-identity-animation'
							) }
						</p>
					) }
				</div>
			</div>
		</>
	);
}
