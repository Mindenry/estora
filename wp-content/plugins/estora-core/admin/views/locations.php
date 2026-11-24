<?php
/**
 * Locations module.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride -- template locals, not globals.

global $wpdb;
$rows  = $wpdb->get_results( 'SELECT * FROM `' . esc_sql( estora_table( 'locations' ) ) . '` ORDER BY type ASC, name ASC', ARRAY_A );
$by_id = array();
foreach ( (array) $rows as $row ) {
	$by_id[ (int) $row['id'] ] = $row;
}
$provinces = 0;
$districts = 0;
foreach ( (array) $rows as $row ) {
	if ( 'province' === $row['type'] ) {
		++$provinces;
	} else {
		++$districts;
	}
}

$estora_eyebrow     = __( 'Inventory', 'estora-core' );
$estora_title       = __( 'Locations', 'estora-core' );
$estora_description = __( 'Province and district records mirrored from the property location taxonomy for imports and filters.', 'estora-core' );
$estora_actions     = current_user_can( 'manage_categories' ) ? '<a class="estora-btn estora-btn--ghost" href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=property_location&post_type=property' ) ) . '">' . esc_html__( 'Edit taxonomy', 'estora-core' ) . '</a>' : '';
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="locations" aria-busy="false">
		<div class="estora-kpis estora-kpis--compact">
			<div class="estora-kpi"><span class="estora-kpi__label"><?php esc_html_e( 'Provinces', 'estora-core' ); ?></span><span class="estora-kpi__value"><?php echo esc_html( (string) $provinces ); ?></span></div>
			<div class="estora-kpi"><span class="estora-kpi__label"><?php esc_html_e( 'Districts', 'estora-core' ); ?></span><span class="estora-kpi__value"><?php echo esc_html( (string) $districts ); ?></span></div>
			<div class="estora-kpi"><span class="estora-kpi__label"><?php esc_html_e( 'Records', 'estora-core' ); ?></span><span class="estora-kpi__value"><?php echo esc_html( (string) count( $rows ) ); ?></span></div>
		</div>
		<section class="estora-panel">
			<div class="estora-panel__head"><h2><?php esc_html_e( 'Location catalogue', 'estora-core' ); ?></h2></div>
			<?php if ( $rows ) : ?>
				<div class="estora-tablewrap">
					<table class="estora-table">
						<thead><tr><th scope="col"><?php esc_html_e( 'Name', 'estora-core' ); ?></th><th scope="col"><?php esc_html_e( 'Type', 'estora-core' ); ?></th><th scope="col"><?php esc_html_e( 'Parent', 'estora-core' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Listings', 'estora-core' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<?php
								$term   = get_term_by( 'slug', (string) $row['slug'], 'property_location' );
								$count  = $term instanceof WP_Term ? (int) $term->count : 0;
								$parent = ! empty( $row['parent_id'] ) && isset( $by_id[ (int) $row['parent_id'] ] ) ? $by_id[ (int) $row['parent_id'] ]['name'] : '—';
								?>
								<tr>
									<td class="strong"><?php echo esc_html( (string) $row['name'] ); ?></td>
									<td><span class="estora-badge badge-neutral"><?php echo esc_html( ucfirst( (string) $row['type'] ) ); ?></span></td>
									<td><?php echo esc_html( $parent ); ?></td>
									<td class="num"><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<div class="estora-emptyadmin"><strong><?php esc_html_e( 'No locations', 'estora-core' ); ?></strong><?php esc_html_e( 'Run the demo seed or add property location terms.', 'estora-core' ); ?></div>
			<?php endif; ?>
		</section>
	</main>
</div>
