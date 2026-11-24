<?php
/**
 * Agents module.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride -- template locals, not globals.

use Estora\Core\Services\Report_Service;
use Estora\Core\Support\Capabilities;
use Estora\Core\Support\Formatter;

$capabilities = Capabilities::class;
use Estora\Core\Admin\Admin_App;

$reports  = new Report_Service();
$rows     = $reports->agent_performance();
$active   = count( array_filter( $rows, static fn( $row ) => ! empty( $row['active'] ) ) );
$leads    = array_sum( array_column( $rows, 'leads' ) );
$upcoming = array_sum( array_column( $rows, 'upcoming_appointments' ) );

$estora_eyebrow     = __( 'Team', 'estora-core' );
$estora_title       = __( 'Agents', 'estora-core' );
$estora_description = __( 'Agent directory, ownership load and conversion. Opening a profile uses the WordPress user editor.', 'estora-core' );
$estora_actions     = '';
if ( current_user_can( 'create_users' ) ) {
	$estora_actions = '<a class="estora-btn" href="' . esc_url( admin_url( 'user-new.php' ) ) . '">' . esc_html__( 'Add user', 'estora-core' ) . '</a>';
}
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="agents" aria-busy="false">
		<div class="estora-kpis estora-kpis--compact">
			<div class="estora-kpi"><span class="estora-kpi__label"><?php esc_html_e( 'Agents', 'estora-core' ); ?></span><span class="estora-kpi__value"><?php echo esc_html( (string) count( $rows ) ); ?></span><span class="estora-kpi__delta"><?php /* translators: %d: active agent count */ echo esc_html( sprintf( __( '%d active', 'estora-core' ), $active ) ); ?></span></div>
			<div class="estora-kpi"><span class="estora-kpi__label"><?php esc_html_e( 'Assigned leads', 'estora-core' ); ?></span><span class="estora-kpi__value"><?php echo esc_html( number_format_i18n( $leads ) ); ?></span><span class="estora-kpi__delta"><?php esc_html_e( 'All time', 'estora-core' ); ?></span></div>
			<div class="estora-kpi"><span class="estora-kpi__label"><?php esc_html_e( 'Upcoming viewings', 'estora-core' ); ?></span><span class="estora-kpi__value"><?php echo esc_html( number_format_i18n( $upcoming ) ); ?></span><span class="estora-kpi__delta"><?php esc_html_e( 'Across the team', 'estora-core' ); ?></span></div>
		</div>

		<section class="estora-panel">
			<div class="estora-panel__head"><h2><?php esc_html_e( 'Team performance', 'estora-core' ); ?></h2></div>
			<?php if ( $rows ) : ?>
				<div class="estora-tablewrap">
					<table class="estora-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Agent', 'estora-core' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Role', 'estora-core' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'estora-core' ); ?></th>
								<th scope="col" class="num"><?php esc_html_e( 'Leads', 'estora-core' ); ?></th>
								<th scope="col" class="num"><?php esc_html_e( 'Won', 'estora-core' ); ?></th>
								<th scope="col" class="num"><?php esc_html_e( 'Conversion', 'estora-core' ); ?></th>
								<th scope="col" class="num"><?php esc_html_e( 'Upcoming', 'estora-core' ); ?></th>
								<th scope="col" class="num"><?php esc_html_e( 'Actions', 'estora-core' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<?php $user = get_userdata( (int) $row['agent_id'] ); ?>
								<tr>
									<td>
										<span class="strong"><?php echo esc_html( $row['name'] ); ?></span><br>
										<span class="estora-muted"><?php echo esc_html( (string) ( $user ? get_user_meta( $user->ID, 'estora_title', true ) : '' ) ); ?></span>
									</td>
									<td><?php echo esc_html( $user ? Capabilities::role_label( $user ) : '—' ); ?></td>
									<td><?php echo ! empty( $row['active'] ) ? '<span class="estora-badge badge-ok">' . esc_html__( 'Active', 'estora-core' ) . '</span>' : '<span class="estora-badge badge-neutral">' . esc_html__( 'Inactive', 'estora-core' ) . '</span>'; ?></td>
									<td class="num"><?php echo esc_html( (string) $row['leads'] ); ?></td>
									<td class="num"><?php echo esc_html( (string) $row['won'] ); ?></td>
									<td class="num"><?php echo esc_html( (string) $row['conversion_rate'] . '%' ); ?></td>
									<td class="num"><?php echo esc_html( (string) $row['upcoming_appointments'] ); ?></td>
									<td>
										<div class="estora-table__actions">
											<?php if ( current_user_can( 'edit_users' ) ) : ?>
												<a class="estora-rowbtn" href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . (int) $row['agent_id'] ) ); ?>"><?php esc_html_e( 'Edit', 'estora-core' ); ?></a>
											<?php endif; ?>
											<a class="estora-rowbtn" href="<?php echo esc_url( \Estora\Core\Admin\Admin_App::url( 'appointments', array( 'agent_id' => (int) $row['agent_id'] ) ) ); ?>"><?php esc_html_e( 'Viewings', 'estora-core' ); ?></a>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<div class="estora-emptyadmin"><strong><?php esc_html_e( 'No agents yet', 'estora-core' ); ?></strong><?php esc_html_e( 'Create a user with an ESTORA sales role to see them here.', 'estora-core' ); ?></div>
			<?php endif; ?>
		</section>
	</main>
</div>
