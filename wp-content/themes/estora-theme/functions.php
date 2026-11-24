<?php
/**
 * ESTORA theme bootstrap.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

define( 'ESTORA_THEME_VERSION', '1.3.0' );

/**
 * Asset URL with a filemtime cache break.
 *
 * @param string $path Relative path under assets/.
 */
function estora_theme_asset( string $path ): string {
	$file = get_template_directory() . '/assets/' . ltrim( $path, '/' );
	$ver  = file_exists( $file ) ? (string) filemtime( $file ) : ESTORA_THEME_VERSION;
	return get_template_directory_uri() . '/assets/' . ltrim( $path, '/' ) . '?v=' . $ver;
}

/**
 * Theme setup.
 */
function estora_theme_setup(): void {
	load_theme_textdomain( 'estora-theme', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );

	add_image_size( 'estora-card', 800, 600, true );
	add_image_size( 'estora-large', 1600, 1000, true );
	add_image_size( 'estora-wide', 2000, 1100, true );

	register_nav_menus(
		array(
			'primary' => __( 'Primary navigation', 'estora-theme' ),
			'footer'  => __( 'Footer navigation', 'estora-theme' ),
		)
	);

	add_theme_support(
		'editor-color-palette',
		array(
			array( 'name' => __( 'Ink', 'estora-theme' ), 'slug' => 'ink', 'color' => '#1B1A18' ),
			array( 'name' => __( 'Paper', 'estora-theme' ), 'slug' => 'paper', 'color' => '#FAF8F3' ),
			array( 'name' => __( 'Sand', 'estora-theme' ), 'slug' => 'sand', 'color' => '#EDE6DA' ),
			array( 'name' => __( 'Pine', 'estora-theme' ), 'slug' => 'pine', 'color' => '#2F5D50' ),
			array( 'name' => __( 'Brass', 'estora-theme' ), 'slug' => 'brass', 'color' => '#B08D57' ),
		)
	);
}
add_action( 'after_setup_theme', 'estora_theme_setup' );

/**
 * Front-end assets.
 */
function estora_theme_assets(): void {
	wp_enqueue_style( 'estora-tokens', estora_theme_asset( 'css/tokens.css' ), array(), null );
	wp_enqueue_style( 'bootstrap', estora_theme_asset( 'vendor/bootstrap.min.css' ), array( 'estora-tokens' ), null );
	wp_enqueue_style( 'estora-main', estora_theme_asset( 'css/main.css' ), array( 'bootstrap' ), null );

	wp_enqueue_script( 'bootstrap', estora_theme_asset( 'vendor/bootstrap.bundle.min.js' ), array(), null, true );
	wp_enqueue_script( 'estora-main', estora_theme_asset( 'js/main.js' ), array(), null, true );
	wp_script_add_data( 'estora-main', 'strategy', 'defer' );

	$settings = array(
		'rest'          => esc_url_raw( rest_url( 'estora/v1' ) ),
		'nonce'         => wp_create_nonce( 'wp_rest' ),
		'compareLimit'  => 3,
		'currency'      => estora_setting( 'currency', 'THB' ),
		'isLoggedIn'    => is_user_logged_in(),
		'favoritesPage' => estora_page_url( 'favorites' ),
		'comparePage'   => estora_page_url( 'compare' ),
		'propertyArchive' => get_post_type_archive_link( 'property' ) ?: home_url( '/properties/' ),
		'timezone'      => wp_timezone_string(),
		'strings'       => array(
			'saved'        => __( 'Saved to favourites', 'estora-theme' ),
			'removed'      => __( 'Removed from favourites', 'estora-theme' ),
			'error'        => __( 'Something went wrong. Please try again.', 'estora-theme' ),
			'sending'      => __( 'Sending…', 'estora-theme' ),
			'sent'         => __( 'Inquiry received', 'estora-theme' ),
			'compareFull'  => __( 'You can compare three properties at a time.', 'estora-theme' ),
			'copied'       => __( 'Link copied', 'estora-theme' ),
			'noSlots'      => __( 'No slots available on this day. Choose another date.', 'estora-theme' ),
			'closed'       => __( 'The office is closed on this day.', 'estora-theme' ),
			'favoritesEmpty' => __( 'No saved properties yet.', 'estora-theme' ),
			'compareAdd'   => estora_text( 'Compare', 'เปรียบเทียบ' ),
			'compareAdded' => estora_text( 'Added', 'เพิ่มแล้ว' ),
			'compareAddedToast' => estora_text( 'Added to comparison', 'เพิ่มในรายการเปรียบเทียบแล้ว' ),
			'compareRemovedToast' => estora_text( 'Removed from comparison', 'นำออกจากรายการเปรียบเทียบแล้ว' ),
			'compareEmptyTitle' => estora_text( 'No properties selected', 'ยังไม่ได้เลือกทรัพย์' ),
			'compareEmptyBody' => estora_text( 'Add up to three properties from any listing to compare price, size and specification.', 'เลือกทรัพย์ได้สูงสุดสามรายการจากหน้าใดก็ได้ เพื่อเปรียบเทียบราคา ขนาด และข้อมูลจำเพาะ' ),
			'compareUnavailable' => estora_text( 'Comparison unavailable', 'เปรียบเทียบไม่ได้ในขณะนี้' ),
			'compareTryAgain' => estora_text( 'Please try again.', 'โปรดลองอีกครั้ง' ),
			'browseProperties' => estora_text( 'Browse properties', 'ดูทรัพย์ทั้งหมด' ),
			'attribute'     => estora_text( 'Attribute', 'รายละเอียด' ),
			'price'         => estora_text( 'Price', 'ราคา' ),
			'purpose'       => estora_text( 'Purpose', 'วัตถุประสงค์' ),
			'type'          => estora_text( 'Type', 'ประเภท' ),
			'location'      => estora_text( 'Location', 'ทำเล' ),
			'landArea'      => estora_text( 'Land area', 'พื้นที่ดิน' ),
			'usableArea'    => estora_text( 'Usable area', 'พื้นที่ใช้สอย' ),
			'bedrooms'      => estora_text( 'Bedrooms', 'ห้องนอน' ),
			'bathrooms'     => estora_text( 'Bathrooms', 'ห้องน้ำ' ),
			'parking'       => estora_text( 'Parking', 'ที่จอดรถ' ),
			'availability'  => estora_text( 'Availability', 'สถานะ' ),
			'amenities'     => estora_text( 'Amenities', 'สิ่งอำนวยความสะดวก' ),
			'remove'        => estora_text( 'Remove', 'นำออก' ),
			'perMonth'      => estora_text( ' / month', ' / เดือน' ),
			'favoritesEmptyBody' => estora_text( 'Use the heart on any listing to keep it here.', 'กดรูปหัวใจบนประกาศใดก็ได้เพื่อเก็บไว้ที่นี่' ),
			'loadingTimes'  => estora_text( 'Loading available times.', 'กำลังโหลดเวลาว่าง' ),
			'chooseTime'    => estora_text( 'Choose an available time.', 'เลือกเวลาที่ว่าง' ),
			'viewingRequested' => estora_text( 'Viewing requested', 'ส่งคำขอนัดชมแล้ว' ),
			'yourReference' => estora_text( 'Your reference is', 'รหัสอ้างอิงของคุณคือ' ),
			'close'         => estora_text( 'Close', 'ปิด' ),
			'copyFailed'    => estora_text( 'Copy failed', 'คัดลอกไม่สำเร็จ' ),
			'saveAria'      => estora_text( 'Save property', 'บันทึกทรัพย์' ),
			'removeAria'    => estora_text( 'Remove from favourites', 'นำออกจากรายการโปรด' ),
			'calcLoan'      => estora_text( 'Loan', 'ยอดกู้' ),
			'calcTerm'      => estora_text( 'over', 'ระยะ' ),
			'calcAt'        => estora_text( 'at', 'ดอกเบี้ย' ),
			'calcYears'     => estora_text( 'years', 'ปี' ),
		),
	);
	wp_localize_script( 'estora-main', 'estoraData', $settings );
}
add_action( 'wp_enqueue_scripts', 'estora_theme_assets' );

