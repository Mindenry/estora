<?php
/**
 * Front page.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

$estora_featured = estora_public_properties( array( 'featured' => '1', 'per_page' => 3, 'sort' => 'newest' ) );
$estora_latest   = estora_public_properties( array( 'per_page' => 6, 'sort' => 'newest', 'exclude' => wp_list_pluck( $estora_featured['items'], 'id' ) ) );
$estora_options  = array( 'types' => array(), 'locations' => array() );
$estora_stats    = array( 'published' => 0, 'available' => 0 );

if ( class_exists( '\Estora\Core\Repositories\Property_Repository' ) ) {
	$estora_repo    = new \Estora\Core\Repositories\Property_Repository();
	$estora_options = $estora_repo->filter_options();
	$estora_stats   = $estora_repo->stats();
}

$estora_posts  = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 3, 'ignore_sticky_posts' => true ) );
$estora_quotes = new WP_Query( array( 'post_type' => 'testimonial', 'posts_per_page' => 3, 'no_found_rows' => true ) );

$estora_destinations = array();
$estora_districts    = 0;
foreach ( (array) $estora_options['locations'] as $estora_location ) {
	if ( 0 === (int) $estora_location['parent'] ) {
		$estora_term  = get_term_by( 'slug', (string) $estora_location['slug'], 'property_location' );
		$estora_count = $estora_term instanceof WP_Term ? (int) $estora_term->count : 0;
		if ( $estora_term instanceof WP_Term ) {
			foreach ( get_term_children( $estora_term->term_id, 'property_location' ) as $estora_child_id ) {
				$estora_child = get_term( $estora_child_id, 'property_location' );
				if ( $estora_child instanceof WP_Term ) {
					$estora_count += (int) $estora_child->count;
				}
			}
		}
		$estora_location['count'] = $estora_count;
		$estora_destinations[]    = $estora_location;
	} else {
		++$estora_districts;
	}
}

usort(
	$estora_destinations,
	static function ( $a, $b ) {
		return (int) $b['count'] <=> (int) $a['count'];
	}
);

$estora_destination_images = array(
	'bangkok'    => 'bangkok-condo',
	'chiang-mai' => 'chiangmai-house',
	'phuket'     => 'phuket-villa',
	'hua-hin'    => 'hua-hin-house',
	'pattaya'    => 'pattaya-condo',
	'khon-kaen'  => 'house-4',
);
$estora_hero_place = $estora_destinations[0] ?? null;

get_header();
?>

<section class="estora-hero" aria-labelledby="estora-hero-title">
	<div class="estora-hero__media" aria-hidden="true">
		<img src="<?php echo esc_url( estora_theme_asset( 'img/photos/hero-villa.jpg' ) ); ?>" alt="" width="2000" height="1250" fetchpriority="high">
	</div>
	<div class="estora-container estora-hero__inner">
		<div class="estora-hero__copy">
			<p class="estora-hero__eyebrow"><?php echo esc_html( implode( ' · ', array_map( 'estora_place_name', wp_list_pluck( array_slice( $estora_destinations, 0, 6 ), 'name' ) ) ) ); ?></p>
			<h1 id="estora-hero-title"><?php echo esc_html( estora_text( 'A home you can trust, from first viewing to handover.', 'บ้านที่คุณไว้ใจได้ ตั้งแต่ชมหลังแรกจนถึงวันโอน' ) ); ?></h1>
			<p class="estora-hero__lede"><?php echo esc_html( estora_text( 'Houses, townhouses and low-rise apartments across Thailand — inspected, documented and represented directly by the team you meet.', 'บ้านเดี่ยว ทาวน์เฮาส์ และคอนโดโลว์ไรส์ทั่วประเทศไทย ตรวจสอบและจัดทำข้อมูลโดยทีมงานที่คุณพบจริง' ) ); ?></p>
			<div class="estora-hero__actions">
				<a class="estora-btn estora-btn--light" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php echo esc_html( estora_text( 'Browse all homes', 'ดูทรัพย์ทั้งหมด' ) ); ?> <?php echo estora_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<a class="estora-hero__link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html( estora_text( 'Talk to an advisor', 'คุยกับที่ปรึกษา' ) ); ?> <?php echo estora_icon( 'arrow', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			</div>
			<dl class="estora-hero__proof">
				<div><dt><?php echo esc_html( estora_text( 'Available', 'พร้อมอยู่' ) ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) ( $estora_stats['available'] ?? 0 ) ) ); ?></dd></div>
				<div><dt><?php echo esc_html( estora_text( 'Live listings', 'ประกาศทั้งหมด' ) ); ?></dt><dd><?php echo esc_html( number_format_i18n( (int) ( $estora_stats['published'] ?? 0 ) ) ); ?></dd></div>
				<div><dt><?php echo esc_html( estora_text( 'Destinations', 'ทำเล' ) ); ?></dt><dd><?php echo esc_html( number_format_i18n( count( $estora_destinations ) ) ); ?></dd></div>
				<div><dt><?php echo esc_html( estora_text( 'Neighbourhoods', 'ย่าน' ) ); ?></dt><dd><?php echo esc_html( number_format_i18n( $estora_districts ) ); ?></dd></div>
			</dl>
		</div>
	</div>

	<div class="estora-container estora-hero__searchwrap">
		<div class="estora-searchcard">
			<div class="estora-searchcard__head">
				<div>
					<p class="estora-searchcard__label"><?php echo esc_html( estora_text( 'Find a property', 'ค้นหาทรัพย์' ) ); ?></p>
					<p class="estora-searchcard__hint"><?php echo esc_html( estora_text( 'Search by purpose, type or destination.', 'ค้นตามวัตถุประสงค์ ประเภท หรือทำเล' ) ); ?></p>
				</div>
				<strong><?php echo esc_html( number_format_i18n( (int) ( $estora_stats['available'] ?? 0 ) ) ); ?> <?php echo esc_html( estora_text( 'available now', 'พร้อมอยู่ตอนนี้' ) ); ?></strong>
			</div>
			<?php estora_search_form( array( 'variant' => 'hero' ) ); ?>
			<?php if ( $estora_destinations ) : ?>
				<div class="estora-searchcard__foot">
					<span><?php echo esc_html( estora_text( 'Popular', 'ยอดนิยม' ) ); ?></span>
					<?php foreach ( array_slice( $estora_destinations, 0, 5 ) as $estora_quick_place ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'location', $estora_quick_place['slug'], estora_properties_url() ) ); ?>"><?php echo esc_html( estora_place_name( (string) $estora_quick_place['name'] ) ); ?><span aria-hidden="true">↗</span></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="estora-section estora-section--featured" id="collection">
	<div class="estora-container">
		<?php estora_section_head( estora_text( 'The collection', 'คอลเล็กชัน' ), estora_text( 'Homes we would show first', 'บ้านที่เราเลือกมาแนะนำก่อน' ), estora_properties_url(), estora_text( 'View all properties', 'ดูทรัพย์ทั้งหมด' ), '01' ); ?>
		<?php if ( ! empty( $estora_featured['items'] ) ) : ?>
			<div class="estora-grid estora-grid--3">
				<?php foreach ( $estora_featured['items'] as $estora_property ) : ?>
					<?php estora_property_card( $estora_property ); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="estora-empty">
				<h2><?php echo esc_html( estora_text( 'Listings are being prepared', 'กำลังเตรียมรายการทรัพย์' ) ); ?></h2>
				<p><?php echo esc_html( estora_text( 'New properties appear here as soon as the property team publishes them.', 'ทรัพย์ใหม่จะแสดงที่นี่ทันทีที่ทีมทรัพย์สินเผยแพร่' ) ); ?></p>
				<a class="estora-btn" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php echo esc_html( estora_text( 'Browse all properties', 'ดูทรัพย์ทั้งหมด' ) ); ?></a>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php if ( $estora_destinations ) : ?>
<section class="estora-section estora-section--ink estora-destinations">
	<div class="estora-container">
		<?php estora_section_head( estora_text( 'Where we work', 'ทำเลที่เราดูแล' ), estora_text( 'Six destinations, one standard.', 'หกทำเล มาตรฐานเดียว' ), '', '', '02' ); ?>
		<div class="estora-destinations__grid">
			<?php foreach ( $estora_destinations as $estora_index => $estora_place ) : ?>
				<?php
				$estora_photo = $estora_destination_images[ $estora_place['slug'] ] ?? 'chiangmai-house';
				$estora_link  = add_query_arg( 'location', $estora_place['slug'], estora_properties_url() );
				?>
				<a class="estora-destination" href="<?php echo esc_url( $estora_link ); ?>">
					<img src="<?php echo esc_url( estora_theme_asset( 'img/photos/' . $estora_photo . '.jpg' ) ); ?>" alt="" width="800" height="600" loading="lazy">
					<span class="estora-destination__index" aria-hidden="true">0<?php echo esc_html( (string) ( $estora_index + 1 ) ); ?></span>
					<span class="estora-destination__body">
						<span class="estora-destination__count">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: number of homes. */
									_n( '%s home', '%s homes', (int) $estora_place['count'], 'estora-theme' ),
									number_format_i18n( (int) $estora_place['count'] )
								)
							);
							?>
						</span>
						<strong><?php echo esc_html( estora_place_name( (string) $estora_place['name'] ) ); ?></strong>
						<span class="estora-destination__link"><?php echo esc_html( estora_text( 'View homes', 'ดูทรัพย์ในทำเลนี้' ) ); ?> <?php echo estora_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="estora-pulse">
	<div class="estora-container estora-pulse__grid">
		<div class="estora-pulse__intro">
			<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( estora_text( 'Market pulse', 'ภาพรวมตลาด' ) ); ?></p>
			<p><?php echo esc_html( estora_text( 'A live snapshot from the same records that power the listings.', 'ข้อมูลสดจากชุดเดียวกับที่ใช้ทำประกาศขาย' ) ); ?></p>
		</div>
		<div class="estora-pulse__metric"><strong><?php echo esc_html( number_format_i18n( (int) ( $estora_stats['available'] ?? 0 ) ) ); ?></strong><span><?php echo esc_html( estora_text( 'Available now', 'พร้อมอยู่' ) ); ?></span></div>
		<div class="estora-pulse__metric"><strong><?php echo esc_html( number_format_i18n( (int) ( $estora_stats['by_purpose']['sale'] ?? 0 ) ) ); ?></strong><span><?php echo esc_html( estora_text( 'For sale', 'ขาย' ) ); ?></span></div>
		<div class="estora-pulse__metric"><strong><?php echo esc_html( number_format_i18n( (int) ( $estora_stats['by_purpose']['rent'] ?? 0 ) ) ); ?></strong><span><?php echo esc_html( estora_text( 'For rent', 'ให้เช่า' ) ); ?></span></div>
		<div class="estora-pulse__metric"><strong><?php echo esc_html( estora_price( round( (float) ( $estora_stats['average_price'] ?? 0 ), -4 ) ) ); ?></strong><span><?php echo esc_html( estora_text( 'Average asking price', 'ราคาเฉลี่ย' ) ); ?></span></div>
	</div>
