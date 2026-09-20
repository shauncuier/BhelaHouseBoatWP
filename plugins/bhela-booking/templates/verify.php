<?php
/**
 * The public verification result.
 *
 * Deliberately plain and deliberately thin. Provided by includes/verify.php: $v and
 * $settings. Everything it may show is already in $v — this template must not reach
 * back into the certificate for anything else, which is what keeps "shows no amounts"
 * a property of one function rather than a habit of one page.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tone = 'valid' === $v['status'] ? '#1E7A53' : ( 'superseded' === $v['status'] ? '#B4761B' : '#B0392B' );
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( __( 'Certificate verification', 'bhela-booking' ) . ' — ' . ( $settings['business_name'] ?? 'BHELA' ) ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
	* { margin:0; padding:0; box-sizing:border-box; }
	body { font-family:'Hind Siliguri','Nirmala UI',Poppins,sans-serif; background:#eef3f3; color:#12302f; padding:32px 16px; line-height:1.6; }
	.vfy { max-width:520px; margin:0 auto; background:#fff; border-radius:12px; box-shadow:0 10px 40px rgba(11,46,51,.14); overflow:hidden; }
	.vfy-head { background:#0B2E33; color:#fff; padding:20px 24px; }
	.vfy-head h1 { font-size:18px; font-weight:700; }
	.vfy-head p { font-size:12.5px; color:#bcd8d6; margin-top:2px; }
	.vfy-body { padding:24px; }
	.vfy-state { display:inline-block; padding:7px 16px; border-radius:999px; color:#fff; font-weight:700; font-size:14px; background:<?php echo esc_attr( $tone ); ?>; }
	dl { margin-top:20px; display:grid; grid-template-columns:auto 1fr; gap:10px 18px; font-size:15px; }
	dt { color:#6d8a89; font-size:12.5px; text-transform:uppercase; letter-spacing:.7px; align-self:center; }
	dd { font-weight:600; font-family:Poppins,'Hind Siliguri',sans-serif; }
	.vfy-note { margin-top:20px; font-size:13px; color:#4a6968; border-top:1px solid #e6eeed; padding-top:14px; }
	.vfy-foot { background:#f4f9f8; padding:14px 24px; font-size:12px; color:#6d8a89; text-align:center; }
</style>
</head>
<body>
	<div class="vfy">
		<div class="vfy-head">
			<h1><?php echo esc_html( $settings['business_name'] ?? 'BHELA' ); ?></h1>
			<p><?php esc_html_e( 'Certificate verification', 'bhela-booking' ); ?></p>
		</div>

		<div class="vfy-body">
			<?php if ( ! $v['found'] ) : ?>
				<span class="vfy-state"><?php esc_html_e( 'NOT FOUND', 'bhela-booking' ); ?></span>
				<dl>
					<dt><?php esc_html_e( 'Certificate No', 'bhela-booking' ); ?></dt>
					<dd><?php echo esc_html( $v['number'] ); ?></dd>
				</dl>
				<p class="vfy-note">
					<?php esc_html_e( 'এই নম্বরের কোনো সনদ BHELA-র রেকর্ডে নেই। নম্বরটি আবার মিলিয়ে দেখুন, অথবা BHELA অফিসে যোগাযোগ করুন।', 'bhela-booking' ); ?>
				</p>
			<?php else : ?>
				<span class="vfy-state">
					<?php
					echo 'valid' === $v['status']
						? esc_html__( 'VALID', 'bhela-booking' )
						: esc_html__( 'SUPERSEDED', 'bhela-booking' );
					?>
				</span>

				<dl>
					<dt><?php esc_html_e( 'Certificate No', 'bhela-booking' ); ?></dt>
					<dd><?php echo esc_html( $v['number'] ); ?></dd>

					<dt><?php esc_html_e( 'Document type', 'bhela-booking' ); ?></dt>
					<dd><?php echo esc_html( $v['type'] ); ?></dd>

					<dt><?php esc_html_e( 'Issue date', 'bhela-booking' ); ?></dt>
					<dd><?php echo esc_html( mysql2date( 'd M Y', $v['issued'] ) ); ?></dd>

					<dt><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></dt>
					<dd><?php echo esc_html( $v['name'] ); ?></dd>

					<?php if ( 'superseded' === $v['status'] && $v['replaced_by'] ) : ?>
						<dt><?php esc_html_e( 'Replaced by', 'bhela-booking' ); ?></dt>
						<dd><?php echo esc_html( $v['replaced_by'] ); ?></dd>
					<?php endif; ?>
				</dl>

				<p class="vfy-note">
					<?php if ( 'superseded' === $v['status'] ) : ?>
						<?php esc_html_e( 'এই সনদটি BHELA ইস্যু করেছিল, তবে পরে সংশোধিত সনদ দিয়ে প্রতিস্থাপিত হয়েছে। সর্বশেষ সংস্করণটি ব্যবহার করুন।', 'bhela-booking' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'এই সনদটি BHELA ইস্যু করেছে এবং এটি বৈধ।', 'bhela-booking' ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php
			// Said plainly, because a verifier's next question is always "why can I not
			// see the amount". The answer is that this page is public.
			?>
			<p class="vfy-note">
				<?php esc_html_e( 'গোপনীয়তার কারণে এই পাতায় কোনো টাকার অঙ্ক, হার বা ব্যক্তিগত তথ্য দেখানো হয় না — শুধু সনদটি আসল কি না তা যাচাই করা যায়।', 'bhela-booking' ); ?>
			</p>
		</div>

		<div class="vfy-foot">
			<?php esc_html_e( 'এটি BHELA কর্তৃক ইস্যুকৃত financial supporting document যাচাইয়ের পাতা; NBR-ইস্যুকৃত Tax Certificate নয়।', 'bhela-booking' ); ?>
		</div>
	</div>
</body>
</html>
