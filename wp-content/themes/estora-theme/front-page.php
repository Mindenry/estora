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

$estora_posts = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 3, 'ignore_sticky_posts' => true ) );
$estora_quotes = new WP_Query( array( 'post_type' => 'testimonial', 'posts_per_page' => 3, 'no_found_rows' => true ) );

$estora_provinces = array();
foreach ( (array) $estora_options['locations'] as $estora_term_row ) {
	if ( 0 === (int) $estora_term_row['parent'] ) {
		$estora_provinces[] = $estora_term_row;
	}
}

$estora_places = array();
foreach ( $estora_provinces as $estora_place ) {
	$estora_term  = get_term_by( 'slug', (string) $estora_place['slug'], 'property_location' );
	$estora_count = $estora_term instanceof WP_Term ? (int) $estora_term->count : 0;
	if ( $estora_term instanceof WP_Term ) {
		foreach ( get_term_children( $estora_term->term_id, 'property_location' ) as $estora_child_id ) {
			$estora_child = get_term( $estora_child_id, 'property_location' );
			if ( $estora_child instanceof WP_Term ) {
				$estora_count += (int) $estora_child->count;
			}
		}
	}
	$estora_place['count'] = $estora_count;
	$estora_places[]       = $estora_place;
}

usort(
	$estora_places,
	static function ( $a, $b ) {
		return (int) $b['count'] <=> (int) $a['count'];
	}
);

$estora_hero_place = $estora_places[0] ?? null;
$estora_hero_image = estora_plate( 'plate-1' );
$estora_region     = wp_list_pluck( array_slice( $estora_places, 0, 3 ), 'name' );

get_header();
?>

<section class="estora-hero estora-hero--split" aria-labelledby="estora-hero-title">
	<div class="estora-container estora-hero__grid">
		<div class="estora-hero__copy">
			<p class="estora-eyebrow"><span aria-hidden="true"></span>
				<?php
				echo esc_html(
					$estora_region
						? implode( ' · ', array_map( 'wp_strip_all_tags', $estora_region ) )
						: __( 'Chiang Mai · Bangkok · Phuket', 'estora-theme' )
				);
				?>
			</p>
			<h1 id="estora-hero-title"><?php esc_html_e( 'A property file', 'estora-theme' ); ?> <em><?php esc_html_e( 'you can trust', 'estora-theme' ); ?></em>, <?php esc_html_e( 'from first viewing to handover.', 'estora-theme' ); ?></h1>
			<p class="estora-hero__lede"><?php esc_html_e( 'Houses, townhouses and low-rise apartments, represented directly and kept current by the people who inspect them.', 'estora-theme' ); ?></p>
			<div class="estora-hero__actions">
				<a class="estora-btn" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php esc_html_e( 'Explore the collection', 'estora-theme' ); ?> <?php echo estora_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				<a class="estora-hero__link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Talk to an advisor', 'estora-theme' ); ?> <?php echo estora_icon( 'arrow', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			</div>
			<p class="estora-hero__proof">
				<span><strong><?php echo esc_html( number_format_i18n( (int) ( $estora_stats['available'] ?? 0 ) ) ); ?></strong> <?php esc_html_e( 'available now', 'estora-theme' ); ?></span>
				<span><strong><?php echo esc_html( number_format_i18n( (int) ( $estora_stats['published'] ?? 0 ) ) ); ?></strong> <?php esc_html_e( 'live listings', 'estora-theme' ); ?></span>
				<span><strong><?php echo esc_html( number_format_i18n( count( $estora_places ) ) ); ?></strong> <?php esc_html_e( 'provinces', 'estora-theme' ); ?></span>
			</p>
		</div>

		<div class="estora-hero__visual">
			<div class="estora-hero__frame">
				<img src="<?php echo esc_url( $estora_hero_image ); ?>" alt="<?php echo esc_attr( $estora_hero_place ? sprintf( /* translators: %s: province name. */ __( 'Low-rise home illustration in %s', 'estora-theme' ), $estora_hero_place['name'] ) : __( 'Low-rise home illustration', 'estora-theme' ) ); ?>" width="1600" height="1000" fetchpriority="high">
				<?php if ( $estora_hero_place ) : ?>
					<a class="estora-hero__place" href="<?php echo esc_url( add_query_arg( 'location', $estora_hero_place['slug'], estora_properties_url() ) ); ?>">
						<span class="estora-hero__place-label"><?php esc_html_e( 'Neighbourhood focus', 'estora-theme' ); ?></span>
						<strong><?php echo esc_html( $estora_hero_place['name'] ); ?></strong>
						<span class="estora-hero__place-meta">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: number of homes. */
									_n( '%s home available', '%s homes available', (int) $estora_hero_place['count'], 'estora-theme' ),
									number_format_i18n( (int) $estora_hero_place['count'] )
								)
							);
							?>
						</span>
						<span class="estora-hero__place-arrow" aria-hidden="true"><?php echo estora_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="estora-container estora-hero__searchwrap">
		<div class="estora-searchcard">
			<div class="estora-searchcard__head">
				<p class="estora-searchcard__label"><?php esc_html_e( 'Find a property', 'estora-theme' ); ?></p>
				<p class="estora-searchcard__count"><strong><?php echo esc_html( number_format_i18n( (int) ( $estora_stats['available'] ?? 0 ) ) ); ?></strong> <?php esc_html_e( 'available now', 'estora-theme' ); ?></p>
			</div>
			<?php estora_search_form( array( 'variant' => 'hero' ) ); ?>
			<?php if ( $estora_places ) : ?>
				<div class="estora-searchcard__foot">
					<span><?php esc_html_e( 'Popular', 'estora-theme' ); ?></span>
					<?php foreach ( array_slice( $estora_places, 0, 3 ) as $estora_quick_place ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'location', $estora_quick_place['slug'], estora_properties_url() ) ); ?>"><?php echo esc_html( $estora_quick_place['name'] ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>

