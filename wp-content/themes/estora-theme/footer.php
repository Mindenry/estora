<?php
/**
 * Theme footer.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;
?>
</main>

<footer class="estora-footer">
	<div class="estora-container">
		<div class="estora-footer__lead">
			<div class="estora-footer__brand">
				<a class="estora-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<span class="estora-brand__mark" aria-hidden="true"></span>
					<span class="estora-brand__word">ESTORA</span>
				</a>
				<p><?php echo esc_html( estora_company_text( 'company_tagline', 'Residential property, handled properly.' ) ); ?></p>
				<?php estora_language_switcher( 'estora-lang--footer' ); ?>
			</div>
			<div class="estora-footer__col">
				<h2><?php esc_html_e( 'Explore', 'estora-theme' ); ?></h2>
				<ul>
					<li><a href="<?php echo esc_url( estora_properties_url() ); ?>"><?php esc_html_e( 'All properties', 'estora-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( add_query_arg( 'purpose', 'sale', estora_properties_url() ) ); ?>"><?php esc_html_e( 'For sale', 'estora-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( add_query_arg( 'purpose', 'rent', estora_properties_url() ) ); ?>"><?php esc_html_e( 'For rent', 'estora-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( estora_page_url( 'favorites' ) ); ?>"><?php esc_html_e( 'Favorites', 'estora-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( estora_page_url( 'compare' ) ); ?>"><?php esc_html_e( 'Compare', 'estora-theme' ); ?></a></li>
				</ul>
			</div>
			<div class="estora-footer__col">
				<h2><?php esc_html_e( 'Company', 'estora-theme' ); ?></h2>
				<ul>
					<li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About us', 'estora-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/news/' ) ); ?>"><?php esc_html_e( 'News', 'estora-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>"><?php esc_html_e( 'Privacy policy', 'estora-theme' ); ?></a></li>
					<li><a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>"><?php esc_html_e( 'Terms of service', 'estora-theme' ); ?></a></li>
				</ul>
			</div>
			<div class="estora-footer__col estora-footer__contact">
				<h2><?php esc_html_e( 'Office', 'estora-theme' ); ?></h2>
				<p><?php echo esc_html( estora_company_text( 'company_address' ) ); ?></p>
				<p>
					<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', estora_company( 'company_phone' ) ) ); ?>"><?php echo esc_html( estora_company( 'company_phone' ) ); ?></a><br>
					<a href="<?php echo esc_url( 'mailto:' . estora_company( 'company_email' ) ); ?>"><?php echo esc_html( estora_company( 'company_email' ) ); ?></a>
				</p>
				<p class="estora-footer__hours"><?php echo esc_html( estora_company_text( 'company_hours_text' ) ); ?></p>
			</div>
		</div>

		<div class="estora-footer__bottom">
			<span>© <?php echo esc_html( date_i18n( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>. <?php esc_html_e( 'All rights reserved.', 'estora-theme' ); ?></span>
			<span class="estora-footer__legal">
				<a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'estora-theme' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>"><?php esc_html_e( 'Terms', 'estora-theme' ); ?></a>
				<span><?php esc_html_e( 'Listings are subject to availability.', 'estora-theme' ); ?></span>
				<a href="https://unsplash.com" target="_blank" rel="noopener noreferrer"><?php echo esc_html( estora_text( 'Demo photography via Unsplash', 'ภาพประกอบตัวอย่างจาก Unsplash' ) ); ?></a>
			</span>
		</div>
	</div>
</footer>

<div class="estora-toast" data-estora-toast hidden role="status" aria-live="polite"></div>
<?php wp_footer(); ?>
</body>
</html>
