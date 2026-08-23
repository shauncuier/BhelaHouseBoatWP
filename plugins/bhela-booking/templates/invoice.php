<?php
/**
 * Printable invoice template. $invoice array is provided by includes/invoice.php.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s        = $invoice['settings'];
$statuses = bhela_bm_statuses();
$bal      = bhela_bm_balance( $invoice['total'], $invoice['paid'] );
$due      = $bal['due'];
// A discounted booking keeps the pre-discount figure in base_price, while the
// line items still add up to it — so without an explicit discount row the guest
// sees the items total one number and the summary total another.
$base     = (int) $invoice['base_price'] > 0 ? (int) $invoice['base_price'] : (int) $invoice['total'];
$discount = max( 0, $base - (int) $invoice['total'] );
// Booking-support number: the dedicated one when set, else the general WhatsApp.
// Used twice (payment block and footer), so it is resolved once up here.
// Two distinct numbers: the general booking WhatsApp in the payment block, and
// the operation manager's own in the footer. Falling both back to the same
// setting printed one number twice and hid the other entirely.
$inv_wa     = $s['whatsapp'];
$inv_mgr_wa = ! empty( $s['support_whatsapp'] ) ? $s['support_whatsapp'] : '';
// Only repeat a number in the footer when it is genuinely a different one —
// compare normalised, so +8801781720957 and 01781720957 count as the same.
if ( $inv_mgr_wa && bhela_bm_normalize_mobile( $inv_mgr_wa ) === bhela_bm_normalize_mobile( $inv_wa ) ) {
	$inv_mgr_wa = '';
}
// Trip logistics. All three were hardcoded into this template — the boarding ghat
// once and the package label twice more further down — so changing where the boat
// leaves from meant editing PHP. A booking may override the ghat; blank means the
// house default.
$inv_package  = (string) ( $s['package_label'] ?? '' );
$inv_boarding = (string) ( get_post_meta( $invoice['booking_id'], '_bhela_boarding', true ) ?: ( $s['boarding_ghat'] ?? '' ) );
$inv_stay     = bhela_bm_booking_stay( $invoice['booking_id'] );
$logo     = '';
$theme_logo = get_template_directory() . '/assets/images/logo.png';
if ( file_exists( $theme_logo ) ) {
	$logo = get_template_directory_uri() . '/assets/images/logo.png';
}
$day_labels = array( 'weekday' => 'Weekday (২০% ছাড়)', 'weekend' => 'Weekend', 'holiday' => 'Holiday' );
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Invoice <?php echo esc_html( $invoice['invoice_no'] ); ?> — <?php echo esc_html( $s['business_name'] ); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Noto+Sans+Bengali:wght@400;600;700&family=Noto+Serif+Bengali:wght@600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
	* { margin:0; padding:0; box-sizing:border-box; }
	/* 'Nirmala UI' is the local safety net. The two Bengali webfonts above load
	   from Google and normally win, but a guest reading this offline, behind a
	   blocked CDN, or saving it to PDF on a locked-down machine falls through to
	   Poppins — which has no ৳, so every figure on the invoice gets an
	   undersized substituted symbol. */
	body { font-family:'Noto Sans Bengali','Hind Siliguri','Nirmala UI','Poppins',sans-serif; background:#eef2f2; color:#1B2B2A; padding:24px; font-size:15px; }
	.invoice { max-width:820px; margin:0 auto; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 40px rgba(11,46,51,.12); }
	.inv-head { background:linear-gradient(135deg,#0B2E33,#14676B); color:#fff; padding:32px 40px; display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; }
	.inv-head img { height:64px; border-radius:10px; background:#fff; padding:4px; }
	.inv-head h1 { font-size:22px; letter-spacing:.5px; }
	.inv-head p { opacity:.85; font-size:13px; }
	.inv-no { text-align:right; }
	.inv-no .num { font-size:20px; font-weight:700; color:#FFB88C; }
	.badge { display:inline-block; margin-top:6px; padding:3px 14px; border-radius:20px; font-size:12px; font-weight:700; background:<?php echo esc_attr( bhela_bm_status_color( $invoice['status'] ) ); ?>; color:#fff; text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }
	.inv-body { padding:32px 40px; }
	.cols { display:flex; justify-content:space-between; gap:24px; flex-wrap:wrap; margin-bottom:28px; }
	.cols h3 { font-size:12px; text-transform:uppercase; letter-spacing:1px; color:#14676B; margin-bottom:8px; }
	.cols p { line-height:1.7; }
	table.items { width:100%; border-collapse:collapse; margin-bottom:24px; }
	table.items th { background:#F2F7F6; text-align:left; padding:12px 14px; font-size:12px; text-transform:uppercase; letter-spacing:.5px; color:#0B2E33; }
	table.items td { padding:14px; border-bottom:1px solid #E7EEED; }
	table.items td:last-child, table.items th:last-child { text-align:right; }
	.totals { margin-left:auto; width:320px; max-width:100%; }
	.totals .row { display:flex; justify-content:space-between; padding:8px 4px; }
	.totals .row.grand { border-top:2px solid #0B2E33; font-size:19px; font-weight:700; margin-top:6px; padding-top:14px; align-items:baseline; }
	/* Balance Due is the one figure the guest acts on, so it outweighs every other row. */
	.totals .row.grand.due strong { font-size:26px; line-height:1.1; letter-spacing:-.5px; }
	.totals .row.due strong { color:#D8621E; }
	.totals .row.paid strong { color:#1a7f37; }
	.totals .row.discount strong { color:#1a7f37; }
	.totals .row.subtotal-net { border-top:1px solid #d9dde0; font-weight:600; }
	/* PAID stamp — the guest's one-glance answer to "do I still owe anything?".
	   Built from a border and foreground text only, never a fill: the browser
	   default is print-color-adjust:economy, which drops background-color and
	   background-image, so a filled green badge prints as nothing at all. An
	   outline survives ink-saving mode, greyscale, and a photocopy. */
	.paid-stamp { display:flex; align-items:center; gap:14px; margin-top:16px;
		padding:12px 18px; border:3px solid #1a7f37; border-radius:10px;
		color:#1a7f37; transform:rotate(-3deg); }
	.paid-stamp__tick { font-size:26px; line-height:1; flex:none; }
	.paid-stamp__en { font-size:22px; font-weight:700; letter-spacing:2px;
		text-transform:uppercase; line-height:1.1; }
	.paid-stamp__bn { font-size:13px; font-weight:600; letter-spacing:.3px; }
	/* Settled: the alarm colour has nothing left to warn about. */
	.totals .row.due.is-settled strong { color:#1a7f37; }
	@media (max-width:560px) { .paid-stamp { transform:none; } }
	.pay-info { background:#F8F5EF; border-radius:10px; padding:18px 22px; margin:26px 0; line-height:1.9; }
	.pay-info h3 { color:#14676B; font-size:14px; margin-bottom:6px; }
	.pay-qrs { display:flex; gap:22px; flex-wrap:wrap; align-items:flex-start; margin-top:16px; padding-top:16px; border-top:1px dashed #d8cfbc; }
	.pay-qrs figure { margin:0; text-align:center; }
	.pay-qrs img { width:150px; height:150px; object-fit:cover; border-radius:10px; border:3px solid #fff; box-shadow:0 4px 16px rgba(11,46,51,.15); }
	.pay-qrs figcaption { font-size:12.5px; font-weight:700; color:#0B2E33; margin-top:6px; line-height:1.4; }
	.pay-qrs figcaption small { font-weight:400; color:#5b6b6a; }
	.pay-qrs__hint { flex-basis:100%; font-size:12.5px; color:#5b6b6a; margin-top:4px; }
	/* Trip manager — the one contact a guest needs after booking, so it is a
	   card in the body rather than a line in the footer. Kept light so it does
	   not drink ink when the invoice is printed. */
	.mgr { display:flex; align-items:center; gap:18px; flex-wrap:wrap;
		background:#EBF7EF; border:1px solid #A8DDBB; border-left:6px solid #25D366;
		border-radius:12px; padding:18px 22px; margin:26px 0; }
	.mgr__ico { width:46px; height:46px; border-radius:50%; background:#25D366; color:#fff;
		display:flex; align-items:center; justify-content:center; font-size:24px; flex:none; }
	.mgr__body { flex:1; min-width:200px; }
	.mgr__label { font-size:11.5px; text-transform:uppercase; letter-spacing:1px; color:#14676B; font-weight:700; }
	.mgr__name { font-size:17px; font-weight:700; color:#0B2E33; line-height:1.3; margin-top:2px; }
	.mgr__num { display:inline-block; font-size:21px; font-weight:700; color:#0B2E33;
		text-decoration:none; letter-spacing:-.3px; margin-top:4px; }
	.mgr__num:hover { color:#137A74; }
	.mgr__hint { font-size:12.5px; color:#4b6b5e; margin-top:3px; }
	.mgr__cta { background:#25D366; color:#fff; text-decoration:none; font-weight:700; font-size:14px;
		padding:12px 22px; border-radius:999px; white-space:nowrap; flex:none; }
	.mgr__cta:hover { background:#1da851; }
	@media (max-width:560px) { .mgr__cta { width:100%; text-align:center; } }

	.note { font-size:12.5px; color:#5b6b6a; line-height:1.8; border-top:1px dashed #cdd9d8; padding-top:16px; }
	/* Service notes. Bordered rather than filled, for the same reason the PAID
	   stamp is: browsers print with print-color-adjust:economy by default and drop
	   background colours, so a tinted panel prints as nothing and takes the text's
	   only visual grouping with it. */
	.svc-note { border:1px solid #cdd9d8; border-left:4px solid #14676B; border-radius:8px;
		padding:12px 16px; margin-bottom:16px; }
	.svc-note h4 { font-size:12px; text-transform:uppercase; letter-spacing:.6px;
		color:#14676B; margin-bottom:6px; }
	.svc-note ul { margin:0; padding-left:18px; }
	.svc-note li { font-size:12.5px; color:#3d5150; line-height:1.7; }
	.inv-foot { background:#0B2E33; color:#cfe3e2; text-align:center; padding:18px; font-size:13px; }
	.inv-foot a { color:#cfe3e2; text-decoration:none; }
	.inv-foot a:hover { color:#fff; text-decoration:underline; }
	.inv-foot__by { margin-top:8px; font-size:11.5px; color:#7fa6a4; }
	.inv-foot__by a { color:#9fd8d2; font-weight:600; }
	.print-bar { max-width:820px; margin:0 auto 16px; display:flex; justify-content:flex-end; gap:10px; }
	.print-bar button { background:#F2762E; color:#fff; border:0; padding:12px 26px; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; font-family:inherit; display:inline-flex; align-items:center; }
	.print-bar button:hover { background:#D8621E; }
	.inv-meta-row { display:inline-flex; align-items:center; gap:8px; margin-top:6px; vertical-align:middle; width:100%; }
	.inv-icon { display:inline-block; width:16px; height:16px; fill:#14676B; flex-shrink:0; }
	.btn-icon { display:inline-block; width:18px; height:18px; fill:currentColor; margin-right:8px; flex-shrink:0; }
	@media print {
		body { background:#fff; padding:0; }
		.print-bar { display:none; }
		.invoice { box-shadow:none; border-radius:0; max-width:100%; }
	}
</style>
</head>
<body>
	<div class="print-bar">
		<button onclick="window.print()">
			<svg class="btn-icon" viewBox="0 0 24 24"><path d="M19 8H5c-1.66 0-3 1.34-3 3v6h4v4h12v-4h4v-6c0-1.66-1.34-3-3-3zm-3 11H8v-5h8v5zm3-7c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm-1-9H6v4h12V3z"/></svg>
			Print / Save as PDF
		</button>
	</div>
	<div class="invoice">
		<div class="inv-head">
			<div style="display:flex;align-items:center;gap:16px">
				<?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="BHELA logo"><?php endif; ?>
				<div>
					<h1><?php echo esc_html( $s['business_name'] ); ?></h1>
					<p><?php echo esc_html( $s['business_tagline'] ); ?></p>
					<p><?php echo esc_html( $s['address'] ); ?></p>
				</div>
			</div>
			<div class="inv-no">
				<div style="font-size:13px;opacity:.8">INVOICE</div>
				<div class="num"><?php echo esc_html( $invoice['invoice_no'] ); ?></div>
				<div style="font-size:12px;opacity:.8;margin-top:4px"><?php echo esc_html( mysql2date( 'd M Y', $invoice['created'] ) ); ?></div>
				<span class="badge"><?php echo esc_html( $statuses[ $invoice['status'] ] ?? $invoice['status'] ); ?></span>
			</div>
		</div>

		<div class="inv-body">
			<div class="cols">
				<div>
					<h3>Bill To / অতিথি</h3>
					<p><strong><?php echo esc_html( $invoice['name'] ); ?></strong><br>
					<span class="inv-meta-row"><svg class="inv-icon" viewBox="0 0 24 24"><path d="M17 1.01L7 1c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V3c0-1.1-.9-1.99-2-1.99zM17 19H7V5h10v14z"/></svg><?php
				$inv_tel = bhela_bm_phone_href( $invoice['phone'] );
				echo $inv_tel
					? '<a href="' . esc_url( $inv_tel ) . '" style="color:inherit;text-decoration:none">' . esc_html( bhela_bm_phone_intl( $invoice['phone'] ) ) . '</a>'
					: esc_html( bhela_bm_phone_intl( $invoice['phone'] ) );
				?></span>
					<?php if ( $invoice['email'] ) : ?><br><span class="inv-meta-row"><svg class="inv-icon" viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg><?php echo esc_html( $invoice['email'] ); ?></span><?php endif; ?></p>
				</div>
				<div>
					<h3>Trip Details / ভ্রমণ</h3>
					<p><span class="inv-meta-row"><svg class="inv-icon" viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg><strong><?php echo esc_html( $invoice['travel_date'] ); ?></strong><?php if ( $invoice['day_type'] ) : ?>&nbsp;(<?php echo esc_html( $day_labels[ $invoice['day_type'] ] ?? $invoice['day_type'] ); ?>)<?php endif; ?></span><br>
					<span class="inv-meta-row"><svg class="inv-icon" viewBox="0 0 24 24"><path d="M6 2v6h.01L6 8.01 10 12l-4 4 .01.01H6V22h12v-5.99h-.01L18 16l-4-4 4-3.99-.01-.01H18V2zm10 14.5V20H8v-3.5l4-4 4 4zm-4-5L8 7.5V4h8v3.5l-4 4z"/></svg><?php echo esc_html( $inv_package ); ?> প্যাকেজ</span><br>
					<span class="inv-meta-row"><svg class="inv-icon" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>Boarding: <?php echo esc_html( $inv_boarding ); ?></span><br>
					<span class="inv-meta-row"><svg class="inv-icon" viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>Check-in <?php echo esc_html( $inv_stay['in_time'] ); ?> &middot; Check-out <?php echo esc_html( $inv_stay['out_time'] ); ?></span></p>
				</div>
			</div>

			<table class="items">
				<thead><tr><th>Description</th><th>Guests</th><th>Per Person</th><th>Amount</th></tr></thead>
				<tbody>
					<?php if ( ! empty( $invoice['lines'] ) ) : ?>
						<?php foreach ( $invoice['lines'] as $inv_line ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $inv_line['label'] ); ?></strong><br>
								<span style="font-size:12.5px;color:#5b6b6a"><?php echo esc_html( $inv_line['who'] ); ?></span></td>
								<?php
								// Guests = people in the cabin. Older records stored the rate
								// tier in `occ`, so prefer `people` when it is present.
								$inv_guests = (int) ( $inv_line['people'] ?? $inv_line['occ'] ?? 0 );
								?>
								<td><?php echo esc_html( $inv_guests ); ?> জন</td>
								<td><?php echo esc_html( isset( $inv_line['rate'] ) ? bhela_bm_money( $inv_line['rate'] ) : '—' ); ?><?php if ( ! empty( $inv_line['c48'] ) ) : ?><br><span style="font-size:12px;color:#5b6b6a">শিশু (৪–৮) <?php echo esc_html( bhela_bm_money( (int) bhela_bm_get_settings()['child_fee'] ) ); ?>/জন</span><?php endif; ?></td>
								<td><?php echo esc_html( bhela_bm_money( (int) ( $inv_line['total'] ?? 0 ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<td colspan="4" style="font-size:12.5px;color:#5b6b6a"><?php echo esc_html( $inv_package ); ?> — থাকা, সকল খাবার, হাওর ভ্রমণ, গাইড ও নিরাপত্তা অন্তর্ভুক্ত</td>
						</tr>
					<?php else : ?>
						<tr>
							<td><strong><?php echo esc_html( $invoice['cabin'] ? $invoice['cabin'] : 'Houseboat Package' ); ?></strong><br>
							<span style="font-size:12.5px;color:#5b6b6a"><?php echo esc_html( $inv_package ); ?> — থাকা, সকল খাবার, হাওর ভ্রমণ, গাইড ও নিরাপত্তা</span></td>
							<td><?php echo esc_html( $invoice['guests'] ); ?> জন</td>
							<td><?php echo esc_html( $invoice['per_person'] ? bhela_bm_money( $invoice['per_person'] ) : '—' ); ?></td>
							<td><?php echo esc_html( $invoice['total'] ? bhela_bm_money( $invoice['total'] ) : '—' ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<div class="totals">
				<div class="row"><span>Subtotal</span><strong><?php echo esc_html( bhela_bm_money( $base ) ); ?></strong></div>
				<?php if ( $discount > 0 ) : ?>
					<div class="row discount"><span>Discount / ছাড়</span><strong>− <?php echo esc_html( bhela_bm_money( $discount ) ); ?></strong></div>
					<div class="row subtotal-net"><span>Total</span><strong><?php echo esc_html( bhela_bm_money( $invoice['total'] ) ); ?></strong></div>
				<?php endif; ?>
				<div class="row"><span>Advance (<?php echo esc_html( bhela_bm_advance_pct( $invoice['advance'], $invoice['total'] ) ); ?>%)</span><strong><?php echo esc_html( bhela_bm_money( $invoice['advance'] ) ); ?></strong></div>
				<div class="row paid"><span>Paid</span><strong><?php echo esc_html( bhela_bm_money( $invoice['paid'] ) ); ?><?php echo $invoice['pay_method'] ? ' (' . esc_html( strtoupper( $invoice['pay_method'] ) ) . ( $invoice['txn_id'] ? ' — ' . esc_html( $invoice['txn_id'] ) : '' ) . ')' : ''; ?></strong></div>
				<div class="row grand due<?php echo $bal['settled'] ? ' is-settled' : ''; ?>"><span>Balance Due</span><strong><?php echo esc_html( bhela_bm_money( $due ) ); ?></strong></div>
				<?php
				// The row stays even when it reads ৳0 — a guest comparing an unpaid
				// copy with a settled one should see the same lines saying different
				// things, not one document missing a line.
				if ( $bal['settled'] ) :
					?>
					<div class="paid-stamp">
						<span class="paid-stamp__tick" aria-hidden="true">✓</span>
						<span>
							<span class="paid-stamp__en">Paid</span><br>
							<span class="paid-stamp__bn">সম্পূর্ণ পরিশোধিত</span>
						</span>
					</div>
				<?php endif; ?>
			</div>

			<div class="pay-info">
				<h3>💳 Payment Options / পেমেন্ট মাধ্যম</h3>
				<strong>Bangla QR (bKash/Bank App):</strong> <?php echo esc_html( $s['bkash_number'] ); ?><br>
				<strong>Nagad:</strong> <?php echo esc_html( $s['nagad_number'] ); ?>
				<?php if ( $s['bank_details'] ) : ?><br><strong>Bank:</strong> <?php echo nl2br( esc_html( $s['bank_details'] ) ); ?><?php endif; ?>
				<?php
				// One page used to carry three phone formats; everything dialable is
				// normalised to +880 here and linked, since invoices get read on a
				// phone at least as often as they get printed.
				$inv_phone = function ( $raw ) {
					$label = bhela_bm_phone_intl( $raw );
					$href  = bhela_bm_phone_href( $raw );
					return $href
						? '<a href="' . esc_url( $href ) . '" style="color:inherit;text-decoration:none">' . esc_html( $label ) . '</a>'
						: esc_html( $label );
				};
				?>
				<br><strong>📞</strong> <?php echo $inv_phone( $s['phone_1'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>, <?php echo $inv_phone( $s['phone_2'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<br><strong>Booking Support (WhatsApp):</strong> <a href="<?php echo esc_url( bhela_bm_wa_url( $inv_wa, 'আসসালামু আলাইকুম। আমার বুকিং নম্বর: ' . $invoice['invoice_no'] ) ); ?>" style="color:inherit"><?php echo esc_html( bhela_bm_phone_intl( $inv_wa ) ); ?></a>

				<?php if ( ! empty( $s['nagad_qr'] ) || ! empty( $s['bangla_qr'] ) ) : ?>
					<div class="pay-qrs">
						<?php if ( ! empty( $s['bangla_qr'] ) ) : ?>
							<figure>
								<img src="<?php echo esc_url( $s['bangla_qr'] ); ?>" alt="Bangla QR — bKash বা ব্যাংক অ্যাপ দিয়ে স্ক্যান করে পেমেন্ট করুন">
								<figcaption>Bangla QR<br><small>bKash / Bank App</small></figcaption>
							</figure>
						<?php endif; ?>
						<?php if ( ! empty( $s['nagad_qr'] ) ) : ?>
							<figure>
								<img src="<?php echo esc_url( $s['nagad_qr'] ); ?>" alt="Nagad QR — স্ক্যান করে পেমেন্ট করুন">
								<figcaption>Nagad<br><small><?php echo esc_html( $s['nagad_number'] ); ?></small></figcaption>
							</figure>
						<?php endif; ?>
						<p class="pay-qrs__hint">📲 অ্যাপ খুলে <strong>Scan QR</strong> → পেমেন্ট করুন → Transaction ID টি WhatsApp-এ পাঠান</p>
					</div>
				<?php endif; ?>
			</div>

			<?php
			// The manager runs everything once the booking is made, so his number
			// is the single most useful thing on this page after the amount due.
			$mgr_wa = $inv_mgr_wa ? $inv_mgr_wa : $inv_wa;
			if ( ! empty( $s['ops_manager'] ) && $mgr_wa ) :
				$mgr_msg = 'আসসালামু আলাইকুম। আমার বুকিং নম্বর: ' . $invoice['invoice_no'];
				?>
				<div class="mgr">
					<span class="mgr__ico" aria-hidden="true">👤</span>
					<div class="mgr__body">
						<div class="mgr__label"><?php esc_html_e( 'Your trip manager', 'bhela-booking' ); ?></div>
						<div class="mgr__name"><?php echo esc_html( $s['ops_manager'] ); ?></div>
						<a class="mgr__num" href="<?php echo esc_url( bhela_bm_wa_url( $mgr_wa, $mgr_msg ) ); ?>"><?php echo esc_html( bhela_bm_phone_intl( $mgr_wa ) ); ?></a>
						<div class="mgr__hint"><?php esc_html_e( 'বুকিংয়ের পর ট্রিপ সংক্রান্ত যেকোনো প্রয়োজনে সরাসরি যোগাযোগ করুন — সময়, রিপোর্টিং, খাবার বা যেকোনো পরিবর্তন।', 'bhela-booking' ); ?></div>
					</div>
					<a class="mgr__cta" href="<?php echo esc_url( bhela_bm_wa_url( $mgr_wa, $mgr_msg ) ); ?>">💬 <?php esc_html_e( 'WhatsApp', 'bhela-booking' ); ?></a>
				</div>
			<?php endif; ?>

			<?php if ( $invoice['message'] ) : ?>
				<p style="margin-bottom:16px"><strong>Note:</strong> <?php echo esc_html( $invoice['message'] ); ?></p>
			<?php endif; ?>

			<?php
			// The standing service notes — AC hours, electricity. Same setting the
			// WhatsApp confirmation prints, so the two cannot tell a guest different
			// things about what they are getting. Titled rather than left as a bare
			// "Note:", because the guest's own note above already uses that word.
			$inv_notes = function_exists( 'bhela_bm_confirm_note_lines' ) ? bhela_bm_confirm_note_lines() : array();
			if ( $inv_notes ) :
				?>
				<div class="svc-note">
					<h4><?php esc_html_e( 'Service Notes / সার্ভিস তথ্য', 'bhela-booking' ); ?></h4>
					<ul>
						<?php foreach ( $inv_notes as $inv_n ) : ?>
							<li><?php echo esc_html( $inv_n ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="note"><?php echo nl2br( esc_html( bhela_bm_render_invoice_note( $s['invoice_note'], $invoice ) ) ); ?></div>
		</div>

		<?php
		// The name, tagline and address are already in the header, so repeating
		// them here said nothing new. The footer now carries only what is not
		// above it: where to find us online, and who built the system.
		$inv_site = home_url( '/' );
		$inv_host = preg_replace( '#^www\.#', '', (string) wp_parse_url( $inv_site, PHP_URL_HOST ) );
		?>
		<div class="inv-foot">
			<a href="<?php echo esc_url( $inv_site ); ?>">🌐 <?php echo esc_html( $inv_host ); ?></a>
			&nbsp;·&nbsp;
			<a href="mailto:<?php echo esc_attr( $s['email'] ); ?>">✉️ <?php echo esc_html( $s['email'] ); ?></a>
			<div class="inv-foot__by">
				<?php
				printf(
					/* translators: %s: linked developer name */
					esc_html__( 'Designed &amp; developed by %s', 'bhela-booking' ),
					'<a href="https://3s-soft.com" target="_blank" rel="noopener">3s-Soft</a>'
				);
				?>
			</div>
		</div>
	</div>
</body>
</html>
