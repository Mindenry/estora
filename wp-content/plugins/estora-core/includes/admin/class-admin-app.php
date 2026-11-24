<?php
/**
 * ESTORA back-office application: /estora-admin/ shell, routing and redirects.
 *
 * @package Estora\Core
 */

namespace Estora\Core\Admin;

defined( 'ABSPATH' ) || exit;

use Estora\Core\Support\Capabilities;
use Estora\Core\Support\Formatter;

final class Admin_App {

	public const QUERY_VAR     = 'estora_app';
	public const ID_VAR        = 'estora_id';
	public const ROUTE_VERSION = '1.1.0';

	/**
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'init', array( $this, 'register_routes' ), 5 );
		add_action( 'init', array( $this, 'maybe_flush' ), 99 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_request' ), 1 );
		add_filter( 'redirect_canonical', array( $this, 'no_canonical' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 20 );
		add_filter( 'body_class', array( $this, 'body_class' ), 99 );
		add_filter( 'pre_get_document_title', array( $this, 'document_title' ) );
		add_filter( 'show_admin_bar', array( $this, 'admin_bar' ) );
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 20, 3 );
		add_action( 'admin_init', array( $this, 'redirect_wp_admin' ) );
		add_action( 'admin_menu', array( $this, 'prune_admin_menu' ), 999 );
		add_action( 'login_enqueue_scripts', array( $this, 'login_assets' ) );
		add_filter( 'login_headerurl', array( $this, 'login_header_url' ) );
		add_filter( 'login_headertext', array( $this, 'login_header_text' ) );
	}

	/**
	 * Register the app rewrite rules.
	 */
	public function register_routes(): void {
		add_rewrite_rule( '^estora-admin/?$', 'index.php?' . self::QUERY_VAR . '=overview', 'top' );
		add_rewrite_rule( '^estora-admin/leads/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=lead&' . self::ID_VAR . '=$matches[1]', 'top' );
		add_rewrite_rule( '^estora-admin/([a-z0-9-]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
		add_rewrite_rule( '^estora-admin/(.+)/?$', 'index.php?' . self::QUERY_VAR . '=404', 'top' );
	}

	/**
	 * Flush rules once after an upgrade.
	 */
	public function maybe_flush(): void {
		if ( get_option( 'estora_app_route_version' ) === self::ROUTE_VERSION ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( 'estora_app_route_version', self::ROUTE_VERSION, false );
	}

	/**
	 * Register the query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public function query_vars( $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::ID_VAR;
		return $vars;
	}

	/**
	 * Disable canonical redirects for the app.
	 *
	 * @param string|false $redirect Redirect URL.
	 * @param string       $request  Requested URL.
	 * @return string|false
	 */
	public function no_canonical( $redirect, $request ) {
		return $this->is_app_request() ? false : $redirect;
	}

	/**
	 * URL for an app route.
	 *
	 * @param string              $route Route.
	 * @param array<string,mixed> $args  Query args.
	 */
	public static function url( string $route = 'overview', array $args = array() ): string {
		$path = 'overview' === $route ? '' : $route . '/';
		$url  = home_url( '/estora-admin/' . $path );
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * URL for a lead detail screen.
	 *
	 * @param int $lead_id Lead.
	 */
	public static function lead_url( int $lead_id ): string {
		return home_url( '/estora-admin/leads/' . $lead_id . '/' );
	}

	/**
	 * Is this request the app?
	 */
	public function is_app_request(): bool {
		return '' !== (string) get_query_var( self::QUERY_VAR );
	}

	/**
	 * Route definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function routes(): array {
		return array(
			'overview'      => array(
				'view'  => 'dashboard',
				'title' => __( 'Overview', 'estora-core' ),
				'caps'  => array(),
			),
			'properties'    => array(
				'view'  => 'inventory',
				'title' => __( 'Properties', 'estora-core' ),
				'caps'  => array( Capabilities::VIEW_PROPERTIES ),
			),
			'leads'         => array(
				'view'  => 'leads',
				'title' => __( 'Leads', 'estora-core' ),
				'caps'  => array( Capabilities::VIEW_LEADS ),
			),
			'pipeline'      => array(
				'view'         => 'leads',
				'title'        => __( 'CRM Pipeline', 'estora-core' ),
				'caps'         => array( Capabilities::VIEW_LEADS ),
				'initial_view' => 'board',
			),
			'lead'          => array(
				'view'   => 'lead',
				'title'  => __( 'Lead detail', 'estora-core' ),
				'caps'   => array( Capabilities::VIEW_LEADS ),
				'hidden' => true,
			),
			'appointments'  => array(
				'view'  => 'appointments',
				'title' => __( 'Appointments', 'estora-core' ),
				'caps'  => array( Capabilities::MANAGE_APPOINTMENTS ),
			),
			'agents'        => array(
				'view'  => 'agents',
				'title' => __( 'Agents', 'estora-core' ),
				'caps'  => array( Capabilities::VIEW_ALL_LEADS, Capabilities::EDIT_PROPERTIES ),
			),
			'locations'     => array(
				'view'  => 'locations',
				'title' => __( 'Locations', 'estora-core' ),
				'caps'  => array( Capabilities::VIEW_PROPERTIES ),
			),
			'campaigns'     => array(
				'view'  => 'campaigns',
				'title' => __( 'Campaigns', 'estora-core' ),
				'caps'  => array( Capabilities::MANAGE_CAMPAIGNS ),
			),
			'content'       => array(
				'view'  => 'content',
				'title' => __( 'Content', 'estora-core' ),
				'caps'  => array( Capabilities::MANAGE_CMS, 'edit_pages' ),
			),
			'reports'       => array(
				'view'  => 'reports',
				'title' => __( 'Reports', 'estora-core' ),
				'caps'  => array( Capabilities::VIEW_REPORTS ),
			),
			'notifications' => array(
				'view'  => 'notifications',
				'title' => __( 'Notifications', 'estora-core' ),
				'caps'  => array( 'read' ),
			),
			'activity'      => array(
				'view'  => 'audit',
				'title' => __( 'Activity log', 'estora-core' ),
				'caps'  => array( Capabilities::VIEW_AUDIT ),
			),
			'users'         => array(
				'view'  => 'users',
				'title' => __( 'User management', 'estora-core' ),
				'caps'  => array( Capabilities::MANAGE_USERS ),
			),
			'settings'      => array(
				'view'  => 'settings',
				'title' => __( 'Settings', 'estora-core' ),
				'caps'  => array( Capabilities::MANAGE_SETTINGS ),
			),
			'404'           => array(
				'view'  => 'app-404',
				'title' => __( 'Not found', 'estora-core' ),
				'caps'  => array(),
			),
			'forbidden'     => array(
				'view'  => 'app-forbidden',
				'title' => __( 'Not allowed', 'estora-core' ),
				'caps'  => array(),
			),
		);
	}

	/**
	 * Whether a route is allowed for the current user.
	 *
	 * @param array<string,mixed> $definition Route definition.
	 */
	public function route_allowed( array $definition ): bool {
		$caps = (array) ( $definition['caps'] ?? array() );
		if ( empty( $caps ) ) {
			return true;
		}
		foreach ( $caps as $cap ) {
			if ( current_user_can( $cap ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Handle the app request.
	 */
	public function handle_request(): void {
		if ( ! $this->is_app_request() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		if ( ! Capabilities::can_access_app() ) {
			wp_die(
				esc_html__( 'Your account does not have access to the ESTORA back office.', 'estora-core' ),
				esc_html__( 'Not allowed', 'estora-core' ),
				array( 'response' => 403 )
			);
		}

		$route   = sanitize_key( (string) get_query_var( self::QUERY_VAR ) );
		$route   = $route ?: 'overview';
		$lead_id = absint( get_query_var( self::ID_VAR ) );
		$routes  = $this->routes();

		if ( ! isset( $routes[ $route ] ) ) {
			$route = '404';
		}

		if ( 'lead' === $route ) {
			if ( ! $lead_id ) {
				wp_safe_redirect( self::url( 'leads' ) );
				exit;
			}
			$_GET['id'] = $lead_id; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$definition = $routes[ $route ];
		$status     = 200;

		if ( '404' === $route ) {
			$status = 404;
		} elseif ( ! $this->route_allowed( $definition ) ) {
			$status     = 403;
			$route      = 'forbidden';
			$definition = $routes['forbidden'];
		}

		if ( 200 !== $status ) {
			status_header( $status );
		}
		nocache_headers();

		$this->render_shell( $route, $definition, $lead_id );
		exit;
	}

	/**
	 * Render the full application shell.
	 *
	 * @param string              $route      Route.
	 * @param array<string,mixed> $definition Route definition.
	 * @param int                 $lead_id    Lead ID when relevant.
	 */
	private function render_shell( string $route, array $definition, int $lead_id = 0 ): void {
		$user       = wp_get_current_user();
		$role_label = Capabilities::role_label( $user );
		$nav_groups = $this->nav_groups();
		$crumbs     = $this->breadcrumbs( $route, $definition );

		$estora_shell = array(
			'route'       => $route,
			'definition'  => $definition,
			'lead_id'     => $lead_id,
			'user'        => $user,
			'role_label'  => $role_label,
			'initials'    => Formatter::initials( $user->display_name ),
			'nav_groups'  => $nav_groups,
			'breadcrumbs' => $crumbs,
			'search_url'  => current_user_can( Capabilities::VIEW_LEADS ) ? self::url( 'leads' ) : self::url( 'properties' ),
			'initial_view' => $definition['initial_view'] ?? 'table',
		);

		require ESTORA_CORE_DIR . '/admin/views/app-shell.php';
	}

	/**
	 * Grouped navigation for the current user.
	 *
	 * @return array<int,array{label:string,items:array<int,array<string,mixed>>}>
	 */
	public function nav_groups(): array {
		$routes = $this->routes();
		$groups = array(
			array(
				'label' => __( 'Workspace', 'estora-core' ),
				'items' => array(
					array( 'route' => 'overview', 'label' => __( 'Overview', 'estora-core' ), 'icon' => 'grid' ),
					array( 'route' => 'properties', 'label' => __( 'Properties', 'estora-core' ), 'icon' => 'building' ),
					array( 'route' => 'leads', 'label' => __( 'Leads', 'estora-core' ), 'icon' => 'users' ),
					array( 'route' => 'pipeline', 'label' => __( 'CRM Pipeline', 'estora-core' ), 'icon' => 'pipeline' ),
					array( 'route' => 'appointments', 'label' => __( 'Appointments', 'estora-core' ), 'icon' => 'calendar' ),
					array( 'route' => 'agents', 'label' => __( 'Agents', 'estora-core' ), 'icon' => 'user' ),
					array( 'route' => 'locations', 'label' => __( 'Locations', 'estora-core' ), 'icon' => 'pin' ),
				),
			),
			array(
				'label' => __( 'Growth', 'estora-core' ),
				'items' => array(
					array( 'route' => 'campaigns', 'label' => __( 'Campaigns', 'estora-core' ), 'icon' => 'megaphone' ),
					array( 'route' => 'content', 'label' => __( 'Content', 'estora-core' ), 'icon' => 'file' ),
					array( 'route' => 'reports', 'label' => __( 'Reports', 'estora-core' ), 'icon' => 'chart' ),
				),
			),
			array(
				'label' => __( 'System', 'estora-core' ),
				'items' => array(
					array( 'route' => 'notifications', 'label' => __( 'Notifications', 'estora-core' ), 'icon' => 'bell' ),
					array( 'route' => 'activity', 'label' => __( 'Activity log', 'estora-core' ), 'icon' => 'history' ),
					array( 'route' => 'users', 'label' => __( 'User management', 'estora-core' ), 'icon' => 'shield' ),
					array( 'route' => 'settings', 'label' => __( 'Settings', 'estora-core' ), 'icon' => 'settings' ),
				),
			),
		);

		$out = array();
		foreach ( $groups as $group ) {
			$items = array();
			foreach ( $group['items'] as $item ) {
				$definition = $routes[ $item['route'] ] ?? null;
				if ( ! $definition || ! $this->route_allowed( $definition ) ) {
					continue;
				}
				$item['url']    = self::url( $item['route'] );
				$item['active'] = $item['route'] === (string) get_query_var( self::QUERY_VAR )
					|| ( 'leads' === $item['route'] && 'lead' === (string) get_query_var( self::QUERY_VAR ) );
				$items[]        = $item;
			}
			if ( $items ) {
				$out[] = array( 'label' => $group['label'], 'items' => $items );
			}
		}
		return $out;
	}

	/**
	 * Breadcrumbs for the header.
	 *
	 * @param string              $route      Route.
	 * @param array<string,mixed> $definition Definition.
	 * @return array<int,array{label:string,url?:string}>
	 */
	private function breadcrumbs( string $route, array $definition ): array {
		$crumbs = array(
			array( 'label' => __( 'ESTORA', 'estora-core' ), 'url' => self::url( 'overview' ) ),
		);
		if ( 'overview' === $route ) {
			$crumbs[] = array( 'label' => __( 'Overview', 'estora-core' ) );
			return $crumbs;
		}
		if ( 'lead' === $route ) {
			$crumbs[] = array( 'label' => __( 'Leads', 'estora-core' ), 'url' => self::url( 'leads' ) );
			$crumbs[] = array( 'label' => __( 'Detail', 'estora-core' ) );
			return $crumbs;
		}
		$crumbs[] = array( 'label' => (string) $definition['title'] );
		return $crumbs;
	}

	/**
	 * Enqueue app assets and remove the public theme from this route.
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_app_request() ) {
			return;
		}

		foreach ( array( 'bootstrap', 'estora-main', 'estora-tokens' ) as $handle ) {
			wp_dequeue_style( $handle );
		}
		foreach ( array( 'bootstrap', 'estora-main' ) as $handle ) {
			wp_dequeue_script( $handle );
		}

		wp_enqueue_style( 'estora-tokens', ESTORA_CORE_URL . 'assets/css/tokens.css', array(), ESTORA_CORE_VERSION );
		wp_enqueue_style( 'estora-admin', ESTORA_CORE_URL . 'assets/css/admin.css', array( 'estora-tokens' ), ESTORA_CORE_VERSION );
		wp_enqueue_style( 'estora-app', ESTORA_CORE_URL . 'assets/css/admin-app.css', array( 'estora-admin' ), ESTORA_CORE_VERSION );

		wp_enqueue_script( 'estora-admin', ESTORA_CORE_URL . 'assets/js/admin.js', array(), ESTORA_CORE_VERSION, true );
		wp_enqueue_script( 'estora-app', ESTORA_CORE_URL . 'assets/js/admin-app.js', array( 'estora-admin' ), ESTORA_CORE_VERSION, true );

		wp_localize_script( 'estora-admin', 'estoraAdmin', $this->script_data() );
	}

	/**
	 * Data shared with the admin JS.
	 *
	 * @return array<string,mixed>
	 */
	private function script_data(): array {
		$user  = wp_get_current_user();
		$route = (string) get_query_var( self::QUERY_VAR ) ?: 'overview';

		return array(
			'api'         => esc_url_raw( rest_url( 'estora/v1' ) ),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'page'        => $route,
			'app'         => true,
			'initialView' => 'pipeline' === $route ? 'board' : 'table',
			'currentUser' => array(
				'id'       => $user->ID,
				'name'     => $user->display_name,
				'role'     => Capabilities::role_label( $user ),
				'initials' => Formatter::initials( $user->display_name ),
			),
			'caps'        => array(
				'viewAllLeads'       => current_user_can( Capabilities::VIEW_ALL_LEADS ),
				'assignLeads'        => current_user_can( Capabilities::ASSIGN_LEADS ),
				'editLeads'          => current_user_can( Capabilities::EDIT_LEADS ),
				'manageAppointments' => current_user_can( Capabilities::MANAGE_APPOINTMENTS ),
				'viewReports'        => current_user_can( Capabilities::VIEW_REPORTS ),
				'viewLeads'          => current_user_can( Capabilities::VIEW_LEADS ),
				'manageSettings'     => current_user_can( Capabilities::MANAGE_SETTINGS ),
				'viewAudit'          => current_user_can( Capabilities::VIEW_AUDIT ),
				'manageCampaigns'    => current_user_can( Capabilities::MANAGE_CAMPAIGNS ),
				'editProperties'     => current_user_can( Capabilities::EDIT_PROPERTIES ),
				'manageCms'          => current_user_can( Capabilities::MANAGE_CMS ),
				'manageUsers'        => current_user_can( Capabilities::MANAGE_USERS ),
			),
			'urls'        => array(
				'app'          => self::url( 'overview' ),
				'properties'   => self::url( 'properties' ),
				'leads'        => self::url( 'leads' ),
				'leadBase'     => home_url( '/estora-admin/leads/' ),
				'pipeline'     => self::url( 'pipeline' ),
				'appointments' => self::url( 'appointments' ),
				'reports'      => self::url( 'reports' ),
				'notifications' => self::url( 'notifications' ),
				'newProperty'  => admin_url( 'post-new.php?post_type=property' ),
				'newLead'      => self::url( 'leads' ) . '#new',
				'export'       => admin_url( 'admin-post.php' ),
				'lead'         => admin_url( 'admin.php?page=estora-lead&id=' ),
				'property'     => admin_url( 'post.php?post=' ),
				'profile'      => admin_url( 'profile.php' ),
				'wpAdmin'      => admin_url(),
				'logout'       => wp_logout_url( wp_login_url( self::url( 'overview' ) ) ),
			),
		);
	}

	/**
	 * Body classes.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public function body_class( $classes ): array {
		if ( $this->is_app_request() ) {
			$classes   = array_diff( $classes, array( 'estora-public' ) );
			$classes[] = 'estora-app-body';
		}
		return $classes;
	}

	/**
	 * Document title for app routes.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public function document_title( $title ): string {
		if ( ! $this->is_app_request() ) {
			return (string) $title;
		}
		$route  = (string) get_query_var( self::QUERY_VAR );
		$routes = $this->routes();
		$label  = $routes[ $route ]['title'] ?? __( 'Back office', 'estora-core' );
		return $label . ' — ' . get_bloginfo( 'name' );
	}

	/**
	 * Hide the WordPress toolbar in the app and for non-admin staff.
	 *
	 * @param bool $show Current value.
	 */
	public function admin_bar( $show ): bool {
		if ( $this->is_app_request() ) {
			return false;
		}
		if ( ! current_user_can( 'manage_options' ) && Capabilities::can_access_app() ) {
			return false;
		}
		return (bool) $show;
	}

	/**
	 * Send authenticated staff to the business dashboard after login.
	 *
	 * @param string                  $redirect_to Requested URL.
	 * @param string                  $requested   Raw requested URL.
	 * @param \WP_User|\WP_Error|null $user User.
	 * @return string
	 */
	public function login_redirect( $redirect_to, $requested, $user ) {
		if ( ! $user instanceof \WP_User || ! Capabilities::can_access_app( $user->ID ) ) {
			return $redirect_to;
		}
		return self::url( 'overview' );
	}

	/**
	 * Keep the default WP dashboard out of the staff flow and move legacy
	 * ESTORA pages to their app routes.
	 */
	public function redirect_wp_admin(): void {
		if ( wp_doing_ajax() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $page && 0 === strpos( $page, 'estora-' ) ) {
			$map = array(
				'estora-dashboard'    => 'overview',
				'estora-leads'        => 'leads',
				'estora-appointments' => 'appointments',
				'estora-inventory'    => 'properties',
				'estora-reports'      => 'reports',
				'estora-campaigns'    => 'campaigns',
				'estora-audit'        => 'activity',
				'estora-settings'     => 'settings',
			);
			if ( 'estora-lead' === $page ) {
				$lead_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				wp_safe_redirect( self::lead_url( $lead_id ) );
				exit;
			}
			if ( isset( $map[ $page ] ) ) {
				wp_safe_redirect( self::url( $map[ $page ] ) );
				exit;
			}
		}

		global $pagenow;
		if ( 'index.php' === $pagenow && ! current_user_can( 'manage_options' ) && Capabilities::can_access_app() ) {
			wp_safe_redirect( self::url( 'overview' ) );
			exit;
		}
	}

	/**
	 * Remove technical WordPress menus from business staff.
	 */
	public function prune_admin_menu(): void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$technical = array(
			'index.php',
			'edit-comments.php',
			'tools.php',
			'plugins.php',
			'themes.php',
			'options-general.php',
			'options-writing.php',
			'options-reading.php',
			'options-discussion.php',
			'options-media.php',
			'options-permalink.php',
			'options-privacy.php',
		);
		foreach ( $technical as $menu ) {
			remove_menu_page( $menu );
		}
		if ( ! current_user_can( 'list_users' ) ) {
			remove_menu_page( 'users.php' );
			remove_menu_page( 'profile.php' );
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			remove_menu_page( 'upload.php' );
		}
	}

	/**
	 * Branded login screen assets.
	 */
	public function login_assets(): void {
		wp_enqueue_style( 'estora-tokens', ESTORA_CORE_URL . 'assets/css/tokens.css', array(), ESTORA_CORE_VERSION );
		wp_enqueue_style( 'estora-login', ESTORA_CORE_URL . 'assets/css/login.css', array( 'estora-tokens' ), ESTORA_CORE_VERSION );
	}

	/**
	 * Login logo link.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public function login_header_url( $url ): string {
		return home_url( '/' );
	}

	/**
	 * Login logo text.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public function login_header_text( $text ): string {
		return 'ESTORA';
	}

	/**
	 * Inline icon set.
	 *
	 * @param string $name Name.
	 * @param int    $size Size.
	 */
	public static function icon( string $name, int $size = 18 ): string {
		$paths = array(
			'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
			'building'  => '<path d="M3 21h18"/><path d="M5 21V5l7-3v19"/><path d="M12 8h7v13"/><path d="M8 7v2M8 12v2M8 17v2M15 12v2M15 17v2"/>',
			'users'     => '<circle cx="9" cy="8" r="3"/><path d="M3 21c0-3.5 2.7-6 6-6s6 2.5 6 6"/><path d="M16 5.5a3 3 0 0 1 0 5.8"/><path d="M18 15c2.3.6 4 2.5 4 5"/>',
			'pipeline'  => '<path d="M4 6h16"/><path d="M7 12h10"/><path d="M10 18h4"/><circle cx="5" cy="6" r="1.4"/><circle cx="18" cy="12" r="1.4"/><circle cx="12" cy="18" r="1.4"/>',
			'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>',
			'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
			'pin'       => '<path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.3"/>',
			'megaphone' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l3 5h2l-2-5h2l8 4V4l-8 4H4a1 1 0 0 0-1 1Z"/><path d="M18 8v8"/>',
			'file'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8M8 17h5"/>',
			'chart'     => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
			'bell'      => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>',
			'history'   => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 4v4h4"/><path d="M12 7v5l3 2"/>',
			'shield'    => '<path d="M12 3l8 3v5c0 5-3.4 8.5-8 10-4.6-1.5-8-5-8-10V6Z"/><path d="m9 12 2 2 4-4"/>',
			'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21h-4v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6V3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1H21v4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
			'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
			'menu'      => '<path d="M4 6h16M4 12h16M4 18h16"/>',
			'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
			'chevron'   => '<path d="m9 18 6-6-6-6"/>',
			'external'  => '<path d="M14 3h7v7"/><path d="M21 3l-9 9"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
			'plus'      => '<path d="M12 5v14M5 12h14"/>',
			'check'     => '<path d="m4 12 5 5L20 6"/>',
			'alert'     => '<path d="M12 3 2 21h20Z"/><path d="M12 9v5M12 18h.01"/>',
			'home'      => '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>',
		);
		$path  = $paths[ $name ] ?? $paths['grid'];
		return sprintf(
			'<svg class="estora-ai" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			$size,
			$path
		);
	}
}
