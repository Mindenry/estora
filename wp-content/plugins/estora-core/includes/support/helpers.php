<?php
/**
 * Public helper functions. Thin wrappers over the plugin services.
 *
 * @package Estora\Core
 */

use Estora\Core\Domain\Property_Status;
use Estora\Core\Repositories\Property_Repository;
use Estora\Core\Services\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Current public language: "en" or "th".
 *
 * Priority: ?lang= → estora_lang cookie → browser Accept-Language → English.
 */
function estora_language(): string {
	static $language = null;
	if ( null !== $language ) {
		return $language;
	}

	$candidate = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : '';
	if ( ! in_array( $candidate, array( 'en', 'th' ), true ) ) {
		$candidate = isset( $_COOKIE['estora_lang'] ) ? sanitize_key( wp_unslash( $_COOKIE['estora_lang'] ) ) : '';
	}
	if ( ! in_array( $candidate, array( 'en', 'th' ), true ) ) {
		$accept   = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '' ) ) );
		$candidate = str_starts_with( $accept, 'th' ) ? 'th' : 'en';
	}

	$language = $candidate;
	return $language;
}

/**
 * Whether the public site is currently Thai.
 */
function estora_is_thai(): bool {
	return 'th' === estora_language();
}

/**
 * Inline bilingual string.
 *
 * @param string $en English.
 * @param string $th Thai.
 */
function estora_text( string $en, string $th ): string {
	return estora_is_thai() ? $th : $en;
}

/**
 * Localised value from post meta, falling back to an English value.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key without prefix, e.g. "title_th".
 * @param string $fallback Fallback.
 */
function estora_localized_meta( int $post_id, string $key, string $fallback = '' ): string {
	if ( estora_is_thai() ) {
		$value = (string) get_post_meta( $post_id, '_estora_' . $key, true );
		if ( '' !== $value ) {
			return $value;
		}
	}
	return $fallback;
}

/**
 * Thai display names for places used across the public site.
 *
 * @param string $name English name.
 */
function estora_place_name( string $name ): string {
	if ( ! estora_is_thai() ) {
		return $name;
	}
	$map = array(
		'Bangkok'              => 'กรุงเทพมหานคร',
		'Chiang Mai'           => 'เชียงใหม่',
		'Phuket'               => 'ภูเก็ต',
		'Hua Hin'              => 'หัวหิน',
		'Pattaya'              => 'พัทยา',
		'Khon Kaen'            => 'ขอนแก่น',
		'Prachuap Khiri Khan'  => 'ประจวบคีรีขันธ์',
		'Chonburi'             => 'ชลบุรี',
		'Suthep'               => 'สุเทพ',
		'Chang Phueak'         => 'ช้างเผือก',
		'Hai Ya'               => 'หายยา',
		'Watthana'             => 'วัฒนา',
		'Khlong Toei'          => 'คลองเตย',
		'Huai Khwang'          => 'ห้วยขวาง',
		'Sathon'               => 'สาทร',
		'Kathu'                => 'กะทู้',
		'Mueang Phuket'        => 'เมืองภูเก็ต',
		'Nong Kae'             => 'หนองแก',
		'Bang Lamung'          => 'บางละมุง',
		'Nong Prue'            => 'หนองปรือ',
		'Mueang Khon Kaen'     => 'เมืองขอนแก่น',
		'Khlong Tan Nuea'      => 'คลองตันเหนือ',
		'Khlong Tan'           => 'คลองตัน',
		'Kamala'               => 'กมลา',
		'Thung Wat Don'        => 'ทุ่งวัดดอน',
		'Na Kluea'             => 'นาเกลือ',
		'Nai Mueang'           => 'ในเมือง',
		'Rawai'                => 'ราไวย์',
	);
	return $map[ $name ] ?? $name;
}

/**
 * Thai property type label.
 *
 * @param string $slug     Type slug.
 * @param string $fallback Fallback label.
 */
function estora_type_name( string $slug, string $fallback = '' ): string {
	if ( ! estora_is_thai() ) {
		return $fallback ?: ucfirst( $slug );
	}
	$map = array(
		'house'      => 'บ้านเดี่ยว',
		'condo'      => 'คอนโดมิเนียม',
		'townhouse'  => 'ทาวน์เฮาส์',
		'land'       => 'ที่ดิน',
		'commercial' => 'อาคารพาณิชย์',
	);
	return $map[ $slug ] ?? ( $fallback ?: $slug );
}

/**
 * Thai purpose label.
 *
 * @param string $purpose sale|rent.
 */
