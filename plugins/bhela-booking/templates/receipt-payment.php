<?php
/**
 * Payment receipt — one template, two directions.
 *
 * The brief's §12 insists money coming IN and money going OUT are never the same
 * transaction, so the receipt names which it is in its title and in its certifying
 * sentence rather than printing a neutral "amount" either way.
 *
 * It is rendered live from an already-immutable record rather than frozen — see the
 * header of includes/documents.php for why a snapshot would add nothing here.
 *
 * Provided by includes/documents.php: $receipt, $settings.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s     = $settings;
$money = 'bhela_bm_money';

$logo       = '';
$theme_logo = get_template_directory() . '/assets/images/logo.png';
if ( file_exists( $theme_logo ) ) {
	$logo = get_template_directory_uri() . '/assets/images/logo.png';
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $receipt['number'] . ' — ' . $receipt['en'] ); ?></title>
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
					<?php // Blank hides the line outright — §13.25. ?>
					<?php if ( ! empty( $s['vessel_reg'] ) ) : ?><div class="org"><?php echo esc_html( $s['vessel_reg'] ); ?></div><?php endif; ?>
				</div>
			</div>
			<div class="cert-no">
				<?php esc_html_e( 'Receipt No.', 'bhela-booking' ); ?><br>
				<span class="num"><?php echo esc_html( $receipt['number'] ); ?></span><br>
				<?php echo esc_html( mysql2date( 'd M Y', $receipt['date'] ) ); ?>
			</div>
		</div>

		<?php if ( ! empty( $receipt['void'] ) ) : ?>
			<?php
			// A cancelled receipt still prints, and says so. Somebody may be holding it,
			// and a statement they cannot reconcile against their own paperwork is worse
			// than one that admits the entry was reversed.
			?>
			<div class="cert-super" style="margin-top:14px">
				<?php esc_html_e( 'এই রসিদটি বাতিল।', 'bhela-booking' ); ?>
				<?php echo esc_html( $receipt['void_reason'] ); ?>
			</div>
		<?php endif; ?>

		<div class="cert-title">
			<h2><?php echo esc_html( $receipt['label'] ); ?></h2>
			<div class="en"><?php echo esc_html( $receipt['en'] ); ?></div>
		</div>

		<div class="cert-body">
			<p class="cert-sent">
				<?php
				if ( 'capital' === $receipt['kind'] ) {
					printf(
						/* translators: 1: business, 2: amount, 3: investor, 4: date */
						wp_kses(
							__( '<u>%1$s</u> acknowledges receipt of <u>%2$s</u> from <u>%3$s</u> on <u>%4$s</u>, towards the investment named below.', 'bhela-booking' ),
							array( 'u' => array() )
						),
						esc_html( $s['business_name'] ),
						esc_html( $money( $receipt['amount'] ) ),
						esc_html( $receipt['name'] ),
						esc_html( mysql2date( 'd M Y', $receipt['date'] ) )
					);
				} else {
					printf(
						/* translators: 1: business, 2: amount, 3: investor, 4: date */
						wp_kses(
							__( '<u>%1$s</u> has paid <u>%2$s</u> to <u>%3$s</u> on <u>%4$s</u>, against profit recorded in the BHELA Investment Management System.', 'bhela-booking' ),
							array( 'u' => array() )
						),
						esc_html( $s['business_name'] ),
						esc_html( $money( $receipt['amount'] ) ),
						esc_html( $receipt['name'] ),
						esc_html( mysql2date( 'd M Y', $receipt['date'] ) )
					);
				}
				?>
			</p>

			<table class="cert-tbl" style="margin-top:18px">
				<tbody>
					<tr>
						<td><?php echo esc_html( $receipt['dir'] ); ?></td>
						<td class="num"><?php echo esc_html( $receipt['name'] ); ?></td>
					</tr>
					<?php if ( $receipt['code'] ) : ?>
						<tr>
							<td><?php esc_html_e( 'Investor ID', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( $receipt['code'] ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $receipt['investment'] ) : ?>
						<tr>
							<td><?php esc_html_e( 'Investment ID', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( $receipt['investment'] ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $receipt['agreement'] ) : ?>
						<tr>
							<td><?php esc_html_e( 'Agreement reference', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( $receipt['agreement'] ); ?></td>
						</tr>
					<?php endif; ?>
					<tr>
						<td><?php esc_html_e( 'তারিখ · Date', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( mysql2date( 'd M Y', $receipt['date'] ) ); ?></td>
					</tr>
					<?php if ( $receipt['method'] ) : ?>
						<tr>
							<td><?php esc_html_e( 'মাধ্যম · Method', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( $receipt['method'] ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $receipt['ref'] ) : ?>
						<tr>
							<td><?php esc_html_e( 'রেফারেন্স · Reference', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( $receipt['ref'] ); ?></td>
						</tr>
					<?php endif; ?>
					<tr class="total">
						<td><?php esc_html_e( 'AMOUNT IN TK.', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $receipt['amount'] ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<?php if ( $receipt['note'] ) : ?>
				<div class="cert-note"><?php echo esc_html( $receipt['note'] ); ?></div>
			<?php endif; ?>

			<div class="cert-sign">
				<div>
					<?php if ( ! empty( $s['cert_signatory'] ) ) : ?><strong><?php echo esc_html( $s['cert_signatory'] ); ?></strong><?php endif; ?>
					<span class="role">
						<?php
						echo '' !== trim( (string) ( $s['cert_signatory_role'] ?? '' ) )
							? esc_html( $s['cert_signatory_role'] )
							: esc_html__( 'For BHELA', 'bhela-booking' );
						?>
					</span>
				</div>
				<div>
					<span class="role">
						<?php
						echo 'capital' === $receipt['kind']
							? esc_html__( 'Investor', 'bhela-booking' )
							: esc_html__( 'Received by', 'bhela-booking' );
						?>
					</span>
				</div>
			</div>
		</div>

		<div class="cert-disc">
			<?php esc_html_e( 'এটি BHELA কর্তৃক ইস্যুকৃত financial supporting document; NBR-ইস্যুকৃত Tax Certificate নয়।', 'bhela-booking' ); ?>
		</div>

		<div class="cert-foot">
			<?php esc_html_e( 'এই রসিদ সিস্টেম থেকে তৈরি — হাতে লেখা কোনো অঙ্ক এতে নেই।', 'bhela-booking' ); ?>
		</div>
	</div>
</body>
</html>
