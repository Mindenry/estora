<?php
/**
 * Property archive with server-side filtering.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

$estora_args = array(
	'purpose'      => estora_filter_value( 'purpose' ),
	'type'         => estora_filter_value( 'type' ),
	'location'     => estora_filter_value( 'location' ),
	'province'     => estora_filter_value( 'province' ),
	'district'     => estora_filter_value( 'district' ),
	'q'            => estora_filter_value( 'q' ),
	'min_price'    => estora_filter_value( 'min_price' ),
	'max_price'    => estora_filter_value( 'max_price' ),
	'bedrooms'     => estora_filter_value( 'bedrooms' ),
	'bathrooms'    => estora_filter_value( 'bathrooms' ),
	'min_area'     => estora_filter_value( 'min_area' ),
	'max_area'     => estora_filter_value( 'max_area' ),
	'availability' => estora_filter_value( 'availability' ),
	'sort'         => estora_filter_value( 'sort', 'newest' ),
	'page'         => max( 1, (int) estora_filter_value( 'page', '1' ) ),
	'per_page'     => (int) estora_setting( 'listing_per_page', 9 ),
);

$estora_result = estora_public_properties( $estora_args );

$estora_labels = array(
	'purpose'      => __( 'Purpose', 'estora-theme' ),
	'type'         => __( 'Type', 'estora-theme' ),
	'location'     => __( 'Location', 'estora-theme' ),
	'q'            => __( 'Keyword', 'estora-theme' ),
	'min_price'    => __( 'Min price', 'estora-theme' ),
	'max_price'    => __( 'Max price', 'estora-theme' ),
	'bedrooms'     => __( 'Bedrooms', 'estora-theme' ),
	'availability' => __( 'Availability', 'estora-theme' ),
	'sort'         => __( 'Sort', 'estora-theme' ),
);

get_header();
?>
<section class="estora-pagehead">
	<div class="estora-container">
		<?php
		estora_breadcrumbs(
			array(
				array( 'label' => __( 'Properties', 'estora-theme' ) ),
			)
		);
		?>
		<h1><?php esc_html_e( 'Properties', 'estora-theme' ); ?></h1>
		<div class="estora-pagehead__meta">
			<span>
				<?php
				printf(
					/* translators: %s: result count */
					esc_html( _n( '%s property', '%s properties', (int) $estora_result['total'], 'estora-theme' ) ),
					esc_html( number_format_i18n( (int) $estora_result['total'] ) )
				);
				?>
			</span>
			<span aria-hidden="true">·</span>
			<span><?php esc_html_e( 'Updated directly by the property team', 'estora-theme' ); ?></span>
		</div>
	</div>
</section>

<div class="estora-container">
	<div class="estora-filterbar">
		<div class="estora-filterbar__head">
			<h2><?php esc_html_e( 'Refine', 'estora-theme' ); ?></h2>
			<a class="estora-textlink" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php esc_html_e( 'Reset all', 'estora-theme' ); ?></a>
		</div>
		<?php estora_search_form( array( 'variant' => 'full', 'attributes' => 'data-filter-form' ) ); ?>
		<div class="estora-filterbar__foot">
			<span class="estora-resultcount">
				<?php
				printf(
					/* translators: 1: shown, 2: total */
					esc_html__( 'Showing %1$s of %2$s', 'estora-theme' ),
					esc_html( number_format_i18n( count( $estora_result['items'] ) ) ),
					esc_html( number_format_i18n( (int) $estora_result['total'] ) )
				);
				?>
			</span>
			<span class="estora-muted"><?php esc_html_e( 'Sold and rented homes stay visible and are labelled.', 'estora-theme' ); ?></span>
		</div>
	</div>

	<?php
	$estora_active = array_filter(
		$estora_args,
		static function ( $value, $key ) use ( $estora_labels ) {
			return isset( $estora_labels[ $key ] ) && '' !== $value && 'newest' !== $value;
		},
		ARRAY_FILTER_USE_BOTH
	);
	?>
	<?php if ( ! empty( $estora_active ) ) : ?>
		<div class="estora-activefilters" aria-label="<?php esc_attr_e( 'Active filters', 'estora-theme' ); ?>">
			<?php foreach ( $estora_active as $estora_key => $estora_value ) : ?>
				<?php
				$estora_display = (string) $estora_value;
				if ( 'purpose' === $estora_key ) {
					$estora_display = estora_purpose_name( $estora_display );
				} elseif ( 'type' === $estora_key ) {
					$estora_display = estora_type_name( $estora_display, $estora_display );
				} elseif ( in_array( $estora_key, array( 'location', 'province', 'district' ), true ) ) {
					$estora_display = estora_place_name( $estora_display );
				}
				?>
				<a href="<?php echo esc_url( remove_query_arg( $estora_key ) ); ?>">
					<?php echo esc_html( $estora_labels[ $estora_key ] ); ?>: <?php echo esc_html( $estora_display ); ?>
					<span aria-hidden="true">×</span>
				</a>
			<?php endforeach; ?>
			<a href="<?php echo esc_url( estora_properties_url() ); ?>"><?php esc_html_e( 'Clear all', 'estora-theme' ); ?></a>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $estora_result['items'] ) ) : ?>
		<div class="estora-grid estora-grid--3">
			<?php foreach ( $estora_result['items'] as $estora_property ) : ?>
				<?php estora_property_card( $estora_property ); ?>
			<?php endforeach; ?>
		</div>
		<?php estora_pagination( $estora_result ); ?>
	<?php else : ?>
		<div class="estora-empty">
			<h2><?php echo ! empty( $estora_result['error'] ) ? esc_html__( 'The listings could not be loaded', 'estora-theme' ) : esc_html__( 'No properties match this search', 'estora-theme' ); ?></h2>
			<p><?php echo ! empty( $estora_result['error'] ) ? esc_html__( 'A temporary problem occurred. Please try again or contact the office.', 'estora-theme' ) : esc_html__( 'Try widening the price range or removing the location filter. Everything currently published is shown here; we do not hide sold homes.', 'estora-theme' ); ?></p>
			<a class="estora-btn" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php esc_html_e( 'Refresh listings', 'estora-theme' ); ?></a>
		</div>
	<?php endif; ?>
</div>

<section class="estora-section estora-section--sand" style="margin-top:64px">
	<div class="estora-container">
		<div class="estora-cta__inner">
			<div>
				<p class="estora-eyebrow"><span aria-hidden="true"></span><?php esc_html_e( 'Not seeing it?', 'estora-theme' ); ?></p>
				<h2 style="font-size:var(--fs-28)"><?php esc_html_e( 'Send us the brief instead.', 'estora-theme' ); ?></h2>
			</div>
			<a class="estora-btn" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Tell us what you need', 'estora-theme' ); ?></a>
		</div>
	</div>
</section>

<?php get_footer(); ?>
