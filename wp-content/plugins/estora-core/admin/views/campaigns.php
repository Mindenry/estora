<?php
/**
 * Campaigns screen.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;

$estora_eyebrow     = __( 'Marketing', 'estora-core' );
$estora_title       = __( 'Campaigns', 'estora-core' );
$estora_description = __( 'UTM campaigns and the leads attributed to them in the last 30 days.', 'estora-core' );
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="campaigns" aria-busy="true">
		<div class="estora-split estora-split--wide">
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Campaigns', 'estora-core' ); ?></h2></div>
				<div data-campaign-table></div>
			</section>
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'New campaign', 'estora-core' ); ?></h2></div>
				<form class="estora-stack" data-campaign-form>
					<label><?php esc_html_e( 'Name', 'estora-core' ); ?><input name="name" required maxlength="190"></label>
					<div class="estora-formgrid">
						<label><?php esc_html_e( 'Channel', 'estora-core' ); ?>
							<select name="channel">
								<option value="web">Web</option>
								<option value="meta">Meta</option>
								<option value="google">Google</option>
								<option value="email">Email</option>
								<option value="print">Print</option>
							</select>
						</label>
						<label><?php esc_html_e( 'Status', 'estora-core' ); ?>
							<select name="status">
								<option value="draft">Draft</option>
								<option value="active">Active</option>
								<option value="paused">Paused</option>
								<option value="ended">Ended</option>
							</select>
						</label>
						<label><?php esc_html_e( 'UTM source', 'estora-core' ); ?><input name="utm_source" placeholder="facebook"></label>
						<label><?php esc_html_e( 'UTM medium', 'estora-core' ); ?><input name="utm_medium" placeholder="cpc"></label>
						<label><?php esc_html_e( 'UTM campaign', 'estora-core' ); ?><input name="utm_campaign" required placeholder="nimman-autumn"></label>
						<label><?php esc_html_e( 'Budget (THB)', 'estora-core' ); ?><input name="budget" type="number" min="0" step="100"></label>
						<label><?php esc_html_e( 'Starts', 'estora-core' ); ?><input type="date" name="starts_at"></label>
						<label><?php esc_html_e( 'Ends', 'estora-core' ); ?><input type="date" name="ends_at"></label>
					</div>
					<label><?php esc_html_e( 'Notes', 'estora-core' ); ?><textarea name="notes" rows="3"></textarea></label>
					<p class="estora-form-error" data-form-error role="alert" hidden></p>
					<div><button type="submit" class="estora-btn"><?php esc_html_e( 'Create campaign', 'estora-core' ); ?></button></div>
				</form>
			</section>
		</div>
	</main>
</div>