</section>

<section class="estora-section estora-standard">
	<div class="estora-container estora-standard__grid">
		<div class="estora-standard__media">
			<img src="<?php echo esc_url( estora_theme_asset( 'img/photos/bangkok-condo.jpg' ) ); ?>" alt="" width="1600" height="1200" loading="lazy">
			<span class="estora-standard__tag"><?php echo esc_html( estora_text( 'One file. One owner.', 'แฟ้มเดียว เจ้าของคนเดียว' ) ); ?></span>
		</div>
		<div class="estora-standard__copy">
			<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( estora_text( 'The ESTORA standard', 'มาตรฐาน ESTORA' ) ); ?></p>
			<h2><?php echo esc_html( estora_text( 'Built for the part after the brochure.', 'สร้างมาเพื่องานหลังปิดประกาศขาย' ) ); ?></h2>
			<p class="estora-standard__lede"><?php echo esc_html( estora_text( 'A listing is only the beginning. One team keeps the price history, viewings, documents and handover in the same file.', 'ประกาศขายเป็นเพียงจุดเริ่มต้น ทีมเดียวดูแลประวัติราคา นัดชม เอกสาร และการส่งมอบไว้ในแฟ้มเดียวกัน' ) ); ?></p>
			<ol class="estora-standard__list">
				<li><span>01</span><div><strong><?php echo esc_html( estora_text( 'Inspected before published', 'ตรวจก่อนเผยแพร่' ) ); ?></strong><p><?php echo esc_html( estora_text( 'Address, title type and specification are checked by the property team.', 'ทีมทรัพย์สินตรวจที่อยู่ ประเภทเอกสารสิทธิ์ และข้อมูลจำเพาะ' ) ); ?></p></div></li>
				<li><span>02</span><div><strong><?php echo esc_html( estora_text( 'One agent owns your file', 'ตัวแทนหนึ่งคนดูแลแฟ้มของคุณ' ) ); ?></strong><p><?php echo esc_html( estora_text( 'The person who shows you the home is the person who answers the next question.', 'คนที่พาคุณชมบ้านคือคนที่ตอบคำถามครั้งถัดไป' ) ); ?></p></div></li>
				<li><span>03</span><div><strong><?php echo esc_html( estora_text( 'Viewings that hold', 'นัดชมที่ไม่หลุด' ) ); ?></strong><p><?php echo esc_html( estora_text( 'Slots are reserved against the agent and the property on our side.', 'เวลานัดถูกจองทั้งฝั่งตัวแทนและทรัพย์ในระบบของเรา' ) ); ?></p></div></li>
			</ol>
			<a class="estora-textlink" href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php echo esc_html( estora_text( 'How we work', 'วิธีทำงานของเรา' ) ); ?> <?php echo estora_icon( 'arrow', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		</div>
	</div>
</section>

<?php if ( ! empty( $estora_latest['items'] ) ) : ?>
<section class="estora-section estora-section--latest">
	<div class="estora-container">
		<?php estora_section_head( estora_text( 'New to the market', 'เข้าใหม่' ), estora_text( 'Latest listings', 'ประกาศล่าสุด' ), estora_properties_url(), estora_text( 'See everything', 'ดูทั้งหมด' ), '03' ); ?>
		<div class="estora-grid estora-grid--3">
			<?php foreach ( $estora_latest['items'] as $estora_property ) : ?>
				<?php estora_property_card( $estora_property ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $estora_quotes->have_posts() ) : ?>
<section class="estora-section estora-section--sand estora-quotes-section">
	<div class="estora-container">
		<?php estora_section_head( estora_text( 'Clients', 'ลูกค้า' ), estora_text( 'What the file looked like from the other side', 'มุมมองจากอีกฝั่งของแฟ้ม' ), '', '', '04' ); ?>
		<div class="estora-quotes estora-quotes--editorial">
			<?php while ( $estora_quotes->have_posts() ) : ?>
				<?php
				$estora_quotes->the_post();
				$estora_quote_th = (string) get_post_meta( get_the_ID(), '_estora_content_th', true );
				$estora_role     = (string) get_post_meta( get_the_ID(), '_estora_testimonial_role', true );
				$estora_role_th  = (string) get_post_meta( get_the_ID(), '_estora_testimonial_role_th', true );
				?>
				<figure class="estora-quote">
					<blockquote><?php echo esc_html( estora_is_thai() && $estora_quote_th ? $estora_quote_th : wp_strip_all_tags( get_the_content() ) ); ?></blockquote>
					<figcaption>
						<strong><?php the_title(); ?></strong>
						<span><?php echo esc_html( estora_is_thai() && $estora_role_th ? $estora_role_th : $estora_role ); ?></span>
					</figcaption>
				</figure>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $estora_posts->have_posts() ) : ?>
