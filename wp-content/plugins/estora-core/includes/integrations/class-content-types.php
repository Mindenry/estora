<?php
/**
 * Property post type, taxonomies, meta box and admin columns.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Integrations;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Domain\Property_Status;
use Estora\Core\Repositories\Activity_Repository;
use Estora\Core\Repositories\Property_Repository;
use Estora\Core\Support\Capabilities;
use Estora\Core\Support\Formatter;
use Estora\Core\Support\Validation_Exception;

final class Content_Types {

	/**
	 * Revert guard.
	 *
	 * @var bool
	 */
	private static bool $reverting = false;

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ) );
		add_action( 'save_post_property', array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'manage_property_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_property_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
	}

	/**
	 * Register CPT, taxonomies and meta.
	 */
	public function register(): void {
		register_post_type(
			'property',
			array(
				'labels'             => array(
					'name'               => __( 'Properties', 'estora-core' ),
					'singular_name'      => __( 'Property', 'estora-core' ),
					'add_new'            => __( 'Add property', 'estora-core' ),
					'add_new_item'       => __( 'Add property', 'estora-core' ),
					'edit_item'          => __( 'Edit property', 'estora-core' ),
					'view_item'          => __( 'View property', 'estora-core' ),
					'search_items'       => __( 'Search properties', 'estora-core' ),
					'not_found'          => __( 'No properties found', 'estora-core' ),
					'not_found_in_trash' => __( 'No properties in trash', 'estora-core' ),
					'menu_name'          => __( 'Properties', 'estora-core' ),
				),
				'public'             => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_admin_bar'  => true,
				'publicly_queryable' => true,
				'has_archive'        => 'properties',
				'rewrite'            => array(
					'slug'       => 'properties',
					'with_front' => false,
				),
				'menu_icon'          => 'dashicons-building',
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author' ),
				'taxonomies'         => array( 'property_type', 'property_purpose', 'property_location' ),
				'show_in_rest'       => false,
				'capability_type'    => array( 'property', 'properties' ),
				'map_meta_cap'       => true,
				'capabilities'       => array(
					'edit_posts'             => Capabilities::EDIT_PROPERTIES,
					'edit_others_posts'      => Capabilities::EDIT_PROPERTIES,
					'edit_published_posts'   => Capabilities::EDIT_PROPERTIES,
					'edit_private_posts'     => Capabilities::EDIT_PROPERTIES,
					'publish_posts'          => Capabilities::PUBLISH_PROPERTIES,
					'read_posts'             => Capabilities::VIEW_PROPERTIES,
					'read_private_posts'     => Capabilities::VIEW_PROPERTIES,
					'delete_posts'           => Capabilities::DELETE_PROPERTIES,
					'delete_others_posts'    => Capabilities::DELETE_PROPERTIES,
					'delete_published_posts' => Capabilities::DELETE_PROPERTIES,
					'delete_private_posts'   => Capabilities::DELETE_PROPERTIES,
				),
			)
		);

		register_post_type(
			'testimonial',
			array(
				'labels'          => array(
					'name'          => __( 'Testimonials', 'estora-core' ),
					'singular_name' => __( 'Testimonial', 'estora-core' ),
					'add_new_item'  => __( 'Add testimonial', 'estora-core' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => 'estora-dashboard',
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => array(
					'edit_posts'             => Capabilities::MANAGE_CMS,
					'edit_others_posts'      => Capabilities::MANAGE_CMS,
					'publish_posts'          => Capabilities::MANAGE_CMS,
					'read_private_posts'     => Capabilities::MANAGE_CMS,
					'delete_posts'           => Capabilities::MANAGE_CMS,
				),
			)
		);

		register_post_status(
			'archived',
			array(
				'label'                     => __( 'Archived', 'estora-core' ),
				'public'                    => false,
				'internal'                  => false,
				'private'                   => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: count */
				'label_count'               => _n_noop( 'Archived <span class="count">(%s)</span>', 'Archived <span class="count">(%s)</span>', 'estora-core' ),
			)
		);

		register_taxonomy(
			'property_type',
			array( 'property' ),
			array(
				'labels'            => array(
					'name'          => __( 'Property types', 'estora-core' ),
					'singular_name' => __( 'Property type', 'estora-core' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'property-type' ),
				'show_in_rest'      => false,
			)
		);

		register_taxonomy(
			'property_purpose',
			array( 'property' ),
			array(
				'labels'            => array(
					'name'          => __( 'Purposes', 'estora-core' ),
					'singular_name' => __( 'Purpose', 'estora-core' ),
				),
				'public'            => true,
				'hierarchical'      => false,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'purpose' ),
				'show_in_rest'      => false,
			)
		);

		register_taxonomy(
			'property_location',
			array( 'property' ),
			array(
				'labels'            => array(
					'name'          => __( 'Locations', 'estora-core' ),
					'singular_name' => __( 'Location', 'estora-core' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => false,
				'rewrite'           => array( 'slug' => 'location' ),
				'show_in_rest'      => false,
			)
		);

		$meta              = Property_Repository::meta_map();
		$meta['reference'] = 'text';
		foreach ( $meta as $key => $type ) {
			register_post_meta(
				'property',
				'_estora_' . $key,
				array(
					'show_in_rest'      => false,
					'single'            => true,
					'type'              => in_array( $type, array( 'float', 'int' ), true ) ? 'number' : ( 'bool' === $type ? 'boolean' : ( 'array' === $type ? 'array' : 'string' ) ),
					'sanitize_callback' => static function ( $value ) {
						return is_array( $value ) ? array_map( 'absint', $value ) : sanitize_text_field( (string) $value );
					},
					'auth_callback'     => static function () {
						return current_user_can( Capabilities::EDIT_PROPERTIES );
					},
				)
			);
		}
	}

	/**
	 * Register meta boxes.
	 */
	public function meta_boxes(): void {
		add_meta_box(
			'estora-property-details',
			__( 'Listing details', 'estora-core' ),
			array( $this, 'render_details_box' ),
			'property',
			'normal',
			'high'
		);

		add_meta_box(
			'estora-property-gallery',
			__( 'Gallery', 'estora-core' ),
			array( $this, 'render_gallery_box' ),
			'property',
			'side',
			'default'
		);

		add_meta_box(
			'estora-property-seo',
			__( 'Listing SEO', 'estora-core' ),
			array( $this, 'render_seo_box' ),
			'property',
			'side',
			'default'
		);
	}

	/**
	 * Render the main fields.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_details_box( $post ): void {
		$repo = new Property_Repository();
		wp_nonce_field( 'estora_property_meta', 'estora_property_nonce' );

		$purpose_terms  = wp_get_post_terms( $post->ID, 'property_purpose', array( 'fields' => 'slugs' ) );
		$purpose        = ( ! is_wp_error( $purpose_terms ) && $purpose_terms ) ? $purpose_terms[0] : '';
		$type_terms     = wp_get_post_terms( $post->ID, 'property_type' );
		$type           = ( ! is_wp_error( $type_terms ) && $type_terms ) ? $type_terms[0]->slug : '';
		$location_terms = wp_get_post_terms( $post->ID, 'property_location' );
		$location       = ( ! is_wp_error( $location_terms ) && $location_terms ) ? $location_terms[0]->slug : '';

		$agents       = get_users( array( 'capability' => Capabilities::EDIT_LEADS, 'number' => 100 ) );
		$price        = estora_property_price( $post->ID );
		$availability = (string) $repo->meta( $post->ID, 'availability', Property_Status::AVAILABLE );
		$amenities    = (array) $repo->meta( $post->ID, 'amenities', array() );
		?>
		<div class="estora-metabox">
			<div class="estora-metabox__grid">
				<p>
					<label for="estora_price"><?php esc_html_e( 'Price (sale price or monthly rent)', 'estora-core' ); ?></label>
					<input type="number" step="0.01" min="0" id="estora_price" name="estora_price" class="widefat" value="<?php echo esc_attr( (string) ( $price ?: '' ) ); ?>">
					<span class="estora-metabox__hint"><?php echo esc_html( $price ? estora_price( $price ) : __( 'Required before publishing.', 'estora-core' ) ); ?></span>
				</p>
				<p>
					<label for="estora_purpose"><?php esc_html_e( 'Purpose', 'estora-core' ); ?></label>
					<select id="estora_purpose" name="estora_purpose" class="widefat">
						<option value=""><?php esc_html_e( 'Select…', 'estora-core' ); ?></option>
						<option value="sale" <?php selected( $purpose, 'sale' ); ?>><?php esc_html_e( 'For sale', 'estora-core' ); ?></option>
						<option value="rent" <?php selected( $purpose, 'rent' ); ?>><?php esc_html_e( 'For rent', 'estora-core' ); ?></option>
					</select>
				</p>
				<p>
					<label for="estora_rent_period"><?php esc_html_e( 'Rent period', 'estora-core' ); ?></label>
					<select id="estora_rent_period" name="estora_rent_period" class="widefat">
						<option value="month" <?php selected( (string) $repo->meta( $post->ID, 'rent_period', 'month' ), 'month' ); ?>><?php esc_html_e( 'Per month', 'estora-core' ); ?></option>
						<option value="year" <?php selected( (string) $repo->meta( $post->ID, 'rent_period', 'month' ), 'year' ); ?>><?php esc_html_e( 'Per year', 'estora-core' ); ?></option>
					</select>
				</p>
				<p>
					<label for="estora_type"><?php esc_html_e( 'Property type', 'estora-core' ); ?></label>
					<select id="estora_type" name="estora_type" class="widefat">
						<option value=""><?php esc_html_e( 'Select…', 'estora-core' ); ?></option>
						<?php foreach ( get_terms( array( 'taxonomy' => 'property_type', 'hide_empty' => false ) ) as $term ) : ?>
							<?php if ( $term instanceof \WP_Term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $type, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="estora_location"><?php esc_html_e( 'Location term', 'estora-core' ); ?></label>
					<select id="estora_location" name="estora_location" class="widefat">
						<option value=""><?php esc_html_e( 'Select…', 'estora-core' ); ?></option>
						<?php foreach ( get_terms( array( 'taxonomy' => 'property_location', 'hide_empty' => false ) ) as $term ) : ?>
							<?php if ( $term instanceof \WP_Term ) : ?>
								<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $location, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="estora_availability"><?php esc_html_e( 'Availability', 'estora-core' ); ?></label>
					<select id="estora_availability" name="estora_availability" class="widefat">
						<?php foreach ( Property_Status::availability_values() as $value ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $availability, $value ); ?>><?php echo esc_html( Property_Status::availability_label( $value ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="estora_agent_id"><?php esc_html_e( 'Assigned agent', 'estora-core' ); ?></label>
					<select id="estora_agent_id" name="estora_agent_id" class="widefat">
						<option value="0"><?php esc_html_e( 'Unassigned', 'estora-core' ); ?></option>
						<?php foreach ( $agents as $agent ) : ?>
							<option value="<?php echo esc_attr( (string) $agent->ID ); ?>" <?php selected( (int) $repo->meta( $post->ID, 'agent_id' ), $agent->ID ); ?>><?php echo esc_html( $agent->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="estora-metabox__check">
					<label>
						<input type="checkbox" name="estora_featured" value="1" <?php checked( (bool) $repo->meta( $post->ID, 'featured' ) ); ?>>
						<?php esc_html_e( 'Featured on the homepage', 'estora-core' ); ?>
					</label>
				</p>
			</div>

			<h3><?php esc_html_e( 'Address', 'estora-core' ); ?></h3>
			<div class="estora-metabox__grid">
				<p class="estora-metabox__wide">
					<label for="estora_address"><?php esc_html_e( 'Street address', 'estora-core' ); ?></label>
					<input type="text" id="estora_address" name="estora_address" class="widefat" value="<?php echo esc_attr( (string) $repo->meta( $post->ID, 'address' ) ); ?>">
				</p>
				<p>
					<label for="estora_province"><?php esc_html_e( 'Province', 'estora-core' ); ?></label>
					<input type="text" id="estora_province" name="estora_province" class="widefat" value="<?php echo esc_attr( (string) $repo->meta( $post->ID, 'province' ) ); ?>">
				</p>
				<p>
					<label for="estora_district"><?php esc_html_e( 'District', 'estora-core' ); ?></label>
					<input type="text" id="estora_district" name="estora_district" class="widefat" value="<?php echo esc_attr( (string) $repo->meta( $post->ID, 'district' ) ); ?>">
				</p>
				<p>
					<label for="estora_subdistrict"><?php esc_html_e( 'Subdistrict', 'estora-core' ); ?></label>
					<input type="text" id="estora_subdistrict" name="estora_subdistrict" class="widefat" value="<?php echo esc_attr( (string) $repo->meta( $post->ID, 'subdistrict' ) ); ?>">
				</p>
				<p>
					<label for="estora_postal_code"><?php esc_html_e( 'Postal code', 'estora-core' ); ?></label>
					<input type="text" id="estora_postal_code" name="estora_postal_code" class="widefat" value="<?php echo esc_attr( (string) $repo->meta( $post->ID, 'postal_code' ) ); ?>">
				</p>
				<p>
					<label for="estora_latitude"><?php esc_html_e( 'Latitude', 'estora-core' ); ?></label>
					<input type="number" step="0.000001" id="estora_latitude" name="estora_latitude" class="widefat" value="<?php echo esc_attr( (string) ( $repo->meta( $post->ID, 'latitude' ) ?: '' ) ); ?>">
				</p>
				<p>
					<label for="estora_longitude"><?php esc_html_e( 'Longitude', 'estora-core' ); ?></label>
					<input type="number" step="0.000001" id="estora_longitude" name="estora_longitude" class="widefat" value="<?php echo esc_attr( (string) ( $repo->meta( $post->ID, 'longitude' ) ?: '' ) ); ?>">
				</p>
			</div>

			<h3><?php esc_html_e( 'Specifications', 'estora-core' ); ?></h3>
			<div class="estora-metabox__grid estora-metabox__grid--four">
				<?php
				$specs = array(
					'land_area'   => __( 'Land area (m²)', 'estora-core' ),
					'usable_area' => __( 'Usable area (m²)', 'estora-core' ),
					'bedrooms'    => __( 'Bedrooms', 'estora-core' ),
					'bathrooms'   => __( 'Bathrooms', 'estora-core' ),
					'parking'     => __( 'Parking spaces', 'estora-core' ),
					'floors'      => __( 'Floors', 'estora-core' ),
					'year_built'  => __( 'Year built', 'estora-core' ),
				);
				foreach ( $specs as $key => $label ) :
					?>
					<p>
						<label for="estora_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
						<input type="number" step="any" min="0" id="estora_<?php echo esc_attr( $key ); ?>" name="estora_<?php echo esc_attr( $key ); ?>" class="widefat" value="<?php echo esc_attr( (string) ( $repo->meta( $post->ID, $key ) ?: '' ) ); ?>">
					</p>
				<?php endforeach; ?>
			</div>

			<p>
				<label for="estora_amenities"><?php esc_html_e( 'Amenities (comma separated)', 'estora-core' ); ?></label>
				<input type="text" id="estora_amenities" name="estora_amenities" class="widefat" value="<?php echo esc_attr( implode( ', ', $amenities ) ); ?>" placeholder="Pool, Parking, Security, Garden">
			</p>
			<p>
				<label for="estora_video_url"><?php esc_html_e( 'Video tour URL', 'estora-core' ); ?></label>
				<input type="url" id="estora_video_url" name="estora_video_url" class="widefat" value="<?php echo esc_attr( (string) $repo->meta( $post->ID, 'video_url' ) ); ?>">
			</p>
			<p class="estora-metabox__hint">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: reference */
						__( 'Reference: %s', 'estora-core' ),
						(string) ( $repo->meta( $post->ID, 'reference' ) ?: Formatter::reference( $post->ID ) )
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Gallery meta box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_gallery_box( $post ): void {
		$repo  = new Property_Repository();
		$ids   = (array) $repo->meta( $post->ID, 'gallery', array() );
		$value = implode( ',', array_map( 'intval', $ids ) );
		?>
		<div class="estora-gallery-field">
			<input type="hidden" id="estora_gallery" name="estora_gallery" value="<?php echo esc_attr( $value ); ?>">
			<div class="estora-gallery-field__preview" data-estora-gallery-preview>
				<?php foreach ( $ids as $id ) : ?>
					<?php $src = wp_get_attachment_image_src( (int) $id, 'thumbnail' ); ?>
					<?php if ( $src ) : ?>
						<figure data-id="<?php echo esc_attr( (string) $id ); ?>">
							<img src="<?php echo esc_url( $src[0] ); ?>" alt="">
							<button type="button" class="button-link" data-remove-image="<?php echo esc_attr( (string) $id ); ?>" aria-label="<?php esc_attr_e( 'Remove image', 'estora-core' ); ?>">&times;</button>
						</figure>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
			<p>
				<button type="button" class="button" data-estora-gallery-add><?php esc_html_e( 'Add images', 'estora-core' ); ?></button>
			</p>
			<p class="estora-metabox__hint"><?php esc_html_e( 'JPG, PNG or WebP, up to 5 MB each. Drag to reorder is not required; removal and re-adding sets the order.', 'estora-core' ); ?></p>
		</div>
		<?php
	}

	/**
	 * SEO meta box.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function render_seo_box( $post ): void {
		$repo = new Property_Repository();
		?>
		<p>
			<label for="estora_seo_title"><?php esc_html_e( 'Meta title', 'estora-core' ); ?></label>
			<input type="text" id="estora_seo_title" name="estora_seo_title" class="widefat" maxlength="190" value="<?php echo esc_attr( (string) $repo->meta( $post->ID, 'seo_title' ) ); ?>">
		</p>
		<p>
			<label for="estora_seo_description"><?php esc_html_e( 'Meta description', 'estora-core' ); ?></label>
			<textarea id="estora_seo_description" name="estora_seo_description" class="widefat" rows="3" maxlength="300"><?php echo esc_textarea( (string) $repo->meta( $post->ID, 'seo_description' ) ); ?></textarea>
		</p>
		<p>
			<label for="estora_placeholder"><?php esc_html_e( 'Fallback art plate', 'estora-core' ); ?></label>
			<select id="estora_placeholder" name="estora_placeholder" class="widefat">
				<?php for ( $i = 1; $i <= 6; $i++ ) : ?>
					<option value="plate-<?php echo esc_attr( (string) $i ); ?>" <?php selected( (string) $repo->meta( $post->ID, 'placeholder' ), 'plate-' . $i ); ?>><?php echo esc_html( 'Plate ' . $i ); ?></option>
				<?php endfor; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Persist the meta box.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save_meta_box( $post_id, $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || 'property' !== $post->post_type ) {
			return;
		}
		if ( ! isset( $_POST['estora_property_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['estora_property_nonce'] ) ), 'estora_property_meta' ) ) {
			return;
		}
		if ( ! current_user_can( Capabilities::EDIT_PROPERTIES ) ) {
			return;
		}

		$repo = new Property_Repository();
		$data = array(
			'price'          => sanitize_text_field( wp_unslash( $_POST['estora_price'] ?? '' ) ),
			'rent_period'    => sanitize_key( wp_unslash( $_POST['estora_rent_period'] ?? 'month' ) ),
			'address'        => sanitize_text_field( wp_unslash( $_POST['estora_address'] ?? '' ) ),
			'province'       => sanitize_text_field( wp_unslash( $_POST['estora_province'] ?? '' ) ),
			'district'       => sanitize_text_field( wp_unslash( $_POST['estora_district'] ?? '' ) ),
			'subdistrict'    => sanitize_text_field( wp_unslash( $_POST['estora_subdistrict'] ?? '' ) ),
			'postal_code'    => sanitize_text_field( wp_unslash( $_POST['estora_postal_code'] ?? '' ) ),
			'latitude'       => sanitize_text_field( wp_unslash( $_POST['estora_latitude'] ?? '' ) ),
			'longitude'      => sanitize_text_field( wp_unslash( $_POST['estora_longitude'] ?? '' ) ),
			'land_area'      => sanitize_text_field( wp_unslash( $_POST['estora_land_area'] ?? '' ) ),
			'usable_area'    => sanitize_text_field( wp_unslash( $_POST['estora_usable_area'] ?? '' ) ),
			'bedrooms'       => sanitize_text_field( wp_unslash( $_POST['estora_bedrooms'] ?? '' ) ),
			'bathrooms'      => sanitize_text_field( wp_unslash( $_POST['estora_bathrooms'] ?? '' ) ),
			'parking'        => sanitize_text_field( wp_unslash( $_POST['estora_parking'] ?? '' ) ),
			'floors'         => sanitize_text_field( wp_unslash( $_POST['estora_floors'] ?? '' ) ),
			'year_built'     => sanitize_text_field( wp_unslash( $_POST['estora_year_built'] ?? '' ) ),
			'agent_id'       => absint( $_POST['estora_agent_id'] ?? 0 ),
			'availability'   => sanitize_key( wp_unslash( $_POST['estora_availability'] ?? Property_Status::AVAILABLE ) ),
			'featured'       => isset( $_POST['estora_featured'] ) ? 1 : 0,
			'video_url'      => esc_url_raw( wp_unslash( $_POST['estora_video_url'] ?? '' ) ),
			'seo_title'      => sanitize_text_field( wp_unslash( $_POST['estora_seo_title'] ?? '' ) ),
			'seo_description' => sanitize_textarea_field( wp_unslash( $_POST['estora_seo_description'] ?? '' ) ),
			'placeholder'    => sanitize_key( wp_unslash( $_POST['estora_placeholder'] ?? 'plate-1' ) ),
		);

		$amenities         = sanitize_text_field( wp_unslash( $_POST['estora_amenities'] ?? '' ) );
		$data['amenities'] = array_values( array_filter( array_map( 'trim', explode( ',', $amenities ) ) ) );

		$gallery         = sanitize_text_field( wp_unslash( $_POST['estora_gallery'] ?? '' ) );
		$ids             = array_values( array_filter( array_map( 'absint', explode( ',', $gallery ) ) ) );
		$ids             = array_values(
			array_filter(
				$ids,
				static function ( $id ) {
					$mime = get_post_mime_type( $id );
					return $mime && 0 === strpos( (string) $mime, 'image/' );
				}
			)
		);
		$data['gallery'] = $ids;

		$purpose = sanitize_key( wp_unslash( $_POST['estora_purpose'] ?? '' ) );
		if ( $purpose ) {
			wp_set_object_terms( $post_id, array( $purpose ), 'property_purpose' );
		}
		$type = sanitize_title( wp_unslash( $_POST['estora_type'] ?? '' ) );
		if ( $type ) {
			wp_set_object_terms( $post_id, array( $type ), 'property_type' );
		}
		$location = sanitize_title( wp_unslash( $_POST['estora_location'] ?? '' ) );
		if ( $location ) {
			wp_set_object_terms( $post_id, array( $location ), 'property_location' );
		}

		try {
			$repo->update_meta_fields( $post_id, $data, $purpose );
			update_post_meta( $post_id, '_estora_archived_at', 'archived' === $post->post_status ? estora_now() : '' );

			$activity = new Activity_Repository();
			$activity->log( 'property.updated', 'property', $post_id, 'Property details saved from the editor.' );

			if ( 'publish' === $post->post_status ) {
				$service = new \Estora\Core\Services\Property_Service();
				$missing = $service->publish_requirements( $post_id );
				if ( ! empty( $missing ) && ! self::$reverting ) {
					self::$reverting = true;
					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_status' => 'draft',
						)
					);
					set_transient(
						'estora_property_notice_' . get_current_user_id(),
						sprintf(
							/* translators: %s: missing fields */
							__( 'The property was returned to draft because these fields are required before publishing: %s.', 'estora-core' ),
							implode( ', ', $missing )
						),
						60
					);
				}
			}
		} catch ( Validation_Exception $e ) {
			set_transient(
				'estora_property_notice_' . get_current_user_id(),
				$e->getMessage() . ' ' . implode( ' ', array_merge( ...array_values( $e->get_errors() ) ) ),
				60
			);
		}
	}

	/**
	 * Admin notices produced by the save guard.
	 */
	public function notices(): void {
		$key = 'estora_property_notice_' . get_current_user_id();
		$msg = get_transient( $key );
		if ( $msg ) {
			delete_transient( $key );
			printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( (string) $msg ) );
		}
	}

	/**
	 * Enqueue admin assets on property screens.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue( $hook ): void {
		$screen = get_current_screen();
		if ( ! $screen || 'property' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'estora-admin', ESTORA_CORE_URL . 'assets/css/admin.css', array(), ESTORA_CORE_VERSION );
		wp_enqueue_script( 'estora-admin-property', ESTORA_CORE_URL . 'assets/js/property-editor.js', array( 'jquery' ), ESTORA_CORE_VERSION, true );
	}

	/**
	 * Listing columns.
	 *
	 * @param array<string,string> $columns Columns.
	 * @return array<string,string>
	 */
	public function columns( $columns ): array {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['estora_thumb'] = __( 'Photo', 'estora-core' );
				$new['estora_ref']   = __( 'Ref', 'estora-core' );
				$new['estora_price'] = __( 'Price', 'estora-core' );
				$new['estora_avail'] = __( 'Availability', 'estora-core' );
				$new['estora_agent'] = __( 'Agent', 'estora-core' );
			}
		}
		return $new;
	}

	/**
	 * Column values.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function column_content( $column, $post_id ): void {
		$repo = new Property_Repository();
		switch ( $column ) {
			case 'estora_thumb':
				$image = estora_property_image( (int) $post_id, 'thumbnail' );
				printf( '<img src="%s" alt="" width="64" height="40" style="object-fit:cover;border-radius:2px">', esc_url( $image['url'] ) );
				break;
			case 'estora_ref':
				echo esc_html( (string) ( $repo->meta( (int) $post_id, 'reference' ) ?: Formatter::reference( (int) $post_id ) ) );
				break;
			case 'estora_price':
				echo esc_html( estora_property_price_label( (int) $post_id ) );
				break;
			case 'estora_avail':
				$value = estora_property_availability( (int) $post_id );
				printf( '<span class="estora-pill estora-pill--%s">%s</span>', esc_attr( $value ), esc_html( Property_Status::availability_label( $value ) ) );
				break;
			case 'estora_agent':
				$agent_id = (int) $repo->meta( (int) $post_id, 'agent_id' );
				echo esc_html( $agent_id ? estora_agent_name( $agent_id ) : '—' );
				break;
		}
	}

	/**
	 * Title placeholder.
	 *
	 * @param string   $text Placeholder.
	 * @param \WP_Post $post Post.
	 */
	public function title_placeholder( $text, $post ) {
		return 'property' === $post->post_type ? __( 'Listing title, e.g. Courtyard House, Nimman', 'estora-core' ) : $text;
	}
}
