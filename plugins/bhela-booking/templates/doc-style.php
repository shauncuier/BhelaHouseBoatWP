<?php
/**
 * The stylesheet every printable BHELA document shares.
 *
 * Four documents draw on it — both certificates, the payment receipt and the account
 * statement — and a copy in each would drift until they stopped looking like they came
 * from the same office, which for paper filed with a bank is most of what makes it look
 * official. It lives in a partial for the same reason the masthead does (§13.22).
 *
 * This is the one place an inline <style> block is correct: these are standalone
 * documents served outside the theme, not wp-admin screens, so §6.3's rule about
 * admin.css does not reach them.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<style>
	* { margin:0; padding:0; box-sizing:border-box; }
	/* 'Nirmala UI' is the local safety net, exactly as on the invoice: the Bengali
	   webfonts load from Google and normally win, but somebody saving this to PDF
	   offline or behind a blocked CDN falls through to Poppins — which has no ৳, so
	   every figure would get an undersized substituted symbol. */
	body {
		font-family:'Hind Siliguri','Noto Sans Bengali','Nirmala UI',Poppins,sans-serif;
		background:#e9edee; color:#14201f; padding:24px 16px; line-height:1.65; font-size:14px;
	}
	.cert { max-width:820px; margin:0 auto; background:#fff; border:1.5px solid #14201f; }
	.cert-head { display:flex; justify-content:space-between; gap:24px; padding:20px 26px 14px; border-bottom:1px solid #d9e0e0; }
	.cert-head img { height:52px; width:auto; }
	.cert-head h1 { font-size:19px; font-weight:700; letter-spacing:.2px; }
	.cert-head .org { font-size:12.5px; color:#546a69; margin-top:2px; }
	.cert-no { text-align:right; flex-shrink:0; font-size:12.5px; color:#546a69; }
	.cert-no .num { font-size:15px; font-weight:700; color:#14201f; font-family:Poppins,sans-serif; letter-spacing:.3px; }
	.cert-title { text-align:center; padding:20px 26px 4px; }
	.cert-title h2 { font-family:'Noto Serif Bengali','Hind Siliguri',serif; font-size:23px; }
	.cert-title .en { font-size:12.5px; letter-spacing:2.4px; text-transform:uppercase; color:#546a69; margin-top:4px; }
	.cert-body { padding:12px 26px 22px; }
	/* The certifying sentence: values set into running text and underlined, which is
	   the convention the reference document uses. */
	.cert-sent { margin:16px 0 4px; text-align:justify; }
	.cert-sent u { text-decoration:underline; text-underline-offset:3px; font-weight:600; font-family:Poppins,'Hind Siliguri',sans-serif; }
	h3.cert-sec { font-size:12.5px; letter-spacing:1px; text-transform:uppercase; color:#2c5a58; margin:22px 0 8px; padding-bottom:5px; border-bottom:1px solid #d9e0e0; }
	table.cert-tbl { width:100%; border-collapse:collapse; font-size:13.5px; }
	table.cert-tbl th, table.cert-tbl td { padding:7px 10px; border-bottom:1px solid #e8eded; text-align:left; }
	table.cert-tbl th { font-size:11.5px; text-transform:uppercase; letter-spacing:.7px; color:#546a69; border-bottom:1.5px solid #14201f; }
	table.cert-tbl td.num, table.cert-tbl th.num { text-align:right; font-family:Poppins,'Hind Siliguri',sans-serif; white-space:nowrap; }
	table.cert-tbl tr.total td { font-weight:700; border-top:1.5px solid #14201f; border-bottom:0; }
	table.cert-tbl tr.soft td { color:#546a69; }
	.cert-state { display:inline-block; margin-top:10px; padding:5px 14px; border:1.5px solid #14201f; font-weight:700; letter-spacing:1.2px; font-size:13px; }
	.cert-note { border-left:3px solid #C99A2E; background:#fdf8ee; padding:10px 13px; font-size:12.5px; color:#5b4a26; margin:14px 0; }
	.cert-super { background:#fbeaea; border:1px solid #edc4c4; border-left:3px solid #A8372B; padding:10px 13px; font-size:13px; color:#7a2820; margin:0 26px; font-weight:600; }
	.cert-sign { display:flex; gap:26px; margin-top:42px; }
	.cert-sign div { flex:1; border-top:1px solid #14201f; padding-top:6px; font-size:12.5px; }
	.cert-sign .role { color:#546a69; font-size:11.5px; display:block; }
	.cert-verify { display:flex; gap:16px; align-items:center; margin-top:26px; padding-top:16px; border-top:1px solid #d9e0e0; }
	.cert-verify .txt { font-size:11.5px; color:#546a69; line-height:1.55; }
	.cert-verify .txt strong { display:block; color:#14201f; font-size:12.5px; font-family:Poppins,sans-serif; }
	.cert-foot { background:#14201f; color:#c6d3d2; padding:9px 26px; font-size:11.5px; text-align:center; }
	.cert-disc { font-size:11.5px; color:#7a2820; background:#fbeaea; padding:9px 26px; text-align:center; border-top:1px solid #edc4c4; }
	.print-bar { max-width:820px; margin:0 auto 14px; display:flex; justify-content:flex-end; }
	.print-bar button { background:#14676B; color:#fff; border:0; padding:10px 22px; border-radius:6px; font-size:14px; font-weight:700; cursor:pointer; font-family:inherit; }
	.print-bar button:hover { background:#0f5054; }
	/* A phone. Every document is laid out for A4, and an investor opens it from the
	   portal on a handset: at 360px the certificate ran 406px wide and the statement
	   462px, pushing the amount column off the screen. Screen only — the printed page
	   is untouched. The tables scroll inside their own box rather than squeezing a
	   money column onto two lines. */
	@media screen and (max-width: 640px) {
		.cert-head { flex-direction:column; gap:10px; padding:16px; }
		.cert-no { text-align:left; }
		table.cert-tbl { display:block; overflow-x:auto; -webkit-overflow-scrolling:touch; }
		.cert-sign { flex-direction:column; gap:22px; }
		.cert-verify { flex-direction:column; align-items:flex-start; }
		.cert-foot, .cert-disc { padding:9px 16px; }
	}
	@media print {
		body { background:#fff; padding:0; font-size:12px; }
		.print-bar { display:none; }
		.cert { border:1px solid #14201f; max-width:100%; }
		table.cert-tbl tr { break-inside:avoid; page-break-inside:avoid; }
		.cert-sign, .cert-verify { break-inside:avoid; page-break-inside:avoid; }
		/* A QR must print as true black on true white: browsers default to
		   print-color-adjust:economy, which can lighten it enough to cost a scanner
		   its contrast margin. */
		.cert-verify svg { print-color-adjust:exact; -webkit-print-color-adjust:exact; }
	}
	@page { size:A4; margin:13mm; }
</style>
