<?php
/**
 * Property queries and persistence (CPT + meta).
 *
 * @package Estora\Core
 */

namespace Estora\Core\Repositories;

use Estora\Core\Domain\Property_Status;
use Estora\Core\Support\Formatter;
use Estora\Core\Support\Validation_Exception;
use Estora\Core\Support\Validator;

defined( 'ABSPATH' ) || exit;

final class Property_Repository {

	public const POST_TYPE = 'property';

	/**
	 * Meta keys with their sanitiser.
	 *
	 * @return array<string,string>
	 */
	public static function meta_map(): array {
		return array(
			'price_sale'         => 'float',
			'price_rent'         => 'float',
			'rent_period'        => 'key',
			'address'            => 'text',
			'province'           => 'text',
			'district'           => 'text',
			'subdistrict'        => 'text',
			'postal_code'        => 'text',
			'latitude'           => 'float',
			'longitude'          => 'float',
			'land_area'          => 'float',
			'usable_area'        => 'float',
			'bedrooms'           => 'int',
			'bathrooms'          => 'int',
			'parking'            => 'int',
			'floors'             => 'int',
			'year_built'         => 'int',
			'amenities'          => 'array',
			'gallery'            => 'array',
			'agent_id'           => 'int',
			'availability'       => 'key',
			'featured'           => 'bool',
			'seo_title'          => 'text',
			'seo_description'    => 'textarea',
			'video_url'          => 'url',
			'placeholder'        => 'key',
			'price_display'      => 'float',
			'price_sort'         => 'text',
			'area_sort'          => 'text',
			'archived_at'        => 'text',
		);
	}

