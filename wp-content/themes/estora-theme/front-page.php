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

$estora_agents = get_users( array( 'capability' => 'estora_edit_leads', 'number' => 20, 'fields' => 'ID' ) );
$estora_posts  = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 3, 'ignore_sticky_posts' => true ) );
$estora_quotes = new WP_Query( array( 'post_type' => 'testimonial', 'posts_per_page' => 3, 'no_found_rows' => true ) );

$estora_provinces = array();
foreach ( (array) $estora_options['locations'] as $estora_term ) {
	if ( 0 === (int) $estora_term['parent'] ) {
		$estora_provinces[] = $estora_term;
	}
}
get_header();
?>

<section class="estora-hero">
	<div class="estora-hero__bg">
		<img src="<?php echo esc_url( estora_theme_asset( 'img/hero.png' ) ); ?>" alt="" width="2400" height="1400" fetchpriority="high">
	</div>
	<div class="estora-container estora-hero__inner">
		<div class="estora-hero__copy">
			<p class="estora-eyebrow"><span aria-hidden="true"></span><?php esc_html_e( 'Chiang Mai · Bangkok · Phuket', 'estora-theme' ); ?></p>
			<h1><?php esc_html_e( 'A property file you can trust, from first viewing to handover.', 'estora-theme' ); ?></h1>
			<p class="estora-hero__lede"><?php esc_html_e( 'Houses, townhouses and low-rise apartments, represented directly and kept current by the people who inspect them.', 'estora-theme' ); ?></p>
			<div class="estora-hero__stats">
				<div><strong><?php echo esc_html( number_format_i18n( (int) ( $estora_stats['available'] ?? 0 ) ) ); ?></strong><span><?php esc_html_e( 'Available now', 'estora-theme' ); ?></span></div>
				<div><strong><?php echo esc_html( number_format_i18n( (int) ( $estora_stats['published'] ?? 0 ) ) ); ?></strong><span><?php esc_html_e( 'Live listings', 'estora-theme' ); ?></span></div>
				<div><strong><?php echo esc_html( (string) count( $estora_agents ) ); ?></strong><span><?php esc_html_e( 'Agents', 'estora-theme' ); ?></span></div>
			</div>
		</div>
		<?php estora_search_form( array( 'variant' => 'hero' ) ); ?>
	</div>
</section>

<section class="estora-section">
	<div class="estora-container">
		<?php estora_section_head( __( 'Selected listings', 'estora-theme' ), __( 'Currently worth your attention', 'estora-theme' ), estora_properties_url(), __( 'All properties', 'estora-theme' ) ); ?>
		<?php if ( ! empty( $estora_featured['items'] ) ) : ?>
			<div class="estora-grid estora-grid--3">
				<?php foreach ( $estora_featured['items'] as $estora_property ) : ?>
					<?php estora_property_card( $estora_property ); ?>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="estora-empty">
				<h2><?php esc_html_e( 'Listings are being prepared', 'estora-theme' ); ?></h2>
				<p><?php esc_html_e( 'New properties appear here as soon as the property team publishes them.', 'estora-theme' ); ?></p>
				<a class="estora-btn" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php esc_html_e( 'Browse all properties', 'estora-theme' ); ?></a>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php if ( ! empty( $estora_provinces ) ) : ?>
<section class="estora-section estora-section--sand">
	<div class="estora-container">
		<?php estora_section_head( __( 'Where we work', 'estora-theme' ), __( 'Three provinces, one standard', 'estora-theme' ) ); ?>
		<div class="estora-locations">
			<?php foreach ( array_slice( $estora_provinces, 0, 3 ) as $estora_index => $estora_place ) : ?>
				<a class="estora-location" href="<?php echo esc_url( add_query_arg( 'location', $estora_place['slug'], estora_properties_url() ) ); ?>">
					<img src="<?php echo esc_url( estora_plate( 'plate-' . ( ( $estora_index % 6 ) + 1 ) ) ); ?>" alt="" width="800" height="600" loading="lazy">
					<span class="estora-location__body">
						<h3><?php echo esc_html( $estora_place['name'] ); ?></h3>
						<span><?php esc_html_e( 'View listings in this province', 'estora-theme' ); ?></span>
					</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( ! empty( $estora_latest['items'] ) ) : ?>
