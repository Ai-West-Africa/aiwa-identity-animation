<?php
/**
 * ACF local field group registrations.
 *
 * @package Starisian\Sparxstar\Photon
 * @since   0.5.0
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\Photon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF local field group registrations.
 *
 * Registers the "User Details" field group on `acf/include_fields`.
 * This single consolidated group covers all contact, communication,
 * social, and card-visibility data for the Photon Business Card.
 *
 * Fields registered:
 *   spx_org_name          — Company / organisation name
 *   spx_role_title        — Professional title / job role
 *   spx_loc_addr_01       — Street address line 1
 *   spx_loc_addr_02       — Street address line 2
 *   spx_loc_city          — City
 *   spx_loc_region        — State / Province / Region
 *   spx_loc_postcode      — Postal / ZIP code
 *   spx_loc_country_code  — ISO 3166-1 alpha-3 country code (select)
 *   spx_rel_com_matrix    — Communication channels repeater (type + identifier)
 *   spx_rel_social_matrix — Social media accounts repeater (platform + URL)
 *   spx_img_brand_blob    — Logo / brand image
 *   spx_state_img_pub     — Share Profile Image toggle
 *   spx_state_card_active — Display Business Card toggle
 *
 * @package Starisian\Sparxstar\Photon
 * @since   0.5.0
 * @version 1.1.0
 */
final class AcfFields {

