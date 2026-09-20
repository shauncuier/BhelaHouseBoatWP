<?php
/**
 * Certificates issued under the previous, share-based model.
 *
 * Four of these were already in the wild when BHELA moved to fixed-return investments,
 * and the rewrite that followed changed the shape of the stored snapshot — so the new
 * templates would have thrown on every one of them. That is not a cosmetic problem:
 * §13.75 says a certificate must read the same next year as the day it was issued, and
 * a document that errors instead of rendering breaks that promise more completely than
 * a wrong figure would.
 *
 * So old certificates keep their own renderer. It draws from the OLD snapshot keys, adds
 * nothing, computes nothing, and says at the top which model it was issued under — a
 * reader comparing it against a newer certificate needs to know why the two are shaped
 * differently. Deleting them was never an option: financial documents are not deleted
 * here, and somebody may be holding one.
 *
 * Provided by includes/certificates.php: $cert, $snap, $settings, $types.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s     = $settings;
$money = 'bhela_bm_money';
$type  = $types[ $cert['type'] ] ?? array( 'label' => '', 'en' => '' );

$logo       = '';
$theme_logo = get_template_directory() . '/assets/images/logo.png';
if ( file_exists( $theme_logo ) ) {
	$logo = get_template_directory_uri() . '/assets/images/logo.png';
}
$verify_url = function_exists( 'bhela_bm_verify_url' ) ? bhela_bm_verify_url( $cert['number'] ) : '';
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $cert['number'] . ' — ' . $type['en'] ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Noto+Sans+Bengali:wght@400;600;700&family=Noto+Serif+Bengali:wght@600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<?php require BHELA_BM_PATH . 'templates/doc-style.php'; ?>
</head>
<body>
	<div class="print-bar">
		<button onclick="window.print()"><?php esc_html_e( 'প্রিন্ট / PDF সেভ করুন', 'bhela-booking' ); ?></button>
	</div>
	<div class="cert">
		<div class="cert-head">
			<div style="display:flex;align-items:center;gap:14px">
				<?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $s['business_name'] ); ?>"><?php endif; ?>
				<div>
					<h1><?php echo esc_html( $s['business_name'] ); ?></h1>
					<?php if ( ! empty( $s['address'] ) ) : ?><div class="org"><?php echo esc_html( $s['address'] ); ?></div><?php endif; ?>
					<?php if ( ! empty( $s['vessel_reg'] ) ) : ?><div class="org"><?php echo esc_html( $s['vessel_reg'] ); ?></div><?php endif; ?>
				</div>
			</div>
			<div class="cert-no">
				<?php esc_html_e( 'Certificate No.', 'bhela-booking' ); ?><br>
				<span class="num"><?php echo esc_html( $cert['number'] ); ?></span><br>
				<?php echo esc_html( mysql2date( 'd M Y', $cert['issued'] ) ); ?>
			</div>
		</div>

		<?php if ( ! empty( $cert['superseded'] ) ) : ?>
			<div class="cert-super" style="margin-top:14px">
				<?php
				printf(
					/* translators: %s: the replacing certificate number */
					esc_html__( 'এই সনদটি প্রতিস্থাপিত — বর্তমান সংস্করণ %s।', 'bhela-booking' ),
					esc_html( $cert['superseded_number'] )
				);
				?>
			</div>
		<?php endif; ?>

		<div class="cert-title">
			<h2><?php echo esc_html( $snap['type_label'] ?? $type['label'] ); ?></h2>
			<div class="en"><?php echo esc_html( $snap['type_en'] ?? $type['en'] ); ?></div>
		</div>

		<div class="cert-body">
			<div class="cert-note">
				<?php esc_html_e( 'এই সনদটি ভেলার আগের শেয়ারভিত্তিক পদ্ধতিতে ইস্যু করা হয়েছিল। ইস্যুর দিনের হিসাব হুবহু রাখা হয়েছে — পরে পদ্ধতি বদলালেও এই কাগজের অঙ্ক বদলায় না। নতুন সনদের গঠন আলাদা, তাই দুটি পাশাপাশি রাখলে ছক ভিন্ন দেখাবে।', 'bhela-booking' ); ?>
			</div>

			<table class="cert-tbl">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'বিনিয়োগকারী · Investor', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $snap['name'] ?? '' ); ?></td>
					</tr>
					<?php if ( ! empty( $snap['code'] ) ) : ?>
						<tr>
							<td><?php esc_html_e( 'Investor ID', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( $snap['code'] ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( isset( $snap['shares'] ) ) : ?>
						<tr>
							<td><?php esc_html_e( 'শেয়ার · Shares', 'bhela-booking' ); ?></td>
							<td class="num">
								<?php
								printf(
									'%1$d / %2$d · %3$s%%',
									(int) $snap['shares'],
									(int) ( $snap['total_shares'] ?? 0 ),
									esc_html( (string) ( $snap['share_pct'] ?? 0 ) )
								);
								?>
							</td>
						</tr>
					<?php endif; ?>
					<?php if ( ! empty( $snap['season_label'] ) || ! empty( $snap['from'] ) ) : ?>
						<tr>
							<td><?php esc_html_e( 'সময়কাল · Period', 'bhela-booking' ); ?></td>
							<td class="num">
								<?php
								echo esc_html(
									( ! empty( $snap['season_label'] ) ? $snap['season_label'] . ' · ' : '' )
									. ( ! empty( $snap['from'] ) ? mysql2date( 'd M Y', $snap['from'] ) . ' — ' . mysql2date( 'd M Y', $snap['to'] ) : '' )
								);
								?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( 'profit' === $cert['type'] ) : ?>
				<h3 class="cert-sec"><?php esc_html_e( 'Profit', 'bhela-booking' ); ?></h3>
				<table class="cert-tbl">
					<tbody>
						<?php if ( isset( $snap['pot']['distributable'] ) ) : ?>
							<tr>
								<td><?php esc_html_e( 'সিজনের বণ্টনযোগ্য লাভ', 'bhela-booking' ); ?></td>
								<td class="num"><?php echo esc_html( $money( $snap['pot']['distributable'] ) ); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ( isset( $snap['entitlement'] ) ) : ?>
							<tr>
								<td><?php esc_html_e( 'অনুপাত অনুযায়ী প্রাপ্য', 'bhela-booking' ); ?></td>
								<td class="num"><?php echo esc_html( $money( $snap['entitlement'] ) ); ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<td><?php esc_html_e( 'ঘোষিত লাভ', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( $money( $snap['declared'] ?? 0 ) ); ?></td>
						</tr>
						<?php if ( ! empty( $snap['adjustments'] ) ) : ?>
							<tr>
								<td><?php esc_html_e( 'সমন্বয়', 'bhela-booking' ); ?></td>
								<td class="num"><?php echo esc_html( $money( $snap['adjustments'] ) ); ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<td><?php esc_html_e( 'প্রদত্ত', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( $money( $snap['paid'] ?? 0 ) ); ?></td>
						</tr>
						<tr class="total">
							<td>
								<?php
								echo ( (int) ( $snap['balance'] ?? 0 ) < 0 )
									? esc_html__( 'আপনি ভেলাকে দেবেন', 'bhela-booking' )
									: esc_html__( 'ভেলা আপনাকে দেবে', 'bhela-booking' );
								?>
							</td>
							<td class="num"><?php echo esc_html( $money( abs( (int) ( $snap['balance'] ?? 0 ) ) ) ); ?></td>
						</tr>
					</tbody>
				</table>
			<?php else : ?>
				<h3 class="cert-sec"><?php esc_html_e( 'Investment', 'bhela-booking' ); ?></h3>
				<table class="cert-tbl">
					<tbody>
						<?php foreach ( (array) ( $snap['years'] ?? array() ) as $y ) : ?>
							<tr>
								<td><?php echo esc_html( $y['year'] ); ?></td>
								<td class="num"><?php echo esc_html( $money( $y['amount'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( ! empty( $snap['undated'] ) ) : ?>
							<tr class="soft">
								<td><?php esc_html_e( 'পূর্ববর্তী বিনিয়োগ — তারিখ রেকর্ড নেই', 'bhela-booking' ); ?></td>
								<td class="num"><?php echo esc_html( $money( $snap['undated'] ) ); ?></td>
							</tr>
						<?php endif; ?>
						<tr class="total">
							<td><?php esc_html_e( 'মোট বিনিয়োগ', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( $money( $snap['total'] ?? 0 ) ); ?></td>
						</tr>
					</tbody>
				</table>
			<?php endif; ?>

			<div class="cert-verify">
				<?php
				echo function_exists( 'bhela_bm_qr_svg' ) && $verify_url
					? bhela_bm_qr_svg( $verify_url, 104, __( 'Certificate verification QR code', 'bhela-booking' ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					: '';
				?>
				<div class="txt">
					<strong><?php esc_html_e( 'Verify this certificate', 'bhela-booking' ); ?></strong>
					<?php echo esc_html( $verify_url ); ?>
				</div>
			</div>
		</div>

		<div class="cert-disc">
			<?php esc_html_e( 'এটি BHELA কর্তৃক ইস্যুকৃত financial supporting document; NBR-ইস্যুকৃত Tax Certificate নয়।', 'bhela-booking' ); ?>
		</div>

		<div class="cert-foot">
			<?php
			printf(
				/* translators: 1: certificate number, 2: issue date */
				esc_html__( 'সনদ নম্বর %1$s · ইস্যু %2$s', 'bhela-booking' ),
				esc_html( $cert['number'] ),
				esc_html( mysql2date( 'd M Y', $cert['issued'] ) )
			);
			?>
		</div>
	</div>
</body>
</html>
