<?php
/**
 * ESTORA back office: menu, assets, CSV export, agent profile fields.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Integrations;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Services\Report_Service;
use Estora\Core\Support\Capabilities;

final class Admin_UI {

	/**
	 * Hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_estora_export', array( $this, 'export_csv' ) );
		add_action( 'show_user_profile', array( $this, 'agent_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'agent_fields' ) );
		add_action( 'personal_options_update', array( $this, 'save_agent_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_agent_fields' ) );
		add_filter( 'manage_users_columns', array( $this, 'user_columns' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'user_column_content' ), 10, 3 );
		add_filter( 'bulk_actions-edit-property', array( $this, 'property_bulk_actions' ) );
	}

	/**
	 * Register the menu.
	 */
	public function menu(): void {
		$has_any = current_user_can( Capabilities::VIEW_REPORTS )
			|| current_user_can( Capabilities::VIEW_LEADS )
			|| current_user_can( Capabilities::MANAGE_APPOINTMENTS )
			|| current_user_can( Capabilities::VIEW_PROPERTIES )
			|| current_user_can( Capabilities::MANAGE_SETTINGS );

		if ( ! $has_any ) {
			return;
		}

		add_menu_page(
			__( 'ESTORA', 'estora-core' ),
			__( 'ESTORA', 'estora-core' ),
			'read',
			'estora-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-admin-home',
			3
		);

		add_submenu_page( 'estora-dashboard', __( 'Dashboard', 'estora-core' ), __( 'Dashboard', 'estora-core' ), Capabilities::VIEW_REPORTS, 'estora-dashboard', array( $this, 'render_dashboard' ) );
		add_submenu_page( 'estora-dashboard', __( 'Leads', 'estora-core' ), __( 'Leads', 'estora-core' ), Capabilities::VIEW_LEADS, 'estora-leads', array( $this, 'render_leads' ) );
		add_submenu_page( 'estora-dashboard', __( 'Appointments', 'estora-core' ), __( 'Appointments', 'estora-core' ), Capabilities::MANAGE_APPOINTMENTS, 'estora-appointments', array( $this, 'render_appointments' ) );
		add_submenu_page( 'estora-dashboard', __( 'Inventory', 'estora-core' ), __( 'Inventory', 'estora-core' ), Capabilities::VIEW_PROPERTIES, 'estora-inventory', array( $this, 'render_inventory' ) );
		add_submenu_page( 'estora-dashboard', __( 'Reports', 'estora-core' ), __( 'Reports', 'estora-core' ), Capabilities::VIEW_REPORTS, 'estora-reports', array( $this, 'render_reports' ) );
		add_submenu_page( 'estora-dashboard', __( 'Campaigns', 'estora-core' ), __( 'Campaigns', 'estora-core' ), Capabilities::MANAGE_CAMPAIGNS, 'estora-campaigns', array( $this, 'render_campaigns' ) );
		add_submenu_page( 'estora-dashboard', __( 'Activity log', 'estora-core' ), __( 'Activity log', 'estora-core' ), Capabilities::VIEW_AUDIT, 'estora-audit', array( $this, 'render_audit' ) );
		add_submenu_page( 'estora-dashboard', __( 'Settings', 'estora-core' ), __( 'Settings', 'estora-core' ), Capabilities::MANAGE_SETTINGS, 'estora-settings', array( $this, 'render_settings' ) );

		// Hidden detail screen.
		add_submenu_page( null, __( 'Lead', 'estora-core' ), __( 'Lead', 'estora-core' ), Capabilities::VIEW_LEADS, 'estora-lead', array( $this, 'render_lead' ) );
	}

	/**
	 * Enqueue admin assets only on ESTORA screens.
	 *
	 * @param string $hook Current hook.
	 */
	public function assets( $hook ): void {
		$page        = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_estora   = 0 === strpos( $page, 'estora-' );
		$screen      = get_current_screen();
		$is_property = $screen && 'property' === $screen->post_type;

		if ( ! $is_estora && ! $is_property && 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'estora-tokens', ESTORA_CORE_URL . 'assets/css/tokens.css', array(), ESTORA_CORE_VERSION );
		wp_enqueue_style( 'estora-admin', ESTORA_CORE_URL . 'assets/css/admin.css', array( 'estora-tokens' ), ESTORA_CORE_VERSION );

		if ( ! $is_estora ) {
			return;
		}

		wp_enqueue_script( 'estora-admin', ESTORA_CORE_URL . 'assets/js/admin.js', array(), ESTORA_CORE_VERSION, true );
		wp_localize_script(
			'estora-admin',
			'estoraAdmin',
			array(
				'api'         => esc_url_raw( rest_url( 'estora/v1' ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'page'        => $page,
				'currentUser' => array(
					'id'   => get_current_user_id(),
					'name' => wp_get_current_user()->display_name,
				),
				'caps'        => array(
					'viewAllLeads'      => current_user_can( Capabilities::VIEW_ALL_LEADS ),
					'assignLeads'       => current_user_can( Capabilities::ASSIGN_LEADS ),
					'editLeads'         => current_user_can( Capabilities::EDIT_LEADS ),
					'manageAppointments' => current_user_can( Capabilities::MANAGE_APPOINTMENTS ),
					'viewReports'       => current_user_can( Capabilities::VIEW_REPORTS ),
					'manageSettings'    => current_user_can( Capabilities::MANAGE_SETTINGS ),
					'viewAudit'         => current_user_can( Capabilities::VIEW_AUDIT ),
					'manageCampaigns'   => current_user_can( Capabilities::MANAGE_CAMPAIGNS ),
					'editProperties'    => current_user_can( Capabilities::EDIT_PROPERTIES ),
				),
				'urls'        => array(
					'newProperty' => admin_url( 'post-new.php?post_type=property' ),
					'leads'       => admin_url( 'admin.php?page=estora-leads' ),
					'reports'     => admin_url( 'admin.php?page=estora-reports' ),
					'export'      => admin_url( 'admin-post.php' ),
					'lead'        => admin_url( 'admin.php?page=estora-lead&id=' ),
					'property'    => admin_url( 'post.php?post=' ),
				),
			)
		);
	}

	/**
	 * Render a view with a capability check.
	 *
	 * @param string $view View file name.
	 * @param string $cap  Capability.
	 */
	private function view( string $view, string $cap ): void {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'estora-core' ), 403 );
		}
		require ESTORA_CORE_DIR . '/admin/views/' . $view . '.php';
	}

	/** Dashboard screen. */
	public function render_dashboard(): void {
		$this->view( 'dashboard', Capabilities::VIEW_REPORTS );
	}

	/** Leads screen. */
	public function render_leads(): void {
		$this->view( 'leads', Capabilities::VIEW_LEADS );
	}

	/** Lead detail screen. */
	public function render_lead(): void {
		$this->view( 'lead', Capabilities::VIEW_LEADS );
	}

	/** Appointments screen. */
	public function render_appointments(): void {
		$this->view( 'appointments', Capabilities::MANAGE_APPOINTMENTS );
	}

	/** Inventory screen. */
	public function render_inventory(): void {
		$this->view( 'inventory', Capabilities::VIEW_PROPERTIES );
	}

	/** Reports screen. */
	public function render_reports(): void {
		$this->view( 'reports', Capabilities::VIEW_REPORTS );
	}

	/** Campaigns screen. */
	public function render_campaigns(): void {
		$this->view( 'campaigns', Capabilities::MANAGE_CAMPAIGNS );
	}

	/** Audit screen. */
	public function render_audit(): void {
		$this->view( 'audit', Capabilities::VIEW_AUDIT );
	}

	/** Settings screen. */
	public function render_settings(): void {
		$this->view( 'settings', Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * Stream a CSV report.
	 */
	public function export_csv(): void {
		if ( ! current_user_can( Capabilities::VIEW_REPORTS ) ) {
			wp_die( esc_html__( 'You are not allowed to export reports.', 'estora-core' ), 403 );
		}
		check_admin_referer( 'estora_export' );

		$report = isset( $_GET['report'] ) ? sanitize_key( wp_unslash( $_GET['report'] ) ) : '';
		if ( 'leads' === $report && ! current_user_can( Capabilities::VIEW_LEADS ) ) {
			wp_die( esc_html__( 'You are not allowed to export leads.', 'estora-core' ), 403 );
		}

		try {
			$data = ( new Report_Service() )->export( $report );
		} catch ( \InvalidArgumentException $e ) {
			wp_die( esc_html__( 'Unknown report.', 'estora-core' ), 400 );
		}

		( new \Estora\Core\Repositories\Activity_Repository() )->log( 'report.exported', 'report', 0, 'CSV exported: ' . $report );

		$filename = 'estora-' . $report . '-' . gmdate( 'Ymd-His' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, $data['headers'] );
		foreach ( $data['rows'] as $row ) {
			fputcsv( $out, $row );
		}
		fclose( $out );
		exit;
	}

	/**
	 * Agent fields on the user profile.
	 *
	 * @param \WP_User $user User.
	 */
	public function agent_fields( $user ): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}
		$active = (string) get_user_meta( $user->ID, 'estora_active', true );
		?>
		<h2><?php esc_html_e( 'ESTORA agent profile', 'estora-core' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="estora_title"><?php esc_html_e( 'Job title', 'estora-core' ); ?></label></th>
				<td><input type="text" name="estora_title" id="estora_title" class="regular-text" value="<?php echo esc_attr( (string) get_user_meta( $user->ID, 'estora_title', true ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="estora_phone"><?php esc_html_e( 'Public phone', 'estora-core' ); ?></label></th>
				<td><input type="text" name="estora_phone" id="estora_phone" class="regular-text" value="<?php echo esc_attr( (string) get_user_meta( $user->ID, 'estora_phone', true ) ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Account status', 'estora-core' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="estora_active" value="1" <?php checked( '0' !== $active ); ?>>
						<?php esc_html_e( 'Active (can sign in and be assigned leads)', 'estora-core' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save agent fields.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_agent_fields( $user_id ): void {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}
		// The profile screens already verify this nonce; repeat it for defence in depth.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), 'update-user_' . absint( $user_id ) ) ) {
			return;
		}
		if ( ! isset( $_POST['estora_title'] ) && ! isset( $_POST['estora_phone'] ) ) {
			return;
		}

		update_user_meta( $user_id, 'estora_title', sanitize_text_field( wp_unslash( $_POST['estora_title'] ?? '' ) ) );
		update_user_meta( $user_id, 'estora_phone', sanitize_text_field( wp_unslash( $_POST['estora_phone'] ?? '' ) ) );
		update_user_meta( $user_id, 'estora_active', isset( $_POST['estora_active'] ) ? '1' : '0' );
	}

	/**
	 * Users list column.
	 *
	 * @param array<string,string> $columns Columns.
	 * @return array<string,string>
	 */
	public function user_columns( $columns ): array {
		$columns['estora_agent'] = __( 'ESTORA', 'estora-core' );
		return $columns;
	}

	/**
	 * Users list column content.
	 *
	 * @param string $value Value.
	 * @param string $name  Column.
	 * @param int    $id    User ID.
	 */
	public function user_column_content( $value, $name, $id ) {
		if ( 'estora_agent' !== $name ) {
			return $value;
		}
		$active = '0' !== (string) get_user_meta( $id, 'estora_active', true );
		$title  = (string) get_user_meta( $id, 'estora_title', true );
		return esc_html( ( $title ? $title . ' · ' : '' ) . ( $active ? __( 'Active', 'estora-core' ) : __( 'Inactive', 'estora-core' ) ) );
	}

	/**
	 * Remove the native "edit" bulk action we do not support.
	 *
	 * @param array<string,string> $actions Actions.
	 * @return array<string,string>
	 */
	public function property_bulk_actions( $actions ): array {
		return $actions;
	}
}