function estora_purpose_name( string $purpose ): string {
	if ( ! estora_is_thai() ) {
		return 'rent' === $purpose ? 'For rent' : 'For sale';
	}
	return 'rent' === $purpose ? 'ให้เช่า' : 'ขาย';
}

/**
 * Thai availability label.
 *
 * @param string $status Status slug.
 */
function estora_availability_name( string $status ): string {
	if ( ! estora_is_thai() ) {
		$map = array(
			'available'   => 'Available',
			'reserved'    => 'Reserved',
			'sold'        => 'Sold',
			'rented'      => 'Rented',
			'unavailable' => 'Unavailable',
		);
		return $map[ $status ] ?? ucfirst( $status );
	}
	$map = array(
		'available'   => 'พร้อมขาย/ให้เช่า',
		'reserved'    => 'จองแล้ว',
		'sold'        => 'ขายแล้ว',
		'rented'      => 'ให้เช่าแล้ว',
		'unavailable' => 'ไม่พร้อม',
	);
	return $map[ $status ] ?? $status;
}

/**
 * Thai amenity label for the known demo vocabulary.
 *
 * @param string $name English amenity.
 */
function estora_amenity_name( string $name ): string {
	if ( ! estora_is_thai() ) {
		return $name;
	}
	$map = array(
		'Courtyard'          => 'คอร์ตยาร์ด',
		'Private pool'       => 'สระว่ายน้ำส่วนตัว',
		'Covered parking'    => 'ที่จอดรถมีหลังคา',
		'Security'           => 'ระบบรักษาความปลอดภัย',
		'Concierge'          => 'คอนเซียร์จ',
		'Pool'               => 'สระว่ายน้ำ',
		'Gym'                => 'ฟิตเนส',
		'Sky lounge'         => 'สกายเลานจ์',
		'Garden'             => 'สวน',
		'Carport'            => 'ที่จอดรถ',
		'Air conditioning'   => 'เครื่องปรับอากาศ',
		'Pets considered'    => 'พิจารณาสัตว์เลี้ยง',
		'Infinity pool'      => 'สระอินฟินิตี้',
		'Sea view'           => 'วิวทะเล',
		'Staff room'         => 'ห้องแม่บ้าน',
		'Generator'          => 'เครื่องปั่นไฟสำรอง',
		'Gated'              => 'รั้วรอบขอบชิด',
		'Loading bay'        => 'พื้นที่โหลดสินค้า',
		'Three-phase power'  => 'ไฟสามเฟส',
		'Office mezzanine'   => 'ออฟฟิศชั้นลอย',
		'24h access'         => 'เข้าได้ 24 ชั่วโมง',
		'High ceilings'      => 'เพดานสูง',
		'Balcony'            => 'ระเบียง',
		'Fibre internet'     => 'อินเทอร์เน็ตไฟเบอร์',
		'Furnished'          => 'พร้อมเฟอร์นิเจอร์',
		'Chanote title'      => 'โฉนดที่ดิน',
		'Road access'        => 'ติดถนน',
		'Mountain view'      => 'วิวภูเขา',
		'Private lift'       => 'ลิฟต์ส่วนตัว',
		'Roof terrace'       => 'ดาดฟ้า',
		'Smart entry'        => 'ระบบเข้าออกอัจฉริยะ',
		'Beach access'       => 'ติดชายหาด',
		'Co-working room'    => 'ห้องโคเวิร์กกิ้ง',
	);
	return $map[ $name ] ?? $name;
}

/**
 * Localised company setting for the public site.
 *
 * @param string $key     Setting key.
 * @param string $default Default.
 */
function estora_company_text( string $key, string $default = '' ): string {
	$value = function_exists( 'estora_company' ) ? estora_company( $key, $default ) : $default;
	if ( ! estora_is_thai() ) {
		return $value;
	}
	$map = array(
		'company_tagline'   => 'อสังหาริมทรัพย์ที่ดูแลอย่างมืออาชีพ',
		'company_address'   => '14 ถนนนิมมานเหมินท์ ตำบลสุเทพ อำเภอเมือง จังหวัดเชียงใหม่ 50200',
		'company_hours_text' => 'วันจันทร์–วันเสาร์ 09:00–18:00 น.',
	);
	return $map[ $key ] ?? $value;
}

/**
 * Thai label for seeded staff titles.
 *
 * @param string $title English title.
 */
