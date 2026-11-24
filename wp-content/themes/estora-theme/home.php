<?php
/**
 * News & notes archive.
 *
 * @package Estora\Theme
 */

defined( 'ABSPATH' ) || exit;

$estora_categories = estora_news_categories();
$estora_is_first   = true;
$estora_page       = max( 1, (int) get_query_var( 'paged' ) );

get_header();
?>
<section class="estora-pagehead estora-pagehead--journal">
	<div class="estora-container">
		<?php estora_breadcrumbs( array( array( 'label' => __( 'News', 'estora-theme' ) ) ) ); ?>
		<div class="estora-pagehead__split">
			<div>
				<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( estora_text( 'Journal', 'บทความ' ) ); ?></p>
				<h1><?php echo esc_html( estora_text( 'News & notes', 'ข่าวสารและบันทึก' ) ); ?></h1>
			</div>
			<p class="estora-pagehead__lede"><?php echo esc_html( estora_text( 'Market observations and practical guides from the office. Written by the people who handle the files, not a content agency.', 'มุมมองตลาดและคู่มือใช้งานจริงจากสำนักงาน เขียนโดยคนที่ดูแลแฟ้มจริง ไม่ใช่เอเจนซีทำคอนเทนต์' ) ); ?></p>
		</div>
	</div>
</section>

<div class="estora-container estora-section estora-journal-page">
	<?php if ( $estora_categories ) : ?>
		<nav class="estora-topicbar" aria-label="<?php esc_attr_e( 'Topics', 'estora-theme' ); ?>">
			<span class="estora-topicbar__label"><?php echo esc_html( estora_text( 'Topics', 'หัวข้อ' ) ); ?></span>
			<a class="is-active" href="<?php echo esc_url( home_url( '/news/' ) ); ?>"><?php echo esc_html( estora_text( 'All notes', 'ทั้งหมด' ) ); ?></a>
			<?php foreach ( $estora_categories as $estora_category ) : ?>
				<a href="<?php echo esc_url( get_category_link( $estora_category ) ); ?>"><?php echo esc_html( estora_news_category_label( $estora_category->slug ) ); ?><span><?php echo esc_html( number_format_i18n( (int) $estora_category->count ) ); ?></span></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<div class="estora-journal-layout">
			<div class="estora-journal-main">
				<?php $estora_first_posts = array(); ?>
				<?php while ( have_posts() ) : ?>
					<?php
					the_post();
					if ( $estora_is_first && 1 === $estora_page ) {
						$estora_is_first = false;
						?>
						<div class="estora-journal-feature">
							<?php estora_article_card( (int) get_the_ID(), 'feature' ); ?>
						</div>
						<?php
						continue;
					}
					$estora_first_posts[] = (int) get_the_ID();
					?>
				<?php endwhile; ?>

				<?php if ( $estora_first_posts ) : ?>
					<div class="estora-journal-grid">
						<?php foreach ( $estora_first_posts as $estora_post_id ) : ?>
							<?php estora_article_card( $estora_post_id ); ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php
				the_posts_pagination(
					array(
						'prev_text' => '←',
						'next_text' => '→',
						'class'     => 'estora-pagination',
					)
				);
				?>
			</div>

			<aside class="estora-journal-aside" aria-label="<?php esc_attr_e( 'From the office', 'estora-theme' ); ?>">
				<div class="estora-panel estora-panel--ink">
					<p class="estora-eyebrow"><span aria-hidden="true"></span><?php echo esc_html( estora_text( 'From the office', 'จากสำนักงาน' ) ); ?></p>
					<h2><?php echo esc_html( estora_text( 'Ask before you commit.', 'ถามก่อนตัดสินใจ' ) ); ?></h2>
					<p class="estora-journal-aside__copy"><?php echo esc_html( estora_text( 'Title checks, transfer costs, rental terms — the office answers these daily and will look at the file with you.', 'ตรวจโฉนด ค่าโอน เงื่อนไขสัญญาเช่า ทีมงานตอบเรื่องเหล่านี้ทุกวัน และพร้อมช่วยดูแฟ้มให้คุณ' ) ); ?></p>
					<a class="estora-textlink" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php echo esc_html( estora_text( 'Talk to an advisor', 'คุยกับที่ปรึกษา' ) ); ?> <?php echo estora_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
				</div>

				<?php if ( $estora_categories ) : ?>
					<div class="estora-panel">
						<h2><?php echo esc_html( estora_text( 'Read by topic', 'อ่านตามหัวข้อ' ) ); ?></h2>
						<ul class="estora-topiclist">
							<?php foreach ( $estora_categories as $estora_category ) : ?>
								<li>
									<a href="<?php echo esc_url( get_category_link( $estora_category ) ); ?>">
										<span><?php echo esc_html( estora_news_category_label( $estora_category->slug ) ); ?></span>
										<em><?php echo esc_html( number_format_i18n( (int) $estora_category->count ) ); ?></em>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<div class="estora-panel estora-journal-aside__property">
					<p class="estora-eyebrow"><?php echo esc_html( estora_text( 'The catalogue', 'แคตตาล็อก' ) ); ?></p>
					<p><?php echo esc_html( estora_text( 'Every note starts from a real file. The same records power the listings.', 'ทุกบันทึกเริ่มจากแฟ้มจริง และข้อมูลชุดเดียวกับที่ใช้ทำประกาศขาย' ) ); ?></p>
					<a class="estora-btn estora-btn--ghost" href="<?php echo esc_url( estora_properties_url() ); ?>"><?php echo esc_html( estora_text( 'Browse properties', 'ดูทรัพย์ทั้งหมด' ) ); ?></a>
				</div>
			</aside>
		</div>
	<?php else : ?>
		<div class="estora-empty">
			<h2><?php esc_html_e( 'No articles yet', 'estora-theme' ); ?></h2>
			<p><?php esc_html_e( 'Practical notes will appear here as the office publishes them.', 'estora-theme' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
