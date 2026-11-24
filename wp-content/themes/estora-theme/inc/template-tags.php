<?php
/**
 * ESTORA theme template helpers.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline icon set (consistent 1.6px stroke library).
 *
 * @param string $name Icon name.
 * @param int    $size Pixel size.
 */
function estora_icon( string $name, int $size = 18 ): string {
	$paths = array(
		'pin'      => '<path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
		'bed'      => '<path d="M3 18v-7h18v7"/><path d="M3 18h18"/><path d="M6 11V8h5v3"/><path d="M14 11V8h4v3"/>',
		'bath'     => '<path d="M4 12h16v3a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4v-3Z"/><path d="M7 12V6a2 2 0 0 1 4 0"/><path d="M4 19h16"/>',
		'area'     => '<path d="M4 9V4h5"/><path d="M20 15v5h-5"/><path d="M4 4l16 16"/>',
		'parking'  => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 17V7h4a3 3 0 0 1 0 6H9"/>',
		'heart'    => '<path d="M20.8 5.6a5 5 0 0 0-7.1 0L12 7.3l-1.7-1.7a5 5 0 1 0-7.1 7.1L12 21l8.8-8.3a5 5 0 0 0 0-7.1Z"/>',
		'arrow'    => '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>',
		'phone'    => '<path d="M4 5c0-1 1-2 2-2h2l2 5-2 1a12 12 0 0 0 5 5l1-2 5 2v2c0 1-1 2-2 2A16 16 0 0 1 4 5Z"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>',
		'check'    => '<path d="m4 12 5 5L20 6"/>',
		'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
		'share'    => '<circle cx="6" cy="12" r="3"/><circle cx="18" cy="6" r="3"/><circle cx="18" cy="18" r="3"/><path d="m8.6 10.6 6.8-3.2M8.6 13.4l6.8 3.2"/>',
		'expand'   => '<path d="M9 3H3v6M15 3h6v6M9 21H3v-6M15 21h6v-6"/>',
		'grid'     => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
		'floor'    => '<path d="M4 20h16M6 20V8l6-4 6 4v12"/>',
	);

	$path = $paths[ $name ] ?? $paths['check'];
	return sprintf(
		'<svg class="estora-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
		$size,
		$path
	);
}

/**
 * Art plate URL.
 *
 * @param string $key Plate key.
 */
function estora_plate( string $key = 'plate-1' ): string {
	if ( ! preg_match( '/^plate-[1-6]$/', $key ) ) {
		$key = 'plate-1';
	}
	return estora_theme_asset( 'img/' . $key . '.png' );
}

/**
 * Archive URL.
 */
function estora_properties_url(): string {
	$url = get_post_type_archive_link( 'property' );
	return $url ? $url : home_url( '/properties/' );
}

/**
 * Current filter value.
 *
 * @param string $key Key.
 * @param string $default Default.
 */
function estora_filter_value( string $key, string $default = '' ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default;
}

/**
 * Search/filter form.
 *
 * @param array<string,mixed> $args Options.
 */
