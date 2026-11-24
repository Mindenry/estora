<?php
/**
 * Content management module.
 *
 * @package Estora\Core
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride -- template locals, not globals.

$pages        = wp_count_posts( 'page' );
$posts        = wp_count_posts( 'post' );
$testimonials = wp_count_posts( 'testimonial' );
$recent_pages = get_posts( array( 'post_type' => 'page', 'numberposts' => 5, 'post_status' => array( 'publish', 'draft', 'pending' ), 'orderby' => 'modified', 'order' => 'DESC' ) );
$recent_posts = get_posts( array( 'post_type' => 'post', 'numberposts' => 5, 'post_status' => array( 'publish', 'draft', 'pending' ), 'orderby' => 'modified', 'order' => 'DESC' ) );
$front_id     = (int) get_option( 'page_on_front' );

$estora_eyebrow     = __( 'Marketing', 'estora-core' );
$estora_title       = __( 'Content', 'estora-core' );
$estora_description = __( 'Pages, articles, testimonials and media remain in WordPress; open them here without exposing technical menus.', 'estora-core' );
$estora_actions     = '';
if ( current_user_can( 'edit_pages' ) ) {
	$estora_actions = '<a class="estora-btn" href="' . esc_url( admin_url( 'post-new.php?post_type=page' ) ) . '">' . esc_html__( 'New page', 'estora-core' ) . '</a>';
}
require __DIR__ . '/partials/header.php';
?>
	<main class="estora-screen" data-screen="content" aria-busy="false">
		<div class="estora-kpis estora-kpis--compact">
			<div class="estora-kpi"><span class="estora-kpi__label"><?php esc_html_e( 'Published pages', 'estora-core' ); ?></span><span class="estora-kpi__value"><?php echo esc_html( (string) ( $pages->publish ?? 0 ) ); ?></span></div>
			<div class="estora-kpi"><span class="estora-kpi__label"><?php esc_html_e( 'Published articles', 'estora-core' ); ?></span><span class="estora-kpi__value"><?php echo esc_html( (string) ( $posts->publish ?? 0 ) ); ?></span></div>
			<div class="estora-kpi"><span class="estora-kpi__label"><?php esc_html_e( 'Testimonials', 'estora-core' ); ?></span><span class="estora-kpi__value"><?php echo esc_html( (string) ( $testimonials->publish ?? 0 ) ); ?></span></div>
		</div>

		<div class="estora-contentgrid">
			<article class="estora-contentcard">
				<h3><?php esc_html_e( 'Pages', 'estora-core' ); ?></h3>
				<p><?php esc_html_e( 'Homepage, about, contact, legal and landing pages.', 'estora-core' ); ?></p>
				<?php if ( current_user_can( 'edit_pages' ) ) : ?>
					<a class="estora-btn estora-btn--sm estora-btn--ghost" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Manage pages', 'estora-core' ); ?></a>
				<?php endif; ?>
				<?php if ( $front_id && current_user_can( 'edit_page', $front_id ) ) : ?>
					<a class="estora-btn estora-btn--sm" href="<?php echo esc_url( get_edit_post_link( $front_id, '' ) ); ?>"><?php esc_html_e( 'Edit homepage', 'estora-core' ); ?></a>
				<?php endif; ?>
			</article>
			<article class="estora-contentcard">
				<h3><?php esc_html_e( 'Articles', 'estora-core' ); ?></h3>
				<p><?php esc_html_e( 'Market notes and guides shown on the public news page.', 'estora-core' ); ?></p>
				<?php if ( current_user_can( 'edit_posts' ) ) : ?>
					<a class="estora-btn estora-btn--sm estora-btn--ghost" href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'Manage articles', 'estora-core' ); ?></a>
					<a class="estora-btn estora-btn--sm" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'New article', 'estora-core' ); ?></a>
				<?php endif; ?>
			</article>
			<article class="estora-contentcard">
				<h3><?php esc_html_e( 'Testimonials', 'estora-core' ); ?></h3>
				<p><?php esc_html_e( 'Client quotes displayed on the homepage.', 'estora-core' ); ?></p>
				<?php if ( current_user_can( 'edit_posts' ) ) : ?>
					<a class="estora-btn estora-btn--sm estora-btn--ghost" href="<?php echo esc_url( admin_url( 'edit.php?post_type=testimonial' ) ); ?>"><?php esc_html_e( 'Manage testimonials', 'estora-core' ); ?></a>
				<?php endif; ?>
			</article>
			<article class="estora-contentcard">
				<h3><?php esc_html_e( 'Media library', 'estora-core' ); ?></h3>
				<p><?php esc_html_e( 'Property photography and article images.', 'estora-core' ); ?></p>
				<?php if ( current_user_can( 'upload_files' ) ) : ?>
					<a class="estora-btn estora-btn--sm estora-btn--ghost" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><?php esc_html_e( 'Open media', 'estora-core' ); ?></a>
				<?php endif; ?>
			</article>
			<article class="estora-contentcard">
				<h3><?php esc_html_e( 'Categories', 'estora-core' ); ?></h3>
				<p><?php esc_html_e( 'Group articles by market topic.', 'estora-core' ); ?></p>
				<?php if ( current_user_can( 'manage_categories' ) ) : ?>
					<a class="estora-btn estora-btn--sm estora-btn--ghost" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=category' ) ); ?>"><?php esc_html_e( 'Manage categories', 'estora-core' ); ?></a>
				<?php endif; ?>
			</article>
			<article class="estora-contentcard">
				<h3><?php esc_html_e( 'Company details', 'estora-core' ); ?></h3>
				<p><?php esc_html_e( 'Address, phone, hours and social links used across the site.', 'estora-core' ); ?></p>
				<?php if ( current_user_can( \Estora\Core\Support\Capabilities::MANAGE_SETTINGS ) ) : ?>
					<a class="estora-btn estora-btn--sm estora-btn--ghost" href="<?php echo esc_url( \Estora\Core\Admin\Admin_App::url( 'settings' ) ); ?>"><?php esc_html_e( 'Open settings', 'estora-core' ); ?></a>
				<?php endif; ?>
			</article>
		</div>

		<div class="estora-split">
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Recently edited pages', 'estora-core' ); ?></h2></div>
				<ul class="estora-inline-list">
					<?php foreach ( $recent_pages as $page ) : ?>
						<li>
							<span><?php echo esc_html( get_the_title( $page ) ); ?><br><span class="estora-muted"><?php echo esc_html( get_post_status_object( $page->post_status )->label ?? $page->post_status ); ?></span></span>
							<?php if ( current_user_can( 'edit_page', $page->ID ) ) : ?>
								<a class="estora-rowbtn" href="<?php echo esc_url( get_edit_post_link( $page->ID, '' ) ); ?>"><?php esc_html_e( 'Edit', 'estora-core' ); ?></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
			<section class="estora-panel">
				<div class="estora-panel__head"><h2><?php esc_html_e( 'Recently edited articles', 'estora-core' ); ?></h2></div>
				<ul class="estora-inline-list">
					<?php foreach ( $recent_posts as $post_item ) : ?>
						<li>
							<span><?php echo esc_html( get_the_title( $post_item ) ); ?><br><span class="estora-muted"><?php echo esc_html( get_the_date( '', $post_item ) ); ?></span></span>
							<?php if ( current_user_can( 'edit_post', $post_item->ID ) ) : ?>
								<a class="estora-rowbtn" href="<?php echo esc_url( get_edit_post_link( $post_item->ID, '' ) ); ?>"><?php esc_html_e( 'Edit', 'estora-core' ); ?></a>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		</div>
	</main>
</div>
