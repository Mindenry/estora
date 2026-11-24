<?php
/**
 * Inventory screen.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;
use Estora\Core\Domain\Property_Status;

$estora_eyebrow     = __( 'Inventory', 'estora-core' );
$estora_title       = __( 'Properties', 'estora-core' );
$estora_description = __( 'Publication and availability are separate states. Edits happen in the property editor.', 'estora-core' );
$estora_actions     = '<a class="estora-btn" href="' . esc_url( admin_url( 'post-new.php?post_type=property' ) ) . '">' . esc_html__( 'Add property', 'estora-core' ) . '</a>';
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="inventory" aria-busy="true">
		<div class="estora-toolbar">
			<div class="estora-search">
				<label class="screen-reader-text" for="estora-inv-search"><?php esc_html_e( 'Search properties', 'estora-core' ); ?></label>
				<input type="search" id="estora-inv-search" placeholder="<?php esc_attr_e( 'Search title', 'estora-core' ); ?>" data-inventory-search>
			</div>
			<label class="estora-inline-field">
				<span><?php esc_html_e( 'Availability', 'estora-core' ); ?></span>
				<select data-inventory-availability>
					<option value=""><?php esc_html_e( 'All', 'estora-core' ); ?></option>
					<?php foreach ( Property_Status::availability_values() as $estora_value ) : ?>
						<option value="<?php echo esc_attr( $estora_value ); ?>"><?php echo esc_html( Property_Status::availability_label( $estora_value ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="estora-inline-field">
				<span><?php esc_html_e( 'Publication', 'estora-core' ); ?></span>
				<select data-inventory-status>
					<option value="any"><?php esc_html_e( 'All', 'estora-core' ); ?></option>
					<option value="published"><?php esc_html_e( 'Published', 'estora-core' ); ?></option>
					<option value="draft"><?php esc_html_e( 'Draft', 'estora-core' ); ?></option>
					<option value="pending"><?php esc_html_e( 'Pending', 'estora-core' ); ?></option>
					<option value="archived"><?php esc_html_e( 'Archived', 'estora-core' ); ?></option>
				</select>
			</label>
		</div>

		<div class="estora-kpis estora-kpis--compact" data-inventory-summary></div>
		<section class="estora-panel" data-inventory-table></section>
		<div class="estora-pagination" data-inventory-pagination></div>
	</main>
</div>
