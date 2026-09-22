<?php
/**
 * Profit Certificate — what a period earned, what was paid, what is still due.
 *
 * Every figure comes from the STORED snapshot ($snap) and nothing is recomputed, so a
 * payment recorded next month cannot change what this document says. When the figures
 * move, a new version is issued and this one is marked superseded.
 *
 * The settlement block is the brief's §9 and §10 exactly: gross, adjustment, net, paid,
 * due, and a status of PAID / PARTIALLY PAID / UNPAID.
 *
 * Provided by includes/certificates.php: $cert, $snap, $settings, $signoff, $types.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require BHELA_BM_PATH . 'templates/certificate-head.php';

$money = 'bhela_bm_money';
$rate  = rtrim( rtrim( number_format( $snap['rate'], 2, '.', '' ), '0' ), '.' );
?>

			<p class="cert-sent">
				<?php
				// The span the profit was earned in. Older snapshots predate it and keep the
				// window they were issued with — a frozen certificate is never re-worded.
				$earned_from = (string) ( $snap['earned_from'] ?? $snap['from'] );
				$earned_to   = (string) ( $snap['earned_to'] ?? $snap['to'] );
				printf(
					/* translators: 1: investor, 2: investor id, 3: investment id, 4: from, 5: to, 6: gross */
					wp_kses(
						__( 'This is to certify that <u>%1$s</u>, Investor ID <u>%2$s</u>, has earned profit under Investment ID <u>%3$s</u> for the period <u>%4$s</u> to <u>%5$s</u> amounting to <u>%6$s</u>, as recorded in the BHELA Investment Management System.', 'bhela-booking' ),
						array( 'u' => array() )
					),
					esc_html( $snap['name'] ),
					esc_html( '' !== $snap['code'] ? $snap['code'] : '—' ),
					esc_html( $snap['investment_id'] ),
					esc_html( mysql2date( 'd M Y', $earned_from ) ),
					esc_html( mysql2date( 'd M Y', $earned_to ) ),
					esc_html( $money( $snap['gross'] ) )
				);
				?>
			</p>

			<h3 class="cert-sec"><?php esc_html_e( 'Basis of calculation', 'bhela-booking' ); ?></h3>

			<table class="cert-tbl">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'মূলধন · Principal investment', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $snap['principal'] ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'পদ্ধতি · Calculation method', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $snap['method_label'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'অনুমোদিত হার · Approved rate', 'bhela-booking' ); ?></td>
						<td class="num">
							<?php
							echo esc_html( $rate . '%' );
							if ( ! empty( $snap['day_basis'] ) ) {
								echo esc_html( ' · ' . $snap['day_basis'] . '-day basis' );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'সূত্র · Formula', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $snap['formula'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'লাভের সময়কাল · Profit period', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( mysql2date( 'd M Y', $earned_from ) . ' — ' . mysql2date( 'd M Y', $earned_to ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<?php // The period ledger the brief's §11 asks for: month by month, as approved. ?>
			<h3 class="cert-sec"><?php esc_html_e( 'Profit by period', 'bhela-booking' ); ?></h3>

			<table class="cert-tbl">
				<thead>
					<tr>
						<th><?php esc_html_e( 'PERIOD', 'bhela-booking' ); ?></th>
						<th class="num"><?php esc_html_e( 'DAYS', 'bhela-booking' ); ?></th>
						<th class="num"><?php esc_html_e( 'PROFIT IN TK.', 'bhela-booking' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $snap['periods'] as $p ) : ?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'd/m/Y', $p['from'] ) . ' — ' . mysql2date( 'd/m/Y', $p['to'] ) ); ?></td>
							<td class="num"><?php echo esc_html( (string) $p['days'] ); ?></td>
							<td class="num"><?php echo esc_html( $money( $p['amount'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					<tr class="total">
						<td colspan="2"><?php esc_html_e( 'Gross profit', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $snap['gross'] ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<h3 class="cert-sec"><?php esc_html_e( 'Profit settlement', 'bhela-booking' ); ?></h3>

			<table class="cert-tbl">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Gross profit', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $snap['gross'] ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Adjustment', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( ( $snap['adjustments'] > 0 ? '+' : '' ) . $money( $snap['adjustments'] ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Net profit', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $snap['net'] ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Profit paid', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $snap['paid'] ) ); ?></td>
					</tr>
					<tr class="total">
						<td><?php esc_html_e( 'Profit due', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $snap['due'] ) ); ?></td>
					</tr>
				</tbody>
			</table>

			<div class="cert-state">
				<?php echo esc_html( $snap['status']['en'] . ' · ' . $snap['status']['bn'] ); ?>
			</div>

			<?php
			// Said on the face of the document rather than quietly assumed away: a
			// payment is money handed to a PERSON, and nothing in the ledger records
			// which agreement it was against. With one investment there is no ambiguity;
			// with two there is, and inventing an allocation would be worse than saying so.
			?>
			<?php if ( ! empty( $snap['other_investments'] ) ) : ?>
				<div class="cert-note">
					<?php
					printf(
						/* translators: %d: how many other live investments */
						esc_html__( 'এই বিনিয়োগকারীর আরও %d টি চলমান বিনিয়োগ আছে। উপরের "Profit paid" ওই সময়ে তাঁকে দেওয়া মোট অর্থ — এটি কোন চুক্তির বিপরীতে দেওয়া হয়েছে তা খাতায় আলাদা করে লেখা থাকে না। চুক্তিভিত্তিক বিভাজন দরকার হলে অ্যাকাউন্ট স্টেটমেন্ট দেখুন।', 'bhela-booking' ),
						(int) $snap['other_investments']
					);
					?>
				</div>
			<?php endif; ?>

			<p style="font-size:11.5px;color:#546a69;margin-top:10px">
				<?php
				printf(
					/* translators: %s: the date the figures were taken */
					esc_html__( 'সব হিসাব %s তারিখের অবস্থা অনুযায়ী।', 'bhela-booking' ),
					esc_html( mysql2date( 'd M Y', $snap['generated'] ) )
				);
				?>
			</p>

<?php
require BHELA_BM_PATH . 'templates/certificate-foot.php';
