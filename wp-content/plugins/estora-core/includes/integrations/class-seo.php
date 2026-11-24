<?php
/**
 * SEO output: titles, descriptions, canonical, Open Graph, JSON-LD, robots.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Integrations;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Repositories\Property_Repository;

final class SEO {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		// Replaced by a single canonical tag in head_meta().
		remove_action( 'wp_head', 'rel_canonical' );
		add_filter( 'document_title_parts', array( $this, 'title_parts' ) );
		add_action( 'wp_head', array( $this, 'head_meta' ), 2 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'wp_sitemaps_post_types', array( $this, 'sitemap_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'sitemap_taxonomies' ) );
	}

	/**
	 * Property-aware title.
	 *
	 * @param array<string,string> $parts Title parts.
	 * @return array<string,string>
	 */
	public function title_parts( $parts ): array {
		if ( is_singular( 'property' ) ) {
			$post_id = get_queried_object_id();
			$repo    = new Property_Repository();
			$seo     = (string) $repo->meta( (int) $post_id, 'seo_title' );
			if ( $seo ) {
				$parts['title'] = $seo;
			} else {
				$parts['title'] = get_the_title( $post_id ) . ' — ' . estora_price( estora_property_price( (int) $post_id ) );
			}
		}
		return $parts;
	}

	/**
	 * Meta tags.
	 */
	public function head_meta(): void {
		$title       = wp_get_document_title();
		$description = get_bloginfo( 'description' );
		$canonical   = $this->canonical_url();
		$image       = '';

		if ( is_singular( 'property' ) ) {
			$post_id     = (int) get_queried_object_id();
			$repo        = new Property_Repository();
			$description = (string) $repo->meta( $post_id, 'seo_description' );
			if ( ! $description ) {
				$description = wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', $post_id ) ), 30 );
			}
			$image = estora_property_image( $post_id, 'estora-large' )['url'];
		} elseif ( is_singular() ) {
			$description = wp_trim_words( wp_strip_all_tags( (string) get_post_field( 'post_content', get_queried_object_id() ) ), 30 ) ?: $description;
			$thumb_id    = (int) get_post_thumbnail_id( get_queried_object_id() );
			if ( $thumb_id ) {
				$src   = wp_get_attachment_image_src( $thumb_id, 'large' );
				$image = $src ? $src[0] : '';
			}
		} elseif ( is_post_type_archive( 'property' ) ) {
			$description = __( 'Browse homes, townhouses, condominiums and land listed by ESTORA.', 'estora-core' );
		}

		$description = mb_substr( sanitize_text_field( $description ), 0, 300 );

		echo "\n<!-- ESTORA SEO -->\n";
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $description ) );
		if ( $canonical ) {
			printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $canonical ) );
		}
		printf( '<meta property="og:site_name" content="%s">' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
		printf( '<meta property="og:type" content="%s">' . "\n", is_singular( 'property' ) ? 'website' : 'article' );
		printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $title ) );
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $description ) );
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( $canonical ?: home_url( add_query_arg( array() ) ) ) );
		if ( $image ) {
			printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $image ) );
		}
		printf( '<meta name="twitter:card" content="%s">' . "\n", $image ? 'summary_large_image' : 'summary' );
		printf( '<meta name="twitter:title" content="%s">' . "\n", esc_attr( $title ) );
		printf( '<meta name="twitter:description" content="%s">' . "\n", esc_attr( $description ) );

		if ( is_singular( 'property' ) ) {
			$this->json_ld( (int) get_queried_object_id() );
		}
	}

	/**
	 * Canonical URL without filter parameters.
	 */
	private function canonical_url(): string {
		if ( is_singular() ) {
			$url = get_permalink( get_queried_object_id() );
			return $url ? $url : '';
		}
		if ( is_post_type_archive( 'property' ) ) {
			return get_post_type_archive_link( 'property' ) ?: '';
		}
		if ( is_home() || is_front_page() ) {
			return home_url( '/' );
		}
		if ( is_archive() ) {
			$link = get_term_link( get_queried_object() );
			return is_wp_error( $link ) ? '' : $link;
		}
		return '';
	}

	/**
	 * RealEstateListing structured data from real fields only.
	 *
	 * @param int $post_id Property.
	 */
	private function json_ld( int $post_id ): void {
		$repo = new Property_Repository();
		$data = $repo->to_array( $post_id );
		if ( ! $data ) {
			return;
		}

		$availability = 'https://schema.org/InStock';
		if ( 'sold' === $data['availability'] ) {
			$availability = 'https://schema.org/SoldOut';
		} elseif ( 'rented' === $data['availability'] ) {
			$availability = 'https://schema.org/SoldOut';
		} elseif ( 'reserved' === $data['availability'] ) {
			$availability = 'https://schema.org/LimitedAvailability';
		} elseif ( 'unavailable' === $data['availability'] ) {
			$availability = 'https://schema.org/OutOfStock';
		}

		$address = array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $data['address'],
			'addressLocality' => $data['location']['district'] ?: $data['location']['province'],
			'addressRegion'   => $data['location']['province'],
			'postalCode'      => $data['location']['postal_code'],
			'addressCountry'  => 'TH',
		);

		$schema = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'RealEstateListing',
			'@id'           => $data['url'],
			'url'           => $data['url'],
			'name'          => $data['title'],
			'description'   => wp_strip_all_tags( $data['excerpt'] ),
			'datePosted'    => $data['created_at'],
			'image'         => $data['image']['url'],
			'numberOfRooms' => $data['bedrooms'],
			'floorSize'     => array(
				'@type'    => 'QuantitativeValue',
				'value'    => $data['usable_area'],
				'unitCode' => 'MTK',
			),
			'address'       => $address,
			'offers'        => array(
				'@type'         => 'Offer',
				'price'         => $data['price'],
				'priceCurrency' => 'THB',
				'availability'  => $availability,
				'url'           => $data['url'],
			),
		);

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP )
		);
	}

	/**
	 * Noindex filtered archives and search to avoid duplicate metadata.
	 *
	 * @param array<string,mixed> $robots Robots directives.
	 * @return array<string,mixed>
	 */
	public function robots( $robots ): array {
		if ( is_search() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		if ( is_post_type_archive( 'property' ) && ! empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * Include the property CPT in core sitemaps.
	 *
	 * @param array<string,\WP_Post_Type> $types Types.
	 * @return array<string,\WP_Post_Type>
	 */
	public function sitemap_types( $types ): array {
		$property = get_post_type_object( 'property' );
		if ( $property ) {
			$types['property'] = $property;
		}
		return $types;
	}

	/**
	 * Include property taxonomies in sitemaps.
	 *
	 * @param array<string,\WP_Taxonomy> $taxonomies Taxonomies.
	 * @return array<string,\WP_Taxonomy>
	 */
	public function sitemap_taxonomies( $taxonomies ): array {
		foreach ( array( 'property_type', 'property_location' ) as $name ) {
			$taxonomy = get_taxonomy( $name );
			if ( $taxonomy ) {
				$taxonomies[ $name ] = $taxonomy;
			}
		}
		return $taxonomies;
	}
}
