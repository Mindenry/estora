<?php
/**
 * Leads screen.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;
use Estora\Core\Domain\Lead_Status;

$estora_eyebrow     = __( 'CRM', 'estora-core' );
$estora_title       = __( 'Leads', 'estora-core' );
$estora_description = __( 'Every inquiry captured from the website, appointments and manual entry.', 'estora-core' );
$estora_actions     = '<button type="button" class="estora-btn" data-new-lead>' . esc_html__( 'New lead', 'estora-core' ) . '</button>';
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="leads" aria-busy="true">
		<div class="estora-toolbar">
			<div class="estora-search">
				<label class="screen-reader-text" for="estora-lead-search"><?php esc_html_e( 'Search leads', 'estora-core' ); ?></label>
				<input type="search" id="estora-lead-search" placeholder="<?php esc_attr_e( 'Search name, phone, email or reference', 'estora-core' ); ?>" data-lead-search>
			</div>
			<label class="estora-inline-field">
				<span><?php esc_html_e( 'Stage', 'estora-core' ); ?></span>
				<select data-lead-status-filter>
					<option value=""><?php esc_html_e( 'All stages', 'estora-core' ); ?></option>
					<?php foreach ( Lead_Status::all() as $estora_status ) : ?>
						<option value="<?php echo esc_attr( $estora_status ); ?>"><?php echo esc_html( Lead_Status::label( $estora_status ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="estora-inline-field">
				<span><?php esc_html_e( 'Agent', 'estora-core' ); ?></span>
				<select data-lead-agent-filter><option value=""><?php esc_html_e( 'All agents', 'estora-core' ); ?></option></select>
			</label>
			<label class="estora-inline-field">
				<span><?php esc_html_e( 'Sort', 'estora-core' ); ?></span>
				<select data-lead-sort>
					<option value="created_at:DESC"><?php esc_html_e( 'Newest first', 'estora-core' ); ?></option>
					<option value="created_at:ASC"><?php esc_html_e( 'Oldest first', 'estora-core' ); ?></option>
					<option value="next_follow_up_at:ASC"><?php esc_html_e( 'Follow-up date', 'estora-core' ); ?></option>
					<option value="full_name:ASC"><?php esc_html_e( 'Name A–Z', 'estora-core' ); ?></option>
				</select>
			</label>
			<div class="estora-toolbar__views" role="group" aria-label="<?php esc_attr_e( 'View', 'estora-core' ); ?>">
				<button type="button" class="is-active" data-leads-view="table"><?php esc_html_e( 'Table', 'estora-core' ); ?></button>
				<button type="button" data-leads-view="board"><?php esc_html_e( 'Board', 'estora-core' ); ?></button>
			</div>
		</div>

		<div class="estora-stagecounts" data-stage-counts></div>

		<section class="estora-panel" data-leads-table></section>
		<section class="estora-board" data-leads-board hidden></section>
		<div class="estora-pagination" data-leads-pagination></div>

		<aside class="estora-drawer" data-lead-drawer hidden aria-label="<?php esc_attr_e( 'Lead detail', 'estora-core' ); ?>">
			<div class="estora-drawer__head">
				<div>
					<p class="estora-eyebrow"><?php esc_html_e( 'Lead', 'estora-core' ); ?></p>
					<h2 data-drawer-title></h2>
				</div>
				<button type="button" class="estora-iconbtn" data-close-drawer aria-label="<?php esc_attr_e( 'Close', 'estora-core' ); ?>">&times;</button>
			</div>
			<div class="estora-drawer__body" data-drawer-body></div>
		</aside>

		<dialog class="estora-dialog" data-lead-dialog>
			<form method="dialog" data-new-lead-form>
				<h2><?php esc_html_e( 'Create lead', 'estora-core' ); ?></h2>
				<div class="estora-formgrid">
					<label><?php esc_html_e( 'Full name', 'estora-core' ); ?><input name="full_name" required></label>
					<label><?php esc_html_e( 'Phone', 'estora-core' ); ?><input name="phone" inputmode="tel"></label>
					<label><?php esc_html_e( 'Email', 'estora-core' ); ?><input name="email" type="email"></label>
					<label><?php esc_html_e( 'Budget max', 'estora-core' ); ?><input name="budget_max" type="number" min="0" step="1000"></label>
					<label class="estora-formgrid__wide"><?php esc_html_e( 'Message / context', 'estora-core' ); ?><textarea name="message" rows="3"></textarea></label>
					<label><?php esc_html_e( 'Assign to', 'estora-core' ); ?><select name="assigned_agent_id" data-new-lead-agent><option value="0"><?php esc_html_e( 'Unassigned', 'estora-core' ); ?></option></select></label>
					<label><?php esc_html_e( 'Priority', 'estora-core' ); ?>
						<select name="priority">
							<option value="normal"><?php esc_html_e( 'Normal', 'estora-core' ); ?></option>
							<option value="high"><?php esc_html_e( 'High', 'estora-core' ); ?></option>
							<option value="urgent"><?php esc_html_e( 'Urgent', 'estora-core' ); ?></option>
							<option value="low"><?php esc_html_e( 'Low', 'estora-core' ); ?></option>
						</select>
					</label>
					<label class="estora-formgrid__wide"><?php esc_html_e( 'Next follow-up', 'estora-core' ); ?><input type="datetime-local" name="next_follow_up_at"></label>
				</div>
				<p class="estora-form-error" data-form-error role="alert" hidden></p>
				<div class="estora-dialog__actions">
					<button type="button" class="estora-btn estora-btn--ghost" data-close-dialog><?php esc_html_e( 'Cancel', 'estora-core' ); ?></button>
					<button type="submit" class="estora-btn"><?php esc_html_e( 'Create lead', 'estora-core' ); ?></button>
				</div>
			</form>
		</dialog>
	</main>
</div>
