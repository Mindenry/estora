<?php
/**
 * ESTORA application shell.
 *
 * @package Estora\Core
 * @var array<string,mixed> $estora_shell
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride -- template locals, not globals.

$shell     = $estora_shell;
$user      = $shell['user'];
$route     = $shell['route'];
$view_file = ESTORA_CORE_DIR . '/admin/views/' . $shell['definition']['view'] . '.php';
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'estora-app-body' ); ?>>
<?php wp_body_open(); ?>
<a class="estora-app-skip" href="#estora-app-main"><?php esc_html_e( 'Skip to content', 'estora-core' ); ?></a>

<div class="estora-shell" data-shell>
	<aside class="estora-sidebar" id="estora-sidebar" aria-label="<?php esc_attr_e( 'ESTORA navigation', 'estora-core' ); ?>">
		<div class="estora-sidebar__brand">
			<a href="<?php echo esc_url( \Estora\Core\Admin\Admin_App::url( 'overview' ) ); ?>" class="estora-brand" aria-label="<?php esc_attr_e( 'ESTORA overview', 'estora-core' ); ?>">
				<span class="estora-brand__mark" aria-hidden="true"></span>
				<span class="estora-brand__word">ESTORA</span>
			</a>
			<span class="estora-sidebar__product"><?php esc_html_e( 'Back office', 'estora-core' ); ?></span>
		</div>

		<nav class="estora-sidebar__nav">
			<?php foreach ( $shell['nav_groups'] as $group ) : ?>
				<div class="estora-navgroup">
					<p class="estora-navgroup__label"><?php echo esc_html( $group['label'] ); ?></p>
					<?php foreach ( $group['items'] as $item ) : ?>
						<a class="estora-navlink<?php echo $item['active'] ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( $item['url'] ); ?>"
							<?php echo $item['active'] ? 'aria-current="page"' : ''; ?>
							title="<?php echo esc_attr( $item['label'] ); ?>">
							<?php echo \Estora\Core\Admin\Admin_App::icon( $item['icon'], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span><?php echo esc_html( $item['label'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</nav>

		<div class="estora-sidebar__foot">
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a class="estora-navlink estora-navlink--muted" href="<?php echo esc_url( admin_url() ); ?>">
					<?php echo \Estora\Core\Admin\Admin_App::icon( 'external', 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( 'WordPress admin', 'estora-core' ); ?></span>
				</a>
			<?php endif; ?>
			<p class="estora-sidebar__version">v<?php echo esc_html( ESTORA_CORE_VERSION ); ?></p>
		</div>
	</aside>

	<div class="estora-app-scrim" data-app-scrim hidden></div>

	<div class="estora-app-main">
		<header class="estora-appbar">
			<div class="estora-appbar__left">
				<button type="button" class="estora-appbar__menu" data-app-nav-toggle aria-expanded="false" aria-label="<?php esc_attr_e( 'Toggle navigation', 'estora-core' ); ?>">
					<?php echo \Estora\Core\Admin\Admin_App::icon( 'menu', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
				<nav class="estora-appcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'estora-core' ); ?>">
					<ol>
						<?php foreach ( $shell['breadcrumbs'] as $index => $crumb ) : ?>
							<li>
								<?php if ( ! empty( $crumb['url'] ) && $index < count( $shell['breadcrumbs'] ) - 1 ) : ?>
									<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
								<?php else : ?>
									<span <?php echo $index === count( $shell['breadcrumbs'] ) - 1 ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $crumb['label'] ); ?></span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>
				</nav>
			</div>

			<form class="estora-appsearch" method="get" action="<?php echo esc_url( $shell['search_url'] ); ?>" role="search">
				<label class="screen-reader-text" for="estora-app-search"><?php esc_html_e( 'Search records', 'estora-core' ); ?></label>
				<?php echo \Estora\Core\Admin\Admin_App::icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input id="estora-app-search" type="search" name="q" placeholder="<?php esc_attr_e( 'Search records…', 'estora-core' ); ?>" value="<?php echo esc_attr( isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
			</form>

			<div class="estora-appbar__actions">
				<div class="estora-notifications">
					<button type="button" class="estora-iconbtn" data-notifications-toggle aria-label="<?php esc_attr_e( 'Notifications', 'estora-core' ); ?>" aria-expanded="false">
						<?php echo \Estora\Core\Admin\Admin_App::icon( 'bell', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span class="estora-notifications__count" data-notifications-count hidden>0</span>
					</button>
					<div class="estora-notifications__panel" data-notifications-panel hidden>
						<div class="estora-notifications__head">
							<strong><?php esc_html_e( 'Notifications', 'estora-core' ); ?></strong>
							<button type="button" class="estora-linkbtn" data-notifications-read-all><?php esc_html_e( 'Mark all read', 'estora-core' ); ?></button>
						</div>
						<ul data-notifications-list></ul>
					</div>
				</div>

				<div class="estora-usermenu">
					<button type="button" class="estora-usermenu__toggle" data-user-menu-toggle aria-expanded="false">
						<span class="estora-avatar" aria-hidden="true"><?php echo esc_html( $shell['initials'] ); ?></span>
						<span class="estora-usermenu__meta">
							<strong><?php echo esc_html( $user->display_name ); ?></strong>
							<small><?php echo esc_html( $shell['role_label'] ); ?></small>
						</span>
						<?php echo \Estora\Core\Admin\Admin_App::icon( 'chevron', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>
					<div class="estora-usermenu__panel" data-user-menu-panel hidden>
						<div class="estora-usermenu__head">
							<span class="estora-avatar estora-avatar--lg" aria-hidden="true"><?php echo esc_html( $shell['initials'] ); ?></span>
							<div>
								<strong><?php echo esc_html( $user->display_name ); ?></strong>
								<span><?php echo esc_html( $user->user_email ); ?></span>
								<em><?php echo esc_html( $shell['role_label'] ); ?></em>
							</div>
						</div>
						<a href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>"><?php esc_html_e( 'Profile & password', 'estora-core' ); ?></a>
						<?php if ( current_user_can( 'manage_options' ) ) : ?>
							<a href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'WordPress administration', 'estora-core' ); ?></a>
						<?php endif; ?>
						<a class="estora-usermenu__logout" href="<?php echo esc_url( wp_logout_url( wp_login_url( \Estora\Core\Admin\Admin_App::url( 'overview' ) ) ) ); ?>">
							<?php echo \Estora\Core\Admin\Admin_App::icon( 'logout', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php esc_html_e( 'Log out', 'estora-core' ); ?>
						</a>
					</div>
				</div>
			</div>
		</header>

		<main class="estora-appcontent" id="estora-app-main" tabindex="-1">
			<?php if ( is_readable( $view_file ) ) : ?>
				<?php require $view_file; ?>
			<?php else : ?>
				<div class="estora-panel"><p><?php esc_html_e( 'This screen could not be loaded.', 'estora-core' ); ?></p></div>
			<?php endif; ?>
		</main>
	</div>
</div>

<div class="estora-toast" data-toast hidden role="status" aria-live="polite"></div>
<?php wp_footer(); ?>
</body>
</html>
