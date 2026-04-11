<?php

declare(strict_types=1);

namespace Starisian\Sparxstar\Photon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF local field group registrations.
 *
 * Registers two groups on `acf/include_fields`:
 *
 *  1. "Additional User Details" — always active.  Captures the job title,
 *     work/mobile/fax/WhatsApp phone numbers, and the display-card toggle.
 *     These fields complement both WooCommerce-enabled and standard sites.
 *
 *  2. "Extended Contact Details" — registered only when WooCommerce is NOT
 *     active.  Captures company name, postal address, and website URL so
 *     the card still has complete contact data on plain WordPress installs.
 *
 * @package Starisian\Sparxstar\Photon
 * @since   0.5.0
 * @version 0.5.0
 */
final class AcfFields {

	/**
	 * Register the ACF action hooks.
	 *
	 * Called once from {@see Bootloader::init()}.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'acf/include_fields', [ self::class, 'register_base_fields' ] );
		add_action( 'acf/include_fields', [ self::class, 'register_extended_fields' ] );
	}

	/**
	 * Register the "Additional User Details" field group.
	 *
	 * Always active regardless of which other plugins are installed.
	 * Adds: spx_title, spx_work_phone, spx_mobile, spx_fax,
	 *       spx_whatsapp_phone, spx_display_business_card.
	 *
	 * @return void
	 */
	public static function register_base_fields(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			[
				'key'                   => 'group_69d6a285c1a58',
				'title'                 => 'Additional User Details',
				'fields'                => [
					[
						'key'               => 'field_69d6a28a7ff0a',
						'label'             => 'Title',
						'name'              => 'spx_title',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => 'Optional — professional, business title or job role.',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'maxlength'         => 200,
						'allow_in_bindings' => 1,
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					],
					[
						'key'               => 'field_69d6a3837ff0c',
						'label'             => 'Work Phone',
						'name'              => 'spx_work_phone',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'maxlength'         => 25,
						'allow_in_bindings' => 0,
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					],
					[
						'key'               => 'field_69d6a3cf7ff0e',
						'label'             => 'Mobile',
						'name'              => 'spx_mobile',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'maxlength'         => 25,
						'allow_in_bindings' => 1,
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					],
					[
						'key'               => 'field_69d6a3a77ff0d',
						'label'             => 'Fax',
						'name'              => 'spx_fax',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'maxlength'         => 25,
						'allow_in_bindings' => 1,
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					],
					[
						'key'               => 'field_69d6a3287ff0b',
						'label'             => 'WhatsApp',
						'name'              => 'spx_whatsapp_phone',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'maxlength'         => 25,
						'allow_in_bindings' => 1,
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					],
					[
						'key'               => 'field_69d6a4517ff0f',
						'label'             => 'Display Business Card',
						'name'              => 'spx_display_business_card',
						'aria-label'        => '',
						'type'              => 'true_false',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
						'message'           => 'Display the business card on the site.',
						'default_value'     => 1,
						'allow_in_bindings' => 1,
						'ui'                => 0,
						'ui_on_text'        => '',
						'ui_off_text'       => '',
					],
				],
				'location'              => [
					[
						[
							'param'    => 'user_form',
							'operator' => '==',
							'value'    => 'all',
						],
					],
				],
				'menu_order'            => 0,
				'position'              => 'normal',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen'        => '',
				'active'                => true,
				'description'           => 'User fields used in Photon Business Card display.',
				'show_in_rest'          => 0,
				'display_title'         => 'Additional User Fields',
			]
		);
	}

	/**
	 * Register the "Extended Contact Details" fallback field group.
	 *
	 * Only shown when WooCommerce is NOT active.  When WooCommerce is
	 * installed the card pulls company name and postal address from the
	 * billing fields automatically.
	 *
	 * Adds: spx_company, spx_address_1, spx_address_2, spx_city,
	 *       spx_state, spx_postcode, spx_country, spx_website.
	 *
	 * @return void
	 */
	public static function register_extended_fields(): void {
		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}

		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			[
				'key'                   => 'group_spx_extended_contact',
				'title'                 => 'Extended Contact Details',
				'fields'                => [
					[
						'key'               => 'field_spx_company',
						'label'             => 'Company / Organisation',
						'name'              => 'spx_company',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => 'Business or organisation name.',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'maxlength'         => 200,
						'allow_in_bindings' => 1,
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					],
					[
						'key'               => 'field_spx_address_1',
						'label'             => 'Street Address',
						'name'              => 'spx_address_1',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'maxlength'         => 200,
						'allow_in_bindings' => 1,
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					],
					[
						'key'               => 'field_spx_address_2',
						'label'             => 'Street Address Line 2',
						'name'              => 'spx_address_2',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'maxlength'         => 200,
						'allow_in_bindings' => 1,
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					],
					[
						'key'               => 'field_spx_city',
						'label'             => 'City',
						'name'              => 'spx_city',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '50', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'maxlength'         => 100,
						'allow_in_bindings' => 1,
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					],
					[
						'key'               => 'field_spx_state',
						'label'             => 'State / Province',
						'name'              => 'spx_state',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '50', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'maxlength'         => 100,
						'allow_in_bindings' => 1,
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					],
					[
						'key'               => 'field_spx_postcode',
						'label'             => 'Postcode / ZIP',
						'name'              => 'spx_postcode',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '50', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'maxlength'         => 20,
						'allow_in_bindings' => 1,
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					],
					[
						'key'               => 'field_spx_country',
						'label'             => 'Country',
						'name'              => 'spx_country',
						'aria-label'        => '',
						'type'              => 'text',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '50', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'maxlength'         => 100,
						'allow_in_bindings' => 1,
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
					],
					[
						'key'               => 'field_spx_website',
						'label'             => 'Website',
						'name'              => 'spx_website',
						'aria-label'        => '',
						'type'              => 'url',
						'instructions'      => 'Full URL including https://',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => [ 'width' => '', 'class' => '', 'id' => '' ],
						'default_value'     => '',
						'allow_in_bindings' => 1,
						'placeholder'       => 'https://',
					],
				],
				'location'              => [
					[
						[
							'param'    => 'user_form',
							'operator' => '==',
							'value'    => 'all',
						],
					],
				],
				'menu_order'            => 5,
				'position'              => 'normal',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen'        => '',
				'active'                => true,
				'description'           => 'Fallback contact fields used when WooCommerce is not installed.',
				'show_in_rest'          => 0,
				'display_title'         => 'Extended Contact Details',
			]
		);
	}
}
