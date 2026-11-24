<?php
/**
 * User management module.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride -- template locals, not globals.

use Estora\Core\Support\Capabilities;

$roles = wp_roles()->get_names();
unset( $roles['subscriber'], $roles['contributor'], $roles['author'], $roles['editor'] );
$roles['administrator'] = __( 'Super Admin', 'estora-core' );
$users  = get_users(
	array(
		'role__in' => array_keys( $roles ),
		'number'   => 100,
		'orderby'  => 'display_name',
	)
);
$counts = array();
foreach ( $users as $user ) {
	$role = $user->roles[0] ?? '';
	if ( ! isset( $counts[ $role ] ) ) {
		$counts[ $role ] = 0;
	}
	++$counts[ $role ];
}
$lead_counts = ( new \Estora\Core\Repositories\Lead_Repository() )->agent_counts();
$lead_map    = array();
foreach ( $lead_counts as $row ) {
	$lead_map[ (int) $row['agent_id'] ] = (int) $row['total'];
}

$estora_eyebrow     = __( 'Access', 'estora-core' );
$estora_title       = __( 'User management', 'estora-core' );
$estora_description = __( 'Staff accounts, roles and agent status. Password and profile changes use the WordPress user editor.', 'estora-core' );
$estora_actions     = current_user_can( 'create_users' ) ? '<a class="estora-btn" href="' . esc_url( admin_url( 'user-new.php' ) ) . '">' . esc_html__( 'Add user', 'estora-core' ) . '</a>' : '';
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="users" aria-busy="false">
		<div class="estora-kpis estora-kpis--compact">
			<?php foreach ( $roles as $slug => $label ) : ?>
				<div class="estora-kpi">
					<span class="estora-kpi__label"><?php echo esc_html( $label ); ?></span>
					<span class="estora-kpi__value"><?php echo esc_html( (string) ( $counts[ $slug ] ?? 0 ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>

		<section class="estora-panel">
			<div class="estora-panel__head"><h2><?php esc_html_e( 'Staff accounts', 'estora-core' ); ?></h2></div>
			<div class="estora-tablewrap">
				<table class="estora-table">
					<thead><tr><th scope="col"><?php esc_html_e( 'User', 'estora-core' ); ?></th><th scope="col"><?php esc_html_e( 'Role', 'estora-core' ); ?></th><th scope="col"><?php esc_html_e( 'Status', 'estora-core' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Leads', 'estora-core' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Actions', 'estora-core' ); ?></th></tr></thead>
					<tbody>
						<?php foreach ( $users as $user ) : ?>
							<?php
							$active = '0' !== (string) get_user_meta( $user->ID, 'estora_active', true );
							$title  = (string) get_user_meta( $user->ID, 'estora_title', true );
							?>
							<tr>
								<td>
									<span class="strong"><?php echo esc_html( $user->display_name ); ?></span><br>
									<span class="estora-muted"><?php echo esc_html( $user->user_email ); ?><?php echo $title ? ' · ' . esc_html( $title ) : ''; ?></span>
								</td>
								<td><?php echo esc_html( Capabilities::role_label( $user ) ); ?></td>
								<td><?php echo $active ? '<span class="estora-badge badge-ok">' . esc_html__( 'Active', 'estora-core' ) . '</span>' : '<span class="estora-badge badge-neutral">' . esc_html__( 'Inactive', 'estora-core' ) . '</span>'; ?></td>
								<td class="num"><?php echo esc_html( (string) ( $lead_map[ $user->ID ] ?? 0 ) ); ?></td>
								<td>
									<div class="estora-table__actions">
										<?php if ( current_user_can( 'edit_user', $user->ID ) ) : ?>
											<a class="estora-rowbtn" href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $user->ID ) ); ?>"><?php esc_html_e( 'Edit', 'estora-core' ); ?></a>
										<?php endif; ?>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="estora-muted" style="margin-top:14px"><?php esc_html_e( 'Deactivation is handled from the user editor (ESTORA agent profile → Active). Keeping it there avoids duplicating password and session logic.', 'estora-core' ); ?></p>
		</section>
	</main>
</div>