	/**
	 * Sanitise a single meta value.
	 *
	 * @param mixed  $value Value.
	 * @param string $type  Type.
	 * @return mixed
	 */
	private function sanitize_meta( $value, string $type ) {
		switch ( $type ) {
			case 'float':
				return is_numeric( $value ) ? (float) $value : 0;
			case 'int':
				return (int) $value;
			case 'bool':
				return Validator::to_bool( $value ) ? 1 : 0;
			case 'array':
				if ( ! is_array( $value ) ) {
					return array();
				}
				return array_values( array_filter( array_map( 'absint', $value ) ) );
			case 'url':
				return $value ? esc_url_raw( (string) $value ) : '';
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'key':
				return sanitize_key( (string) $value );
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Public property query.
	 *
	 * @param array<string,mixed> $args Arguments.
	 * @return array{items:array<int,array<string,mixed>>,total:int,pages:int,page:int,per_page:int}
	 */
	public function public_query( array $args = array() ): array {
		$args = wp_parse_args(
			$args,
			array(
				'purpose'      => '',
				'type'         => '',
				'location'     => '',
				'province'     => '',
				'district'     => '',
				'q'            => '',
				'min_price'    => '',
				'max_price'    => '',
				'bedrooms'     => '',
				'bathrooms'    => '',
				'min_area'     => '',
				'max_area'     => '',
				'availability' => '',
				'featured'     => '',
				'agent_id'     => 0,
				'status'       => 'publish',
				'sort'         => 'newest',
				'page'         => 1,
				'per_page'     => (int) estora_setting( 'listing_per_page', 9 ),
				'exclude'      => array(),
			)
		);

		$query_args = array(
			'post_type'           => self::POST_TYPE,
			'post_status'         => 'publish' === $args['status'] ? 'publish' : (array) $args['status'],
			'posts_per_page'      => max( 1, min( 48, (int) $args['per_page'] ) ),
			'paged'               => max( 1, (int) $args['page'] ),
			'ignore_sticky_posts' => true,
			'post__not_in'        => array_map( 'absint', (array) $args['exclude'] ),
		);

		if ( $args['q'] ) {
			$query_args['s'] = sanitize_text_field( (string) $args['q'] );
		}

		$tax_query = array();
		if ( $args['purpose'] ) {
			$tax_query[] = array(
				'taxonomy' => 'property_purpose',
				'field'    => 'slug',
				'terms'    => sanitize_title( (string) $args['purpose'] ),
			);
		}
		if ( $args['type'] ) {
			$tax_query[] = array(
				'taxonomy' => 'property_type',
				'field'    => 'slug',
				'terms'    => sanitize_title( (string) $args['type'] ),
			);
		}
		if ( $args['location'] ) {
			$tax_query[] = array(
				'taxonomy'         => 'property_location',
				'field'            => 'slug',
				'terms'            => sanitize_title( (string) $args['location'] ),
				'include_children' => true,
			);
		}
		if ( ! empty( $tax_query ) ) {
			$tax_query['relation']   = 'AND';
			$query_args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		$meta_query = array( 'relation' => 'AND' );

		if ( $args['availability'] ) {
			$meta_query[] = array(
				'key'   => '_estora_availability',
				'value' => sanitize_key( (string) $args['availability'] ),
			);
		}
		if ( '' !== $args['min_price'] && is_numeric( $args['min_price'] ) ) {
			$meta_query[] = array(
				'key'     => '_estora_price_display',
				'value'   => (float) $args['min_price'],
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}
		if ( '' !== $args['max_price'] && is_numeric( $args['max_price'] ) ) {
			$meta_query[] = array(
				'key'     => '_estora_price_display',
				'value'   => (float) $args['max_price'],
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}
		if ( '' !== $args['bedrooms'] && is_numeric( $args['bedrooms'] ) ) {
			$meta_query[] = array(
				'key'     => '_estora_bedrooms',
				'value'   => (int) $args['bedrooms'],
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}
		if ( '' !== $args['bathrooms'] && is_numeric( $args['bathrooms'] ) ) {
			$meta_query[] = array(
				'key'     => '_estora_bathrooms',
				'value'   => (int) $args['bathrooms'],
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}
		if ( '' !== $args['min_area'] && is_numeric( $args['min_area'] ) ) {
			$meta_query[] = array(
				'key'     => '_estora_usable_area',
				'value'   => (float) $args['min_area'],
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}
		if ( '' !== $args['max_area'] && is_numeric( $args['max_area'] ) ) {
			$meta_query[] = array(
				'key'     => '_estora_usable_area',
				'value'   => (float) $args['max_area'],
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}
		if ( $args['province'] ) {
			$meta_query[] = array(
				'key'   => '_estora_province',
				'value' => sanitize_text_field( (string) $args['province'] ),
			);
		}
		if ( $args['district'] ) {
			$meta_query[] = array(
				'key'   => '_estora_district',
				'value' => sanitize_text_field( (string) $args['district'] ),
			);
		}
		if ( '1' === (string) $args['featured'] ) {
			$meta_query[] = array(
				'key'   => '_estora_featured',
				'value' => '1',
			);
		}
		if ( ! empty( $args['agent_id'] ) ) {
			$meta_query[] = array(
				'key'   => '_estora_agent_id',
				'value' => (int) $args['agent_id'],
				'type'  => 'NUMERIC',
			);
		}
		if ( count( $meta_query ) > 1 ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$sort = (string) $args['sort'];
		switch ( $sort ) {
			case 'oldest':
				$query_args['orderby'] = 'date';
				$query_args['order']   = 'ASC';
				break;
			case 'price_asc':
				$query_args['meta_key'] = '_estora_price_display'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = 'meta_value_num';
				$query_args['order']    = 'ASC';
				break;
			case 'price_desc':
				$query_args['meta_key'] = '_estora_price_display'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = 'meta_value_num';
				$query_args['order']    = 'DESC';
				break;
			case 'area_asc':
				$query_args['meta_key'] = '_estora_usable_area'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = 'meta_value_num';
				$query_args['order']    = 'ASC';
				break;
			case 'area_desc':
				$query_args['meta_key'] = '_estora_usable_area'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = 'meta_value_num';
				$query_args['order']    = 'DESC';
				break;
			case 'newest':
			default:
				$query_args['orderby'] = 'date';
				$query_args['order']   = 'DESC';
				break;
		}

		/**
		 * Filter the property query arguments.
		 *
		 * @param array $query_args WP_Query args.
		 * @param array $args       Normalised filters.
		 */
		$query_args = apply_filters( 'estora/property/query_args', $query_args, $args );

		$query = new \WP_Query( $query_args );
		$items = array();
		foreach ( $query->posts as $post ) {
			$data = $this->to_array( $post->ID );
			if ( $data ) {
				$items[] = $data;
			}
		}

		$per_page = (int) $query_args['posts_per_page'];

		return array(
			'items'    => $items,
			'total'    => (int) $query->found_posts,
			'pages'    => (int) $query->max_num_pages,
			'page'     => (int) $query_args['paged'],
			'per_page' => $per_page,
		);
	}

	/**
	 * Full property shape.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>|null
	 */
	public function to_array( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$purpose_terms = wp_get_post_terms( $post_id, 'property_purpose' );
		$type_terms    = wp_get_post_terms( $post_id, 'property_type' );
		$purpose       = ( ! is_wp_error( $purpose_terms ) && $purpose_terms ) ? $purpose_terms[0]->slug : '';
		$type          = ( ! is_wp_error( $type_terms ) && $type_terms ) ? $type_terms[0]->slug : '';
		$price         = (float) $this->meta( $post_id, 'price_display' );
		$availability  = (string) ( $this->meta( $post_id, 'availability' ) ?: Property_Status::AVAILABLE );
		$agent_id      = (int) $this->meta( $post_id, 'agent_id' );
		$image         = estora_property_image( $post_id, 'estora-large' );
		$gallery       = array();
		foreach ( estora_property_gallery_ids( $post_id ) as $attachment_id ) {
			$src = wp_get_attachment_image_src( $attachment_id, 'estora-large' );
			if ( ! $src ) {
				continue;
			}
			$gallery[] = array(
				'id'     => $attachment_id,
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
				'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			);
		}

		$lat = (float) $this->meta( $post_id, 'latitude' );
		$lng = (float) $this->meta( $post_id, 'longitude' );
		$map = ( $lat || $lng ) ? array(
			'lat' => $lat,
			'lng' => $lng,
			'url' => sprintf( 'https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=15/%s/%s', $lat, $lng, $lat, $lng ),
		) : null;

		$reference = (string) ( $this->meta( $post_id, 'reference' ) ?: Formatter::reference( $post_id ) );
		$title     = estora_is_thai() ? estora_localized_meta( $post_id, 'title_th', get_the_title( $post_id ) ) : get_the_title( $post_id );
		$content   = estora_is_thai() ? estora_localized_meta( $post_id, 'content_th', $post->post_content ) : $post->post_content;
		$address   = estora_is_thai() ? estora_localized_meta( $post_id, 'address_th', (string) $this->meta( $post_id, 'address' ) ) : (string) $this->meta( $post_id, 'address' );
		$province  = estora_place_name( (string) $this->meta( $post_id, 'province' ) );
		$district  = estora_place_name( (string) $this->meta( $post_id, 'district' ) );
		$subdistrict = estora_place_name( (string) $this->meta( $post_id, 'subdistrict' ) );
		$amenities = array_map(
			static function ( $amenity ) {
				return estora_amenity_name( (string) $amenity );
			},
			(array) $this->meta( $post_id, 'amenities', array() )
		);

		return array(
			'id'               => $post_id,
			'title'            => $title,
			'slug'             => $post->post_name,
			'url'              => get_permalink( $post_id ),
			'reference'        => $reference,
			'status'           => $post->post_status,
			'status_label'     => Property_Status::publication_label( $post->post_status ),
			'purpose'          => $purpose,
			'purpose_label'    => estora_purpose_name( $purpose ),
			'type'             => $type,
			'type_label'       => estora_type_name( $type, ( ! is_wp_error( $type_terms ) && $type_terms ) ? $type_terms[0]->name : '' ),
			'price'            => $price,
			'price_formatted'  => estora_price( $price ),
			'rent_period'      => (string) $this->meta( $post_id, 'rent_period', 'month' ),
			'availability'     => $availability,
			'availability_label' => estora_availability_name( $availability ),
			'accepts_inquiries' => Property_Status::accepts_inquiries( $availability ),
			'description'      => $content,
			'excerpt'          => wp_trim_words( wp_strip_all_tags( $content ), 28 ),
			'address'          => $address,
			'location'         => array(
				'province'    => $province,
				'district'    => $district,
				'subdistrict' => $subdistrict,
				'postal_code' => (string) $this->meta( $post_id, 'postal_code' ),
			),
			'land_area'        => (float) $this->meta( $post_id, 'land_area' ),
			'usable_area'      => (float) $this->meta( $post_id, 'usable_area' ),
			'bedrooms'         => (int) $this->meta( $post_id, 'bedrooms' ),
			'bathrooms'        => (int) $this->meta( $post_id, 'bathrooms' ),
			'parking'          => (int) $this->meta( $post_id, 'parking' ),
			'floors'           => (int) $this->meta( $post_id, 'floors' ),
			'year_built'       => (int) $this->meta( $post_id, 'year_built' ),
			'amenities'        => $amenities,
			'featured'         => (bool) $this->meta( $post_id, 'featured' ),
			'image'            => $image,
			'gallery'          => $gallery,
			'agent'            => $agent_id ? estora_agent_profile( $agent_id ) : null,
			'map'              => $map,
			'video_url'        => (string) $this->meta( $post_id, 'video_url' ),
			'seo'              => array(
				'title'       => estora_is_thai() ? estora_localized_meta( $post_id, 'seo_title_th', $title ) : ( (string) $this->meta( $post_id, 'seo_title' ) ?: $title ),
				'description' => estora_is_thai() ? estora_localized_meta( $post_id, 'seo_description_th', wp_trim_words( wp_strip_all_tags( $content ), 30 ) ) : ( (string) $this->meta( $post_id, 'seo_description' ) ?: wp_trim_words( wp_strip_all_tags( $content ), 30 ) ),
			),
			'created_at'       => get_post_time( 'c', true, $post_id ),
			'updated_at'       => get_post_modified_time( 'c', true, $post_id ),
		);
	}

	/**
	 * Meta read with prefix.
	 *
	 * @param int    $post_id Post.
	 * @param string $key     Key without prefix.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public function meta( int $post_id, string $key, $default = null ) {
		$value = get_post_meta( $post_id, '_estora_' . $key, true );
		return ( '' === $value || null === $value ) ? $default : $value;
	}

	/**
	 * Create or update a property.
	 *
	 * @param array<string,mixed> $data    Payload.
	 * @param int                 $post_id Existing post ID (0 = create).
	 * @return int
	 * @throws Validation_Exception When invalid.
	 */
	public function save( array $data, int $post_id = 0 ): int {
		$existing = $post_id ? get_post( $post_id ) : null;
		if ( $post_id && ( ! $existing || self::POST_TYPE !== $existing->post_type ) ) {
			throw new Validation_Exception( 'Property not found.', array(), 404 );
		}

		$purpose = isset( $data['purpose'] ) ? sanitize_key( (string) $data['purpose'] ) : ( $existing ? $this->term_slug( $post_id, 'property_purpose' ) : '' );
		$rules   = array(
			'title'       => array( 'required', 'max_length:190' ),
			'purpose'     => array( 'required', 'in:sale,rent' ),
			'type'        => array( 'required', 'max_length:60' ),
			'price'       => array( 'required', 'numeric', 'min:1' ),
			'land_area'   => array( 'numeric', 'min:0' ),
			'usable_area' => array( 'numeric', 'min:0' ),
			'bedrooms'    => array( 'integer', 'min:0', 'max:50' ),
			'bathrooms'   => array( 'integer', 'min:0', 'max:50' ),
			'parking'     => array( 'integer', 'min:0', 'max:200' ),
			'floors'      => array( 'integer', 'min:0', 'max:200' ),
			'year_built'  => array( 'integer', 'min:0', 'max:' . ( (int) current_time( 'Y' ) + 5 ) ),
			'latitude'    => array( 'numeric', 'min:-90', 'max:90' ),
			'longitude'   => array( 'numeric', 'min:-180', 'max:180' ),
			'status'      => array( 'in:draft,pending,published,archived' ),
			'availability' => array( 'in:' . implode( ',', Property_Status::availability_values() ) ),
			'seo_title'   => array( 'max_length:190' ),
			'video_url'   => array( 'url' ),
			'gallery'     => array( 'array', 'max_items:20' ),
			'amenities'   => array( 'array', 'max_items:40' ),
		);

		$check = array_merge( $data, array( 'purpose' => $purpose ) );
		if ( 'rent' === $purpose && empty( $data['rent_period'] ) ) {
			$check['rent_period'] = '';
			$rules['rent_period'] = array( 'required', 'in:month,year' );
		}

		Validator::assert( $check, $rules, 'The property could not be saved.' );

		if ( ! empty( $data['rent_period'] ) && ! in_array( $data['rent_period'], array( 'month', 'year' ), true ) ) {
			throw new Validation_Exception( 'Validation failed.', array( 'rent_period' => array( 'Rent period must be month or year.' ) ) );
		}

		$status = isset( $data['status'] ) ? (string) $data['status'] : 'draft';
		if ( ! isset( $data['status'] ) && $existing ) {
			$reverse = array(
				'publish'  => 'published',
				'draft'    => 'draft',
				'pending'  => 'pending',
				'archived' => 'archived',
			);
			$status  = $reverse[ $existing->post_status ] ?? 'draft';
		}
		$map         = array(
			'draft'     => 'draft',
			'pending'   => 'pending',
			'published' => 'publish',
			'archived'  => 'archived',
		);
		$post_status = $map[ $status ] ?? 'draft';

		$postarr = array(
			'post_type'    => self::POST_TYPE,
			'post_title'   => sanitize_text_field( (string) $data['title'] ),
			'post_content' => wp_kses_post( (string) ( $data['description'] ?? '' ) ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $data['excerpt'] ?? '' ) ),
			'post_status'  => $post_status,
		);
		if ( $post_id ) {
			$postarr['ID'] = $post_id;
		} elseif ( ! empty( $data['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( (string) $data['slug'] );
		}

		$result = wp_insert_post( $postarr, true );
		if ( is_wp_error( $result ) ) {
			throw new Validation_Exception( $result->get_error_message(), array( 'title' => array( $result->get_error_message() ) ) );
		}
		$post_id = (int) $result;

		$terms = array();
		if ( $purpose ) {
			$terms['property_purpose'] = array( $purpose );
		}
		if ( ! empty( $data['type'] ) ) {
			$terms['property_type'] = array( sanitize_title( (string) $data['type'] ) );
		}
		if ( ! empty( $data['location'] ) ) {
			$terms['property_location'] = array( sanitize_title( (string) $data['location'] ) );
		}
		foreach ( $terms as $taxonomy => $slugs ) {
			$set = wp_set_object_terms( $post_id, $slugs, $taxonomy, false );
			if ( is_wp_error( $set ) ) {
				throw new Validation_Exception( 'Invalid ' . $taxonomy . ' value.', array( 'type' => array( 'Choose an existing property type.' ) ) );
			}
		}

		$this->update_meta_fields( $post_id, $data, $purpose );
		update_post_meta( $post_id, '_estora_archived_at', 'archived' === $status ? estora_now() : '' );

		return $post_id;
	}

	/**
	 * Sanitise and persist meta fields plus derived values.
	 *
	 * @param int                 $post_id Property.
	 * @param array<string,mixed> $data    Raw fields.
	 * @param string              $purpose Purpose slug (resolved when empty).
	 */
	public function update_meta_fields( int $post_id, array $data, string $purpose = '' ): void {
		if ( ! $purpose ) {
			$purpose = $this->term_slug( $post_id, 'property_purpose' );
		}

		$meta_input = array();
		foreach ( self::meta_map() as $key => $type ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			if ( 'amenities' === $key ) {
				$meta_input[ $key ] = array_values(
					array_filter(
						array_map( 'sanitize_text_field', (array) $data[ $key ] ),
						static fn( $value ) => '' !== trim( (string) $value )
					)
				);
				continue;
			}
			$meta_input[ $key ] = $this->sanitize_meta( $data[ $key ], $type );
		}

		if ( array_key_exists( 'description', $data ) && ! array_key_exists( 'seo_description', $data ) ) {
			$meta_input['seo_description'] = wp_trim_words( wp_strip_all_tags( (string) $data['description'] ), 30 );
		}

		if ( array_key_exists( 'price', $data ) ) {
			$price                       = (float) $data['price'];
			$meta_input['price_display'] = $price;
			$meta_input['price_sort']    = number_format( $price, 2, '.', '' );
			if ( 'rent' === $purpose ) {
				$meta_input['price_sale'] = 0;
				$meta_input['price_rent'] = $price;
			} else {
				$meta_input['price_sale'] = $price;
				$meta_input['price_rent'] = 0;
			}
		}

		if ( array_key_exists( 'usable_area', $data ) ) {
			$meta_input['area_sort'] = number_format( (float) $data['usable_area'], 2, '.', '' );
		}

		if ( array_key_exists( 'availability', $data ) && '' !== (string) $data['availability'] ) {
			$current_availability = (string) $this->meta( $post_id, 'availability', Property_Status::AVAILABLE );
			if ( $current_availability !== (string) $meta_input['availability'] ) {
				Property_Status::assert_availability( $current_availability, (string) $meta_input['availability'] );
			}
		}

		if ( empty( $meta_input['availability'] ) && ! $this->meta( $post_id, 'availability' ) ) {
			$meta_input['availability'] = Property_Status::AVAILABLE;
		}
		if ( empty( $meta_input['placeholder'] ) ) {
			$meta_input['placeholder'] = 'plate-' . ( ( $post_id % 6 ) + 1 );
		}

		$meta_input['reference'] = (string) ( $this->meta( $post_id, 'reference' ) ?: Formatter::reference( $post_id ) );

		foreach ( $meta_input as $key => $value ) {
			update_post_meta( $post_id, '_estora_' . $key, $value );
		}
	}

	/**
	 * Duplicate a property as a draft.
	 *
	 * @param int $post_id Source.
	 * @return int New ID.
	 * @throws Validation_Exception When the source is missing.
	 */
	public function duplicate( int $post_id ): int {
		$source = get_post( $post_id );
		if ( ! $source || self::POST_TYPE !== $source->post_type ) {
			throw new Validation_Exception( 'Property not found.', array(), 404 );
		}

		$new_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_title'   => $source->post_title . ' (copy)',
				'post_content' => $source->post_content,
				'post_excerpt' => $source->post_excerpt,
				'post_status'  => 'draft',
			),
			true
		);

		if ( is_wp_error( $new_id ) ) {
			throw new Validation_Exception( $new_id->get_error_message() );
		}

		$meta = get_post_meta( $post_id );
		foreach ( $meta as $key => $values ) {
			if ( 0 !== strpos( $key, '_estora_' ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				update_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}
		update_post_meta( $new_id, '_estora_reference', Formatter::reference( (int) $new_id ) );

		foreach ( array( 'property_type', 'property_purpose', 'property_location' ) as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				wp_set_object_terms( (int) $new_id, $terms, $taxonomy );
			}
		}

		return (int) $new_id;
	}

	/**
	 * Change availability with transition enforcement.
	 *
	 * @param int    $post_id      Post.
	 * @param string $availability New value.
	 * @throws Validation_Exception When invalid.
	 */
	public function set_availability( int $post_id, string $availability ): void {
		$current = (string) $this->meta( $post_id, 'availability', Property_Status::AVAILABLE );
		if ( $current !== $availability ) {
			Property_Status::assert_availability( $current, $availability );
		}
		update_post_meta( $post_id, '_estora_availability', $availability );
	}

	/**
	 * Term slug helper.
	 *
	 * @param int    $post_id  Post.
	 * @param string $taxonomy Taxonomy.
	 */
	private function term_slug( int $post_id, string $taxonomy ): string {
		$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
		return ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (string) $terms[0] : '';
	}

	/**
	 * Property counts for dashboard and reports.
	 *
	 * @return array<string,mixed>
	 */
	public function stats(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'trash'", self::POST_TYPE )
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$published = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", self::POST_TYPE )
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$draft = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('draft','pending')", self::POST_TYPE )
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$archived = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'archived'", self::POST_TYPE )
		);

		$availability = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS availability, COUNT(*) AS total
				 FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_estora_availability' AND p.post_type = %s AND p.post_status = 'publish'
				 GROUP BY pm.meta_value",
				self::POST_TYPE
			),
			ARRAY_A
		);
		foreach ( $rows as $row ) {
			$availability[ $row['availability'] ] = (int) $row['total'];
		}

		$purposes = array();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.slug, COUNT(*) AS total
				 FROM {$wpdb->term_relationships} tr
				 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				 INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
				 WHERE tt.taxonomy = 'property_purpose' AND p.post_type = %s AND p.post_status = 'publish'
				 GROUP BY t.slug",
				self::POST_TYPE
			),
			ARRAY_A
		);
		foreach ( $rows as $row ) {
			$purposes[ $row['slug'] ] = (int) $row['total'];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$average = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(pm.meta_value) FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE pm.meta_key = '_estora_price_display' AND p.post_type = %s AND p.post_status = 'publish'",
				self::POST_TYPE
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$stale = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_modified < %s",
				self::POST_TYPE,
				estora_now( '-90 days' )
			)
		);

		return array(
			'total'        => $total,
			'published'    => $published,
			'draft'        => $draft,
			'archived'     => $archived,
			'available'    => $availability[ Property_Status::AVAILABLE ] ?? 0,
			'reserved'     => $availability[ Property_Status::RESERVED ] ?? 0,
			'sold'         => $availability[ Property_Status::SOLD ] ?? 0,
			'rented'       => $availability[ Property_Status::RENTED ] ?? 0,
			'unavailable'  => $availability[ Property_Status::UNAVAILABLE ] ?? 0,
			'by_purpose'   => $purposes,
			'average_price' => $average,
			'stale'        => $stale,
		);
	}

	/**
	 * Filter options for the public archive.
	 *
	 * @return array<string,mixed>
	 */
	public function filter_options(): array {
		$cached = get_transient( 'estora_filter_options' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$types      = array();
		$type_terms = get_terms( array( 'taxonomy' => 'property_type', 'hide_empty' => true ) );
		foreach ( is_array( $type_terms ) ? $type_terms : array() as $term ) {
			if ( $term instanceof \WP_Term ) {
				$types[] = array( 'slug' => $term->slug, 'name' => $term->name );
			}
		}

		$locations      = array();
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT name, slug, parent_id FROM `' . esc_sql( estora_table( 'locations' ) ) . '` ORDER BY name ASC', ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$locations[] = array(
				'slug'   => (string) $row['slug'],
				'name'   => (string) $row['name'],
				'parent' => (int) $row['parent_id'],
			);
		}
		if ( empty( $locations ) ) {
			$location_terms = get_terms( array( 'taxonomy' => 'property_location', 'hide_empty' => true ) );
			foreach ( is_array( $location_terms ) ? $location_terms : array() as $term ) {
				if ( $term instanceof \WP_Term ) {
					$locations[] = array(
						'slug'   => $term->slug,
						'name'   => $term->name,
						'parent' => $term->parent,
					);
				}
			}
		}

		$options = array( 'types' => $types, 'locations' => $locations );
		set_transient( 'estora_filter_options', $options, HOUR_IN_SECONDS );
		return $options;
	}

	/**
	 * Export property rows for CSV.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function export_rows(): array {
		$query = new \WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'archived' ),
				'posts_per_page' => 500,
				'no_found_rows'  => true,
			)
		);

		$out = array();
		foreach ( $query->posts as $post ) {
			$data  = $this->to_array( $post->ID );
			$out[] = array(
				'reference'    => $data['reference'],
				'title'        => $data['title'],
				'type'         => $data['type'],
				'purpose'      => $data['purpose'],
				'status'       => $data['status'],
				'availability' => $data['availability'],
				'price'        => $data['price'],
				'bedrooms'     => $data['bedrooms'],
				'bathrooms'    => $data['bathrooms'],
				'usable_area'  => $data['usable_area'],
				'province'     => $data['location']['province'],
				'district'     => $data['location']['district'],
				'agent'        => $data['agent']['name'] ?? '',
				'updated'      => $data['updated_at'],
			);
		}
		return $out;
	}
}
