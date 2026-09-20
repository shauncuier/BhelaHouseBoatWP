<?php
/**
 * Investment Certificate — what was invested, on what terms, under which agreement.
 *
 * Every figure comes from the STORED snapshot ($snap). Nothing is recomputed: the
 * document must read the same next year as it did the day it was signed, which is the
 * whole reason a certificate is issued rather than rendered.
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
?>

			<p class="cert-sent">
				<?php
				printf(
					/* translators: 1: investor name, 2: investor id, 3: project, 4: amount, 5: investment id */
					wp_kses(
						__( 'This is to certify that <u>%1$s</u>, Investor ID <u>%2$s</u>, has invested with <u>%3$s</u> a principal amount of <u>%4$s</u>, recorded in the BHELA Investment Management System under Investment ID <u>%5$s</u>.', 'bhela-booking' ),
						array( 'u' => array() )
					),
					esc_html( $snap['name'] ),
					esc_html( '' !== $snap['code'] ? $snap['code'] : '—' ),
					esc_html( $snap['project'] ),
					esc_html( $money( $snap['principal'] ) ),
					esc_html( $snap['investment_id'] )
				);
				?>
			</p>

			<h3 class="cert-sec"><?php esc_html_e( 'Investment details', 'bhela-booking' ); ?></h3>

			<table class="cert-tbl">
				<tbody>
					<?php if ( ! empty( $snap['father'] ) ) : ?>
						<tr>
							<td><?php esc_html_e( 'পিতার নাম · Father', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( $snap['father'] ); ?></td>
						</tr>
					<?php endif; ?>
					<tr>
						<td><?php esc_html_e( 'মূলধন · Investment amount', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $money( $snap['principal'] ) ); ?></td>
					</tr>
					<?php if ( $snap['invest_date'] ) : ?>
						<tr>
							<td><?php esc_html_e( 'বিনিয়োগের তারিখ · Investment date', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( mysql2date( 'd M Y', $snap['invest_date'] ) ); ?></td>
						</tr>
					<?php endif; ?>
					<tr>
						<td><?php esc_html_e( 'মেয়াদ · Investment period', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( mysql2date( 'd M Y', $snap['start'] ) . ' — ' . mysql2date( 'd M Y', $snap['maturity'] ) ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'সময়কাল · Duration', 'bhela-booking' ); ?></td>
						<td class="num">
							<?php
							printf(
								/* translators: %d: months */
								esc_html__( '%d months', 'bhela-booking' ),
								(int) $snap['months']
							);
							?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'ধরন · Investment type', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $snap['inv_type'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'লাভের ভিত্তি · Profit basis', 'bhela-booking' ); ?></td>
						<td class="num">
							<?php
							echo esc_html( $snap['method_label'] . ' · ' . rtrim( rtrim( number_format( $snap['rate'], 2, '.', '' ), '0' ), '.' ) . '%' );
							// The day-count basis changes the answer, so the document says which
							// was used rather than leaving it to be reverse-engineered.
							if ( ! empty( $snap['day_basis'] ) ) {
								echo esc_html( ' · ' . $snap['day_basis'] . '-day basis' );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'পরিশোধের সময়সূচি · Payment frequency', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $snap['frequency'] ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'অবস্থা · Status', 'bhela-booking' ); ?></td>
						<td class="num"><?php echo esc_html( $snap['inv_status'] ); ?></td>
					</tr>
					<?php if ( ! empty( $snap['agreement_ref'] ) ) : ?>
						<tr>
							<td><?php esc_html_e( 'চুক্তির রেফারেন্স · Agreement reference', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( $snap['agreement_ref'] ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<?php
			// The receipts the principal is the sum of. Printed so the certificate shows
			// its own working: a total nobody can trace back to dated payments is a
			// number the reader has to take on trust.
			?>
			<?php if ( ! empty( $snap['receipts'] ) ) : ?>
				<h3 class="cert-sec"><?php esc_html_e( 'Amounts received', 'bhela-booking' ); ?></h3>
				<table class="cert-tbl">
					<thead>
						<tr>
							<th><?php esc_html_e( 'DATE', 'bhela-booking' ); ?></th>
							<th><?php esc_html_e( 'METHOD', 'bhela-booking' ); ?></th>
							<th><?php esc_html_e( 'REFERENCE', 'bhela-booking' ); ?></th>
							<th class="num"><?php esc_html_e( 'AMOUNT IN TK.', 'bhela-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $snap['receipts'] as $r ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'd/m/Y', $r['date'] ) ); ?></td>
								<td><?php echo esc_html( $r['method'] ); ?></td>
								<td><?php echo esc_html( $r['ref'] ); ?></td>
								<td class="num"><?php echo esc_html( $money( $r['amount'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						<tr class="total">
							<td colspan="3"><?php esc_html_e( 'Total', 'bhela-booking' ); ?></td>
							<td class="num"><?php echo esc_html( $money( $snap['principal'] ) ); ?></td>
						</tr>
					</tbody>
				</table>
			<?php endif; ?>

			<div class="cert-note">
				<?php esc_html_e( 'This investment is subject to the terms and conditions of the applicable Investment Agreement. This certificate records the investment as held in BHELA\'s books and does not by itself create or vary any term of that agreement.', 'bhela-booking' ); ?>
			</div>

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
