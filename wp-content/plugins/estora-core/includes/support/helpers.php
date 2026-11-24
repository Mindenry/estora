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