	/**
	 * Register the ACF action hook.
	 *
	 * Called once from {@see Bootloader::init()}.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'acf/include_fields', [ self::class, 'register_fields' ] );
	}

	/**
	 * Register the "User Details" field group.
	 *
	 * Always active regardless of which other plugins are installed.
	 *
	 * @return void
	 */
	public static function register_fields(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			[
				'key'    => 'group_69d6a285c1a58',
				'title'  => 'User Details',
				'fields' => [
					[
						'key'       => 'field_spx_org_identifier',
						'label'     => 'Company',
						'name'      => 'spx_org_name',
						'type'      => 'text',
						'maxlength' => 200,
					],
					[
						'key'       => 'field_spx_role_descriptor',
						'label'     => 'Title',
						'name'      => 'spx_role_title',
						'type'      => 'text',
						'maxlength' => 200,
					],
					[
						'key'       => 'field_spx_loc_street_primary',
						'label'     => 'Address',
						'name'      => 'spx_loc_addr_01',
						'type'      => 'text',
						'maxlength' => 250,
					],
					[
						'key'       => 'field_spx_loc_street_secondary',
						'label'     => 'Address 2',
						'name'      => 'spx_loc_addr_02',
						'type'      => 'text',
						'maxlength' => 250,
					],
					[
						'key'       => 'field_spx_loc_municipality',
						'label'     => 'City',
						'name'      => 'spx_loc_city',
						'type'      => 'text',
						'maxlength' => 200,
					],
					[
						'key'       => 'field_spx_loc_subdivision',
						'label'     => 'State / Province / Region',
						'name'      => 'spx_loc_region',
						'type'      => 'text',
						'maxlength' => 200,
					],
					[
						'key'       => 'field_spx_loc_postal_index',
						'label'     => 'Postal Code',
						'name'      => 'spx_loc_postcode',
						'type'      => 'text',
						'maxlength' => 20,
					],
					[
						'key'           => 'field_spx_loc_nation_state',
						'label'         => 'Country',
						'name'          => 'spx_loc_country_code',
						'type'          => 'select',
						'choices'       => [
							'AFG' => 'افغانستان',
							'ALB' => 'Shqipëria',
							'DZA' => 'الجزائر',
							'AND' => 'Andorra',
							'AGO' => 'Angola',
							'ATG' => 'Antigua and Barbuda',
							'ARG' => 'Argentina',
							'ARM' => 'Հայաստան',
							'AUS' => 'Australia',
							'AUT' => 'Österreich',
							'AZE' => 'Azərbaycan',
							'BHS' => 'Bahamas',
							'BHR' => 'البحرين',
							'BGD' => 'বাংলাদেশ',
							'BRB' => 'Barbados',
							'BLR' => 'Беларусь',
							'BEL' => 'België',
							'BLZ' => 'Belize',
							'BEN' => 'Bénin',
							'BTN' => 'འབྲུག་ཡུལ་',
							'BOL' => 'Bolivia',
							'BIH' => 'Bosna i Hercegovina',
							'BWA' => 'Botswana',
							'BRA' => 'Brasil',
							'BRN' => 'Brunei',
							'BGR' => 'България',
							'BFA' => 'Burkina Faso',
							'BDI' => 'Burundi',
							'CPV' => 'Cabo Verde',
							'KHM' => 'កម្ពុជា',
							'CMR' => 'Cameroun',
							'CAN' => 'Canada',
							'CAF' => 'République centrafricaine',
							'TCD' => 'Tchad',
							'CHL' => 'Chile',
							'CHN' => '中国',
							'COL' => 'Colombia',
							'COM' => 'جزر القمر',
							'COG' => 'Congo',
							'COD' => 'République démocratique du Congo',
							'CRI' => 'Costa Rica',
							'CIV' => "Côte d'Ivoire",
							'HRV' => 'Hrvatska',
							'CUB' => 'Cuba',
							'CYP' => 'Κύπρος',
							'CZE' => 'Česko',
							'DNK' => 'Danmark',
							'DJI' => 'Djibouti',
							'DMA' => 'Dominica',
							'DOM' => 'República Dominicana',
							'ECU' => 'Ecuador',
							'EGY' => 'مصر',
							'SLV' => 'El Salvador',
							'GNQ' => 'Guinea Ecuatorial',
							'ERI' => 'ኤርትራ',
							'EST' => 'Eesti',
							'SWZ' => 'eSwatini',
							'ETH' => 'ኢትዮጵያ',
							'FJI' => 'Fiji',
							'FIN' => 'Suomi',
							'FRA' => 'France',
							'GAB' => 'Gabon',
							'GMB' => 'Gambia',
							'GEO' => 'საქართველო',
							'DEU' => 'Deutschland',
							'GHA' => 'Ghana',
							'GRC' => 'Ελλάδα',
							'GRD' => 'Grenada',
							'GTM' => 'Guatemala',
							'GIN' => 'Guinée',
							'GNB' => 'Guiné-Bissau',
							'GUY' => 'Guyana',
							'HTI' => 'Haïti',
							'HND' => 'Honduras',
							'HUN' => 'Magyarország',
							'ISL' => 'Ísland',
							'IND' => 'भारत',
							'IDN' => 'Indonesia',
							'IRN' => 'ایران',
							'IRQ' => 'العراق',
							'IRL' => 'Éire',
							'ISR' => 'יִשְׂרָאֵל',
							'ITA' => 'Italia',
							'JAM' => 'Jamaica',
							'JPN' => '日本',
							'JOR' => 'الأردن',
							'KAZ' => 'Қазақстан',
							'KEN' => 'Kenya',
							'KIR' => 'Kiribati',
							'KWT' => 'الكويت',
							'KGZ' => 'Кыргызстан',
							'LAO' => 'ລາວ',
							'LVA' => 'Latvija',
							'LBN' => 'لبنان',
							'LSO' => 'Lesotho',
							'LBR' => 'Liberia',
							'LBY' => 'ليبيا',
							'LIE' => 'Liechtenstein',
							'LTU' => 'Lietuva',
							'LUX' => 'Lëtzebuerg',
							'MDG' => 'Madagasikara',
							'MWI' => 'Malawi',
							'MYS' => 'Malaysia',
							'MDV' => 'ދިވެހިރާއްޖެ',
							'MLI' => 'Mali',
							'MLT' => 'Malta',
							'MHL' => 'Marshall Islands',
							'MRT' => 'موريتانيا',
							'MUS' => 'Maurice',
							'MEX' => 'México',
							'FSM' => 'Micronesia',
							'MDA' => 'Moldova',
							'MCO' => 'Monaco',
							'MNG' => 'Монгол улс',
							'MNE' => 'Crna Gora',
							'MAR' => 'المغرب',
							'MOZ' => 'Moçambique',
							'MMR' => 'မြန်မာ',
							'NAM' => 'Namibia',
							'NRU' => 'Nauru',
							'NPL' => 'नेपाल',
							'NLD' => 'Nederland',
							'NZL' => 'New Zealand',
							'NIC' => 'Nicaragua',
							'NER' => 'Niger',
							'NGA' => 'Nigeria',
							'PRK' => '조선',
							'MKD' => 'Северна Македонија',
							'NOR' => 'Norge',
							'OMN' => 'عمان',
							'PAK' => 'پاکستان',
							'PLW' => 'Palau',
							'PSE' => 'فلسطين',
							'PAN' => 'Panamá',
							'PNG' => 'Papua New Guinea',
							'PRY' => 'Paraguay',
							'PER' => 'Perú',
							'PHL' => 'Pilipinas',
							'POL' => 'Polska',
							'PRT' => 'Portugal',
							'QAT' => 'قطر',
							'ROU' => 'România',
							'RUS' => 'Россия',
							'RWA' => 'Rwanda',
							'KNA' => 'Saint Kitts and Nevis',
							'LCA' => 'Saint Lucia',
							'VCG' => 'Saint Vincent and the Grenadines',
							'WSM' => 'Samoa',
							'SMR' => 'San Marino',
							'STP' => 'São Tomé e Príncipe',
							'SAU' => 'السعودية',
							'SEN' => 'Sénégal',
							'SRB' => 'Србија',
							'SYC' => 'Seychelles',
							'SLE' => 'Sierra Leone',
							'SGP' => 'Singapore',
							'SVK' => 'Slovensko',
							'SVN' => 'Slovenija',
							'SLB' => 'Solomon Islands',
							'SOM' => 'Soomaaliya',
							'ZAF' => 'South Africa',
							'SSD' => 'South Sudan',
							'ESP' => 'España',
							'LKA' => 'ශ්‍රී ලංකාව',
							'SDN' => 'السودان',
							'SUR' => 'Suriname',
							'SWE' => 'Sverige',
							'CHE' => 'Schweiz',
							'SYR' => 'سوريا',
							'TWN' => '台灣',
							'TJK' => 'Тоҷикистон',
							'TZA' => 'Tanzania',
							'THA' => 'ประเทศไทย',
							'TLS' => 'Timor-Leste',
							'TGO' => 'Togo',
							'TON' => 'Tonga',
							'TTO' => 'Trinidad and Tobago',
							'TUN' => 'تونس',
							'TUR' => 'Türkiye',
							'TKM' => 'Türkmenistan',
							'TUV' => 'Tuvalu',
							'UGA' => 'Uganda',
							'UKR' => 'Україна',
							'ARE' => 'الإمارات العربية المتحدة',
							'GBR' => 'United Kingdom',
							'USA' => 'United States',
							'URY' => 'Uruguay',
							'UZB' => 'Oʻzbekiston',
							'VUT' => 'Vanuatu',
							'VAT' => 'Civitas Vaticana',
							'VEN' => 'Venezuela',
							'VNM' => 'Việt Nam',
							'YEM' => 'اليمن',
							'ZMB' => 'Zambia',
							'ZWE' => 'Zimbabwe',
						],
						'ui'            => 1,
						'ajax'          => 1,
						'return_format' => 'value',
					],
					[
						'key'          => 'field_spx_col_com_repeater',
						'label'        => 'Communication Channels',
						'name'         => 'spx_rel_com_matrix',
						'type'         => 'repeater',
						'instructions' => 'Add multiple telecommunication or messaging nodes.',
						'layout'       => 'table',
						'button_label' => 'Add Channel',
						'sub_fields'   => [
							[
								'key'           => 'field_spx_node_com_type',
								'label'         => 'Channel Type',
								'name'          => 'spx_node_key',
								'type'          => 'select',
								'choices'       => [
									'mobile'   => 'Mobile',
									'work'     => 'Work Phone',
									'home'     => 'Home Phone',
									'direct'   => 'Direct Line',
									'whatsapp' => 'WhatsApp',
									'telegram' => 'Telegram',
									'signal'   => 'Signal',
									'wechat'   => 'WeChat',
									'viber'    => 'Viber',
									'line'     => 'Line',
									'zalo'     => 'Zalo',
									'kakao'    => 'KakaoTalk',
									'fax'      => 'Fax',
									'teams'    => 'Microsoft Teams',
									'zoom'     => 'Zoom',
								],
								'return_format' => 'value',
							],
							[
								'key'          => 'field_spx_node_com_val',
								'label'        => 'Identifier / Number',
								'name'         => 'spx_node_val',
								'type'         => 'text',
								'maxlength'    => 25,
								'instructions' => 'Include country code prefix (e.g., +44).',
							],
						],
					],
					[
						'key'          => 'field_spx_col_social_external',
						'label'        => 'Social Media',
						'name'         => 'spx_rel_social_matrix',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Add Node',
						'sub_fields'   => [
							[
								'key'           => 'field_spx_node_platform_type',
								'label'         => 'Social Account',
								'name'          => 'spx_node_key',
								'type'          => 'select',
								'choices'       => [
									'amazon_music'  => 'Amazon Music',
									'apple_music'   => 'Apple Music',
									'audiomack'     => 'Audiomack',
									'baidu_tieba'   => 'Baidu Tieba',
									'bandcamp'      => 'Bandcamp',
									'behance'       => 'Behance',
									'bereal'        => 'BeReal',
									'bilibili'      => 'Bilibili',
									'bluesky'       => 'Bluesky',
									'caffeine'      => 'Caffeine',
									'deezer'        => 'Deezer',
									'discord'       => 'Discord',
									'douyin'        => 'Douyin',
									'dribbble'      => 'Dribbble',
									'facebook'      => 'Facebook',
									'flickr'        => 'Flickr',
									'github'        => 'GitHub',
									'instagram'     => 'Instagram',
									'kick'          => 'Kick',
									'kuaishou'      => 'Kuaishou',
									'lemmy'         => 'Lemmy',
									'line'          => 'Line',
									'linkedin'      => 'LinkedIn',
									'mastodon'      => 'Mastodon',
									'medium'        => 'Medium',
									'pandora'       => 'Pandora',
									'pinterest'     => 'Pinterest',
									'pixiv'         => 'Pixiv',
									'qq'            => 'QQ',
									'quora'         => 'Quora',
									'reddit'        => 'Reddit',
									'rumble'        => 'Rumble',
									'snapchat'      => 'Snapchat',
									'soundcloud'    => 'SoundCloud',
									'spotify'       => 'Spotify',
									'stack_overflow' => 'Stack Overflow',
									'telegram'      => 'Telegram',
									'threads'       => 'Threads',
									'tidal'         => 'Tidal',
									'tiktok'        => 'TikTok',
									'truth_social'  => 'Truth Social',
									'tumblr'        => 'Tumblr',
									'twitch'        => 'Twitch',
									'viber'         => 'Viber',
									'vk'            => 'VK',
									'vsco'          => 'VSCO',
									'whatsapp'      => 'WhatsApp',
									'wechat'        => 'WeChat',
									'weibo'         => 'Weibo',
									'xiaohongshu'   => 'Xiaohongshu',
									'x'             => 'X',
									'youtube'       => 'YouTube',
									'youtube_music' => 'YouTube Music',
									'zhihu'         => 'Zhihu',
								],
								'return_format' => 'value',
							],
							[
								'key'      => 'field_spx_node_uri_target',
								'label'    => 'Profile URL',
								'name'     => 'spx_node_val',
								'type'     => 'url',
								'required' => 1,
							],
						],
					],
					[
						'key'           => 'field_spx_media_blob_brand',
						'label'         => 'Logo / Image',
						'name'          => 'spx_img_brand_blob',
						'type'          => 'image',
						'return_format' => 'array',
						'preview_size'  => 'medium',
					],
					[
						'key'           => 'field_spx_bool_img_visibility',
						'label'         => 'Share Profile Image',
						'name'          => 'spx_state_img_pub',
						'type'          => 'true_false',
						'default_value' => 1,
						'ui'            => 1,
					],
					[
						'key'           => 'field_spx_bool_card_activation',
						'label'         => 'Display Business Card',
						'name'          => 'spx_state_card_active',
						'type'          => 'true_false',
						'default_value' => 1,
						'ui'            => 1,
					],
				],
				'location'     => [
					[
						[
							'param'    => 'user_form',
							'operator' => '==',
							'value'    => 'all',
						],
					],
				],
				'active'       => true,
				'show_in_rest' => 1,
			]
		);
	}
}
