<?php
/**
 * Template Name: Contact
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<section class="estora-pagehead">
		<div class="estora-container">
			<?php estora_breadcrumbs( array( array( 'label' => get_the_title() ) ) ); ?>
			<p class="estora-eyebrow"><span aria-hidden="true"></span><?php esc_html_e( 'Contact', 'estora-theme' ); ?></p>
			<h1><?php the_title(); ?></h1>
		</div>
	</section>

	<div class="estora-container estora-section" style="display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:clamp(24px,5vw,64px);align-items:start">
		<div class="estora-panel">
			<h2><?php esc_html_e( 'Tell us what you need', 'estora-theme' ); ?></h2>
			<div class="estora-page__body" style="margin-bottom:16px"><?php the_content(); ?></div>
			<form class="estora-form" data-inquiry-form>
				<input type="hidden" name="property_id" value="0">
				<div class="estora-form__row">
					<label><?php esc_html_e( 'Full name', 'estora-theme' ); ?><input name="name" autocomplete="name" required></label>
					<label><?php esc_html_e( 'Phone', 'estora-theme' ); ?><input name="phone" type="tel" autocomplete="tel" required></label>
				</div>
				<label><?php esc_html_e( 'Email', 'estora-theme' ); ?><input name="email" type="email" autocomplete="email"></label>
				<label><?php esc_html_e( 'What are you looking for?', 'estora-theme' ); ?><textarea name="message" rows="5" required placeholder="<?php esc_attr_e( 'Budget, area, number of bedrooms, timing…', 'estora-theme' ); ?>"></textarea></label>
				<div class="estora-hp" aria-hidden="true"><label><?php esc_html_e( 'Company', 'estora-theme' ); ?><input name="company" tabindex="-1" autocomplete="off"></label></div>
				<label class="estora-form__consent"><input type="checkbox" name="consent" value="1" required> <?php esc_html_e( 'I agree to be contacted about my inquiry.', 'estora-theme' ); ?></label>
				<p class="estora-form__error" data-form-error role="alert" hidden></p>
				<p class="estora-form__success" data-form-success role="status" hidden></p>
				<button class="estora-btn" type="submit"><?php esc_html_e( 'Send message', 'estora-theme' ); ?></button>
				<noscript><p class="estora-muted"><?php esc_html_e( 'JavaScript is required for this form. Please call or email the office.', 'estora-theme' ); ?></p></noscript>
			</form>
		</div>

		<div class="estora-stack" style="display:grid;gap:16px">
			<div class="estora-panel">
				<h2><?php esc_html_e( 'Office', 'estora-theme' ); ?></h2>
				<p><?php echo esc_html( estora_company( 'company_address' ) ); ?></p>
				<p>
					<a class="estora-textlink" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', estora_company( 'company_phone' ) ) ); ?>"><?php echo estora_icon( 'phone', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( estora_company( 'company_phone' ) ); ?></a><br>
					<a class="estora-textlink" href="<?php echo esc_url( 'mailto:' . estora_company( 'company_email' ) ); ?>"><?php echo estora_icon( 'mail', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( estora_company( 'company_email' ) ); ?></a>
				</p>
				<p class="estora-muted"><?php echo esc_html( estora_company( 'company_hours_text' ) ); ?></p>
			</div>
			<div class="estora-panel">
				<h2><?php esc_html_e( 'Buying or selling?', 'estora-theme' ); ?></h2>
				<p class="estora-muted"><?php esc_html_e( 'For a valuation or a listing brief, include the address and the timing. We will come back with a realistic figure, not the highest one.', 'estora-theme' ); ?></p>
				<a class="estora-btn estora-btn--ghost" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php esc_html_e( 'See current listings', 'estora-theme' ); ?></a>
			</div>
		</div>
	</div>
	<?php
endwhile;

get_footer();