/**
 * Body classes.
 *
 * @param string[] $classes Classes.
 * @return string[]
 */
function estora_body_classes( $classes ): array {
	$classes[] = 'estora-public';
	if ( is_front_page() ) {
		$classes[] = 'is-home';
	}
	return $classes;
}
add_filter( 'body_class', 'estora_body_classes' );

/**
 * Excerpt length.
 *
 * @param int $length Length.
 */
function estora_excerpt_length( $length ): int {
	return 26;
}
add_filter( 'excerpt_length', 'estora_excerpt_length' );

/**
 * Excerpt suffix.
 *
 * @param string $more More.
 */
function estora_excerpt_more( $more ): string {
	return '…';
}
add_filter( 'excerpt_more', 'estora_excerpt_more' );

/**
 * Skip link target for accessibility.
 */
function estora_skip_link(): void {
	printf(
		'<a class="estora-skip" href="#estora-main">%s</a>',
		esc_html__( 'Skip to content', 'estora-theme' )
	);
}
add_action( 'wp_body_open', 'estora_skip_link', 5 );

/**
 * Contact details from plugin settings, with a safe fallback when the plugin is off.
 *
 * @param string $key Key.
 * @param string $default Fallback.
 */
function estora_company( string $key, string $default = '' ): string {
	return function_exists( 'estora_setting' ) ? (string) estora_setting( $key, $default ) : $default;
}

/**
 * Owner credit shown in the footer and on the About page.
 */
function estora_owner(): string {
	$owner = estora_company( 'company_owner', 'พีรวิชญ์ หล้าคำภา / Peerawit Lakhampa' );
	return '' !== trim( $owner ) ? $owner : 'พีรวิชญ์ หล้าคำภา / Peerawit Lakhampa';
}

require get_template_directory() . '/inc/i18n.php';
require get_template_directory() . '/inc/template-tags.php';

/**
 * Fallback primary navigation when no menu is assigned.
 */
function estora_nav_fallback(): void {
	echo '<ul class="estora-nav__list">';
	printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/' ) ), esc_html__( 'Home', 'estora-theme' ) );
	printf( '<li><a href="%s">%s</a></li>', esc_url( estora_properties_url() ), esc_html__( 'Properties', 'estora-theme' ) );
	printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/about/' ) ), esc_html__( 'About', 'estora-theme' ) );
	printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/news/' ) ), esc_html__( 'News', 'estora-theme' ) );
	printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( '/contact/' ) ), esc_html__( 'Contact', 'estora-theme' ) );
	echo '</ul>';
}
