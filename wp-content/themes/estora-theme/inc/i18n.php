<?php
/**
 * Public bilingual layer (English / Thai).
 *
 * Translations are applied at runtime so the theme has no build step and
 * missing strings fall back to English instead of disappearing.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL for the current request in a given language.
 *
 * @param string $lang en|th.
 */
function estora_lang_url( string $lang ): string {
	$lang = in_array( $lang, array( 'en', 'th' ), true ) ? $lang : 'en';
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	$path = strtok( $uri, '?' );
	$args = array();
	$qs   = parse_url( $uri, PHP_URL_QUERY );
	if ( $qs ) {
		parse_str( (string) $qs, $args );
	}
	$args['lang'] = $lang;
	return home_url( $path ?: '/' ) . '?' . http_build_query( $args );
}

/**
 * Language switcher.
 *
 * @param string $class Extra class.
 */
function estora_language_switcher( string $class = '' ): void {
	$current = estora_language();
	?>
	<div class="estora-lang <?php echo esc_attr( $class ); ?>" role="group" aria-label="<?php esc_attr_e( 'Language', 'estora-theme' ); ?>">
		<a href="<?php echo esc_url( estora_lang_url( 'en' ) ); ?>" hreflang="en" lang="en" class="<?php echo 'en' === $current ? 'is-active' : ''; ?>" <?php echo 'en' === $current ? 'aria-current="true"' : ''; ?>>EN</a>
		<span aria-hidden="true">/</span>
		<a href="<?php echo esc_url( estora_lang_url( 'th' ) ); ?>" hreflang="th" lang="th" class="<?php echo 'th' === $current ? 'is-active' : ''; ?>" <?php echo 'th' === $current ? 'aria-current="true"' : ''; ?>>TH</a>
	</div>
	<?php
}

/**
 * English → Thai strings for the public theme and plugin.
 *
 * @return array<string,string>
 */
