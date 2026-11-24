<?php
/**
 * Lead detail screen.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;
$estora_lead_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$estora_eyebrow     = __( 'CRM · Lead', 'estora-core' );
$estora_title       = __( 'Lead detail', 'estora-core' );
$estora_description = __( 'Contact history, notes, ownership and pipeline stage.', 'estora-core' );
$estora_actions     = '<a class="estora-btn estora-btn--ghost" href="' . esc_url( admin_url( 'admin.php?page=estora-leads' ) ) . '">' . esc_html__( 'Back to leads', 'estora-core' ) . '</a>';
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="lead" data-lead-id="<?php echo esc_attr( (string) $estora_lead_id ); ?>" aria-busy="true">
		<div class="estora-leaddetail">
			<section class="estora-panel" data-lead-summary></section>
			<section class="estora-panel" data-lead-pipeline></section>
		</div>
		<div class="estora-split estora-split--wide">
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Add a note', 'estora-core' ); ?></h2></div>
				<form data-note-form class="estora-stack">
					<label><?php esc_html_e( 'Note', 'estora-core' ); ?><textarea name="body" rows="4" required maxlength="5000"></textarea></label>
					<div class="estora-inline">
						<label class="estora-inline-field">
							<span><?php esc_html_e( 'Type', 'estora-core' ); ?></span>
							<select name="note_type">
								<option value="note"><?php esc_html_e( 'Note', 'estora-core' ); ?></option>
								<option value="call"><?php esc_html_e( 'Call', 'estora-core' ); ?></option>
								<option value="email"><?php esc_html_e( 'Email', 'estora-core' ); ?></option>
								<option value="meeting"><?php esc_html_e( 'Meeting', 'estora-core' ); ?></option>
							</select>
						</label>
						<label class="estora-check"><input type="checkbox" name="is_internal" value="1"> <?php esc_html_e( 'Internal only', 'estora-core' ); ?></label>
					</div>
					<div><button class="estora-btn" type="submit"><?php esc_html_e( 'Save note', 'estora-core' ); ?></button></div>
				</form>
				<div class="estora-notes" data-lead-notes></div>
			</section>
			<div class="estora-stack">
				<section class="estora-panel">
					<div class="estora-panel__head"><h2><?php esc_html_e( 'Assignment history', 'estora-core' ); ?></h2></div>
					<ol class="estora-timeline" data-lead-assignments></ol>
				</section>
				<section class="estora-panel">
					<div class="estora-panel__head"><h2><?php esc_html_e( 'Activity', 'estora-core' ); ?></h2></div>
					<ol class="estora-timeline" data-lead-timeline></ol>
				</section>
			</div>
		</div>
	</main>
</div>
