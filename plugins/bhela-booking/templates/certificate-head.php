<?php
/**
 * The top half of both certificates: document chrome, the masthead, the holder.
 *
 * Two certificates sharing one stylesheet, one masthead and one identity block. A copy
 * in each template is a copy that drifts — the two documents would slowly stop looking
 * like they came from the same office, which for something filed with a bank is most of
 * what makes it look official. Same reasoning as §13.22.
 *
 * The shape follows the reference BHELA supplied: a ruled page, the letterhead with a
 * document code top right, one centred title, and then a certifying sentence with the
 * values set into it rather than a form of labelled boxes.
 *
 * Provided by includes/certificates.php: $cert, $snap, $settings, $signoff, $types.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$s    = $settings;
$type = $types[ $cert['type'] ] ?? array( 'label' => '', 'en' => '' );

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
					<?php if ( ! empty( $s['business_tagline'] ) ) : ?><div class="org"><?php echo esc_html( $s['business_tagline'] ); ?></div><?php endif; ?>
					<?php if ( ! empty( $s['address'] ) ) : ?><div class="org"><?php echo esc_html( $s['address'] ); ?></div><?php endif; ?>
					<?php
					// Blank hides the line outright — §13.25. A stale or invented vessel
					// registration misrepresents the boat; a missing one is only missing.
					?>
					<?php if ( ! empty( $s['vessel_reg'] ) ) : ?><div class="org"><?php echo esc_html( $s['vessel_reg'] ); ?></div><?php endif; ?>
				</div>
			</div>
			<div class="cert-no">
				<?php esc_html_e( 'Certificate No.', 'bhela-booking' ); ?><br>
				<span class="num"><?php echo esc_html( $cert['number'] ); ?></span><br>
				<?php echo esc_html( mysql2date( 'd M Y', $cert['issued'] ) ); ?><br>
				<?php echo esc_html( $snap['investment_id'] ); ?>
			</div>
		</div>

		<?php if ( ! empty( $cert['superseded'] ) ) : ?>
			<?php
			// The investor may be holding this piece of paper, so it still renders in
			// full and says plainly that a newer version replaced it. Hiding it would
			// leave somebody holding a document the office no longer acknowledges, with
			// nothing on the page to tell them.
			?>
			<div class="cert-super" style="margin-top:14px">
				<?php
				printf(
					/* translators: %s: the replacing certificate number */
					esc_html__( 'এই সনদটি প্রতিস্থাপিত — বর্তমান সংস্করণ %s।', 'bhela-booking' ),
					esc_html( $cert['superseded_number'] )
				);
				if ( ! empty( $cert['super_reason'] ) ) {
					echo ' ' . esc_html( $cert['super_reason'] );
				}
				?>
			</div>
		<?php endif; ?>

		<div class="cert-title">
			<h2><?php echo esc_html( $type['label'] ); ?></h2>
			<div class="en"><?php echo esc_html( $type['en'] ); ?></div>
		</div>

		<div class="cert-body">