<section class="estora-section estora-journal">
	<div class="estora-container">
		<?php estora_section_head( estora_text( 'Notes from the office', 'บันทึกจากสำนักงาน' ), estora_text( 'Market notes and practical guides', 'บทความตลาดและคู่มือใช้งานจริง' ), home_url( '/news/' ), estora_text( 'All articles', 'บทความทั้งหมด' ), '05' ); ?>
		<div class="estora-grid estora-grid--3">
			<?php while ( $estora_posts->have_posts() ) : ?>
				<?php $estora_posts->the_post(); ?>
				<article class="estora-article-card">
					<p class="estora-article-card__meta"><?php echo esc_html( get_the_date() ); ?> · <?php echo esc_html( get_the_category_list( ', ' ) ? wp_strip_all_tags( get_the_category_list( ', ' ) ) : __( 'Journal', 'estora-theme' ) ); ?></p>
					<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<p><?php echo esc_html( get_the_excerpt() ); ?></p>
					<a class="estora-textlink" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read', 'estora-theme' ); ?> <?php echo estora_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				</article>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="estora-cta estora-cta--photo">
	<div class="estora-cta__media" aria-hidden="true">
		<img src="<?php echo esc_url( estora_theme_asset( 'img/photos/pool-detail.jpg' ) ); ?>" alt="" width="1600" height="1200" loading="lazy">
	</div>
	<div class="estora-container estora-cta__inner">
		<div class="estora-cta__copy">
			<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( estora_text( 'Next step', 'ก้าวต่อไป' ) ); ?></p>
			<h2><?php echo esc_html( estora_text( 'Tell us what you are looking for.', 'บอกเราว่าคุณกำลังมองหาอะไร' ) ); ?></h2>
			<p><?php echo esc_html( estora_text( 'A short brief is enough. We reply with the listings that fit and the ones that do not.', 'บรีฟสั้น ๆ ก็พอ เราจะตอบกลับพร้อมทรัพย์ที่ตรงและไม่ตรงให้คุณตัดสินใจ' ) ); ?></p>
		</div>
		<a class="estora-cta__action" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">
			<span><?php echo esc_html( estora_text( 'Contact the office', 'ติดต่อสำนักงาน' ) ); ?></span>
			<span class="estora-cta__arrow" aria-hidden="true"><?php echo estora_icon( 'arrow', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</a>
	</div>
</section>

<?php get_footer(); ?>
