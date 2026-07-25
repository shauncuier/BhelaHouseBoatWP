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
$due      = max( 0, (int) $invoice['total'] - (int) $invoice['paid'] );
// A discounted booking keeps the pre-discount figure in base_price, while the
// line items still add up to it — so without an explicit discount row the guest
// sees the items total one number and the summary total another.
$base     = (int) $invoice['base_price'] > 0 ? (int) $invoice['base_price'] : (int) $invoice['total'];
$discount = max( 0, $base - (int) $invoice['total'] );
// Booking-support number: the dedicated one when set, else the general WhatsApp.
// Used twice (payment block and footer), so it is resolved once up here.
$inv_support = ! empty( $s['support_whatsapp'] ) ? $s['support_whatsapp'] : $s['whatsapp'];
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
	body { font-family:'Noto Sans Bengali','Hind Siliguri','Poppins',sans-serif; background:#eef2f2; color:#1B2B2A; padding:24px; font-size:15px; }
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
	.pay-info { background:#F8F5EF; border-radius:10px; padding:18px 22px; margin:26px 0; line-height:1.9; }
	.pay-info h3 { color:#14676B; font-size:14px; margin-bottom:6px; }
	.pay-qrs { display:flex; gap:22px; flex-wrap:wrap; align-items:flex-start; margin-top:16px; padding-top:16px; border-top:1px dashed #d8cfbc; }
	.pay-qrs figure { margin:0; text-align:center; }
	.pay-qrs img { width:150px; height:150px; object-fit:cover; border-radius:10px; border:3px solid #fff; box-shadow:0 4px 16px rgba(11,46,51,.15); }
	.pay-qrs figcaption { font-size:12.5px; font-weight:700; color:#0B2E33; margin-top:6px; line-height:1.4; }
	.pay-qrs figcaption small { font-weight:400; color:#5b6b6a; }
	.pay-qrs__hint { flex-basis:100%; font-size:12.5px; color:#5b6b6a; margin-top:4px; }
	.note { font-size:12.5px; color:#5b6b6a; line-height:1.8; border-top:1px dashed #cdd9d8; padding-top:16px; }
	.inv-foot { background:#0B2E33; color:#cfe3e2; text-align:center; padding:18px; font-size:13px; }
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
					<span class="inv-meta-row"><svg class="inv-icon" viewBox="0 0 24 24"><path d="M6 2v6h.01L6 8.01 10 12l-4 4 .01.01H6V22h12v-5.99h-.01L18 16l-4-4 4-3.99-.01-.01H18V2zm10 14.5V20H8v-3.5l4-4 4 4zm-4-5L8 7.5V4h8v3.5l-4 4z"/></svg>২ দিন ১ রাত প্যাকেজ</span><br>
					<span class="inv-meta-row"><svg class="inv-icon" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>Boarding: Anwarpur Ghat</span></p>
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
							<td colspan="4" style="font-size:12.5px;color:#5b6b6a">২ দিন ১ রাত — থাকা, সকল খাবার, হাওর ভ্রমণ, গাইড ও নিরাপত্তা অন্তর্ভুক্ত</td>
						</tr>
					<?php else : ?>
						<tr>
							<td><strong><?php echo esc_html( $invoice['cabin'] ? $invoice['cabin'] : 'Houseboat Package' ); ?></strong><br>
							<span style="font-size:12.5px;color:#5b6b6a">২ দিন ১ রাত — থাকা, সকল খাবার, হাওর ভ্রমণ, গাইড ও নিরাপত্তা</span></td>
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
				<div class="row grand due"><span>Balance Due</span><strong><?php echo esc_html( bhela_bm_money( $due ) ); ?></strong></div>
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
				<br><strong>Booking Support (WhatsApp):</strong> <a href="<?php echo esc_url( bhela_bm_wa_url( $inv_support, 'আসসালামু আলাইকুম। আমার বুকিং নম্বর: ' . $invoice['invoice_no'] ) ); ?>" style="color:inherit"><?php echo esc_html( bhela_bm_phone_intl( $inv_support ) ); ?></a>

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

			<?php if ( $invoice['message'] ) : ?>
				<p style="margin-bottom:16px"><strong>Note:</strong> <?php echo esc_html( $invoice['message'] ); ?></p>
			<?php endif; ?>

			<div class="note"><?php echo nl2br( esc_html( $s['invoice_note'] ) ); ?></div>
		</div>

		<div class="inv-foot">
			<?php echo esc_html( $s['business_name'] ); ?> — "<?php echo esc_html( $s['business_tagline'] ); ?>" | <?php echo esc_html( $s['email'] ); ?>
			<?php if ( ! empty( $s['ops_manager'] ) ) : ?>
				<div style="margin-top:6px;font-size:12.5px;opacity:.92">
					<?php esc_html_e( 'Operation Manager:', 'bhela-booking' ); ?> <strong><?php echo esc_html( $s['ops_manager'] ); ?></strong>
					<?php if ( $inv_support ) : ?>
						&nbsp;·&nbsp; <?php esc_html_e( 'WhatsApp:', 'bhela-booking' ); ?>
						<a href="<?php echo esc_url( bhela_bm_wa_url( $inv_support ) ); ?>" style="color:inherit"><?php echo esc_html( bhela_bm_phone_intl( $inv_support ) ); ?></a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</body>
</html>