function estora_search_form( array $args = array() ): void {
	$args = wp_parse_args(
		$args,
		array(
			'variant'    => 'hero',
			'action'     => estora_properties_url(),
			'attributes' => '',
		)
	);

	$purpose  = estora_filter_value( 'purpose' );
	$type     = estora_filter_value( 'type' );
	$location = estora_filter_value( 'location' );
	$minimum  = estora_filter_value( 'min_price' );
	$maximum  = estora_filter_value( 'max_price' );
	$beds     = estora_filter_value( 'bedrooms' );
	$sort     = estora_filter_value( 'sort', 'newest' );
	$keyword  = estora_filter_value( 'q' );

	$options = array( 'types' => array(), 'locations' => array() );
	if ( function_exists( 'estora_public_properties' ) ) {
		$repo    = new \Estora\Core\Repositories\Property_Repository();
		$options = $repo->filter_options();
	}
	?>
	<form class="estora-search estora-search--<?php echo esc_attr( $args['variant'] ); ?>" method="get" action="<?php echo esc_url( $args['action'] ); ?>" role="search" <?php echo $args['attributes'] ? esc_attr( $args['attributes'] ) : ''; ?>>
		<?php if ( 'full' === $args['variant'] ) : ?>
			<div class="estora-search__field estora-search__field--wide">
				<label for="estora-q"><?php esc_html_e( 'Keyword', 'estora-theme' ); ?></label>
				<input type="search" id="estora-q" name="q" value="<?php echo esc_attr( $keyword ); ?>" placeholder="<?php esc_attr_e( 'Reference, project or street', 'estora-theme' ); ?>">
			</div>
		<?php endif; ?>

		<div class="estora-search__field">
			<label for="estora-purpose"><?php esc_html_e( 'Purpose', 'estora-theme' ); ?></label>
			<select id="estora-purpose" name="purpose">
				<option value=""><?php esc_html_e( 'Buy or rent', 'estora-theme' ); ?></option>
				<option value="sale" <?php selected( $purpose, 'sale' ); ?>><?php esc_html_e( 'For sale', 'estora-theme' ); ?></option>
				<option value="rent" <?php selected( $purpose, 'rent' ); ?>><?php esc_html_e( 'For rent', 'estora-theme' ); ?></option>
			</select>
		</div>

		<div class="estora-search__field">
			<label for="estora-type"><?php esc_html_e( 'Property type', 'estora-theme' ); ?></label>
			<select id="estora-type" name="type">
				<option value=""><?php esc_html_e( 'Any type', 'estora-theme' ); ?></option>
				<?php foreach ( (array) $options['types'] as $term ) : ?>
					<option value="<?php echo esc_attr( $term['slug'] ); ?>" <?php selected( $type, $term['slug'] ); ?>><?php echo esc_html( $term['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="estora-search__field">
			<label for="estora-location"><?php esc_html_e( 'Location', 'estora-theme' ); ?></label>
			<select id="estora-location" name="location">
				<option value=""><?php esc_html_e( 'Anywhere', 'estora-theme' ); ?></option>
				<?php foreach ( (array) $options['locations'] as $term ) : ?>
					<option value="<?php echo esc_attr( $term['slug'] ); ?>" <?php selected( $location, $term['slug'] ); ?>><?php echo esc_html( $term['name'] ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php if ( 'full' === $args['variant'] ) : ?>
			<div class="estora-search__field">
				<label for="estora-min"><?php esc_html_e( 'Min price', 'estora-theme' ); ?></label>
				<input type="number" id="estora-min" name="min_price" min="0" step="10000" value="<?php echo esc_attr( $minimum ); ?>" placeholder="0">
			</div>
			<div class="estora-search__field">
				<label for="estora-max"><?php esc_html_e( 'Max price', 'estora-theme' ); ?></label>
				<input type="number" id="estora-max" name="max_price" min="0" step="10000" value="<?php echo esc_attr( $maximum ); ?>" placeholder="<?php esc_attr_e( 'No limit', 'estora-theme' ); ?>">
			</div>
			<div class="estora-search__field">
				<label for="estora-beds"><?php esc_html_e( 'Bedrooms', 'estora-theme' ); ?></label>
				<select id="estora-beds" name="bedrooms">
					<option value=""><?php esc_html_e( 'Any', 'estora-theme' ); ?></option>
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<option value="<?php echo esc_attr( (string) $i ); ?>" <?php selected( $beds, (string) $i ); ?>><?php echo esc_html( $i . '+' ); ?></option>
					<?php endfor; ?>
				</select>
			</div>
			<div class="estora-search__field">
				<label for="estora-sort"><?php esc_html_e( 'Sort', 'estora-theme' ); ?></label>
				<select id="estora-sort" name="sort">
					<option value="newest" <?php selected( $sort, 'newest' ); ?>><?php esc_html_e( 'Newest first', 'estora-theme' ); ?></option>
					<option value="oldest" <?php selected( $sort, 'oldest' ); ?>><?php esc_html_e( 'Oldest first', 'estora-theme' ); ?></option>
					<option value="price_asc" <?php selected( $sort, 'price_asc' ); ?>><?php esc_html_e( 'Price, low to high', 'estora-theme' ); ?></option>
					<option value="price_desc" <?php selected( $sort, 'price_desc' ); ?>><?php esc_html_e( 'Price, high to low', 'estora-theme' ); ?></option>
					<option value="area_asc" <?php selected( $sort, 'area_asc' ); ?>><?php esc_html_e( 'Area, small to large', 'estora-theme' ); ?></option>
					<option value="area_desc" <?php selected( $sort, 'area_desc' ); ?>><?php esc_html_e( 'Area, large to small', 'estora-theme' ); ?></option>
				</select>
			</div>
		<?php endif; ?>

		<button class="estora-search__submit" type="submit">
			<?php esc_html_e( 'Search', 'estora-theme' ); ?>
			<?php echo estora_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>
	</form>
	<?php
}

/**
 * Property card.
 *
 * @param array<string,mixed> $property Property shape.
 * @param string              $context  Context.
 */
function estora_property_card( array $property, string $context = 'grid' ): void {
	$url     = $property['url'] ?? '';
	$purpose = $property['purpose'] ?? 'sale';
	$is_sold = in_array( $property['availability'] ?? 'available', array( 'sold', 'rented', 'unavailable' ), true );
	?>
	<article class="estora-card<?php echo $is_sold ? ' is-unavailable' : ''; ?>" data-property-id="<?php echo esc_attr( (string) ( $property['id'] ?? 0 ) ); ?>">
		<a class="estora-card__media" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $property['title'] ?? '' ); ?>">
			<img src="<?php echo esc_url( $property['image']['url'] ?? estora_plate() ); ?>"
				width="<?php echo esc_attr( (string) ( $property['image']['width'] ?? 1600 ) ); ?>"
				height="<?php echo esc_attr( (string) ( $property['image']['height'] ?? 1000 ) ); ?>"
				alt="<?php echo esc_attr( $property['image']['alt'] ?: ( $property['title'] ?? '' ) ); ?>"
				loading="lazy" decoding="async">
			<span class="estora-chip estora-chip--<?php echo esc_attr( $purpose ); ?>"><?php echo esc_html( 'rent' === $purpose ? __( 'For rent', 'estora-theme' ) : __( 'For sale', 'estora-theme' ) ); ?></span>
			<?php if ( $is_sold ) : ?>
				<span class="estora-chip estora-chip--muted"><?php echo esc_html( $property['availability_label'] ?? '' ); ?></span>
			<?php endif; ?>
		</a>
		<button type="button" class="estora-fav" data-favorite="<?php echo esc_attr( (string) ( $property['id'] ?? 0 ) ); ?>" aria-label="<?php esc_attr_e( 'Save property', 'estora-theme' ); ?>" aria-pressed="false">
			<?php echo estora_icon( 'heart', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</button>
		<div class="estora-card__body">
			<p class="estora-card__price"><?php echo esc_html( $property['price_formatted'] ?? '' ); ?><span><?php echo 'rent' === $purpose ? esc_html( ' / ' . ( 'year' === ( $property['rent_period'] ?? 'month' ) ? __( 'year', 'estora-theme' ) : __( 'month', 'estora-theme' ) ) ) : ''; ?></span></p>
			<h3 class="estora-card__title"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $property['title'] ?? '' ); ?></a></h3>
			<p class="estora-card__location">
				<?php echo estora_icon( 'pin', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo esc_html( trim( ( $property['location']['district'] ?? '' ) . ', ' . ( $property['location']['province'] ?? '' ), ', ' ) ); ?>
			</p>
			<ul class="estora-card__specs">
				<?php if ( ! empty( $property['bedrooms'] ) ) : ?>
					<li><?php echo estora_icon( 'bed', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( (string) $property['bedrooms'] ); ?></span></li>
				<?php endif; ?>
				<?php if ( ! empty( $property['bathrooms'] ) ) : ?>
					<li><?php echo estora_icon( 'bath', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( (string) $property['bathrooms'] ); ?></span></li>
				<?php endif; ?>
				<?php if ( ! empty( $property['usable_area'] ) ) : ?>
					<li><?php echo estora_icon( 'area', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( number_format_i18n( (float) $property['usable_area'] ) . ' m²' ); ?></span></li>
				<?php elseif ( ! empty( $property['land_area'] ) ) : ?>
					<li><?php echo estora_icon( 'area', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( number_format_i18n( (float) $property['land_area'] ) . ' m²' ); ?></span></li>
				<?php endif; ?>
				<?php if ( ! empty( $property['parking'] ) ) : ?>
					<li><?php echo estora_icon( 'parking', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( (string) $property['parking'] ); ?></span></li>
				<?php endif; ?>
			</ul>
			<div class="estora-card__footer">
				<span class="estora-muted"><?php echo esc_html( $property['type_label'] ?? '' ); ?></span>
				<button type="button" class="estora-compare-btn" data-compare="<?php echo esc_attr( (string) ( $property['id'] ?? 0 ) ); ?>" aria-pressed="false"><?php esc_html_e( 'Compare', 'estora-theme' ); ?></button>
			</div>
		</div>
	</article>
	<?php
}

/**
 * Section heading.
 *
 * @param string $eyebrow Eyebrow.
 * @param string $title   Title.
 * @param string $link    Optional link.
 * @param string $link_label Link label.
 * @param string $index   Optional section index.
 */
function estora_section_head( string $eyebrow, string $title, string $link = '', string $link_label = '', string $index = '' ): void {
	?>
	<div class="estora-sectionhead">
		<div class="estora-sectionhead__lead">
			<?php if ( '' !== $index ) : ?>
				<span class="estora-sectionhead__index" aria-hidden="true"><?php echo esc_html( $index ); ?></span>
			<?php endif; ?>
			<div>
				<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( $eyebrow ); ?></p>
				<h2><?php echo esc_html( $title ); ?></h2>
			</div>
		</div>
		<?php if ( $link ) : ?>
			<a class="estora-textlink" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $link_label ?: __( 'View all', 'estora-theme' ) ); ?> <?php echo estora_icon( 'arrow', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Pagination for a repository result.
 *
 * @param array<string,mixed> $result Result with page/pages/total.
 */
function estora_pagination( array $result ): void {
	$pages = (int) ( $result['pages'] ?? 0 );
	if ( $pages <= 1 ) {
		return;
	}
	$current = max( 1, (int) ( $result['page'] ?? 1 ) );
	$base    = add_query_arg( 'page', '%#%', remove_query_arg( 'page' ) );
	$links   = paginate_links(
		array(
			'base'      => $base,
			'format'    => '',
			'current'   => $current,
			'total'     => $pages,
			'mid_size'  => 1,
			'prev_text' => '←',
			'next_text' => '→',
			'type'      => 'array',
		)
	);
	if ( empty( $links ) ) {
		return;
	}
	?>
	<nav class="estora-pagination" aria-label="<?php esc_attr_e( 'Property pages', 'estora-theme' ); ?>">
		<?php foreach ( $links as $link ) : ?>
			<?php echo wp_kses_post( $link ); ?>
		<?php endforeach; ?>
	</nav>
	<?php
}

/**
 * Breadcrumbs.
 *
 * @param array<int,array{label:string,url?:string}> $trail Trail.
 */
function estora_breadcrumbs( array $trail ): void {
	?>
	<nav class="estora-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'estora-theme' ); ?>">
		<ol>
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'estora-theme' ); ?></a></li>
			<?php foreach ( $trail as $crumb ) : ?>
				<li>
					<?php if ( ! empty( $crumb['url'] ) ) : ?>
						<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
					<?php else : ?>
						<span aria-current="page"><?php echo esc_html( $crumb['label'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php
}

/**
 * Share links.
 *
 * @param string $url   URL.
 * @param string $title Title.
 */
function estora_share( string $url, string $title ): void {
	?>
	<div class="estora-share">
		<span class="estora-share__label"><?php esc_html_e( 'Share', 'estora-theme' ); ?></span>
		<a href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ) ); ?>" target="_blank" rel="noopener noreferrer">Facebook</a>
		<a href="<?php echo esc_url( 'https://x.com/intent/tweet?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title ) ); ?>" target="_blank" rel="noopener noreferrer">X</a>
		<button type="button" data-copy-link="<?php echo esc_attr( $url ); ?>"><?php esc_html_e( 'Copy link', 'estora-theme' ); ?></button>
	</div>
	<?php
}

/**
 * Agent mini card.
 *
 * @param array<string,mixed> $agent Agent.
 */
function estora_agent_card( array $agent ): void {
	?>
	<div class="estora-agent">
		<div class="estora-agent__avatar" aria-hidden="true">
			<?php if ( ! empty( $agent['avatar'] ) ) : ?>
				<img src="<?php echo esc_url( $agent['avatar'] ); ?>" alt="" width="72" height="72" loading="lazy">
			<?php else : ?>
				<span><?php echo esc_html( \Estora\Core\Support\Formatter::initials( (string) ( $agent['name'] ?? '' ) ) ); ?></span>
			<?php endif; ?>
		</div>
		<div>
			<p class="estora-eyebrow"><?php esc_html_e( 'Your agent', 'estora-theme' ); ?></p>
			<h3><?php echo esc_html( $agent['name'] ?? '' ); ?></h3>
			<p class="estora-muted"><?php echo esc_html( $agent['title'] ?? '' ); ?></p>
			<?php if ( ! empty( $agent['phone'] ) ) : ?>
				<a class="estora-textlink" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $agent['phone'] ) ); ?>"><?php echo estora_icon( 'phone', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( $agent['phone'] ); ?></a>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
