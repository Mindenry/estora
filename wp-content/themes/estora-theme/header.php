<?php
/**
 * Theme header.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="estora-header" data-header>
	<div class="estora-container estora-header__inner">
		<a class="estora-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php esc_attr_e( 'ESTORA home', 'estora-theme' ); ?>">
			<span class="estora-brand__mark" aria-hidden="true"></span>
			<span class="estora-brand__word">ESTORA</span>
			<span class="estora-brand__tag d-none d-xl-inline"><?php echo esc_html( estora_company_text( 'company_tagline', 'Residential property, handled properly.' ) ); ?></span>
		</a>

		<nav class="estora-nav" id="estora-nav" data-nav aria-label="<?php esc_attr_e( 'Primary', 'estora-theme' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'estora-nav__list',
					'fallback_cb'    => 'estora_nav_fallback',
					'depth'          => 1,
				)
			);
			?>
			<div class="estora-nav__lang"><?php estora_language_switcher(); ?></div>
			<a class="estora-btn estora-nav__cta" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html( estora_text( 'Book a viewing', 'นัดชมทรัพย์' ) ); ?> <?php echo estora_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
		</nav>

		<div class="estora-header__actions">
			<?php estora_language_switcher( 'estora-lang--header' ); ?>
			<a class="estora-header__fav" href="<?php echo esc_url( estora_page_url( 'favorites' ) ); ?>" aria-label="<?php esc_attr_e( 'Saved properties', 'estora-theme' ); ?>">
				<?php echo estora_icon( 'heart', 17 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="estora-header__fav-label"><?php echo esc_html( estora_text( 'Saved', 'รายการโปรด' ) ); ?></span>
				<span class="count" data-fav-count>0</span>
			</a>
			<a class="estora-btn estora-btn--header d-none d-lg-inline-flex" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html( estora_text( 'Book a viewing', 'นัดชมทรัพย์' ) ); ?> <?php echo estora_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			<button type="button" class="estora-menu-toggle" data-nav-toggle aria-expanded="false" aria-controls="estora-nav" aria-label="<?php esc_attr_e( 'Open menu', 'estora-theme' ); ?>">
				<span aria-hidden="true"></span>
			</button>
		</div>
	</div>
</header>

<?php
/* Nav fallback lives in functions.php (estora_nav_fallback). */
?>
<main id="estora-main" tabindex="-1">