<section class="estora-section estora-section--collection">
	<div class="estora-container">
		<?php estora_section_head( __( 'Selected listings', 'estora-theme' ), __( 'Currently worth your attention', 'estora-theme' ), estora_properties_url(), __( 'All properties', 'estora-theme' ), '01' ); ?>
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

<?php if ( ! empty( $estora_places ) ) : ?>
<section class="estora-section estora-section--ink estora-places">
	<div class="estora-container">
		<?php estora_section_head( __( 'Where we work', 'estora-theme' ), __( 'Three provinces, one standard', 'estora-theme' ), '', '', '02' ); ?>
		<div class="estora-locations">
			<?php foreach ( array_slice( $estora_places, 0, 3 ) as $estora_index => $estora_place ) : ?>
				<a class="estora-location" href="<?php echo esc_url( add_query_arg( 'location', $estora_place['slug'], estora_properties_url() ) ); ?>">
					<img src="<?php echo esc_url( estora_plate( 'plate-' . ( ( $estora_index % 6 ) + 1 ) ) ); ?>" alt="" width="800" height="600" loading="lazy">
					<span class="estora-location__index" aria-hidden="true">0<?php echo esc_html( (string) ( $estora_index + 1 ) ); ?></span>
					<span class="estora-location__body">
						<span class="estora-location__meta">
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
						<h3><?php echo esc_html( $estora_place['name'] ); ?></h3>
						<span class="estora-location__link"><?php esc_html_e( 'View listings', 'estora-theme' ); ?> <?php echo estora_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
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
		<?php estora_section_head( __( 'New to the market', 'estora-theme' ), __( 'Latest listings', 'estora-theme' ), estora_properties_url(), __( 'See everything', 'estora-theme' ), '03' ); ?>
		<div class="estora-grid estora-grid--3">
			<?php foreach ( $estora_latest['items'] as $estora_property ) : ?>
				<?php estora_property_card( $estora_property ); ?>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<section class="estora-section estora-section--tight estora-principles-section" id="how-we-work">
	<div class="estora-container">
		<?php estora_section_head( __( 'How we work', 'estora-theme' ), __( 'Built for the part after the brochure', 'estora-theme' ), '', '', '04' ); ?>
		<div class="estora-principles">
			<article class="estora-principle">
				<span class="estora-principle__num" aria-hidden="true">01</span>
				<h3><?php esc_html_e( 'One record, one owner', 'estora-theme' ); ?></h3>
				<p><?php esc_html_e( 'Each property has a single internal file: price history, availability, viewings and the assigned agent. The listing you read is generated from it.', 'estora-theme' ); ?></p>
			</article>
			<article class="estora-principle">
				<span class="estora-principle__num" aria-hidden="true">02</span>
				<h3><?php esc_html_e( 'Inspected before published', 'estora-theme' ); ?></h3>
				<p><?php esc_html_e( 'The property team checks the address, title type and specification before a listing goes live. Sold and rented homes are labelled, not quietly removed.', 'estora-theme' ); ?></p>
			</article>
			<article class="estora-principle">
				<span class="estora-principle__num" aria-hidden="true">03</span>
				<h3><?php esc_html_e( 'Appointments that hold', 'estora-theme' ); ?></h3>
				<p><?php esc_html_e( 'Viewing slots are reserved against the agent and the property on our side, so the time you choose is the time that is kept.', 'estora-theme' ); ?></p>
			</article>
		</div>
	</div>
</section>

<?php if ( $estora_quotes->have_posts() ) : ?>
<section class="estora-section estora-quotes-section">
	<div class="estora-container">
		<?php estora_section_head( __( 'Clients', 'estora-theme' ), __( 'What the file looked like from the other side', 'estora-theme' ), '', '', '05' ); ?>
		<div class="estora-quotes estora-quotes--editorial">
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
<section class="estora-section estora-section--sand estora-journal">
	<div class="estora-container">
		<?php estora_section_head( __( 'Notes from the office', 'estora-theme' ), __( 'Market notes and practical guides', 'estora-theme' ), home_url( '/news/' ), __( 'All articles', 'estora-theme' ), '06' ); ?>
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
		<div class="estora-cta__copy">
			<p class="estora-eyebrow"><span aria-hidden="true"></span><?php esc_html_e( 'Next step', 'estora-theme' ); ?></p>
			<h2><?php esc_html_e( 'Tell us what you are looking for.', 'estora-theme' ); ?></h2>
			<p><?php esc_html_e( 'A short brief is enough. We will reply with the listings that genuinely fit and the ones that do not.', 'estora-theme' ); ?></p>
		</div>
		<a class="estora-cta__action" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">
			<span><?php esc_html_e( 'Contact the office', 'estora-theme' ); ?></span>
			<span class="estora-cta__arrow" aria-hidden="true"><?php echo estora_icon( 'arrow', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
		</a>
	</div>
</section>

<?php get_footer(); ?>