<section class="estora-section">
	<div class="estora-container">
		<?php estora_section_head( __( 'New to the market', 'estora-theme' ), __( 'Latest listings', 'estora-theme' ), estora_properties_url(), __( 'See everything', 'estora-theme' ) ); ?>
		<div class="estora-grid">
			<?php foreach ( $estora_latest['items'] as $estora_property ) : ?>
				<?php estora_property_card( $estora_property ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="estora-section estora-section--tight">
	<div class="estora-container">
		<?php estora_section_head( __( 'How we work', 'estora-theme' ), __( 'Built for the part after the brochure', 'estora-theme' ) ); ?>
		<div class="estora-features">
			<div class="estora-feature">
				<p class="estora-feature__num">01</p>
				<h3><?php esc_html_e( 'One record, one owner', 'estora-theme' ); ?></h3>
				<p><?php esc_html_e( 'Each property has a single internal file: price history, availability, viewings and the assigned agent. The listing you read is generated from it.', 'estora-theme' ); ?></p>
			</div>
			<div class="estora-feature">
				<p class="estora-feature__num">02</p>
				<h3><?php esc_html_e( 'Inspected before published', 'estora-theme' ); ?></h3>
				<p><?php esc_html_e( 'The property team checks the address, title type and specification before a listing goes live. Sold and rented homes are labelled, not quietly removed.', 'estora-theme' ); ?></p>
			</div>
			<div class="estora-feature">
				<p class="estora-feature__num">03</p>
				<h3><?php esc_html_e( 'Appointments that hold', 'estora-theme' ); ?></h3>
				<p><?php esc_html_e( 'Viewing slots are reserved against the agent and the property on our side, so the time you choose is the time that is kept.', 'estora-theme' ); ?></p>
			</div>
		</div>
	</div>
</section>

<?php if ( $estora_quotes->have_posts() ) : ?>
<section class="estora-section">
	<div class="estora-container">
		<?php estora_section_head( __( 'Clients', 'estora-theme' ), __( 'What the file looked like from the other side', 'estora-theme' ) ); ?>
		<div class="estora-quotes">
			<?php while ( $estora_quotes->have_posts() ) : ?>
				<?php $estora_quotes->the_post(); ?>
				<figure class="estora-quote">
					<blockquote><?php echo esc_html( wp_strip_all_tags( get_the_content() ) ); ?></blockquote>
					<figcaption>
						<strong><?php the_title(); ?></strong>
						<span><?php echo esc_html( (string) get_post_meta( get_the_ID(), '_estora_testimonial_role', true ) ); ?></span>
					</figcaption>
				</figure>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php if ( $estora_posts->have_posts() ) : ?>
<section class="estora-section estora-section--sand">
	<div class="estora-container">
		<?php estora_section_head( __( 'Notes from the office', 'estora-theme' ), __( 'Market notes and practical guides', 'estora-theme' ), home_url( '/news/' ), __( 'All articles', 'estora-theme' ) ); ?>
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

<section class="estora-section estora-cta">
	<div class="estora-container estora-cta__inner">
		<div>
			<p class="estora-eyebrow" style="color:#C7D8CF"><span style="background:#C7D8CF" aria-hidden="true"></span><?php esc_html_e( 'Next step', 'estora-theme' ); ?></p>
			<h2><?php esc_html_e( 'Tell us what you are looking for.', 'estora-theme' ); ?></h2>
			<p><?php esc_html_e( 'A short brief is enough. We will reply with the listings that genuinely fit and the ones that do not.', 'estora-theme' ); ?></p>
		</div>
		<a class="estora-btn estora-btn--light" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact the office', 'estora-theme' ); ?> <?php echo estora_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
	</div>
</section>

<?php get_footer(); ?>
