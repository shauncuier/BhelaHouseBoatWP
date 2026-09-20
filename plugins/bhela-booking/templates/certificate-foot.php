<?php
/**
 * The bottom half of both certificates: signatures, verification, disclaimer.
 *
 * The three signatures are read from the records' own history (`$signoff`), not typed
 * into a box — see `bhela_bm_cert_signoff()` for why. A blank one still leaves its rule
 * so the document can be signed by hand, which is §13.25's reasoning applied to a person
 * rather than to a registration number.
 *
 * The disclaimer is NOT a setting and cannot be edited away. These documents state
 * figures about somebody's money and will end up in a tax file; a reader has to be able
 * to see at a glance that BHELA issued them and the NBR did not. A line the office could
 * remove is a line that would be removed.
 *
 * Provided by includes/certificates.php: $cert, $snap, $settings, $signoff, plus
 * $verify_url from the head partial.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s         = $settings;
$signatory = trim( (string) ( $s['cert_signatory'] ?? '' ) );
$sig_role  = trim( (string) ( $s['cert_signatory_role'] ?? '' ) );
$house     = trim( (string) ( $s['cert_note'] ?? '' ) );
$qr        = ( function_exists( 'bhela_bm_qr_svg' ) && $verify_url )
	? bhela_bm_qr_svg( $verify_url, 104, __( 'Certificate verification QR code', 'bhela-booking' ) )
	: '';
?>
			<?php if ( ! empty( $cert['note'] ) ) : ?>
				<div class="cert-note"><?php echo esc_html( $cert['note'] ); ?></div>
			<?php endif; ?>

			<?php // A standing line the office sets once in Settings. Blank prints nothing. ?>
			<?php if ( '' !== $house ) : ?>
				<div class="cert-note"><?php echo esc_html( $house ); ?></div>
			<?php endif; ?>

			<div class="cert-sign">
				<div>
					<?php if ( ! empty( $signoff['prepared'] ) ) : ?><strong><?php echo esc_html( $signoff['prepared'] ); ?></strong><?php endif; ?>
					<span class="role"><?php esc_html_e( 'Prepared by', 'bhela-booking' ); ?></span>
				</div>
				<div>
					<?php if ( ! empty( $signoff['verified'] ) ) : ?><strong><?php echo esc_html( $signoff['verified'] ); ?></strong><?php endif; ?>
					<span class="role"><?php esc_html_e( 'Verified by', 'bhela-booking' ); ?></span>
				</div>
				<div>
					<?php
					// The approver's name comes from who issued the document; the
					// signatory line beneath is the office's standing signature.
					if ( ! empty( $signoff['approved'] ) ) :
						?>
						<strong><?php echo esc_html( $signoff['approved'] ); ?></strong>
					<?php endif; ?>
					<span class="role">
						<?php
						esc_html_e( 'Approved by', 'bhela-booking' );
						if ( '' !== $signatory ) {
							echo ' · ' . esc_html( $signatory );
						}
						if ( '' !== $sig_role ) {
							echo ' (' . esc_html( $sig_role ) . ')';
						}
						?>
					</span>
				</div>
			</div>

			<div class="cert-verify">
				<?php
				// Inline SVG, so it prints black-on-white with nothing fetched from
				// anywhere. echo is safe: bhela_bm_qr_svg() builds the whole element and
				// escapes its only text attribute.
				echo $qr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
				<div class="txt">
					<strong><?php esc_html_e( 'Verify this certificate', 'bhela-booking' ); ?></strong>
					<?php echo esc_html( $verify_url ); ?><br>
					<?php esc_html_e( 'QR স্ক্যান করে বা উপরের ঠিকানায় গিয়ে সনদটি আসল কি না যাচাই করা যায়। যাচাইয়ের পাতায় কোনো টাকার অঙ্ক দেখানো হয় না।', 'bhela-booking' ); ?>
				</div>
			</div>
		</div><!-- /.cert-body -->

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
			<br>
			<?php
			// Says out loud what makes the document worth anything: the figures were
			// frozen when it was issued, so a reprint cannot differ from the paper.
			esc_html_e( 'এই সনদের সব হিসাব ইস্যুর দিনের অবস্থায় সংরক্ষিত — পুনরায় প্রিন্ট করলেও একই থাকবে। সংশোধন হলে নতুন সংস্করণ ইস্যু হয়।', 'bhela-booking' );
			?>
		</div>
	</div><!-- /.cert -->
</body>
</html>
