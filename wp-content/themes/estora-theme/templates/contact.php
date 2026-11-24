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
			<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( estora_text( 'Contact', 'ติดต่อเรา' ) ); ?></p>
			<h1><?php the_title(); ?></h1>
			<p class="estora-muted" style="max-width:620px"><?php echo esc_html( estora_text( 'Tell us what you are looking for. We answer within one business day, Monday to Saturday.', 'บอกเราว่าคุณกำลังมองหาอะไร เราตอบกลับภายในหนึ่งวันทำการ จันทร์ถึงเสาร์' ) ); ?></p>
		</div>
	</section>

	<div class="estora-container estora-section estora-contact-grid">
		<div class="estora-panel">
			<h2><?php echo esc_html( estora_text( 'Send a brief', 'ส่งบรีฟให้เรา' ) ); ?></h2>
			<form class="estora-form" data-inquiry-form>
				<input type="hidden" name="property_id" value="0">
				<div class="estora-form__row">
					<label><?php esc_html_e( 'Full name', 'estora-theme' ); ?><input name="name" autocomplete="name" required></label>
					<label><?php esc_html_e( 'Phone', 'estora-theme' ); ?><input name="phone" type="tel" autocomplete="tel" required></label>
				</div>
				<label><?php esc_html_e( 'Email', 'estora-theme' ); ?><input name="email" type="email" autocomplete="email"></label>
				<label><?php echo esc_html( estora_text( 'What are you looking for?', 'คุณกำลังมองหาอะไร?' ) ); ?><textarea name="message" rows="5" required placeholder="<?php echo esc_attr( estora_text( 'Budget, area, number of bedrooms, timing…', 'งบประมาณ ทำเล จำนวนห้องนอน และช่วงเวลาที่ต้องการ…' ) ); ?>"></textarea></label>
				<div class="estora-hp" aria-hidden="true"><label><?php esc_html_e( 'Company', 'estora-theme' ); ?><input name="company" tabindex="-1" autocomplete="off"></label></div>
				<label class="estora-form__consent"><input type="checkbox" name="consent" value="1" required> <?php echo esc_html( estora_text( 'I agree to be contacted about my inquiry.', 'ยินยอมให้ติดต่อกลับเกี่ยวกับคำถามของฉัน' ) ); ?></label>
				<p class="estora-form__error" data-form-error role="alert" hidden></p>
				<p class="estora-form__success" data-form-success role="status" hidden></p>
				<button class="estora-btn" type="submit"><?php echo esc_html( estora_text( 'Send message', 'ส่งข้อความ' ) ); ?></button>
				<noscript><p class="estora-muted"><?php echo esc_html( estora_text( 'JavaScript is required for this form. Please call or email the office.', 'ฟอร์มนี้ต้องใช้ JavaScript โปรดโทรหรืออีเมลสำนักงาน' ) ); ?></p></noscript>
			</form>
		</div>

		<div class="estora-stack" style="display:grid;gap:16px">
			<div class="estora-panel">
				<h2><?php esc_html_e( 'Office', 'estora-theme' ); ?></h2>
				<p><?php echo esc_html( estora_company_text( 'company_address' ) ); ?></p>
				<p>
					<a class="estora-textlink" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', estora_company( 'company_phone' ) ) ); ?>"><?php echo estora_icon( 'phone', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( estora_company( 'company_phone' ) ); ?></a><br>
					<a class="estora-textlink" href="<?php echo esc_url( 'mailto:' . estora_company( 'company_email' ) ); ?>"><?php echo estora_icon( 'mail', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <?php echo esc_html( estora_company( 'company_email' ) ); ?></a>
				</p>
				<p class="estora-muted"><?php echo esc_html( estora_company_text( 'company_hours_text' ) ); ?></p>
			</div>
			<div class="estora-panel">
				<h2><?php echo esc_html( estora_text( 'Buying or selling?', 'ซื้อหรือขาย?' ) ); ?></h2>
				<p class="estora-muted"><?php echo esc_html( estora_text( 'For a valuation or a listing brief, include the address and the timing. We will come back with a realistic figure, not the highest one.', 'สำหรับการประเมินราคาหรือบรีฟประกาศขาย ส่งที่อยู่และช่วงเวลาให้เรา เราจะตอบด้วยราคาที่สมจริง ไม่ใช่ราคาที่สูงที่สุด' ) ); ?></p>
				<a class="estora-btn estora-btn--ghost" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php echo esc_html( estora_text( 'See current listings', 'ดูประกาศปัจจุบัน' ) ); ?></a>
			</div>
			<div class="estora-panel estora-panel--ink">
				<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( estora_text( 'Prefer to talk?', 'อยากคุยก่อน?' ) ); ?></p>
				<p class="estora-muted"><?php echo esc_html( estora_text( 'Call the office and we will match you with the right advisor.', 'โทรหาสำนักงานแล้วเราจะจับคู่คุณกับที่ปรึกษาที่เหมาะสม' ) ); ?></p>
				<a class="estora-textlink" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', estora_company( 'company_phone' ) ) ); ?>"><?php echo esc_html( estora_company( 'company_phone' ) ); ?> <?php echo estora_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			</div>
		</div>
	</div>
	<?php
endwhile;

get_footer();