function estora_role_title( string $title ): string {
	if ( ! estora_is_thai() ) {
		return $title;
	}
	$map = array(
		'Senior sales agent'     => 'ตัวแทนขายอาวุโส',
		'Sales agent'            => 'ตัวแทนขาย',
		'Sales manager'          => 'ผู้จัดการฝ่ายขาย',
		'Property manager'       => 'ผู้จัดการทรัพย์สิน',
		'Marketing'              => 'การตลาด',
		'Operations administrator' => 'ผู้ดูแลปฏิบัติการ',
	);
	return $map[ $title ] ?? $title;
}

/**
 * Custom table name.
 *
 * @param string $name Table suffix, e.g. "leads".
 */
function estora_table( string $name ): string {
	global $wpdb;
	return $wpdb->prefix . 'estora_' . $name;
}

/**
 * Read a setting.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Fallback.
 * @return mixed
 */
function estora_setting( string $key, $default = null ) {
	return Settings::get( $key, $default );
}

/**
 * Format an amount in the site currency.
 *
 * @param float|int|string $amount Amount.
 * @param bool             $compact Whether to compact large values.
 */
function estora_price( $amount, bool $compact = false ): string {
	return Estora\Core\Support\Formatter::price( $amount, estora_setting( 'currency', 'THB' ), $compact );
}

/**
 * Property meta with a default.
 *
 * @param int    $post_id Property ID.
 * @param string $key     Meta key without prefix.
 * @param mixed  $default Fallback.
 * @return mixed
 */
function estora_meta( int $post_id, string $key, $default = null ) {
	$value = get_post_meta( $post_id, '_estora_' . $key, true );
	return ( '' === $value || null === $value ) ? $default : $value;
}

/**
 * Numeric price shown for a property (sale price or rental price).
 *
 * @param int $post_id Property ID.
 */
function estora_property_price( int $post_id ): float {
	$display = (float) estora_meta( $post_id, 'price_display', 0 );
	if ( $display > 0 ) {
		return $display;
	}
	$sale = (float) estora_meta( $post_id, 'price_sale', 0 );
	$rent = (float) estora_meta( $post_id, 'price_rent', 0 );
	return $sale > 0 ? $sale : $rent;
}

/**
 * Human price label, including the rental period.
 *
 * @param int $post_id Property ID.
 */
function estora_property_price_label( int $post_id ): string {
	$price = estora_property_price( $post_id );
	$label = estora_price( $price );
	if ( 'rent' === estora_property_purpose( $post_id ) ) {
		$period = estora_meta( $post_id, 'rent_period', 'month' );
		$label .= ' / ' . ( 'year' === $period ? __( 'year', 'estora-core' ) : __( 'month', 'estora-core' ) );
	}
	return $label;
}

/**
 * Purpose slug for a property.
 *
 * @param int $post_id Property ID.
 */
function estora_property_purpose( int $post_id ): string {
	$terms = wp_get_post_terms( $post_id, 'property_purpose', array( 'fields' => 'slugs' ) );
	return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : 'sale';
}

/**
 * First term name for a taxonomy.
 *
 * @param int    $post_id  Property ID.
 * @param string $taxonomy Taxonomy.
 */
function estora_term_name( int $post_id, string $taxonomy ): string {
	$terms = wp_get_post_terms( $post_id, $taxonomy );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}
	return $terms[0]->name;
}

/**
 * Availability slug.
 *
 * @param int $post_id Property ID.
 */
function estora_property_availability( int $post_id ): string {
	return (string) estora_meta( $post_id, 'availability', Property_Status::AVAILABLE );
}

/**
 * Whether a property accepts new inquiries.
 *
 * @param int $post_id Property ID.
 */
function estora_accepts_inquiries( int $post_id ): bool {
	return Property_Status::accepts_inquiries( estora_property_availability( $post_id ) );
}

/**
 * Primary image data for a property, with the local artboard fallback.
 *
 * @param int    $post_id Property ID.
 * @param string $size    Image size.
 * @return array{url:string,width:int,height:int,alt:string,srcset:string}
 */
function estora_property_image( int $post_id, string $size = 'estora-card' ): array {
	$thumb_id = (int) get_post_thumbnail_id( $post_id );
	$gallery  = estora_meta( $post_id, 'gallery', array() );
	if ( ! $thumb_id && is_array( $gallery ) && ! empty( $gallery ) ) {
		$thumb_id = (int) $gallery[0];
	}

	if ( $thumb_id ) {
		$src = wp_get_attachment_image_src( $thumb_id, $size );
		if ( $src ) {
			return array(
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
				'alt'    => (string) get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
				'srcset' => (string) wp_get_attachment_image_srcset( $thumb_id, $size ),
			);
		}
	}

	$placeholder = (string) estora_meta( $post_id, 'placeholder', 'plate-1' );
	if ( ! preg_match( '/^plate-[1-6]$/', $placeholder ) ) {
		$placeholder = 'plate-1';
	}

	return array(
		'url'    => get_template_directory_uri() . '/assets/img/' . $placeholder . '.png',
		'width'  => 1600,
		'height' => 1000,
		'alt'    => get_the_title( $post_id ),
		'srcset' => '',
	);
}

