<?php
/**
 * Template Name: About
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

$estora_agents = get_users(
	array(
		'role'    => 'estora_sales_agent',
		'number'  => 6,
		'orderby' => 'display_name',
	)
);
$estora_stats = array( 'published' => 0 );
if ( class_exists( '\Estora\Core\Repositories\Property_Repository' ) ) {
	$estora_stats = ( new \Estora\Core\Repositories\Property_Repository() )->stats();
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<section class="estora-pagehead">
		<div class="estora-container">
			<?php estora_breadcrumbs( array( array( 'label' => get_the_title() ) ) ); ?>
			<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( estora_text( 'About', 'เกี่ยวกับเรา' ) ); ?></p>
			<h1><?php the_title(); ?></h1>
			<p class="estora-muted" style="max-width:620px"><?php echo esc_html( estora_company_text( 'company_tagline' ) ); ?></p>
		</div>
	</section>

	<figure class="estora-about-media">
		<img src="<?php echo esc_url( estora_theme_asset( 'img/photos/chiangmai-house.jpg' ) ); ?>" alt="" width="1600" height="1200" loading="lazy">
	</figure>

	<article class="estora-container estora-section">
		<div class="estora-split">
			<div class="estora-page__body">
				<?php the_content(); ?>
			</div>
			<div>
				<div class="estora-panel">
					<p class="estora-eyebrow"><?php echo esc_html( estora_text( 'At a glance', 'ภาพรวม' ) ); ?></p>
					<ul class="estora-amenities">
						<?php /* translators: %s: number of live listings */ ?>
						<li><?php echo estora_icon( 'grid', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.I18n.MissingTranslatorsComment ?><?php echo esc_html( sprintf( __( '%s live listings', 'estora-theme' ), number_format_i18n( (int) $estora_stats['published'] ) ) ); ?></li>
						<li><?php echo estora_icon( 'pin', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( estora_text( 'Bangkok · Chiang Mai · Phuket · Hua Hin · Pattaya · Khon Kaen', 'กรุงเทพฯ · เชียงใหม่ · ภูเก็ต · หัวหิน · พัทยา · ขอนแก่น' ) ); ?></li>
						<li><?php echo estora_icon( 'phone', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( estora_company( 'company_phone' ) ); ?></li>
						<li><?php echo estora_icon( 'mail', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( estora_company( 'company_email' ) ); ?></li>
						<li><?php echo estora_icon( 'calendar', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( estora_text( 'Monday–Saturday, 09:00–18:00', 'จันทร์–เสาร์ 09:00–18:00' ) ); ?></li>
					</ul>
					<p style="margin-top:16px"><a class="estora-btn" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact the office', 'estora-theme' ); ?></a></p>
				</div>
			</div>
		</div>
	</article>

	<?php if ( $estora_agents ) : ?>
		<section class="estora-section estora-section--sand">
			<div class="estora-container">
				<?php estora_section_head( estora_text( 'Team', 'ทีมงาน' ), estora_text( 'The people who hold the file', 'คนที่ดูแลแฟ้มของคุณ' ), '', '', '01' ); ?>
				<div class="estora-grid estora-grid--3">
					<?php foreach ( $estora_agents as $estora_user ) : ?>
						<?php $estora_profile = estora_agent_profile( $estora_user->ID ); ?>
						<article class="estora-panel">
							<div class="estora-agent">
								<div class="estora-agent__avatar" aria-hidden="true">
									<span><?php echo esc_html( \Estora\Core\Support\Formatter::initials( $estora_user->display_name ) ); ?></span>
								</div>
								<div>
									<h3 style="margin-bottom:2px"><?php echo esc_html( $estora_user->display_name ); ?></h3>
									<p class="estora-muted" style="margin:0"><?php echo esc_html( estora_role_title( (string) $estora_profile['title'] ) ); ?></p>
								</div>
							</div>
							<p class="estora-muted" style="margin-top:14px"><?php echo esc_html( $estora_profile['phone'] ); ?></p>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>
	<?php
endwhile;

get_footer();
