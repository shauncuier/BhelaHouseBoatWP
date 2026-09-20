<?php
/**
 * Investor account statement — the brief's §13, one running balance.
 *
 * The closing balance is **what BHELA owes this investor**: principal received plus
 * profit earned, less profit paid. The page says that in words rather than printing a
 * bare "Balance", because under a fixed-return arrangement an investor is owed their
 * money back and that is a different thing from owning a share of the business. A reader
 * who assumes the wrong one of those misreads the whole document.
 *
 * Reversed rows are shown struck rather than dropped: a statement somebody cannot
 * reconcile against the receipts in their file is not a statement.
 *
 * Provided by includes/documents.php: $statement, $settings.
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

$period = ( $statement['from'] || $statement['to'] )
	? ( ( $statement['from'] ? mysql2date( 'd M Y', $statement['from'] ) : __( 'শুরু থেকে', 'bhela-booking' ) )
		. ' — ' . ( $statement['to'] ? mysql2date( 'd M Y', $statement['to'] ) : __( 'আজ পর্যন্ত', 'bhela-booking' ) ) )
	: __( 'সব সময় · All dates', 'bhela-booking' );
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( __( 'Account statement', 'bhela-booking' ) . ' — ' . $statement['name'] ); ?></title>
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
				<?php esc_html_e( 'Statement', 'bhela-booking' ); ?><br>
				<span class="num"><?php echo esc_html( $statement['code'] ? $statement['code'] : $statement['name'] ); ?></span><br>
				<?php
				printf(
					/* translators: %s: the date the statement was printed */
					esc_html__( 'as at %s', 'bhela-booking' ),
					esc_html( mysql2date( 'd M Y', current_time( 'Y-m-d' ) ) )
				);
				?>
			</div>
		</div>

		<div class="cert-title">
			<h2><?php esc_html_e( 'হিসাব বিবরণী', 'bhela-booking' ); ?></h2>
			<div class="en"><?php esc_html_e( 'Investor Account Statement', 'bhela-booking' ); ?></div>
		</div>

		<div class="cert-body">
			<table class="cert-tbl">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'বিনিয়োগকারী · Investor', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $statement['name'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'সময়কাল · Period', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $period ); ?></td>
					</tr>
				</tbody>
			</table>

			<h3 class="cert-sec"><?php esc_html_e( 'Transactions', 'bhela-booking' ); ?></h3>

			<table class="cert-tbl">
				<thead>
					<tr>
						<th><?php esc_html_e( 'DATE', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'PARTICULAR', 'bhela-booking' ); ?></th>
						<th class="num"><?php esc_html_e( 'CREDIT', 'bhela-booking' ); ?></th>
						<th class="num"><?php esc_html_e( 'DEBIT', 'bhela-booking' ); ?></th>
						<th class="num"><?php esc_html_e( 'BALANCE', 'bhela-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $statement['opening'] ) ) : ?>
						<tr class="soft">
							<td>—</td>
							<td><?php esc_html_e( 'Opening balance', 'bhela-booking' ); ?></td>
							<td class="num">—</td>
							<td class="num">—</td>
							<td class="num"><?php echo esc_html( $money( $statement['opening'] ) ); ?></td>
						</tr>
					<?php endif; ?>

					<?php if ( ! $statement['rows'] ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'এই সময়ে কোনো লেনদেন নেই।', 'bhela-booking' ); ?></td></tr>
					<?php endif; ?>

					<?php foreach ( $statement['rows'] as $row ) : ?>
						<tr<?php echo $row['void'] ? ' class="soft" style="text-decoration:line-through"' : ''; ?>>
							<td><?php echo esc_html( mysql2date( 'd/m/Y', $row['date'] ) ); ?></td>
							<td>
								<?php echo esc_html( $row['label'] ); ?>
								<?php if ( $row['ref'] ) : ?>
									<span style="color:#546a69"> · <?php echo esc_html( $row['ref'] ); ?></span>
								<?php endif; ?>
							</td>
							<td class="num"><?php echo $row['credit'] ? esc_html( $money( $row['credit'] ) ) : '—'; ?></td>
							<td class="num"><?php echo $row['debit'] ? esc_html( $money( $row['debit'] ) ) : '—'; ?></td>
							<td class="num"><?php echo esc_html( $money( $row['balance'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>

					<tr class="total">
						<td colspan="2"><?php esc_html_e( 'ভেলা যত টাকা দিতে বাধ্য · Owed to the investor', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $statement['capital'] + $statement['profit'] ) ); ?></td>
						<td class="num"><?php echo esc_html( $money( $statement['paid'] ) ); ?></td>
						<td class="num"><?php echo esc_html( $money( $statement['balance'] ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<h3 class="cert-sec"><?php esc_html_e( 'Summary', 'bhela-booking' ); ?></h3>

			<table class="cert-tbl">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'মোট বিনিয়োগ গ্রহণ · Investment received', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $statement['capital'] ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'মোট ঘোষিত লাভ · Profit credited', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $statement['profit'] ) ); ?></td>
					</tr>
					<?php if ( 0 !== (int) $statement['adjustment'] ) : ?>
						<tr>
							<td><?php esc_html_e( 'সমন্বয় · Adjustments', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( ( $statement['adjustment'] > 0 ? '+' : '' ) . $money( $statement['adjustment'] ) ); ?></td>
						</tr>
					<?php endif; ?>
					<tr>
						<td><?php esc_html_e( 'পরিশোধিত · Paid out', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $statement['paid'] ) ); ?></td>
					</tr>
					<tr class="total">
						<td><?php esc_html_e( 'বর্তমান স্থিতি · Closing balance', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $statement['balance'] ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<?php
			// The sentence that stops the balance being misread. It is a liability of
			// BHELA's, not a valuation of a shareholding, and those are very different
			// numbers to be handed on a piece of paper.
			?>
			<div class="cert-note">
				<?php esc_html_e( 'উপরের স্থিতি মানে — এই মুহূর্তে ভেলা আপনাকে মোট কত টাকা দিতে বাধ্য: গৃহীত মূলধন ও ঘোষিত লাভ মিলিয়ে, তা থেকে ইতিমধ্যে পরিশোধিত অংশ বাদ দিয়ে। এটি কোনো শেয়ারের বাজারমূল্য নয়।', 'bhela-booking' ); ?>
			</div>

			<div class="cert-sign">
				<div>
					<?php if ( ! empty( $s['cert_signatory'] ) ) : ?><strong><?php echo esc_html( $s['cert_signatory'] ); ?></strong><?php endif; ?>
					<span class="role"><?php esc_html_e( 'For BHELA', 'bhela-booking' ); ?></span>
				</div>
				<div>
					<span class="role"><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></span>
				</div>
			</div>
		</div>

		<div class="cert-disc">
			<?php esc_html_e( 'এটি BHELA কর্তৃক ইস্যুকৃত financial supporting document; NBR-ইস্যুকৃত Tax Certificate নয়।', 'bhela-booking' ); ?>
		</div>

		<div class="cert-foot">
			<?php esc_html_e( 'এই বিবরণী প্রিন্টের সময়ের অবস্থা অনুযায়ী তৈরি — সনদের মতো জমাট নয়, তাই পরে আবার প্রিন্ট করলে নতুন লেনদেনও দেখাবে।', 'bhela-booking' ); ?>
		</div>
	</div>
</body>
</html>