/**
 * Gallery attachment IDs including the featured image.
 *
 * @param int $post_id Property ID.
 * @return int[]
 */
function estora_property_gallery_ids( int $post_id ): array {
	$ids   = array();
	$thumb = (int) get_post_thumbnail_id( $post_id );
	if ( $thumb ) {
		$ids[] = $thumb;
	}
	$gallery = estora_meta( $post_id, 'gallery', array() );
	if ( is_array( $gallery ) ) {
		foreach ( $gallery as $id ) {
			$id = (int) $id;
			if ( $id && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}
	}
	return $ids;
}

/**
 * Agent display name.
 *
 * @param int $user_id User ID.
 */
function estora_agent_name( int $user_id ): string {
	if ( ! $user_id ) {
		return '';
	}
	$user = get_userdata( $user_id );
	return $user ? $user->display_name : '';
}

/**
 * Agent public profile.
 *
 * @param int $user_id User ID.
 * @return array<string,mixed>
 */
function estora_agent_profile( int $user_id ): array {
	if ( ! $user_id ) {
		return array();
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return array();
	}
	$avatar = (string) get_user_meta( $user_id, 'estora_avatar_url', true );
	return array(
		'id'     => $user_id,
		'name'   => $user->display_name,
		'email'  => $user->user_email,
		'phone'  => (string) get_user_meta( $user_id, 'estora_phone', true ),
		'title'  => (string) get_user_meta( $user_id, 'estora_title', true ),
		'avatar' => $avatar ? esc_url_raw( $avatar ) : '',
		'active' => '0' !== (string) get_user_meta( $user_id, 'estora_active', true ),
	);
}

/**
 * Properties for templates. Delegates to the repository.
 *
 * @param array<string,mixed> $args Query arguments.
 * @return array{items: array<int,array<string,mixed>>, total: int, pages: int}
 */
function estora_public_properties( array $args = array() ): array {
	try {
		return ( new Property_Repository() )->public_query( $args );
	} catch ( \Throwable $e ) {
		return array(
			'items'    => array(),
			'total'    => 0,
			'pages'    => 0,
			'page'     => 1,
			'per_page' => 0,
			'error'    => true,
		);
	}
}

/**
 * Single property as an API-shaped array.
 *
 * @param int $post_id Property ID.
 * @return array<string,mixed>|null
 */
function estora_property_data( int $post_id ): ?array {
	$repo = new Property_Repository();
	$data = $repo->to_array( $post_id );
	return $data ?: null;
}

/**
 * Hash the client IP for audit and rate limiting.
 */
function estora_ip_hash(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return $ip ? hash( 'sha256', wp_salt( 'auth' ) . '|' . $ip ) : '';
}

/**
 * Current UTC datetime in MySQL format (all *_at columns are UTC).
 *
 * @param string $modify Optional relative modification, e.g. "-7 days".
 */
function estora_now( string $modify = '' ): string {
	$ts = time();
	if ( $modify ) {
		$ts = strtotime( $modify, $ts );
	}
	return gmdate( 'Y-m-d H:i:s', $ts );
}

/**
 * Current site-local datetime (appointment wall clock uses this).
 *
 * @param string $modify Optional relative modification.
 */
function estora_now_site( string $modify = '' ): string {
	$ts = current_time( 'timestamp' );
	if ( $modify ) {
		$ts = strtotime( $modify, $ts );
	}
	return gmdate( 'Y-m-d H:i:s', $ts );
}

/**
 * Convert a site-local MySQL datetime to UTC for storage.
 *
 * @param string $local Local datetime.
 */
function estora_local_to_utc( string $local ): string {
	$ts = strtotime( $local );
	return $ts ? gmdate( 'Y-m-d H:i:s', $ts - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) : estora_now();
}

/**
 * Format a stored UTC datetime in the site timezone.
 *
 * @param string $utc    UTC datetime.
 * @param string $format PHP date format.
 */
function estora_format_datetime( string $utc, string $format = 'M j, Y H:i' ): string {
	$ts = strtotime( $utc . ' UTC' );
	return $ts ? wp_date( $format, $ts ) : '';
}

/**
 * URL of a page by slug, falling back to the conventional path.
 *
 * @param string $slug Page slug.
 */
function estora_page_url( string $slug ): string {
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
}