function estora_thai_strings(): array {
	return array(
		'Home'                                   => 'หน้าแรก',
		'Properties'                             => 'ทรัพย์สิน',
		'About'                                  => 'เกี่ยวกับเรา',
		'About us'                               => 'เกี่ยวกับเรา',
		'News'                                   => 'ข่าวสาร',
		'Contact'                                => 'ติดต่อเรา',
		'Favourites'                             => 'รายการโปรด',
		'Saved properties'                       => 'ทรัพย์ที่บันทึกไว้',
		'Talk to us'                             => 'คุยกับเรา',
		'Open menu'                              => 'เปิดเมนู',
		'Close menu'                             => 'ปิดเมนู',
		'Language'                               => 'ภาษา',
		'Purpose'                                => 'วัตถุประสงค์',
		'Buy or rent'                            => 'ซื้อหรือเช่า',
		'For sale'                               => 'ขาย',
		'For rent'                               => 'ให้เช่า',
		'Property type'                          => 'ประเภททรัพย์',
		'Any type'                               => 'ทุกประเภท',
		'Location'                               => 'ทำเล',
		'Anywhere'                               => 'ทุกทำเล',
		'Search'                                 => 'ค้นหา',
		'Keyword'                                => 'คำค้น',
		'Reference, project or street'           => 'รหัส โครงการ หรือถนน',
		'Min price'                              => 'ราคาต่ำสุด',
		'Max price'                              => 'ราคาสูงสุด',
		'No limit'                               => 'ไม่จำกัด',
		'Bedrooms'                               => 'ห้องนอน',
		'Bathrooms'                              => 'ห้องน้ำ',
		'Any'                                    => 'ทั้งหมด',
		'Sort'                                   => 'เรียงตาม',
		'Newest first'                           => 'ใหม่ที่สุด',
		'Oldest first'                           => 'เก่าที่สุด',
		'Price, low to high'                     => 'ราคา น้อยไปมาก',
		'Price, high to low'                     => 'ราคา มากไปน้อย',
		'Area, small to large'                   => 'พื้นที่ น้อยไปมาก',
		'Area, large to small'                   => 'พื้นที่ มากไปน้อย',
		'Updated directly by the property team'  => 'อัปเดตโดยทีมทรัพย์สินโดยตรง',
		'%s property'                            => '%s รายการ',
		'%s properties'                          => '%s รายการ',
		'%s bedrooms'                            => '%s ห้องนอน',
		'%s bathrooms'                           => '%s ห้องน้ำ',
		'%s parking spaces'                      => 'ที่จอดรถ %s คัน',
		'%s floors'                              => '%s ชั้น',
		'Built %d'                               => 'สร้างปี %d',
		'Reference %s'                           => 'รหัส %s',
		'Single level'                           => 'ชั้นเดียว',
		'%s live listings'                       => '%s ประกาศ',
		'%s home'                                => '%s หลัง',
		'%s homes'                               => '%s หลัง',
		'News & notes'                           => 'ข่าวสารและบันทึก',
		'Market observations and practical guides from the office. No listings disguised as articles.' => 'มุมมองตลาดและคู่มือใช้งานจริงจากสำนักงาน ไม่มีประกาศขายที่แต่งเป็นบทความ',
		'Read the note'                          => 'อ่านบันทึก',
		'No articles yet'                        => 'ยังไม่มีบทความ',
		'Practical notes will appear here as the office publishes them.' => 'บันทึกที่มีประโยชน์จะแสดงที่นี่เมื่อสำนักงานเผยแพร่',
		'At a glance'                            => 'ภาพรวม',
		'Team'                                   => 'ทีมงาน',
		'The people who hold the file'           => 'คนที่ดูแลแฟ้มของคุณ',
		'Buying or selling?'                     => 'ซื้อหรือขาย?',
		'See current listings'                   => 'ดูประกาศปัจจุบัน',
		'Tell us what you need'                  => 'ส่งบรีฟให้เรา',
		'What are you looking for?'              => 'คุณกำลังมองหาอะไร?',
		'I agree to be contacted about my inquiry.' => 'ยินยอมให้ติดต่อกลับเกี่ยวกับคำถามของฉัน',
		'For a valuation or a listing brief, include the address and the timing. We will come back with a realistic figure, not the highest one.' => 'สำหรับการประเมินราคาหรือบรีฟประกาศขาย ส่งที่อยู่และช่วงเวลาให้เรา เราจะตอบด้วยราคาที่สมจริง ไม่ใช่ราคาที่สูงที่สุด',
		'Your shortlist'                         => 'รายการที่คุณบันทึก',
		'Saved on this device for guests, or to your account when signed in. Nothing is sent to an agent until you inquire.' => 'บันทึกไว้ในเครื่องนี้สำหรับผู้เยี่ยมชม หรือในบัญชีเมื่อเข้าสู่ระบบ จะไม่ส่งข้อมูลถึงตัวแทนจนกว่าคุณจะสอบถาม',
		'Side by side'                           => 'เทียบเคียง',
		'Up to three properties. The table is generated from the live listing records, including availability.' => 'เปรียบเทียบได้สูงสุดสามทรัพย์ ตารางสร้างจากข้อมูลประกาศจริง รวมสถานะความพร้อม',
		'Add more properties'                    => 'เพิ่มทรัพย์เพื่อเปรียบเทียบ',
		'Refine'                                 => 'กรองผลลัพธ์',
		'Reset all'                              => 'ล้างทั้งหมด',
		'Showing %1$s of %2$s'                   => 'แสดง %1$s จาก %2$s',
		'Sold and rented homes stay visible and are labelled.' => 'บ้านที่ขายแล้วหรือให้เช่าแล้วยังแสดงอยู่ พร้อมป้ายสถานะ',
		'Active filters'                         => 'ตัวกรองที่ใช้',
		'Clear all'                              => 'ล้างทั้งหมด',
		'No properties match this search'        => 'ไม่พบทรัพย์ที่ตรงกับเงื่อนไข',
		'Try widening the price range or removing the location filter. Everything currently published is shown here; we do not hide sold homes.' => 'ลองขยายช่วงราคาหรือเอาตัวกรองทำเลออก ทรัพย์ที่เผยแพร่ทั้งหมดจะแสดงที่นี่ เราไม่ซ่อนบ้านที่ขายแล้ว',
		'Refresh listings'                       => 'โหลดรายการใหม่',
		'Not seeing it?'                         => 'ยังไม่เจอที่ถูกใจ?',
		'Send us the brief instead.'             => 'ส่งบรีฟให้เราแทนได้เลย',
		'Save property'                          => 'บันทึกทรัพย์',
		'Compare'                                => 'เปรียบเทียบ',
		'Saved to favourites'                    => 'บันทึกในรายการโปรดแล้ว',
		'Removed from favourites'                => 'ลบออกจากรายการโปรดแล้ว',
		'Something went wrong. Please try again.' => 'เกิดข้อผิดพลาด โปรดลองอีกครั้ง',
		'Sending…'                               => 'กำลังส่ง…',
		'Inquiry received'                       => 'ได้รับคำถามแล้ว',
		'You can compare three properties at a time.' => 'เปรียบเทียบได้สูงสุดสามทรัพย์',
		'Link copied'                            => 'คัดลอกลิงก์แล้ว',
		'No slots available on this day. Choose another date.' => 'ไม่มีเวลาว่างในวันนี้ เลือกวันอื่น',
		'The office is closed on this day.'      => 'สำนักงานปิดในวันนี้',
		'View image %d'                          => 'ดูรูปที่ %d',
		'Usable area'                            => 'พื้นที่ใช้สอย',
		'Land area'                              => 'พื้นที่ดิน',
		'The property'                           => 'รายละเอียดทรัพย์',
		'Specification'                          => 'ข้อมูลจำเพาะ',
		'Amenities'                              => 'สิ่งอำนวยความสะดวก',
		'Open in OpenStreetMap'                  => 'เปิดใน OpenStreetMap',
		'Exact viewing point is shared on confirmation. Location marker is approximate.' => 'จุดนัดชมที่แน่นอนจะแจ้งเมื่อยืนยันแล้ว หมุดบนแผนที่เป็นเพียงตำแหน่งใกล้เคียง',
		'Video tour'                             => 'วิดีโอทัวร์',
		'Open the video tour'                    => 'เปิดวิดีโอทัวร์',
		'Talk to the office'                     => 'คุยกับทีมงาน',
		'This listing is handled by the office team.' => 'ทรัพย์นี้ดูแลโดยทีมงานสำนักงาน',
		'Ask about this property'                => 'สอบถามเกี่ยวกับทรัพย์นี้',
		'Full name'                              => 'ชื่อ-นามสกุล',
		'Phone'                                  => 'โทรศัพท์',
		'Email'                                  => 'อีเมล',
		'Message'                                => 'ข้อความ',
		'What would you like to know?'           => 'อยากทราบรายละเอียดอะไรเพิ่มเติม?',
		'Company'                                => 'บริษัท',
		'I agree to be contacted about this property.' => 'ยินยอมให้ติดต่อกลับเกี่ยวกับทรัพย์นี้',
		'Send inquiry'                           => 'ส่งคำถาม',
		'Request a viewing'                      => 'นัดชมทรัพย์',
		'Preferred date'                         => 'วันที่สะดวก',
		'Choose a date to see available times.'  => 'เลือกวันที่เพื่อดูเวลาว่าง',
		'Name'                                   => 'ชื่อ',
		'Anything we should know?'               => 'มีอะไรให้เราทราบเพิ่มเติมไหม?',
		'I agree to be contacted to arrange this viewing.' => 'ยินยอมให้ติดต่อกลับเพื่อนัดชม',
		'Request viewing'                        => 'ขอนัดชม',
		'This property is no longer accepting inquiries because it is sold, rented or unavailable.' => 'ทรัพย์นี้ไม่รับคำถามแล้ว เนื่องจากขายแล้ว ให้เช่าแล้ว หรือไม่พร้อม',
		'The office can suggest alternatives.'   => 'ทีมงานแนะนำทรัพย์อื่นที่ใกล้เคียงได้',
		'Browse available homes'                 => 'ดูทรัพย์ที่พร้อมอยู่',
		'Similar'                                => 'ใกล้เคียง',
		'Other properties of this type'          => 'ทรัพย์ประเภทเดียวกัน',
		'All properties'                         => 'ทรัพย์ทั้งหมด',
		'per month'                              => 'ต่อเดือน',
		'per year'                               => 'ต่อปี',
		'month'                                  => 'เดือน',
		'year'                                   => 'ปี',
		'Sale price'                             => 'ราคาขาย',
		'Explore'                                 => 'สำรวจ',
		'Privacy policy'                         => 'นโยบายความเป็นส่วนตัว',
		'Terms of service'                       => 'ข้อกำหนดการใช้งาน',
		'Office'                                 => 'สำนักงาน',
		'All rights reserved.'                   => 'สงวนลิขสิทธิ์',
		'Privacy'                                => 'ความเป็นส่วนตัว',
		'Terms'                                  => 'ข้อกำหนด',
		'Listings are subject to availability.'  => 'รายการทรัพย์ขึ้นอยู่กับสถานะคงเหลือ',
		'Read'                                   => 'อ่าน',
		'All articles'                           => 'บทความทั้งหมด',
		'Journal'                                => 'บทความ',
		'Favorites'                              => 'รายการโปรด',
		'No saved properties yet.'               => 'ยังไม่มีทรัพย์ที่บันทึกไว้',
		'No properties to compare yet.'          => 'ยังไม่มีทรัพย์ให้เปรียบเทียบ',
		'Remove'                                 => 'นำออก',
		'View property'                          => 'ดูทรัพย์',
		'Contact the office'                     => 'ติดต่อสำนักงาน',
		'About ESTORA'                           => 'เกี่ยวกับ ESTORA',
		'Company information'                    => 'ข้อมูลบริษัท',
		'Business hours'                         => 'เวลาทำการ',
		'Our office'                             => 'สำนักงานของเรา',
		'Email us'                               => 'อีเมลถึงเรา',
		'Call us'                                => 'โทรหาเรา',
		'Find us'                                => 'มาหาเรา',
		'Send a message'                         => 'ส่งข้อความ',
		'Your name'                              => 'ชื่อของคุณ',
		'Your email'                             => 'อีเมลของคุณ',
		'Your phone'                             => 'โทรศัพท์ของคุณ',
		'How can we help?'                       => 'ให้เราช่วยอะไรได้บ้าง?',
		'Send message'                           => 'ส่งข้อความ',
		'Thank you. We will reply within one business day.' => 'ขอบคุณ เราจะตอบกลับภายในหนึ่งวันทำการ',
		'Page not found'                         => 'ไม่พบหน้านี้',
		'Return home'                            => 'กลับหน้าแรก',
		'Search results'                         => 'ผลการค้นหา',
	);
}

