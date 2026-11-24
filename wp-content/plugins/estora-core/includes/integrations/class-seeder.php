<?php
/**
 * Development seed data. Idempotent unless --force is passed.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Integrations;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Domain\Appointment_Status;
use Estora\Core\Domain\Availability;
use Estora\Core\Domain\Lead_Status;
use Estora\Core\Repositories\Appointment_Repository;
use Estora\Core\Repositories\Campaign_Repository;
use Estora\Core\Repositories\Lead_Repository;
use Estora\Core\Services\Settings;
use Estora\Core\Support\Formatter;

final class Seeder {

	/**
	 * Run the seed.
	 *
	 * @param bool $force Wipe demo business data first.
	 * @return array{ran:bool,message:string}
	 */
	public function run( bool $force = false ): array {
		if ( get_option( 'estora_seeded' ) && ! $force ) {
			return array(
				'ran'     => false,
				'message' => 'Demo content already present. Use --force to rebuild it.',
			);
		}

		if ( $force ) {
			$this->wipe_demo_data();
		}

		$this->configure_site();
		$users = $this->seed_users();
		$this->seed_terms();
		$this->seed_locations();
		$this->seed_pages();
		$this->seed_menu();
		$property_ids = $this->seed_properties( $users );
		$this->seed_testimonials();
		$this->seed_articles();
		$this->seed_campaigns();
		$this->seed_leads( $users, $property_ids );
		$this->seed_appointments( $users, $property_ids );
		$this->seed_activity( $users, $property_ids );

		update_option( 'estora_seeded', estora_now() );
		update_option( 'estora_demo_property_ids', $property_ids );

		delete_transient( 'estora_dashboard_summary' );
		delete_transient( 'estora_filter_options' );

		return array(
			'ran'     => true,
			'message' => sprintf(
				'Seeded %d properties, %d agents, demo leads and viewings.',
				count( $property_ids ),
				5
			),
		);
	}

	/**
	 * Wipe demo business rows. Only reachable through an explicit --force.
	 */
	private function wipe_demo_data(): void {
		global $wpdb;

		$property_ids = (array) get_option( 'estora_demo_property_ids', array() );
		foreach ( $property_ids as $property_id ) {
			wp_delete_post( (int) $property_id, true );
		}

		$demo_posts = get_posts(
			array(
				'post_type'   => array( 'post', 'testimonial', 'property' ),
				'post_status' => 'any',
				'numberposts' => 100,
				'fields'      => 'ids',
				'meta_key'    => '_estora_demo', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		foreach ( $demo_posts as $demo_id ) {
			wp_delete_post( (int) $demo_id, true );
		}

		$tables = array( 'lead_notes', 'lead_assignments', 'appointments', 'favorites', 'inquiries', 'notifications', 'campaigns', 'activity_logs', 'leads' );
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( 'DELETE FROM `' . esc_sql( estora_table( $table ) ) . '`' );
		}

		$testimonials = get_posts(
			array(
				'post_type'   => 'testimonial',
				'numberposts' => 50,
				'post_status' => 'any',
				'fields'      => 'ids',
			)
		);
		foreach ( $testimonials as $id ) {
			wp_delete_post( (int) $id, true );
		}

		delete_option( 'estora_seeded' );
	}

	/**
	 * Site options used by the demo.
	 */
	private function configure_site(): void {
		update_option( 'timezone_string', 'Asia/Bangkok' );
		update_option( 'date_format', 'j M Y' );
		update_option( 'time_format', 'H:i' );
		update_option( 'start_of_week', 1 );
		update_option( 'blogname', 'ESTORA' );
		update_option( 'blogdescription', 'Residential property, handled properly.' );
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( 'posts_per_page', 6 );
		Settings::ensure();
	}

	/**
	 * Staff accounts.
	 *
	 * @return array<string,int>
	 */
	private function seed_users(): array {
		$password = getenv( 'ESTORA_TEST_AGENT_PASSWORD' ) ?: 'Estora!Agent!2026';

		$accounts = array(
			'operations' => array(
				'email' => 'operations@estora.test',
				'name'  => 'Pim Operations',
				'role'  => 'estora_administrator',
				'title' => 'Operations administrator',
				'phone' => '+66 53 000 100',
			),
			'manager'   => array(
				'email' => 'manager@estora.test',
				'name'  => 'Nok Chaiyaporn',
				'role'  => 'estora_sales_manager',
				'title' => 'Sales manager',
				'phone' => '+66 53 000 101',
			),
			'agent'     => array(
				'email' => 'agent@estora.test',
				'name'  => 'Anan Prasert',
				'role'  => 'estora_sales_agent',
				'title' => 'Senior sales agent',
				'phone' => '+66 53 000 102',
			),
			'agent2'    => array(
				'email' => 'mai@estora.test',
				'name'  => 'Mai Wong',
				'role'  => 'estora_sales_agent',
				'title' => 'Sales agent',
				'phone' => '+66 53 000 103',
			),
			'property'  => array(
				'email' => 'property@estora.test',
				'name'  => 'Krit Phan',
				'role'  => 'estora_property_manager',
				'title' => 'Property manager',
				'phone' => '+66 53 000 104',
			),
			'marketing' => array(
				'email' => 'marketing@estora.test',
				'name'  => 'Fern Suda',
				'role'  => 'estora_marketing',
				'title' => 'Marketing',
				'phone' => '+66 53 000 105',
			),
		);

		$ids = array();
		foreach ( $accounts as $key => $account ) {
			$user = get_user_by( 'email', $account['email'] );
			if ( $user ) {
				$id = (int) $user->ID;
				wp_update_user(
					array(
						'ID'           => $id,
						'display_name' => $account['name'],
						'role'         => $account['role'],
					)
				);
			} else {
				$id = wp_insert_user(
					array(
						'user_login'   => $key . '_estora',
						'user_email'   => $account['email'],
						'user_pass'    => $password,
						'display_name' => $account['name'],
						'role'         => $account['role'],
						'first_name'   => strtok( $account['name'], ' ' ),
					)
				);
			}
			if ( is_wp_error( $id ) ) {
				continue;
			}
			$ids[ $key ] = (int) $id;
			update_user_meta( (int) $id, 'estora_active', '1' );
			update_user_meta( (int) $id, 'estora_title', $account['title'] );
			update_user_meta( (int) $id, 'estora_phone', $account['phone'] );
			update_user_meta( (int) $id, 'show_admin_bar_front', 'false' );
		}

		if ( ! empty( $ids['agent'] ) ) {
			Settings::set( 'default_agent_id', (int) $ids['agent'] );
		}

		return $ids;
	}

	/**
	 * Taxonomy terms.
	 */
	private function seed_terms(): void {
		$purposes = array( 'sale' => 'For sale', 'rent' => 'For rent' );
		foreach ( $purposes as $slug => $name ) {
			$this->term( 'property_purpose', $name, $slug );
		}

		$types = array(
			'house'      => 'House',
			'condo'      => 'Condominium',
			'townhouse'  => 'Townhouse',
			'land'       => 'Land',
			'commercial' => 'Commercial',
		);
		foreach ( $types as $slug => $name ) {
			$this->term( 'property_type', $name, $slug );
		}

		$chiangmai = $this->term( 'property_location', 'Chiang Mai', 'chiang-mai' );
		$bangkok   = $this->term( 'property_location', 'Bangkok', 'bangkok' );
		$phuket    = $this->term( 'property_location', 'Phuket', 'phuket' );
		$huahin    = $this->term( 'property_location', 'Hua Hin', 'hua-hin' );
		$pattaya   = $this->term( 'property_location', 'Pattaya', 'pattaya' );
		$khonkaen  = $this->term( 'property_location', 'Khon Kaen', 'khon-kaen' );

		$children = array(
			array( 'Suthep', 'suthep', $chiangmai ),
			array( 'Chang Phueak', 'chang-phueak', $chiangmai ),
			array( 'Hai Ya', 'hai-ya', $chiangmai ),
			array( 'Watthana', 'watthana', $bangkok ),
			array( 'Khlong Toei', 'khlong-toei', $bangkok ),
			array( 'Huai Khwang', 'huai-khwang', $bangkok ),
			array( 'Sathon', 'sathon', $bangkok ),
			array( 'Kathu', 'kathu', $phuket ),
			array( 'Mueang Phuket', 'mueang-phuket', $phuket ),
			array( 'Hua Hin', 'hua-hin-district', $huahin ),
			array( 'Nong Kae', 'nong-kae', $huahin ),
			array( 'Bang Lamung', 'bang-lamung', $pattaya ),
			array( 'Nong Prue', 'nong-prue', $pattaya ),
			array( 'Mueang Khon Kaen', 'mueang-khon-kaen', $khonkaen ),
		);
		foreach ( $children as $child ) {
			$this->term( 'property_location', $child[0], $child[1], (int) $child[2] );
		}
	}

	/**
	 * Mirror taxonomy locations into the custom locations table used by imports
	 * and the filter cache.
	 */
	private function seed_locations(): void {
		global $wpdb;
		$table = estora_table( 'locations' );
		$terms = get_terms( array( 'taxonomy' => 'property_location', 'hide_empty' => false ) );
		if ( ! is_array( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$type = $term->parent ? 'district' : 'province';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM `{$table}` WHERE slug = %s AND type = %s", $term->slug, $type )
			);
			if ( $exists ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$table,
				array(
					'name'       => $term->name,
					'slug'       => $term->slug,
					'type'       => $type,
					'parent_id'  => $term->parent ?: null,
					'created_at' => estora_now(),
				),
				array( '%s', '%s', '%s', '%d', '%s' )
			);
		}
	}

	/**
	 * Create a term if missing.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @param string $name     Name.
	 * @param string $slug     Slug.
	 * @param int    $parent   Parent.
	 * @return int
	 */
	private function term( string $taxonomy, string $name, string $slug, int $parent = 0 ): int {
		$existing = get_term_by( 'slug', $slug, $taxonomy );
		if ( $existing instanceof \WP_Term ) {
			return (int) $existing->term_id;
		}
		$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug, 'parent' => $parent ) );
		return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
	}

	/**
	 * Pages with templates and content.
	 */
	private function seed_pages(): void {
		$home = $this->page(
			'home',
			'Home',
			'',
			'default'
		);
		$news = $this->page( 'news', 'News', '', 'default' );
		$this->page(
			'about',
			'About',
			'<h2>A brokerage built around the file, not the listing.</h2><p>ESTORA represents owners and buyers across Chiang Mai, Bangkok and Phuket. We keep the property record, the customer record and the viewing calendar in one place, so the person who answers your call can see exactly what happened before it.</p><h3>What we do</h3><p>Residential sales and long-term rentals, with a focus on houses, townhouses and low-rise condominiums. Every listing is inspected by our property team before publication. Every offer is documented.</p><h3>How we work</h3><p>One agent owns your file from first viewing to handover. Availability, price and ownership status are updated the moment they change, and the website reflects the same record our office uses.</p>',
			'templates/about.php'
		);
		$this->page(
			'contact',
			'Contact',
			'<p>Tell us what you are looking for. We answer within one business day, Monday to Saturday.</p>',
			'templates/contact.php'
		);
		$this->page( 'favorites', 'Favorites', '', 'templates/favorites.php' );
		$this->page( 'compare', 'Compare', '', 'templates/compare.php' );
		$this->page(
			'privacy',
			'Privacy Policy',
			'<h2>What we collect</h2><p>When you submit an inquiry or request a viewing we store your name, contact details, the property you asked about and your message. We use this to respond and to keep a record of the conversation.</p><h2>How long we keep it</h2><p>Lead records are kept for as long as needed to service your inquiry and to meet legal obligations. Inquiries older than 365 days can be anonymised on request.</p><h2>Your choices</h2><p>Write to our office to request access, correction or deletion of your data. We do not sell personal data.</p>',
			'default'
		);
		$this->page(
			'terms',
			'Terms of Service',
			'<h2>Listings</h2><p>Property information is provided by owners and is believed accurate but is not a warranty. Prices, availability and areas may change without notice. Confirm all details with the office before making a decision.</p><h2>Viewings</h2><p>Viewing requests are subject to owner approval and agent availability. A request is not a confirmed appointment until the office confirms it.</p><h2>Website use</h2><p>Do not attempt to access private records, interfere with the service or submit false information.</p>',
			'default'
		);

		if ( $home && $news ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home );
			update_option( 'page_for_posts', $news );
		}

		$thai_pages = array(
			'home'     => array( 'หน้าแรก', '' ),
			'news'     => array( 'ข่าวสาร', '' ),
			'about'    => array(
				'เกี่ยวกับ ESTORA',
				'<h2>บริษัทนายหน้าที่สร้างรอบแฟ้มทรัพย์ ไม่ใช่แค่ประกาศขาย</h2><p>ESTORA ดูแลเจ้าของและผู้ซื้อในกรุงเทพฯ เชียงใหม่ ภูเก็ต หัวหิน พัทยา และขอนแก่น เรารวมข้อมูลทรัพย์ ลูกค้า และปฏิทินนัดชมไว้ในระบบเดียว ทีมงานจึงเห็นประวัติทั้งหมดก่อนรับสายคุณ</p><h3>สิ่งที่เราทำ</h3><p>ขายและให้เช่าระยะยาวสำหรับบ้านเดี่ยว ทาวน์เฮาส์ และคอนโดมิเนียมทุกทรัพย์ได้รับการตรวจโดยทีมทรัพย์สินก่อนเผยแพร่ และทุกข้อเสนอถูกบันทึกเป็นลายลักษณ์อักษร</p><h3>วิธีทำงาน</h3><p>ตัวแทนหนึ่งคนดูแลแฟ้มของคุณตั้งแต่ชมหลังแรกจนถึงวันโอน สถานะ ราคา และความพร้อมอัปเดตทันทีที่เปลี่ยนแปลง เว็บไซต์จึงสะท้อนข้อมูลชุดเดียวกับที่สำนักงานใช้</p>',
			),
			'contact'  => array(
				'ติดต่อเรา',
				'<p>บอกเราว่าคุณกำลังมองหาอะไร เราตอบกลับภายในหนึ่งวันทำการ วันจันทร์ถึงวันเสาร์</p>',
			),
			'favorites' => array( 'รายการโปรด', '' ),
			'compare'   => array( 'เปรียบเทียบ', '' ),
			'privacy'   => array(
				'นโยบายความเป็นส่วนตัว',
				'<h2>ข้อมูลที่เราเก็บ</h2><p>เมื่อคุณส่งคำถามหรือขอนัดชม เราจะเก็บชื่อ ข้อมูลติดต่อ ทรัพย์ที่สนใจ และข้อความของคุณ เพื่อตอบกลับและเก็บประวัติการสนทนา</p><h2>ระยะเวลาเก็บข้อมูล</h2><p>ข้อมูลลีดจะถูกเก็บเท่าที่จำเป็นต่อการให้บริการและหน้าที่ตามกฎหมาย คุณสามารถขอให้ลบหรือไม่ระบุตัวตนได้เมื่อพ้นระยะเวลา</p><h2>สิทธิของคุณ</h2><p>ติดต่อสำนักงานเพื่อขอเข้าถึง แก้ไข หรือลบข้อมูลของคุณ เราไม่ขายข้อมูลส่วนบุคคล</p>',
			),
			'terms'     => array(
				'ข้อกำหนดการใช้งาน',
				'<h2>ข้อมูลทรัพย์</h2><p>ข้อมูลทรัพย์มาจากเจ้าของและเชื่อว่าถูกต้อง แต่ไม่ใช่การรับประกัน ราคา สถานะ และพื้นที่อาจเปลี่ยนแปลงได้ โปรดยืนยันรายละเอียดกับสำนักงานก่อนตัดสินใจ</p><h2>การนัดชม</h2><p>การขอนัดชมขึ้นอยู่กับการอนุมัติของเจ้าของและตารางของตัวแทน การขอไม่ถือเป็นการยืนยันจนกว่าสำนักงานจะยืนยัน</p><h2>การใช้เว็บไซต์</h2><p>ห้ามพยายามเข้าถึงข้อมูลส่วนตัว รบกวนระบบ หรือส่งข้อมูลเท็จ</p>',
			),
		);
		foreach ( $thai_pages as $slug => $copy ) {
			$page = get_page_by_path( $slug );
			if ( $page instanceof \WP_Post ) {
				update_post_meta( $page->ID, '_estora_title_th', $copy[0] );
				if ( $copy[1] ) {
					update_post_meta( $page->ID, '_estora_content_th', $copy[1] );
				}
			}
		}
	}

	/**
	 * Create or update a page.
	 *
	 * @param string $slug     Slug.
	 * @param string $title    Title.
	 * @param string $content  Content.
	 * @param string $template Page template.
	 * @return int
	 */
	private function page( string $slug, string $title, string $content, string $template ): int {
		$existing = get_page_by_path( $slug );
		$data     = array(
			'post_type'    => 'page',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
		);
		if ( $existing ) {
			$data['ID'] = $existing->ID;
			$id         = wp_update_post( $data );
		} else {
			$id = wp_insert_post( $data );
		}
		if ( $id && ! is_wp_error( $id ) && 'default' !== $template ) {
			update_post_meta( (int) $id, '_wp_page_template', $template );
		}
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Primary navigation.
	 */
	private function seed_menu(): void {
		$menu = wp_get_nav_menu_object( 'Primary' );
		if ( ! $menu ) {
			$menu_id = wp_create_nav_menu( 'Primary' );
		} else {
			$menu_id = (int) $menu->term_id;
		}
		if ( is_wp_error( $menu_id ) ) {
			return;
		}

		$locations            = get_theme_mod( 'nav_menu_locations', array() );
		$locations['primary'] = (int) $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );

		if ( wp_get_nav_menu_items( $menu_id ) ) {
			return;
		}

		$items = array(
			array( 'title' => 'Home', 'url' => home_url( '/' ) ),
			array( 'title' => 'Properties', 'url' => get_post_type_archive_link( 'property' ) ?: home_url( '/properties/' ) ),
			array( 'title' => 'About', 'url' => home_url( '/about/' ) ),
			array( 'title' => 'News', 'url' => home_url( '/news/' ) ),
			array( 'title' => 'Contact', 'url' => home_url( '/contact/' ) ),
		);
		$order = 1;
		foreach ( $items as $item ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'  => $item['title'],
					'menu-item-url'    => $item['url'],
					'menu-item-status' => 'publish',
					'menu-item-position' => $order++,
				)
			);
		}
	}

	/**
	 * Property listings.
	 *
	 * @param array<string,int> $users Staff IDs.
	 * @return int[]
	 */
	private function seed_properties( array $users ): array {
		$listings = array(
			array(
				'title' => 'Courtyard House, Nimman',
				'purpose' => 'sale',
				'type' => 'house',
				'location' => 'suthep',
				'price' => 12500000,
				'beds' => 3, 'baths' => 3, 'usable' => 240, 'land' => 400,
				'address' => '14 Soi 9, Nimmanhaemin Road',
				'province' => 'Chiang Mai', 'district' => 'Suthep', 'subdistrict' => 'Suthep', 'postal' => '50200',
				'lat' => 18.7992, 'lng' => 98.9678,
				'featured' => 1, 'availability' => 'available', 'plate' => 'plate-1',
				'amenities' => array( 'Courtyard', 'Private pool', 'Covered parking', 'Security' ),
				'description' => '<p>A quiet three-bedroom house arranged around a planted courtyard, five minutes from Nimmanhaemin. The living room opens fully to the garden; the kitchen is separate and practical.</p><p>Upstairs, the main bedroom has a walk-in wardrobe and a north-facing balcony. Two further bedrooms share a bathroom. The house was completed in 2019 and has been owner-occupied since.</p>',
				'year' => 2019, 'floors' => 2, 'parking' => 2,
			),
			array(
				'title' => 'Riverside Condominium, Watthana',
				'purpose' => 'sale',
				'type' => 'condo',
				'location' => 'watthana',
				'price' => 8900000,
				'beds' => 2, 'baths' => 2, 'usable' => 78, 'land' => 0,
				'address' => '88 Sukhumvit Soi 39',
				'province' => 'Bangkok', 'district' => 'Watthana', 'subdistrict' => 'Khlong Tan Nuea', 'postal' => '10110',
				'lat' => 13.7367, 'lng' => 100.5730,
				'featured' => 1, 'availability' => 'available', 'plate' => 'plate-2',
				'amenities' => array( 'Concierge', 'Pool', 'Gym', 'Sky lounge' ),
				'description' => '<p>A corner two-bedroom on the 18th floor with windows on two sides and an unobstructed view west. The building has a 25-metre pool, a small gym and a residents\' lounge.</p><p>Sold with fitted wardrobes, kitchen appliances and one parking space. The common-area fee is 55 baht per square metre per month.</p>',
				'year' => 2021, 'floors' => 32, 'parking' => 1,
			),
			array(
				'title' => 'Garden Townhouse, Chang Phueak',
				'purpose' => 'rent',
				'type' => 'townhouse',
				'location' => 'chang-phueak',
				'price' => 32000,
				'beds' => 3, 'baths' => 2, 'usable' => 160, 'land' => 96,
				'address' => '22/4 Moo 5, Chang Phueak',
				'province' => 'Chiang Mai', 'district' => 'Chang Phueak', 'subdistrict' => 'Chang Phueak', 'postal' => '50300',
				'lat' => 18.8140, 'lng' => 98.9750,
				'featured' => 1, 'availability' => 'available', 'plate' => 'plate-3',
				'amenities' => array( 'Garden', 'Carport', 'Air conditioning', 'Pets considered' ),
				'description' => '<p>A three-bedroom townhouse in a small gated row, let unfurnished with white goods included. Small lawn at the back, carport at the front.</p><p>Twelve-month minimum. Two months\' deposit. Water and electricity transferred to the tenant.</p>',
				'year' => 2016, 'floors' => 2, 'parking' => 1,
			),
			array(
				'title' => 'Hillside Villa, Kathu',
				'purpose' => 'sale',
				'type' => 'house',
				'location' => 'kathu',
				'price' => 28500000,
				'beds' => 4, 'baths' => 5, 'usable' => 420, 'land' => 900,
				'address' => '9/12 Soi Kamala, Kathu',
				'province' => 'Phuket', 'district' => 'Kathu', 'subdistrict' => 'Kamala', 'postal' => '83150',
				'lat' => 7.9550, 'lng' => 98.3360,
				'featured' => 1, 'availability' => 'reserved', 'plate' => 'plate-4',
				'amenities' => array( 'Infinity pool', 'Sea view', 'Staff room', 'Generator', 'Gated' ),
				'description' => '<p>A four-bedroom villa on the western slope above Kamala, arranged over two levels with the living spaces on the upper floor to catch the view.</p><p>The pool terrace is 14 metres long. There is a separate staff room and a back-up generator. Freehold, with the chanote held by the current owner.</p>',
				'year' => 2014, 'floors' => 2, 'parking' => 3,
			),
			array(
				'title' => 'Warehouse Unit, Khlong Toei',
				'purpose' => 'rent',
				'type' => 'commercial',
				'location' => 'khlong-toei',
				'price' => 85000,
				'beds' => 0, 'baths' => 2, 'usable' => 650, 'land' => 800,
				'address' => '45 Rama IV Soi 24',
				'province' => 'Bangkok', 'district' => 'Khlong Toei', 'subdistrict' => 'Khlong Tan', 'postal' => '10110',
				'lat' => 13.7230, 'lng' => 100.5520,
				'featured' => 0, 'availability' => 'available', 'plate' => 'plate-5',
				'amenities' => array( 'Loading bay', 'Three-phase power', 'Office mezzanine', '24h access' ),
				'description' => '<p>A 650 square metre warehouse with a 6.5 metre clear height, one loading bay and a small office mezzanine. Three-phase power and 24-hour access.</p><p>Available immediately on a three-year lease.</p>',
				'year' => 2008, 'floors' => 1, 'parking' => 6,
			),
			array(
				'title' => 'Colonial Apartment, Hai Ya',
				'purpose' => 'rent',
				'type' => 'condo',
				'location' => 'hai-ya',
				'price' => 18000,
				'beds' => 2, 'baths' => 1, 'usable' => 72, 'land' => 0,
				'address' => '101 Wua Lai Road',
				'province' => 'Chiang Mai', 'district' => 'Hai Ya', 'subdistrict' => 'Hai Ya', 'postal' => '50100',
				'lat' => 18.7800, 'lng' => 98.9860,
				'featured' => 1, 'availability' => 'available', 'plate' => 'plate-6',
				'amenities' => array( 'High ceilings', 'Balcony', 'Fibre internet', 'Furnished' ),
				'description' => '<p>A two-bedroom upper-floor apartment in a restored 1960s building near Wua Lai. Tall windows, polished timber floors and a deep front balcony.</p><p>Let furnished, including air conditioning in both bedrooms and a washing machine.</p>',
				'year' => 1964, 'floors' => 3, 'parking' => 1,
			),
			array(
				'title' => 'Land Parcel, Suthep',
				'purpose' => 'sale',
				'type' => 'land',
				'location' => 'suthep',
				'price' => 6200000,
				'beds' => 0, 'baths' => 0, 'usable' => 0, 'land' => 1200,
				'address' => 'Off Canal Road, Suthep',
				'province' => 'Chiang Mai', 'district' => 'Suthep', 'subdistrict' => 'Suthep', 'postal' => '50200',
				'lat' => 18.7920, 'lng' => 98.9520,
				'featured' => 0, 'availability' => 'available', 'plate' => 'plate-2',
				'amenities' => array( 'Chanote title', 'Road access', 'Mountain view' ),
				'description' => '<p>A 1,200 square metre parcel with a chanote title and sealed road frontage. Zoned low-density residential. Water and electricity at the boundary.</p>',
				'year' => 0, 'floors' => 0, 'parking' => 0,
			),
			array(
				'title' => 'Townhouse Row, Watthana',
				'purpose' => 'sale',
				'type' => 'townhouse',
				'location' => 'watthana',
				'price' => 7600000,
				'beds' => 3, 'baths' => 3, 'usable' => 180, 'land' => 110,
				'address' => '7/7 Sukhumvit Soi 49',
				'province' => 'Bangkok', 'district' => 'Watthana', 'subdistrict' => 'Khlong Tan Nuea', 'postal' => '10110',
				'lat' => 13.7300, 'lng' => 100.5800,
				'featured' => 0, 'availability' => 'sold', 'plate' => 'plate-3',
				'amenities' => array( 'Private lift', 'Roof terrace', 'Smart entry' ),
				'description' => '<p>A three-storey townhouse with a private lift, roof terrace and secure parking. Sold in October; retained for reference.</p>',
				'year' => 2020, 'floors' => 3, 'parking' => 2,
			),
			array(
				'title' => 'Loft Condominium, Nimman',
				'purpose' => 'rent',
				'type' => 'condo',
				'location' => 'suthep',
				'price' => 26000,
				'beds' => 1, 'baths' => 1, 'usable' => 54, 'land' => 0,
				'address' => '31 Nimmanhaemin Soi 7',
				'province' => 'Chiang Mai', 'district' => 'Suthep', 'subdistrict' => 'Suthep', 'postal' => '50200',
				'lat' => 18.8010, 'lng' => 98.9650,
				'featured' => 1, 'availability' => 'available', 'plate' => 'plate-4',
				'amenities' => array( 'Pool', 'Gym', 'Co-working room', 'Furnished' ),
				'description' => '<p>A one-bedroom loft with a 4.2 metre ceiling in the living area, let furnished. The building has a 20-metre pool, gym and a co-working room on the ground floor.</p>',
				'year' => 2018, 'floors' => 8, 'parking' => 1,
			),
			array(
				'title' => 'Family House, Chang Phueak',
				'purpose' => 'sale',
				'type' => 'house',
				'location' => 'chang-phueak',
				'price' => 9800000,
				'beds' => 4, 'baths' => 3, 'usable' => 280, 'land' => 520,
				'address' => '55 Moo 2, Chang Phueak',
				'province' => 'Chiang Mai', 'district' => 'Chang Phueak', 'subdistrict' => 'Chang Phueak', 'postal' => '50300',
				'lat' => 18.8180, 'lng' => 98.9680,
				'featured' => 0, 'availability' => 'available', 'plate' => 'plate-5',
				'amenities' => array( 'Garden', 'Covered parking', 'Storage', 'Security' ),
				'description' => '<p>A four-bedroom family house in a quiet compound north of the city centre. Large kitchen, separate dining room and a deep covered terrace.</p><p>The garden is mature and irrigated. Two covered parking spaces.</p>',
				'year' => 2012, 'floors' => 2, 'parking' => 2,
			),
			array(
				'title' => 'Beachfront Condominium, Kathu',
				'purpose' => 'rent',
				'type' => 'condo',
				'location' => 'kathu',
				'price' => 65000,
				'beds' => 2, 'baths' => 2, 'usable' => 96, 'land' => 0,
				'address' => '88 Kamala Beach Road',
				'province' => 'Phuket', 'district' => 'Kathu', 'subdistrict' => 'Kamala', 'postal' => '83150',
				'lat' => 7.9480, 'lng' => 98.3390,
				'featured' => 1, 'availability' => 'rented', 'plate' => 'plate-6',
				'amenities' => array( 'Beach access', 'Pool', 'Sea view', 'Furnished' ),
				'description' => '<p>A two-bedroom beachfront unit let until next season. Kept in the catalogue for reference and future availability.</p>',
				'year' => 2017, 'floors' => 12, 'parking' => 1,
			),
			array(
				'title' => 'Ashton Residence, Thonglor',
				'purpose' => 'sale',
				'type' => 'condo',
				'location' => 'watthana',
				'price' => 18900000,
				'beds' => 2, 'baths' => 2, 'usable' => 86, 'land' => 0,
				'address' => '59 Sukhumvit Soi 55',
				'province' => 'Bangkok', 'district' => 'Watthana', 'subdistrict' => 'Khlong Tan Nuea', 'postal' => '10110',
				'lat' => 13.7360, 'lng' => 100.5820,
				'featured' => 1, 'availability' => 'available', 'plate' => 'plate-2',
				'amenities' => array( 'Concierge', 'Pool', 'Gym', 'Fibre internet' ),
				'description' => '<p>A high-floor two-bedroom in the middle of Thonglor, with a wide west-facing living room, a separate kitchen and a balcony deep enough to use. The building has a 30-metre pool, fitness room and 24-hour concierge.</p><p>Walk to BTS Thong Lo in six minutes. Sold with one parking space and fitted wardrobes.</p>',
				'year' => 2022, 'floors' => 28, 'parking' => 1,
			),
			array(
				'title' => 'Sathorn Horizon, Sathon',
				'purpose' => 'sale',
				'type' => 'condo',
				'location' => 'sathon',
				'price' => 12400000,
				'beds' => 2, 'baths' => 2, 'usable' => 74, 'land' => 0,
				'address' => '88 Narathiwat Ratchanakharin Road',
				'province' => 'Bangkok', 'district' => 'Sathon', 'subdistrict' => 'Thung Wat Don', 'postal' => '10120',
				'lat' => 13.7200, 'lng' => 100.5260,
				'featured' => 0, 'availability' => 'available', 'plate' => 'plate-2',
				'amenities' => array( 'Pool', 'Gym', 'Security', 'Sky lounge' ),
				'description' => '<p>A corner two-bedroom with river and city views, a compact study and a covered balcony. The building sits two minutes from BTS Chong Nonsi and has a rooftop lounge.</p>',
				'year' => 2019, 'floors' => 35, 'parking' => 1,
			),
			array(
				'title' => 'Rama IX Sky Home, Huai Khwang',
				'purpose' => 'rent',
				'type' => 'condo',
				'location' => 'huai-khwang',
				'price' => 42000,
				'beds' => 2, 'baths' => 2, 'usable' => 68, 'land' => 0,
				'address' => '9 Rama IX Soi 7',
				'province' => 'Bangkok', 'district' => 'Huai Khwang', 'subdistrict' => 'Huai Khwang', 'postal' => '10310',
				'lat' => 13.7580, 'lng' => 100.5740,
				'featured' => 0, 'availability' => 'available', 'plate' => 'plate-2',
				'amenities' => array( 'Pool', 'Gym', 'Furnished', 'Security' ),
				'description' => '<p>A furnished two-bedroom near MRT Phra Ram 9, with a corner living room, full kitchen and a balcony facing north. Available on a twelve-month lease.</p>',
				'year' => 2018, 'floors' => 24, 'parking' => 1,
			),
			array(
				'title' => 'Baan Talay Courtyard, Hua Hin',
				'purpose' => 'sale',
				'type' => 'house',
				'location' => 'hua-hin-district',
				'price' => 14500000,
				'beds' => 3, 'baths' => 3, 'usable' => 220, 'land' => 480,
				'address' => '12 Soi Hua Hin 88',
				'province' => 'Prachuap Khiri Khan', 'district' => 'Hua Hin', 'subdistrict' => 'Hua Hin', 'postal' => '77110',
				'lat' => 12.5680, 'lng' => 99.9570,
				'featured' => 1, 'availability' => 'available', 'plate' => 'plate-3',
				'amenities' => array( 'Courtyard', 'Private pool', 'Garden', 'Covered parking' ),
				'description' => '<p>A single-storey courtyard house four minutes from Hua Hin beach. The living room opens to a shaded terrace and a small pool; the kitchen is separate and the main suite has its own garden door.</p><p>Freehold with a chanote title. Sold partly furnished.</p>',
				'year' => 2018, 'floors' => 1, 'parking' => 2,
			),
			array(
				'title' => 'Khao Takiab Beach House, Nong Kae',
				'purpose' => 'rent',
				'type' => 'house',
				'location' => 'nong-kae',
				'price' => 65000,
				'beds' => 3, 'baths' => 2, 'usable' => 180, 'land' => 320,
				'address' => '33 Khao Takiab Road',
				'province' => 'Prachuap Khiri Khan', 'district' => 'Nong Kae', 'subdistrict' => 'Nong Kae', 'postal' => '77110',
				'lat' => 12.5210, 'lng' => 99.9840,
				'featured' => 0, 'availability' => 'available', 'plate' => 'plate-3',
				'amenities' => array( 'Beach access', 'Pool', 'Sea view', 'Furnished' ),
				'description' => '<p>A relaxed three-bedroom house one row back from Khao Takiab beach, let furnished for twelve months or more. Broad sea-facing terrace, small pool and covered parking for two cars.</p>',
				'year' => 2015, 'floors' => 2, 'parking' => 2,
			),
			array(
				'title' => 'Wongamat Sea View, Bang Lamung',
				'purpose' => 'sale',
				'type' => 'condo',
				'location' => 'bang-lamung',
				'price' => 9800000,
				'beds' => 2, 'baths' => 2, 'usable' => 92, 'land' => 0,
				'address' => '16 Wongamat Beach Road',
				'province' => 'Chonburi', 'district' => 'Bang Lamung', 'subdistrict' => 'Na Kluea', 'postal' => '20150',
				'lat' => 12.9610, 'lng' => 100.8860,
				'featured' => 0, 'availability' => 'available', 'plate' => 'plate-2',
				'amenities' => array( 'Sea view', 'Pool', 'Gym', 'Security' ),
				'description' => '<p>A two-bedroom on a high floor at Wongamat with a full-width balcony facing the bay. The building has direct beach access, a pool terrace and a small gym.</p>',
				'year' => 2016, 'floors' => 27, 'parking' => 1,
			),
			array(
				'title' => 'Jomtien Pool Villa, Nong Prue',
				'purpose' => 'rent',
				'type' => 'house',
				'location' => 'nong-prue',
				'price' => 85000,
				'beds' => 4, 'baths' => 4, 'usable' => 320, 'land' => 600,
				'address' => '77 Jomtien Second Road',
				'province' => 'Chonburi', 'district' => 'Nong Prue', 'subdistrict' => 'Nong Prue', 'postal' => '20150',
				'lat' => 12.8990, 'lng' => 100.8760,
				'featured' => 1, 'availability' => 'available', 'plate' => 'plate-4',
				'amenities' => array( 'Private pool', 'Garden', 'Covered parking', 'Security' ),
				'description' => '<p>A four-bedroom family villa in a quiet gated lane, five minutes from Jomtien beach. Private pool, mature garden, covered parking for two cars and a separate utility room.</p><p>Available on a twelve-month lease, partly furnished.</p>',
				'year' => 2017, 'floors' => 2, 'parking' => 2,
			),
			array(
				'title' => 'Muang Khon Kaen Family Home',
				'purpose' => 'sale',
				'type' => 'house',
				'location' => 'mueang-khon-kaen',
				'price' => 6200000,
				'beds' => 4, 'baths' => 3, 'usable' => 260, 'land' => 480,
				'address' => '45 Mittraphap Road',
				'province' => 'Khon Kaen', 'district' => 'Mueang Khon Kaen', 'subdistrict' => 'Nai Mueang', 'postal' => '40000',
				'lat' => 16.4320, 'lng' => 102.8230,
				'featured' => 0, 'availability' => 'available', 'plate' => 'plate-3',
				'amenities' => array( 'Garden', 'Covered parking', 'Storage', 'Security' ),
				'description' => '<p>A well-kept four-bedroom family house in central Khon Kaen, close to schools and Mittraphap Road. Large covered terrace, mature garden and a separate storage room.</p>',
				'year' => 2013, 'floors' => 2, 'parking' => 2,
			),
			array(
				'title' => 'Doi Suthep View House, Suthep',
				'purpose' => 'sale',
				'type' => 'house',
				'location' => 'suthep',
				'price' => 16800000,
				'beds' => 4, 'baths' => 4, 'usable' => 310, 'land' => 700,
				'address' => '18 Soi Suthep 9',
				'province' => 'Chiang Mai', 'district' => 'Suthep', 'subdistrict' => 'Suthep', 'postal' => '50200',
				'lat' => 18.8040, 'lng' => 98.9460,
				'featured' => 1, 'availability' => 'available', 'plate' => 'plate-1',
				'amenities' => array( 'Mountain view', 'Private pool', 'Garden', 'Covered parking' ),
				'description' => '<p>A four-bedroom house on the lower slope of Doi Suthep with an uninterrupted mountain view from the living room and pool terrace. The layout separates the main suite from three guest bedrooms.</p>',
				'year' => 2020, 'floors' => 2, 'parking' => 2,
			),
			array(
				'title' => 'Nai Harn Garden Villa, Mueang Phuket',
				'purpose' => 'sale',
				'type' => 'house',
				'location' => 'mueang-phuket',
				'price' => 19500000,
				'beds' => 3, 'baths' => 3, 'usable' => 280, 'land' => 560,
				'address' => '22 Soi Sai Yuan',
				'province' => 'Phuket', 'district' => 'Mueang Phuket', 'subdistrict' => 'Rawai', 'postal' => '83100',
				'lat' => 7.7790, 'lng' => 98.3380,
				'featured' => 1, 'availability' => 'available', 'plate' => 'plate-4',
				'amenities' => array( 'Private pool', 'Garden', 'Sea view', 'Gated' ),
				'description' => '<p>A three-bedroom villa between Nai Harn and Rawai, set behind a private gate with a mature garden and a 12-metre pool. The living pavilion opens on two sides; the main suite has an outdoor shower.</p>',
				'year' => 2019, 'floors' => 1, 'parking' => 2,
			),
		);

		$photo_sets = array(
			array( 'chiangmai-house', array( 'hero-villa', 'interior-living', 'interior-kitchen-2' ) ),
			array( 'bangkok-condo', array( 'pattaya-condo', 'interior-bedroom-2', 'interior-living' ) ),
			array( 'hua-hin-house', array( 'interior-living', 'interior-bedroom', 'interior-kitchen-2' ) ),
			array( 'phuket-villa', array( 'pool-detail', 'interior-bedroom-2', 'interior-living' ) ),
			array( 'warehouse', array( 'interior-kitchen-2', 'interior-living' ) ),
			array( 'interior-bedroom', array( 'interior-living', 'interior-bedroom-2' ) ),
			array( 'land-parcel', array( 'chiangmai-house', 'house-3' ) ),
			array( 'pool-detail', array( 'interior-living', 'interior-kitchen-2' ) ),
			array( 'pattaya-condo', array( 'interior-kitchen-2', 'interior-bedroom-2' ) ),
			array( 'house-4', array( 'interior-living', 'interior-kitchen-2' ) ),
			array( 'phuket-villa', array( 'bangkok-condo', 'interior-bedroom-2' ) ),
			array( 'pattaya-condo', array( 'interior-living', 'interior-kitchen-2' ) ),
			array( 'bangkok-condo', array( 'interior-bedroom-2', 'interior-living' ) ),
			array( 'interior-bedroom-2', array( 'bangkok-condo', 'interior-kitchen-2' ) ),
			array( 'hua-hin-house', array( 'interior-living', 'interior-bedroom' ) ),
			array( 'house-4', array( 'phuket-villa', 'interior-living' ) ),
			array( 'pattaya-condo', array( 'interior-bedroom-2', 'interior-kitchen-2' ) ),
			array( 'hero-villa', array( 'pool-detail', 'interior-living' ) ),
			array( 'chiangmai-house', array( 'house-3', 'interior-bedroom' ) ),
			array( 'house-3', array( 'hero-villa', 'interior-living' ) ),
			array( 'pool-detail', array( 'phuket-villa', 'interior-bedroom-2' ) ),
		);

		$thai_meta = array(
			array( 'บ้านคอร์ตยาร์ด นิมมาน', 'บ้านเดี่ยวสามห้องนอนรอบคอร์ตยาร์ดเขียว ใกล้ Nimmanhaemin ครัวแยกและระเบียงทิศเหนือ', '14 ซอย 9 ถนนนิมมานเหมินท์ สุเทพ เชียงใหม่ 50200' ),
			array( 'ริเวอร์ไซด์ คอนโดมิเนียม วัฒนา', 'คอนโดหัวมุมสองห้องนอนชั้น 18 วิวเมืองโล่ง มีสระว่ายน้ำและฟิตเนสในโครงการ', '88 สุขุมวิท 39 วัฒนา กรุงเทพฯ 10110' ),
			array( 'การ์เดน ทาวน์เฮาส์ ช้างเผือก', 'ทาวน์เฮาส์สามห้องนอนให้เช่าในซอยเล็กที่มีรั้วรอบ สวนหลังบ้านและที่จอดรถมีหลังคา', '22/4 หมู่ 5 ช้างเผือก เชียงใหม่ 50300' ),
			array( 'ฮิลไซด์ วิลล่า กะทู้', 'วิลล่าสี่ห้องนอนบนเนินฝั่งตะวันตกของกมลา วิวทะเล ระเบียงสระว่ายน้ำยาว 14 เมตร', '9/12 ซอยกมลา กะทู้ ภูเก็ต 83150' ),
			array( 'คลังสินค้า คลองเตย', 'คลังสินค้า 650 ตร.ม. เพดานสูง 6.5 เมตร มีพื้นที่โหลดสินค้าและออฟฟิศชั้นลอย', '45 พระราม 4 ซอย 24 คลองเตย กรุงเทพฯ 10110' ),
			array( 'โคโลเนียล อพาร์ตเมนต์ หายยา', 'อพาร์ตเมนต์สองห้องนอนในอาคารยุค 1960 ที่บูรณะใหม่ หน้าต่างสูง พื้นไม้ และระเบียงกว้าง', '101 ถนนวัวลาย หายยา เชียงใหม่ 50100' ),
			array( 'ที่ดิน สุเทพ', 'ที่ดิน 1,200 ตร.ม. โฉนดที่ดิน ติดถนนลาดยาง โซนที่อยู่อาศัยความหนาแน่นต่ำ', 'ติดถนนคลองชลประทาน สุเทพ เชียงใหม่ 50200' ),
			array( 'ทาวน์เฮาส์ วัฒนา', 'ทาวน์เฮาส์สามชั้นพร้อมลิฟต์ส่วนตัว ดาดฟ้า และที่จอดรถปลอดภัย ขายแล้วเก็บไว้เป็นข้อมูลอ้างอิง', '7/7 สุขุมวิท 49 วัฒนา กรุงเทพฯ 10110' ),
			array( 'ลอฟต์ คอนโดมิเนียม นิมมาน', 'คอนโดหนึ่งห้องนอนเพดานสูง 4.2 เมตร ให้เช่าพร้อมเฟอร์นิเจอร์ มีสระว่ายน้ำและโคเวิร์กกิ้ง', '31 นิมมานเหมินท์ ซอย 7 สุเทพ เชียงใหม่ 50200' ),
			array( 'บ้านครอบครัว ช้างเผือก', 'บ้านเดี่ยวสี่ห้องนอนในหมู่บ้านเงียบทางเหนือของเมือง ครัวใหญ่ ระเบียงหลังคาและสวนร่มรื่น', '55 หมู่ 2 ช้างเผือก เชียงใหม่ 50300' ),
			array( 'คอนโดติดหาด กะทู้', 'คอนโดสองห้องนอนติดหาดกมลา ให้เช่าถึงฤดูกาลหน้า เก็บไว้ในแคตตาล็อกเพื่ออ้างอิง', '88 ถนนหาดกมลา กะทู้ ภูเก็ต 83150' ),
			array( 'แอชตัน เรสซิเดนซ์ ทองหล่อ', 'คอนโดสองห้องนอนชั้นสูงใจกลางทองหล่อ วิวตะวันตก ห้องนั่งเล่นกว้างและครัวแยก เดินถึง BTS ทองหล่อ', '59 สุขุมวิท 55 วัฒนา กรุงเทพฯ 10110' ),
			array( 'ซาทอน ฮอไรซอน สาทร', 'คอนโดหัวมุมสองห้องนอนวิวแม่น้ำและเมือง ใกล้ BTS ช่องนนทรี มีสกายเลานจ์บนดาดฟ้า', '88 ถนนนราธิวาสราชนครินทร์ สาทร กรุงเทพฯ 10120' ),
			array( 'พระราม 9 สกาย โฮม ห้วยขวาง', 'คอนโดให้เช่าห้องมุมสองห้องนอน ใกล้ MRT พระราม 9 พร้อมเฟอร์นิเจอร์และครัวเต็ม', '9 พระราม 9 ซอย 7 ห้วยขวาง กรุงเทพฯ 10310' ),
			array( 'บ้านทะเล คอร์ตยาร์ด หัวหิน', 'บ้านเดี่ยวชั้นเดียวรอบคอร์ตยาร์ด ห่างหาดหัวหินสี่นาที มีสระส่วนตัวและ Terrace ร่มรื่น', '12 ซอยหัวหิน 88 หัวหิน ประจวบคีรีขันธ์ 77110' ),
			array( 'เขาตะเกียบ บีช เฮาส์ หนองแก', 'บ้านสามห้องนอนให้เช่าระยะยาว ใกล้หาดเขาตะเกียบ ระเบียงกว้างมองทะเลและสระเล็ก', '33 ถนนเขาตะเกียบ หนองแก ประจวบคีรีขันธ์ 77110' ),
			array( 'วงศ์อมาตย์ ซีวิว บางละมุง', 'คอนโดสองห้องนอนชั้นสูงติดทะเลวงศ์อมาตย์ ระเบียงเต็มความกว้างมองอ่าวพัทยา', '16 ถนนหาดวงศ์อมาตย์ บางละมุง ชลบุรี 20150' ),
			array( 'จอมเทียน พูล วิลล่า หนองปรือ', 'วิลล่าสี่ห้องนอนพร้อมสระส่วนตัวในซอยปิด ใกล้หาดจอมเทียน สวนร่มรื่นและที่จอดรถสองคัน', '77 ถนนจอมเทียนสายสอง หนองปรือ ชลบุรี 20150' ),
			array( 'บ้านครอบครัว เมืองขอนแก่น', 'บ้านเดี่ยวสี่ห้องนอนใจกลางขอนแก่น ใกล้โรงเรียนและถนนมิตรภาพ ระเบียงหลังคาและสวน', '45 ถนนมิตรภาพ เมือง ขอนแก่น 40000' ),
			array( 'ดอยสุเทพ วิว เฮาส์ สุเทพ', 'บ้านสี่ห้องนอนวิวดอยสุเทพจากห้องนั่งเล่นและสระว่ายน้ำ แยกห้องหลักออกจากห้องแขก', '18 ซอยสุเทพ 9 เมือง เชียงใหม่ 50200' ),
			array( 'ในหาน การ์เดน วิลล่า เมืองภูเก็ต', 'วิลล่าสามห้องนอนระหว่างในหานและราไวย์ รั้วส่วนตัว สวนร่มรื่น และสระว่ายน้ำ 12 เมตร', '22 ซอยไสยวน เมือง ภูเก็ต 83100' ),
		);

		$repo   = new \Estora\Core\Repositories\Property_Repository();
		$ids    = array();
		$agents = array( $users['agent'] ?? 0, $users['agent2'] ?? 0 );

		foreach ( $listings as $index => $listing ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'property',
					'post_title'   => $listing['title'],
					'post_content' => $listing['description'],
					'post_status'  => 'publish',
					'post_author'  => $users['property'] ?? 1,
					'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( estora_now_site() . ' -' . ( $index * 2 ) . ' days' ) ),
				)
			);
			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			$agent_id = $agents[ $index % 2 ] ?: ( $users['agent'] ?? 0 );
			$repo->update_meta_fields(
				(int) $post_id,
				array(
					'price'         => $listing['price'],
					'rent_period'   => 'month',
					'address'       => $listing['address'],
					'province'      => $listing['province'],
					'district'      => $listing['district'],
					'subdistrict'   => $listing['subdistrict'],
					'postal_code'   => $listing['postal'],
					'latitude'      => $listing['lat'],
					'longitude'     => $listing['lng'],
					'land_area'     => $listing['land'],
					'usable_area'   => $listing['usable'],
					'bedrooms'      => $listing['beds'],
					'bathrooms'     => $listing['baths'],
					'parking'       => $listing['parking'],
					'floors'        => $listing['floors'],
					'year_built'    => $listing['year'],
					'amenities'     => $listing['amenities'],
					'agent_id'      => $agent_id,
					'availability'  => $listing['availability'],
					'featured'      => $listing['featured'],
					'placeholder'   => $listing['plate'],
					'seo_title'     => $listing['title'] . ' — ' . ucfirst( $listing['purpose'] ) . ' ' . ( 'rent' === $listing['purpose'] ? 'rental' : 'property' ),
					'seo_description' => wp_trim_words( wp_strip_all_tags( $listing['description'] ), 28 ),
				),
				$listing['purpose']
			);

			$photo_set   = $photo_sets[ $index ] ?? array( 'chiangmai-house', array( 'interior-living', 'interior-kitchen-2' ) );
			$primary_id  = $this->photo_attachment( (string) $photo_set[0], $listing['title'] );
			$gallery_ids = array();
			if ( $primary_id ) {
				$gallery_ids[] = $primary_id;
				set_post_thumbnail( (int) $post_id, $primary_id );
			}
			foreach ( (array) ( $photo_set[1] ?? array() ) as $photo_file ) {
				$gallery_id = $this->photo_attachment( (string) $photo_file, $listing['title'] );
				if ( $gallery_id && ! in_array( $gallery_id, $gallery_ids, true ) ) {
					$gallery_ids[] = $gallery_id;
				}
			}
			if ( $gallery_ids ) {
				update_post_meta( (int) $post_id, '_estora_gallery', array_values( $gallery_ids ) );
			}

			$th = $thai_meta[ $index ] ?? array( $listing['title'], '', $listing['address'] );
			$th_content = '<p>' . $th[1] . '</p>';
			$th_content .= '<p>' . $th[0] . ' ตั้งอยู่ที่ ' . $th[2] . ' พื้นที่ใช้สอย ' . number_format_i18n( (float) $listing['usable'] ) . ' ตร.ม. ';
			if ( $listing['beds'] ) {
				$th_content .= $listing['beds'] . ' ห้องนอน ' . $listing['baths'] . ' ห้องน้ำ ';
			}
			if ( $listing['parking'] ) {
				$th_content .= 'ที่จอดรถ ' . $listing['parking'] . ' คัน ';
			}
			$th_content .= 'สนใจนัดชมหรือสอบถามรายละเอียดเพิ่มเติม ติดต่อทีมงาน ESTORA ได้ทุกวัน</p>';

			update_post_meta( (int) $post_id, '_estora_title_th', $th[0] );
			update_post_meta( (int) $post_id, '_estora_content_th', $th_content );
			update_post_meta( (int) $post_id, '_estora_address_th', $th[2] );
			update_post_meta( (int) $post_id, '_estora_seo_title_th', $th[0] . ' | ESTORA' );
			update_post_meta( (int) $post_id, '_estora_seo_description_th', wp_trim_words( wp_strip_all_tags( $th_content ), 26 ) );

			wp_set_object_terms( (int) $post_id, array( $listing['purpose'] ), 'property_purpose' );
			wp_set_object_terms( (int) $post_id, array( $listing['type'] ), 'property_type' );
			wp_set_object_terms( (int) $post_id, array( $listing['location'] ), 'property_location' );
			update_post_meta( (int) $post_id, '_estora_demo', '1' );
			update_post_meta( (int) $post_id, '_estora_reference', Formatter::reference( (int) $post_id ) );

			$ids[] = (int) $post_id;
		}

		return $ids;
	}

	/**
	 * Import a local theme photograph into the media library once.
	 *
	 * @param string $file Base filename without extension.
	 * @param string $alt  Attachment alt text.
	 */
	private function photo_attachment( string $file, string $alt ): int {
		$file = preg_replace( '/[^a-z0-9\-]/', '', strtolower( $file ) );
		if ( ! $file ) {
			return 0;
		}
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_estora_photo_file', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		if ( $existing ) {
			return (int) $existing[0];
		}

		$source = get_template_directory() . '/assets/img/photos/' . $file . '.jpg';
		if ( ! file_exists( $source ) ) {
			return 0;
		}

		$upload = wp_upload_bits( $file . '.jpg', null, file_get_contents( $source ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! empty( $upload['error'] ) ) {
			return 0;
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => $alt,
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( (int) $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( (int) $attachment_id, $metadata );
		update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', $alt );
		update_post_meta( (int) $attachment_id, '_estora_photo_file', $file );
		update_post_meta( (int) $attachment_id, '_estora_demo', '1' );

		return (int) $attachment_id;
	}

	/**
	 * Testimonials.
	 */
	private function seed_testimonials(): void {
		$items = array(
			array( 'Siriporn K.', 'Bought in Suthep', 'They knew which questions to ask the owner before we did. The paperwork took three weeks because it was ready before we started.' ),
			array( 'Daniel W.', 'Rented in Nimman', 'The video call viewing was honest about the street noise, which no other agent mentioned. That is why we took it.' ),
			array( 'Prasert L.', 'Sold in Chang Phueak', 'One person handled the file from valuation to transfer, and the listing was updated the day we accepted an offer.' ),
		);
		$thai = array(
			array( 'ศิริพร ก.', 'ซื้อในสุเทพ', 'ทีมงานรู้ว่าต้องถามเจ้าของเรื่องอะไรบ้างก่อนที่เราจะคิดออก เอกสารโอนเสร็จในสามสัปดาห์เพราะเตรียมไว้ล่วงหน้าแล้ว' ),
			array( 'แดเนียล ว.', 'เช่าในนิมมาน', 'การชมผ่านวิดีโอซื่อตรงเรื่องเสียงรถบนถนน ซึ่งไม่มีตัวแทนรายไหนบอก เราจึงตัดสินใจเช่าที่นี่' ),
			array( 'ประเสริฐ ล.', 'ขายในช้างเผือก', 'มีคนเดียวดูแลแฟ้มตั้งแต่ประเมินราคาจนถึงโอน และประกาศถูกอัปเดตในวันที่เราตกลงรับข้อเสนอ' ),
		);

		foreach ( $items as $index => $item ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => 'testimonial',
					'post_title'   => $item[0],
					'post_content' => $item[2],
					'post_status'  => 'publish',
				)
			);
			if ( ! is_wp_error( $post_id ) ) {
				update_post_meta( (int) $post_id, '_estora_testimonial_role', $item[1] );
				update_post_meta( (int) $post_id, '_estora_title_th', $thai[ $index ][0] ?? $item[0] );
				update_post_meta( (int) $post_id, '_estora_content_th', $thai[ $index ][2] ?? $item[2] );
				update_post_meta( (int) $post_id, '_estora_testimonial_role_th', $thai[ $index ][1] ?? $item[1] );
			}
		}
	}

	/**
	 * Seeded journal entries so the homepage and news page have real posts.
	 */
	private function seed_articles(): void {
		$category_id = 0;
		$category    = get_category_by_slug( 'market-notes' );
		if ( $category ) {
			$category_id = (int) $category->term_id;
		} else {
			$created = wp_insert_term( 'Market notes', 'category', array( 'slug' => 'market-notes' ) );
			$category_id = is_wp_error( $created ) ? 0 : (int) $created['term_id'];
		}

		$articles = array(
			array(
				'title'   => 'What a chanote actually tells you',
				'th_title' => 'โฉนดที่ดินบอกอะไรคุณได้บ้าง',
				'date'    => '-3 days',
				'content' => '<p>Title type is the first thing our property team checks and the last thing a buyer should have to discover at the transfer desk. A chanote (NS.4) is a freehold title with surveyed boundaries; a Nor Sor 3 Gor is a certificate of use that still requires a boundary survey before transfer.</p><p>Neither is automatically a problem. They are different processes with different timelines, and the price should reflect that. We record the title type in the property file before publication so nobody is surprised later.</p><h2>What to ask for</h2><p>Ask for the title number, the owner name on the title, and whether any lease or mortgage is registered against it. A legitimate owner will have those documents ready.</p>',
				'th_content' => '<p>ประเภทเอกสารสิทธิ์คือสิ่งแรกที่ทีมทรัพย์สินของเราตรวจ และเป็นสิ่งสุดท้ายที่ผู้ซื้อควรค้นพบที่เคาน์เตอร์โอน โฉนดที่ดิน (น.ส.4) คือกรรมสิทธิ์ที่ดินมีขอบเขตชัดเจน ส่วน น.ส.3 ก. เป็นหนังสือรับรองการใช้ประโยชน์ที่ยังต้องรังวัดก่อนโอน</p><p>ทั้งสองแบบไม่ได้เป็นปัญหาโดยอัตโนมัติ แต่เป็นกระบวนการและระยะเวลาที่ต่างกัน ราคาควรสะท้อนความต่างนั้น เราบันทึกประเภทเอกสารไว้ในแฟ้มทรัพย์ก่อนเผยแพร่ เพื่อไม่ให้ใครเซอร์ไพรส์ภายหลัง</p><h2>ควรขออะไร</h2><p>ขอเลขที่โฉนด ชื่อเจ้าของในโฉนด และตรวจว่ามีสัญญาเช่าหรือจำนองจดทะเบียนอยู่หรือไม่ เจ้าของที่ถูกต้องจะเตรียมเอกสารเหล่านี้ได้</p>',
				'excerpt' => 'Title type changes the transfer process and the price. Here is what to check before you make an offer.',
				'th_excerpt' => 'ประเภทเอกสารสิทธิ์มีผลต่อขั้นตอนโอนและราคา นี่คือสิ่งที่ควรตรวจก่อนยื่นข้อเสนอ',
			),
			array(
				'title'   => 'Viewing a rental in the rainy season',
				'th_title' => 'ชมบ้านเช่าในช่วงหน้าฝน',
				'date'    => '-9 days',
				'content' => '<p>Water is honest. A house that smells damp in August will be a problem in November, and new paint is easier to spot than new drainage. Look at the base of exterior walls, the ceiling under bathrooms, and the slope of the driveway.</p><p>Ask to see the water bill for the previous three months. A spike can indicate a leak or a pump that runs constantly. Ask where the water tank is and when it was last cleaned.</p><h2>The fifteen-minute check</h2><p>Open every tap, run the shower, and flush every toilet at the same time. Then look at the floor drain. If it backs up, the problem is not cosmetic.</p>',
				'th_content' => '<p>น้ำเป็นสิ่งที่ซื่อสัตย์ที่สุด บ้านที่อับชื้นในเดือนสิงหาคมจะเป็นปัญหาในเดือนพฤศจิกายน และสีทาใหม่สังเกตง่ายกว่าระบบระบายน้ำใหม่ มองที่โคนผนังภายนอก เพดานใต้ห้องน้ำ และความลาดของทางรถ</p><p>ขอดูบิลค่าน้ำสามเดือนล่าสุด หากสูงผิดปกติอาจมีจุดรั่วหรือปั๊มทำงานตลอดเวลา ถามหาตำแหน่งถังน้ำและครั้งสุดท้ายที่ล้างถัง</p><h2>เช็ก 15 นาที</h2><p>เปิดก๊อกทุกจุด เปิดฝักบัว และกดชักโครกพร้อมกัน แล้วดูท่อระบายพื้น หากน้ำย้อน แสดงว่าไม่ใช่เรื่อง cosmetic</p>',
				'excerpt' => 'Damp, drainage and water pressure are easier to assess in the wet season. A short checklist for rental viewings.',
				'th_excerpt' => 'ความชื้น ระบบระบายน้ำ และแรงดันน้ำตรวจได้ง่ายในหน้าฝน เช็กลิสต์สั้น ๆ สำหรับการชมบ้านเช่า',
			),
			array(
				'title'   => 'Why we keep sold listings online',
				'th_title' => 'ทำไมเรายังเก็บประกาศที่ขายแล้วไว้',
				'date'    => '-16 days',
				'content' => '<p>Removing a sold listing makes the market look busier than it is. Keeping it, labelled, gives buyers and owners a reference point: what asked, what sold, and how long it took. We mark availability honestly and stop accepting inquiries when a property is sold or rented.</p><p>The alternative — quietly deleting the evidence — makes every new listing look like a bargain and every agent look clairvoyant.</p><h2>What the label means</h2><p>Sold means the transfer is complete or the deposit is signed and the deal is exclusive. Reserved means an offer is accepted but not yet signed. If you are interested, ask the office; reservations do fall through.</p>',
				'th_content' => '<p>การลบประกาศที่ขายแล้วทำให้ตลาดดูคึกคักเกินจริง การเก็บไว้พร้อมป้ายสถานะทำให้ผู้ซื้อและเจ้าของมีจุดอ้างอิง ว่าราคาเสนอเท่าไร ขายได้เท่าไร และใช้เวลานานแค่ไหน เราระบุสถานะตามจริงและหยุดรับคำถามเมื่อขายหรือให้เช่าแล้ว</p><p>ทางเลือกอื่นคือการลบหลักฐานอย่างเงียบ ๆ ซึ่งทำให้ทุกประกาศใหม่ดูเป็นของถูกและทำให้ตัวแทนดูรู้ล่วงหน้าไปหมด</p><h2>ป้ายสถานะหมายถึงอะไร</h2><p>ขายแล้วหมายถึงโอนเสร็จหรือเซ็นรับมัดจำและดีล exclusive แล้ว จองแล้วหมายถึงรับข้อเสนอแต่ยังไม่เซ็น หากสนใจให้สอบถามสำนักงาน เพราะการจองสามารถหลุดได้</p>',
				'excerpt' => 'Sold and rented homes stay visible and labelled. It makes the market more legible, not less.',
				'th_excerpt' => 'บ้านที่ขายหรือให้เช่าแล้วยังแสดงอยู่พร้อมป้ายสถานะ ทำให้ตลาดอ่านง่ายขึ้น ไม่ใช่ยากขึ้น',
			),
		);

		foreach ( $articles as $article ) {
			$existing = get_posts(
				array(
					'post_type'      => 'post',
					'post_status'    => 'any',
					'title'          => $article['title'],
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			if ( ! empty( $existing ) ) {
				continue;
			}
			$post_id = wp_insert_post(
				array(
					'post_type'     => 'post',
					'post_title'    => $article['title'],
					'post_content'  => $article['content'],
					'post_excerpt'  => $article['excerpt'],
					'post_status'   => 'publish',
					'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( estora_now_site() . ' ' . $article['date'] ) ),
					'post_category' => $category_id ? array( $category_id ) : array(),
				)
			);
			if ( ! is_wp_error( $post_id ) ) {
				update_post_meta( (int) $post_id, '_estora_demo', '1' );
				update_post_meta( (int) $post_id, '_estora_title_th', $article['th_title'] );
				update_post_meta( (int) $post_id, '_estora_content_th', $article['th_content'] );
				update_post_meta( (int) $post_id, '_estora_excerpt_th', $article['th_excerpt'] );
			}
		}
	}

	/**
	 * Campaigns.
	 */
	private function seed_campaigns(): void {
		$repo = new Campaign_Repository();
		$repo->create(
			array(
				'name'            => 'Nimman autumn',
				'slug'            => 'nimman-autumn',
				'channel'         => 'meta',
				'utm_source'      => 'facebook',
				'utm_medium'      => 'cpc',
				'utm_campaign'    => 'nimman-autumn',
				'status'          => 'active',
				'budget'          => 45000,
				'starts_at'       => current_time( 'Y-m-d', true ),
				'notes'           => 'Meta lead campaign for the Nimman loft and courtyard house.',
			)
		);
		$repo->create(
			array(
				'name'            => 'Phuket villa search',
				'slug'            => 'phuket-villa-search',
				'channel'         => 'google',
				'utm_source'      => 'google',
				'utm_medium'      => 'search',
				'utm_campaign'    => 'phuket-villa-search',
				'status'          => 'draft',
				'budget'          => 60000,
				'notes'           => 'Paused until the villa photography is reshot.',
			)
		);
	}

	/**
	 * Leads with notes.
	 *
	 * @param array<string,int> $users      Staff.
	 * @param int[]             $properties Seeded properties.
	 */
	private function seed_leads( array $users, array $properties ): void {
		if ( empty( $properties ) ) {
			return;
		}

		$repo = new Lead_Repository();
		$rows = array(
			array( 'Priya Raman', 'priya.raman@example.com', '+66 81 234 5601', 'website', 'qualified', 'high', 7, 1, 12000000, 'Nimman', 'facebook', 'nimman-autumn' ),
			array( 'Tom Whitfield', 'tom.w@example.com', '+66 89 112 3344', 'website', 'new', 'normal', 1, 0, 9000000, 'Sukhumvit', '', '' ),
			array( 'Kanya Srisuk', 'kanya.s@example.com', '+66 86 778 9900', 'appointment', 'contacted', 'normal', 5, 2, 35000, 'Chang Phueak', '', '' ),
			array( 'Marco Bianchi', 'marco.b@example.com', '+66 92 445 6677', 'website', 'negotiation', 'urgent', 18, 0, 29000000, 'Kamala', 'google', 'phuket-villa-search' ),
			array( 'Chen Wei', 'chen.wei@example.com', '+66 83 220 1188', 'manual', 'new', 'low', 0, 1, 7000000, 'Watthana', '', '' ),
			array( 'Nattaya P.', 'nattaya.p@example.com', '+66 84 556 7788', 'website', 'won', 'normal', 32, 2, 9800000, 'Chang Phueak', '', '' ),
			array( 'Oliver Grant', 'oliver.g@example.com', '+66 61 334 5522', 'website', 'lost', 'normal', 40, 0, 8000000, 'Bangkok', '', '' ),
			array( 'Areeya M.', 'areeya.m@example.com', '+66 87 990 1122', 'contact', 'contacted', 'high', 3, 1, 15000000, 'Suthep', 'facebook', 'nimman-autumn' ),
			array( 'Somchai T.', 'somchai.t@example.com', '+66 82 121 3344', 'website', 'lost', 'low', 25, 2, 6000000, 'Hai Ya', '', '' ),
			array( 'Hannah Blake', 'hannah.b@example.com', '+66 95 667 8899', 'appointment', 'qualified', 'normal', 9, 0, 90000000, 'Phuket', '', '' ),
		);

		foreach ( $rows as $index => $row ) {
			$agent_id = ( $index % 2 === 0 ) ? ( $users['agent'] ?? 0 ) : ( $users['agent2'] ?? 0 );
			$lead_id  = $repo->create(
				array(
					'full_name'         => $row[0],
					'email'             => $row[1],
					'phone'             => $row[2],
					'source'            => $row[3],
					'status'            => $row[4],
					'priority'          => $row[5],
					'property_id'       => $properties[ $index % count( $properties ) ],
					'budget_max'        => $row[8],
					'preferred_location' => $row[9],
					'utm_source'        => $row[10],
					'utm_campaign'      => $row[11],
					'message'           => 'Interested in this listing; please send floor plans and the availability window.',
					'assigned_agent_id' => $agent_id,
					'dedupe_key'        => hash( 'sha256', Formatter::normalize_phone( $row[2] ) . '|' . $properties[ $index % count( $properties ) ] ),
					'last_contact_at'   => 'new' === $row[4] ? null : estora_now( '-' . max( 1, (int) $row[6] - 1 ) . ' days' ),
					'next_follow_up_at' => in_array( $row[4], array( 'new', 'contacted', 'qualified', 'negotiation' ), true )
						? estora_now( ( $index % 3 === 0 ? '-1 day' : '+' . ( $index + 2 ) . ' days' ) )
						: null,
					'won_value'         => 'won' === $row[4] ? (float) $row[8] : null,
				)
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			global $wpdb;
			$wpdb->update( estora_table( 'leads' ), array( 'created_at' => estora_now( '-' . (int) $row[6] . ' days' ) ), array( 'id' => $lead_id ), array( '%s' ), array( '%d' ) );

			$repo->add_assignment( $lead_id, 0, $agent_id, $users['manager'] ?? 0, 'Seeded assignment' );
			$repo->add_note( $lead_id, $agent_id, 'Initial call completed. Requirements confirmed and viewing window agreed.', 'call', true );
			if ( in_array( $row[4], array( 'qualified', 'negotiation', 'won' ), true ) ) {
				$repo->add_note( $lead_id, $agent_id, 'Financing pre-approval received; owner notified.', 'note', true );
			}
			if ( 'won' === $row[4] ) {
				$repo->add_note( $lead_id, $users['manager'] ?? 0, 'Reservation signed. Deposit received by transfer.', 'note', true );
			}
		}
	}

	/**
	 * Seed a readable audit trail so the dashboard has recent activity.
	 *
	 * @param array<string,int> $users      Staff.
	 * @param int[]             $properties Seeded properties.
	 */
	private function seed_activity( array $users, array $properties ): void {
		global $wpdb;
		$activity = new \Estora\Core\Repositories\Activity_Repository();
		$manager  = (int) ( $users['manager'] ?? 0 );
		$agent    = (int) ( $users['agent'] ?? 0 );

		wp_set_current_user( $manager );
		foreach ( array_slice( $properties, 0, 3 ) as $property_id ) {
			$activity->log( 'property.published', 'property', (int) $property_id, 'Published ' . get_the_title( (int) $property_id ) . ' to the website.' );
		}

		$lead_ids = $wpdb->get_col( 'SELECT id FROM `' . esc_sql( estora_table( 'leads' ) ) . '` ORDER BY id DESC LIMIT 4' );
		foreach ( (array) $lead_ids as $lead_id ) {
			wp_set_current_user( $agent );
			$activity->log( 'lead.assigned', 'lead', (int) $lead_id, 'Lead allocated to an agent.' );
		}

		$appointments = $wpdb->get_col( 'SELECT id FROM `' . esc_sql( estora_table( 'appointments' ) ) . '` ORDER BY id ASC LIMIT 3' );
		wp_set_current_user( $manager );
		foreach ( (array) $appointments as $appointment_id ) {
			$activity->log( 'appointment.created', 'appointment', (int) $appointment_id, 'Viewing request received and added to the calendar.' );
		}

		$activity->log( 'settings.updated', 'settings', 0, 'Viewing hours and notification defaults reviewed.' );
		wp_set_current_user( 0 );
	}

	/**
	 * Upcoming and past viewings.
	 *
	 * @param array<string,int> $users      Staff.
	 * @param int[]             $properties Seeded properties.
	 */
	private function seed_appointments( array $users, array $properties ): void {
		if ( empty( $properties ) ) {
			return;
		}

		$repo    = new Appointment_Repository();
		$agent_a = (int) ( $users['agent'] ?? 0 );
		$agent_b = (int) ( $users['agent2'] ?? 0 );
		$today   = current_time( 'Y-m-d' );

		$dates  = array();
		$cursor = $today;
		for ( $found = 0; $found < 4; ) {
			$cursor = gmdate( 'Y-m-d', strtotime( $cursor . ' +1 day' ) );
			if ( Settings::is_open_on( $cursor ) ) {
				$dates[] = $cursor;
				++$found;
			}
		}

		$items = array(
			array( $properties[0], $agent_a, 'Priya Raman', 'priya.raman@example.com', '+66 81 234 5601', $dates[0], '10:00', Appointment_Status::CONFIRMED ),
			array( $properties[1], $agent_b, 'Tom Whitfield', 'tom.w@example.com', '+66 89 112 3344', $dates[0], '14:00', Appointment_Status::PENDING ),
			array( $properties[2], $agent_a, 'Kanya Srisuk', 'kanya.s@example.com', '+66 86 778 9900', $dates[1], '11:30', Appointment_Status::CONFIRMED ),
			array( $properties[3], $agent_b, 'Marco Bianchi', 'marco.b@example.com', '+66 92 445 6677', $dates[2], '15:30', Appointment_Status::PENDING ),
			array( $properties[8], $agent_a, 'Areeya M.', 'areeya.m@example.com', '+66 87 990 1122', $dates[3], '09:00', Appointment_Status::CONFIRMED ),
		);

		foreach ( $items as $item ) {
			$end = Availability::end_time( $item[6], (int) estora_setting( 'slot_minutes', 45 ) );
			try {
				$id = $repo->create(
					array(
						'property_id'    => $item[0],
						'agent_id'       => $item[1],
						'customer_name'  => $item[2],
						'customer_email' => $item[3],
						'customer_phone' => $item[4],
						'requested_date' => $item[5],
						'start_time'     => $item[6],
						'end_time'       => $end,
						'status'         => $item[7],
						'source'         => 'website',
						'notes'          => 'Please confirm by phone before the viewing.',
					)
				);
				$repo->update( $id, array( 'internal_notes' => 'Seeded demo viewing.' ) );
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		// One completed viewing in the past.
		$past = gmdate( 'Y-m-d', strtotime( $today . ' -4 days' ) );
		try {
			$repo->create(
				array(
					'property_id'    => $properties[5],
					'agent_id'       => $agent_b,
					'customer_name'  => 'Daniel W.',
					'customer_email' => 'daniel.w@example.com',
					'customer_phone' => '+66 90 112 4455',
					'requested_date' => $past,
					'start_time'     => '13:00',
					'end_time'       => '13:45',
					'status'         => Appointment_Status::COMPLETED,
					'source'         => 'website',
				)
			);
		} catch ( \Throwable $e ) {
			unset( $e );
		}
	}
}
