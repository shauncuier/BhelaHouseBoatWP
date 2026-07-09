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
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
	* { margin:0; padding:0; box-sizing:border-box; }
	body { font-family:'Hind Siliguri','Poppins',sans-serif; background:#eef2f2; color:#1B2B2A; padding:24px; font-size:15px; }
	.invoice { max-width:820px; margin:0 auto; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 40px rgba(11,46,51,.12); }
	.inv-head { background:linear-gradient(135deg,#0B2E33,#14676B); color:#fff; padding:32px 40px; display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; }
	.inv-head img { height:64px; border-radius:10px; background:#fff; padding:4px; }
	.inv-head h1 { font-size:22px; letter-spacing:.5px; }
	.inv-head p { opacity:.85; font-size:13px; }
	.inv-no { text-align:right; }
	.inv-no .num { font-size:20px; font-weight:700; color:#FFB88C; }
	.badge { display:inline-block; margin-top:6px; padding:3px 14px; border-radius:20px; font-size:12px; font-weight:700; background:<?php echo esc_attr( bhela_bm_status_color( $invoice['status'] ) ); ?>; color:#fff; text-transform:uppercase; letter-spacing:.5px; }
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
	.totals .row.grand { border-top:2px solid #0B2E33; font-size:18px; font-weight:700; margin-top:6px; padding-top:12px; }
	.totals .row.due strong { color:#D8621E; }
	.totals .row.paid strong { color:#1a7f37; }
	.pay-info { background:#F8F5EF; border-radius:10px; padding:18px 22px; margin:26px 0; line-height:1.9; }
	.pay-info h3 { color:#14676B; font-size:14px; margin-bottom:6px; }
	.note { font-size:12.5px; color:#5b6b6a; line-height:1.8; border-top:1px dashed #cdd9d8; padding-top:16px; }
	.inv-foot { background:#0B2E33; color:#cfe3e2; text-align:center; padding:18px; font-size:13px; }
	.print-bar { max-width:820px; margin:0 auto 16px; display:flex; justify-content:flex-end; gap:10px; }
	.print-bar button { background:#F2762E; color:#fff; border:0; padding:12px 26px; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; font-family:inherit; }
	.print-bar button:hover { background:#D8621E; }
	@media print {
		body { background:#fff; padding:0; }
		.print-bar { display:none; }
		.invoice { box-shadow:none; border-radius:0; max-width:100%; }
	}
</style>
</head>
<body>
	<div class="print-bar"><button onclick="window.print()">🖨️ Print / Save as PDF</button></div>
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
				<span class="badge"><?php echo esc_html( strtok( $statuses[ $invoice['status'] ] ?? $invoice['status'], ' ' ) ); ?></span>
			</div>
		</div>

		<div class="inv-body">
			<div class="cols">
				<div>
					<h3>Bill To / অতিথি</h3>
					<p><strong><?php echo esc_html( $invoice['name'] ); ?></strong><br>
					📱 <?php echo esc_html( $invoice['phone'] ); ?>
					<?php if ( $invoice['email'] ) : ?><br>✉️ <?php echo esc_html( $invoice['email'] ); ?><?php endif; ?></p>
				</div>
				<div>
					<h3>Trip Details / ভ্রমণ</h3>
					<p>📅 <strong><?php echo esc_html( $invoice['travel_date'] ); ?></strong>
					<?php if ( $invoice['day_type'] ) : ?> (<?php echo esc_html( $day_labels[ $invoice['day_type'] ] ?? $invoice['day_type'] ); ?>)<?php endif; ?><br>
					⏳ ২ দিন ১ রাত প্যাকেজ<br>
					🚤 Boarding: Anwarpur Ghat</p>
				</div>
			</div>

			<table class="items">
				<thead><tr><th>Description</th><th>Guests</th><th>Per Person</th><th>Amount</th></tr></thead>
				<tbody>
					<tr>
						<td><strong><?php echo esc_html( $invoice['cabin'] ? $invoice['cabin'] : 'Houseboat Package' ); ?></strong><br>
						<span style="font-size:12.5px;color:#5b6b6a">২ দিন ১ রাত — থাকা, সকল খাবার, হাওর ভ্রমণ, গাইড ও নিরাপত্তা</span></td>
						<td><?php echo esc_html( $invoice['guests'] ); ?> জন</td>
						<td><?php echo esc_html( $invoice['per_person'] ? bhela_bm_money( $invoice['per_person'] ) : '—' ); ?></td>
						<td><?php echo esc_html( $invoice['total'] ? bhela_bm_money( $invoice['total'] ) : '—' ); ?></td>
					</tr>
				</tbody>
			</table>

			<div class="totals">
				<div class="row"><span>Subtotal</span><strong><?php echo esc_html( bhela_bm_money( $invoice['total'] ) ); ?></strong></div>
				<div class="row"><span>Advance Due (<?php echo esc_html( $s['advance_percent'] ); ?>%)</span><strong><?php echo esc_html( bhela_bm_money( $invoice['advance'] ) ); ?></strong></div>
				<div class="row paid"><span>Paid</span><strong><?php echo esc_html( bhela_bm_money( $invoice['paid'] ) ); ?><?php echo $invoice['pay_method'] ? ' (' . esc_html( strtoupper( $invoice['pay_method'] ) ) . ( $invoice['txn_id'] ? ' — ' . esc_html( $invoice['txn_id'] ) : '' ) . ')' : ''; ?></strong></div>
				<div class="row grand due"><span>Balance Due</span><strong><?php echo esc_html( bhela_bm_money( $due ) ); ?></strong></div>
			</div>

			<div class="pay-info">
				<h3>💳 Payment Options / পেমেন্ট মাধ্যম</h3>
				<strong>bKash:</strong> <?php echo esc_html( $s['bkash_number'] ); ?> &nbsp;|&nbsp;
				<strong>Nagad:</strong> <?php echo esc_html( $s['nagad_number'] ); ?>
				<?php if ( $s['bank_details'] ) : ?><br><strong>Bank:</strong> <?php echo nl2br( esc_html( $s['bank_details'] ) ); ?><?php endif; ?>
				<br><strong>📞</strong> <?php echo esc_html( $s['phone_1'] ); ?>, <?php echo esc_html( $s['phone_2'] ); ?> &nbsp;|&nbsp; <strong>WhatsApp:</strong> <?php echo esc_html( $s['whatsapp'] ); ?>
			</div>

			<?php if ( $invoice['message'] ) : ?>
				<p style="margin-bottom:16px"><strong>Note:</strong> <?php echo esc_html( $invoice['message'] ); ?></p>
			<?php endif; ?>

			<div class="note"><?php echo nl2br( esc_html( $s['invoice_note'] ) ); ?></div>
		</div>

		<div class="inv-foot">
			<?php echo esc_html( $s['business_name'] ); ?> — "<?php echo esc_html( $s['business_tagline'] ); ?>" | <?php echo esc_html( $s['email'] ); ?>
		</div>
	</div>
</body>
</html>
