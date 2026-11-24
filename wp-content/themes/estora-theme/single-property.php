<?php
/**
 * Single property.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

while ( have_posts() ) :
	the_post();
	$estora_id   = get_the_ID();
	$estora_data = function_exists( 'estora_property_data' ) ? estora_property_data( (int) $estora_id ) : null;
	if ( ! $estora_data ) {
		continue;
	}

	$estora_gallery = $estora_data['gallery'];
	if ( empty( $estora_gallery ) ) {
		$estora_gallery = array( $estora_data['image'] );
	}
	$estora_gallery = array_slice( $estora_gallery, 0, 5 );
	$estora_similar = estora_public_properties(
		array(
			'type'     => $estora_data['type'],
			'exclude'  => array( $estora_id ),
			'per_page' => 3,
			'sort'     => 'newest',
		)
	);

	get_header();
	?>
	<div class="estora-container estora-property-crumbs">
		<?php
		estora_breadcrumbs(
			array(
				array( 'label' => __( 'Properties', 'estora-theme' ), 'url' => estora_properties_url() ),
				array( 'label' => get_the_title() ),
			)
		);
		?>
	</div>

	<article class="estora-container" id="property-<?php echo esc_attr( (string) $estora_id ); ?>">
		<header class="estora-property-head">
			<div>
				<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( $estora_data['purpose_label'] ); ?> · <?php echo esc_html( $estora_data['type_label'] ); ?></p>
				<h1><?php the_title(); ?></h1>
				<p class="estora-property-head__ref">
					<?php echo esc_html( $estora_data['reference'] ); ?> ·
					<?php echo esc_html( trim( implode( ', ', array_unique( array_filter( array( $estora_data['location']['subdistrict'], $estora_data['location']['district'], $estora_data['location']['province'] ) ) ) ) ) ); ?>
					· <span class="estora-badge estora-badge--<?php echo esc_attr( $estora_data['availability'] ); ?>"><?php echo esc_html( $estora_data['availability_label'] ); ?></span>
				</p>
			</div>
			<div class="estora-property-head__price">
				<strong><?php echo esc_html( $estora_data['price_formatted'] ); ?></strong>
				<span><?php echo 'rent' === $estora_data['purpose'] ? esc_html( estora_text( 'per month', 'ต่อเดือน' ) ) : esc_html__( 'Sale price', 'estora-theme' ); ?></span>
				<button type="button" class="estora-fav estora-fav--inline" data-favorite="<?php echo esc_attr( (string) $estora_id ); ?>" aria-label="<?php esc_attr_e( 'Save property', 'estora-theme' ); ?>" aria-pressed="false">
					<?php echo estora_icon( 'heart', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</button>
			</div>
		</header>

		<div class="estora-gallery<?php echo count( $estora_gallery ) > 1 ? '' : ' estora-gallery--single'; ?>" data-gallery>
			<div class="estora-gallery__main" data-gallery-main>
				<img src="<?php echo esc_url( $estora_gallery[0]['url'] ); ?>" alt="<?php echo esc_attr( $estora_gallery[0]['alt'] ?: get_the_title() ); ?>" width="1600" height="1000" fetchpriority="high">
			</div>
			<?php if ( count( $estora_gallery ) > 1 ) : ?>
				<div class="estora-gallery__thumbs">
					<?php foreach ( $estora_gallery as $estora_index => $estora_image ) : ?>
						<button type="button" class="estora-gallery__thumb<?php echo 0 === $estora_index ? ' is-active' : ''; ?>" data-gallery-thumb data-src="<?php echo esc_url( $estora_image['url'] ); ?>" data-alt="<?php echo esc_attr( $estora_image['alt'] ?: get_the_title() ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d image number */ __( 'View image %d', 'estora-theme' ), $estora_index + 1 ) ); ?>">
							<img src="<?php echo esc_url( $estora_image['url'] ); ?>" alt="" width="400" height="400" loading="lazy">
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="estora-property-layout">
			<div class="estora-property-body">
				<div class="estora-specstrip">
					<div><span><?php esc_html_e( 'Bedrooms', 'estora-theme' ); ?></span><strong><?php echo esc_html( $estora_data['bedrooms'] ? (string) $estora_data['bedrooms'] : '—' ); ?></strong></div>
					<div><span><?php esc_html_e( 'Bathrooms', 'estora-theme' ); ?></span><strong><?php echo esc_html( $estora_data['bathrooms'] ? (string) $estora_data['bathrooms'] : '—' ); ?></strong></div>
					<div><span><?php esc_html_e( 'Usable area', 'estora-theme' ); ?></span><strong><?php echo esc_html( $estora_data['usable_area'] ? number_format_i18n( $estora_data['usable_area'] ) . ' m²' : '—' ); ?></strong></div>
					<div><span><?php esc_html_e( 'Land area', 'estora-theme' ); ?></span><strong><?php echo esc_html( $estora_data['land_area'] ? number_format_i18n( $estora_data['land_area'] ) . ' m²' : '—' ); ?></strong></div>
				</div>

				<section>
					<h2><?php esc_html_e( 'The property', 'estora-theme' ); ?></h2>
					<div class="estora-prose"><?php echo wp_kses_post( apply_filters( 'the_content', $estora_data['description'] ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter. ?></div>
				</section>

				<section>
					<h2><?php esc_html_e( 'Specification', 'estora-theme' ); ?></h2>
					<ul class="estora-amenities">
						<?php /* translators: %s: number of bedrooms */ ?>
						<li><?php echo estora_icon( 'bed', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.I18n.MissingTranslatorsComment ?><?php echo esc_html( sprintf( __( '%s bedrooms', 'estora-theme' ), number_format_i18n( $estora_data['bedrooms'] ) ) ); ?></li>
						<?php /* translators: %s: number of bathrooms */ ?>
						<li><?php echo estora_icon( 'bath', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.I18n.MissingTranslatorsComment ?><?php echo esc_html( sprintf( __( '%s bathrooms', 'estora-theme' ), number_format_i18n( $estora_data['bathrooms'] ) ) ); ?></li>
						<?php /* translators: %s: number of parking spaces */ ?>
						<li><?php echo estora_icon( 'parking', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.I18n.MissingTranslatorsComment ?><?php echo esc_html( sprintf( __( '%s parking spaces', 'estora-theme' ), number_format_i18n( $estora_data['parking'] ) ) ); ?></li>
						<?php /* translators: %s: number of floors */ ?>
						<li><?php echo estora_icon( 'floor', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.I18n.MissingTranslatorsComment ?><?php echo esc_html( $estora_data['floors'] ? sprintf( __( '%s floors', 'estora-theme' ), number_format_i18n( $estora_data['floors'] ) ) : __( 'Single level', 'estora-theme' ) ); ?></li>
						<?php if ( $estora_data['year_built'] ) : ?>
							<?php /* translators: %d: construction year */ ?>
							<li><?php echo estora_icon( 'calendar', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.I18n.MissingTranslatorsComment ?><?php echo esc_html( sprintf( __( 'Built %d', 'estora-theme' ), (int) $estora_data['year_built'] ) ); ?></li>
						<?php endif; ?>
						<?php /* translators: %s: property reference code */ ?>
						<li><?php echo estora_icon( 'area', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.I18n.MissingTranslatorsComment ?><?php echo esc_html( sprintf( __( 'Reference %s', 'estora-theme' ), $estora_data['reference'] ) ); ?></li>
					</ul>
				</section>

				<?php if ( ! empty( $estora_data['amenities'] ) ) : ?>
					<section>
						<h2><?php esc_html_e( 'Amenities', 'estora-theme' ); ?></h2>
						<ul class="estora-amenities">
							<?php foreach ( $estora_data['amenities'] as $estora_amenity ) : ?>
								<li><?php echo estora_icon( 'check', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo esc_html( $estora_amenity ); ?></li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php if ( 'sale' === $estora_data['purpose'] && (float) $estora_data['price'] > 0 ) : ?>
					<section class="estora-calc" data-mortgage data-price="<?php echo esc_attr( (string) (float) $estora_data['price'] ); ?>">
						<h2><?php echo esc_html( estora_text( 'Estimate the monthly payment', 'ประมาณการค่างวดต่อเดือน' ) ); ?></h2>
						<p class="estora-calc__lede"><?php echo esc_html( estora_text( 'An indicative figure based on a standard amortising loan. Bank rates, fees and approval terms vary — the office can introduce you to a lender.', 'ตัวเลขประมาณการจากสินเชื่อผ่อนชำระแบบมาตรฐาน อัตราดอกเบี้ย ค่าธรรมเนียม และเงื่อนไขอนุมัติของแต่ละธนาคารแตกต่างกัน ทีมงานแนะนำแหล่งสินเชื่อได้' ) ); ?></p>
						<div class="estora-calc__grid">
							<label class="estora-calc__field">
								<span><?php echo esc_html( estora_text( 'Property price', 'ราคาทรัพย์' ) ); ?></span>
								<input type="number" min="0" step="100000" value="<?php echo esc_attr( (string) (int) $estora_data['price'] ); ?>" data-calc-price>
							</label>
							<label class="estora-calc__field">
								<span><?php echo esc_html( estora_text( 'Down payment', 'เงินดาวน์' ) ); ?> <b data-calc-down-label>20%</b></span>
								<input type="range" min="0" max="60" step="5" value="20" data-calc-down>
								<output data-calc-down-value></output>
							</label>
							<label class="estora-calc__field">
								<span><?php echo esc_html( estora_text( 'Interest rate (% per year)', 'ดอกเบี้ย (% ต่อปี)' ) ); ?></span>
								<input type="number" min="0" max="20" step="0.1" value="6.5" data-calc-rate>
							</label>
							<label class="estora-calc__field">
								<span><?php echo esc_html( estora_text( 'Term', 'ระยะเวลาผ่อน' ) ); ?></span>
								<select data-calc-years>
									<?php foreach ( array( 10, 15, 20, 25, 30 ) as $estora_years ) : ?>
										<option value="<?php echo esc_attr( (string) $estora_years ); ?>" <?php selected( 30, $estora_years ); ?>><?php echo esc_html( sprintf( estora_text( '%d years', '%d ปี' ), $estora_years ) ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						</div>
						<div class="estora-calc__result">
							<div>
								<span><?php echo esc_html( estora_text( 'Estimated monthly payment', 'ค่างวดประมาณต่อเดือน' ) ); ?></span>
								<strong data-calc-out>—</strong>
							</div>
							<p data-calc-summary></p>
						</div>
					</section>
				<?php endif; ?>

				<section>
					<h2><?php esc_html_e( 'Location', 'estora-theme' ); ?></h2>
					<p><?php echo esc_html( $estora_data['address'] ); ?><?php echo $estora_data['location']['postal_code'] ? ', ' . esc_html( $estora_data['location']['postal_code'] ) : ''; ?></p>
					<div class="estora-map">
						<div>
							<strong><?php echo esc_html( trim( implode( ' · ', array_unique( array_filter( array( $estora_data['location']['district'], $estora_data['location']['province'] ) ) ) ) ) ); ?></strong>
							<?php if ( $estora_data['map'] ) : ?>
								<p class="estora-map__coords"><?php echo esc_html( number_format( (float) $estora_data['map']['lat'], 5 ) . ', ' . number_format( (float) $estora_data['map']['lng'], 5 ) ); ?></p>
							<?php endif; ?>
						</div>
						<?php if ( $estora_data['map'] ) : ?>
							<a class="estora-btn estora-btn--ghost estora-btn--sm" href="<?php echo esc_url( $estora_data['map']['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo estora_icon( 'pin', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php esc_html_e( 'Open in OpenStreetMap', 'estora-theme' ); ?></a>
						<?php endif; ?>
					</div>
					<p class="estora-muted" style="margin-top:10px"><?php esc_html_e( 'Exact viewing point is shared on confirmation. Location marker is approximate.', 'estora-theme' ); ?></p>
				</section>

				<?php if ( $estora_data['video_url'] ) : ?>
					<section>
						<h2><?php esc_html_e( 'Video tour', 'estora-theme' ); ?></h2>
						<p><a class="estora-textlink" href="<?php echo esc_url( $estora_data['video_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open the video tour', 'estora-theme' ); ?> <?php echo estora_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></p>
					</section>
				<?php endif; ?>

				<div class="estora-share" style="margin-top:28px">
					<?php estora_share( get_permalink(), get_the_title() ); ?>
					<button type="button" class="estora-compare-btn" data-compare="<?php echo esc_attr( (string) $estora_id ); ?>"><?php esc_html_e( 'Compare', 'estora-theme' ); ?></button>
				</div>
			</div>

			<aside class="estora-sidebar">
				<div class="estora-panel">
					<?php if ( $estora_data['agent'] ) : ?>
						<?php estora_agent_card( $estora_data['agent'] ); ?>
					<?php else : ?>
						<h2><?php esc_html_e( 'Talk to the office', 'estora-theme' ); ?></h2>
						<p class="estora-muted"><?php esc_html_e( 'This listing is handled by the office team.', 'estora-theme' ); ?></p>
						<a class="estora-btn" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', estora_company( 'company_phone' ) ) ); ?>"><?php echo esc_html( estora_company( 'company_phone' ) ); ?></a>
					<?php endif; ?>
				</div>

				<?php if ( $estora_data['accepts_inquiries'] ) : ?>
					<div class="estora-panel">
						<h2><?php esc_html_e( 'Ask about this property', 'estora-theme' ); ?></h2>
						<form class="estora-form" data-inquiry-form>
							<input type="hidden" name="property_id" value="<?php echo esc_attr( (string) $estora_id ); ?>">
							<label><?php esc_html_e( 'Full name', 'estora-theme' ); ?><input name="name" autocomplete="name" required></label>
							<div class="estora-form__row">
								<label><?php esc_html_e( 'Phone', 'estora-theme' ); ?><input name="phone" type="tel" autocomplete="tel" required></label>
								<label><?php esc_html_e( 'Email', 'estora-theme' ); ?><input name="email" type="email" autocomplete="email"></label>
							</div>
							<label><?php esc_html_e( 'Message', 'estora-theme' ); ?><textarea name="message" rows="3" required placeholder="<?php esc_attr_e( 'What would you like to know?', 'estora-theme' ); ?>"></textarea></label>
							<div class="estora-hp" aria-hidden="true">
								<label><?php esc_html_e( 'Company', 'estora-theme' ); ?><input name="company" tabindex="-1" autocomplete="off"></label>
							</div>
							<label class="estora-form__consent"><input type="checkbox" name="consent" value="1" required> <?php esc_html_e( 'I agree to be contacted about this property.', 'estora-theme' ); ?></label>
							<p class="estora-form__error" data-form-error role="alert" hidden></p>
							<p class="estora-form__success" data-form-success role="status" hidden></p>
							<button class="estora-btn" type="submit"><?php esc_html_e( 'Send inquiry', 'estora-theme' ); ?></button>
							<noscript><p class="estora-muted"><?php esc_html_e( 'JavaScript is required for this form. Call or email the office instead.', 'estora-theme' ); ?></p></noscript>
						</form>
					</div>

					<div class="estora-panel">
						<h2><?php esc_html_e( 'Request a viewing', 'estora-theme' ); ?></h2>
						<form class="estora-form" data-appointment-form data-property-id="<?php echo esc_attr( (string) $estora_id ); ?>">
							<input type="hidden" name="property_id" value="<?php echo esc_attr( (string) $estora_id ); ?>">
							<input type="hidden" name="time" value="" data-time-input>
							<label><?php esc_html_e( 'Preferred date', 'estora-theme' ); ?><input type="date" name="date" required></label>
							<div data-slots class="estora-slots"><p class="estora-slots__empty"><?php esc_html_e( 'Choose a date to see available times.', 'estora-theme' ); ?></p></div>
							<div class="estora-form__row">
								<label><?php esc_html_e( 'Name', 'estora-theme' ); ?><input name="name" autocomplete="name" required></label>
								<label><?php esc_html_e( 'Phone', 'estora-theme' ); ?><input name="phone" type="tel" autocomplete="tel" required></label>
							</div>
							<label><?php esc_html_e( 'Email', 'estora-theme' ); ?><input name="email" type="email" autocomplete="email"></label>
							<label><?php esc_html_e( 'Anything we should know?', 'estora-theme' ); ?><textarea name="message" rows="2"></textarea></label>
							<div class="estora-hp" aria-hidden="true"><label><?php esc_html_e( 'Company', 'estora-theme' ); ?><input name="company" tabindex="-1" autocomplete="off"></label></div>
							<label class="estora-form__consent"><input type="checkbox" name="consent" value="1" required> <?php esc_html_e( 'I agree to be contacted to arrange this viewing.', 'estora-theme' ); ?></label>
							<p class="estora-form__error" data-form-error role="alert" hidden></p>
							<p class="estora-form__success" data-form-success role="status" hidden></p>
							<button class="estora-btn" type="submit"><?php esc_html_e( 'Request viewing', 'estora-theme' ); ?></button>
						</form>
					</div>
				<?php else : ?>
					<div class="estora-panel">
						<p class="estora-availability-note"><?php esc_html_e( 'This property is no longer accepting inquiries because it is sold, rented or unavailable.', 'estora-theme' ); ?> <?php esc_html_e( 'The office can suggest alternatives.', 'estora-theme' ); ?></p>
						<p style="margin-top:12px"><a class="estora-btn" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php esc_html_e( 'Browse available homes', 'estora-theme' ); ?></a></p>
					</div>
				<?php endif; ?>
			</aside>
		</div>
	</article>

	<?php if ( ! empty( $estora_similar['items'] ) ) : ?>
		<section class="estora-section estora-section--sand">
			<div class="estora-container">
				<?php estora_section_head( __( 'Similar', 'estora-theme' ), __( 'Other properties of this type', 'estora-theme' ), estora_properties_url(), __( 'All properties', 'estora-theme' ) ); ?>
				<div class="estora-grid estora-grid--3">
					<?php foreach ( $estora_similar['items'] as $estora_property ) : ?>
						<?php estora_property_card( $estora_property ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php
endwhile;

get_footer();