/**
 * Apply Thai theme/plugin strings at runtime.
 *
 * @param string $translation Translated string.
 * @param string $text        Source string.
 * @param string $domain      Text domain.
 * @return string
 */
function estora_translate_string( $translation, $text, $domain ) {
	if ( is_admin() || ! estora_is_thai() ) {
		return $translation;
	}
	if ( ! in_array( $domain, array( 'estora-theme', 'estora-core', 'default' ), true ) ) {
		return $translation;
	}
	$map = estora_thai_strings();
	return $map[ $text ] ?? $translation;
}
add_filter( 'gettext', 'estora_translate_string', 20, 3 );

/**
 * Plural strings.
 *
 * @param string $translation Translation.
 * @param string $single      Singular.
 * @param string $plural      Plural.
 * @param int    $number      Number.
 * @param string $domain      Domain.
 * @return string
 */
function estora_translate_plural( $translation, $single, $plural, $number, $domain ) {
	if ( is_admin() || ! estora_is_thai() ) {
		return $translation;
	}
	if ( ! in_array( $domain, array( 'estora-theme', 'estora-core', 'default' ), true ) ) {
		return $translation;
	}
	$map = estora_thai_strings();
	$key = 1 === (int) $number ? $single : $plural;
	return $map[ $key ] ?? $translation;
}
add_filter( 'ngettext', 'estora_translate_plural', 20, 5 );

/**
 * Localise page, post, property and testimonial titles.
 *
 * @param string $title   Title.
 * @param int    $post_id Post ID.
 * @return string
 */
function estora_localize_title( $title, $post_id = 0 ) {
	if ( is_admin() || ! estora_is_thai() || ! $post_id ) {
		return $title;
	}
	$thai = (string) get_post_meta( (int) $post_id, '_estora_title_th', true );
	return '' !== $thai ? $thai : $title;
}
add_filter( 'the_title', 'estora_localize_title', 10, 2 );

/**
 * Localise public post content.
 *
 * @param string $content Content.
 * @return string
 */
function estora_localize_content( $content ) {
	if ( is_admin() || ! estora_is_thai() || ! in_the_loop() || ! is_singular() ) {
		return $content;
	}
	$thai = (string) get_post_meta( get_the_ID(), '_estora_content_th', true );
	return '' !== $thai ? $thai : $content;
}
add_filter( 'the_content', 'estora_localize_content', 5 );

/**
 * Localise excerpts.
 *
 * @param string $excerpt Excerpt.
 * @return string
 */
function estora_localize_excerpt( $excerpt ) {
	if ( is_admin() || ! estora_is_thai() ) {
		return $excerpt;
	}
	$thai = (string) get_post_meta( get_the_ID(), '_estora_excerpt_th', true );
	return '' !== $thai ? $thai : $excerpt;
}
add_filter( 'get_the_excerpt', 'estora_localize_excerpt' );

/**
 * Localise menu item labels from the same string map.
 *
 * @param string $title Menu title.
 * @return string
 */
function estora_localize_menu_title( $title ) {
	if ( is_admin() || ! estora_is_thai() ) {
		return $title;
	}
	$map = estora_thai_strings();
	return $map[ $title ] ?? $title;
}
add_filter( 'nav_menu_item_title', 'estora_localize_menu_title' );

/**
 * Localise the browser title tagline.
 *
 * @param array<string,string> $parts Title parts.
 * @return array<string,string>
 */
function estora_localize_document_title( $parts ) {
	if ( ! is_admin() && estora_is_thai() && isset( $parts['tagline'] ) ) {
		$parts['tagline'] = 'อสังหาริมทรัพย์ที่ดูแลอย่างมืออาชีพ';
	}
	return $parts;
}
add_filter( 'document_title_parts', 'estora_localize_document_title' );
