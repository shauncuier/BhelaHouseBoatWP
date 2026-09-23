<?php
/**
 * No-coder management guide — a friendly control panel inside wp-admin.
 *
 * The short, clickable companion to docs/BHELA-Owner-Manual.md. Every card names the
 * menu path exactly as it appears in the sidebar, so it has to move when the menus do:
 * the previous version still said "Bookings → Settings" three releases after Settings
 * moved under Setup.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The full owner's manual, published as a shareable page. */
function bhela_bm_manual_url() {
	return (string) apply_filters( 'bhela_bm_manual_url', 'https://claude.ai/artifact/Mf3ATEqjbo7mE197PovfG6' );
}

function bhela_bm_guide_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'setup' ),
		'Quick Guide',
		'🎯 Quick Guide',
		'edit_posts',
		'bhela-bm-guide',
		'bhela_bm_guide_page',
		0
	);
}
add_action( 'admin_menu', 'bhela_bm_guide_menu' );

/**
 * The cards, grouped the way the sidebar is. A card whose screen the viewer cannot
 * open is left out rather than shown with a button that leads to "not allowed".
 */
function bhela_bm_guide_cards() {
	return array(
		__( 'Every day', 'bhela-booking' ) => array(
			array(
				'icon'  => '📊',
				'title' => 'Dashboard — everything at a glance',
				'cap'   => 'bhela_view_reports',
				'steps' => array(
					'Bookings → 📊 Dashboard',
					'Booking counts, money in, upcoming trips, SMS balance and recent activity',
					'Start any job from "Quick Actions"',
					'Anything still ⬜ in the Setup Checklist needs finishing',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-dashboard' ),
				'btn'   => 'Open Dashboard',
			),
			array(
				'icon'  => '📋',
				'title' => 'Confirm a booking and record payment',
				'cap'   => 'edit_bhela_bookings',
				'steps' => array(
					'Bookings → All Bookings → click the guest name',
					'When money arrives: Paid amount, Method (bKash / Nagad / Bank / Cash) and TXN ID',
					'Set Status → Advance Paid or Confirmed → Update',
					'Cabins come off the Trip Calendar automatically; the guest is emailed on Confirmed',
				),
				'link'  => admin_url( 'edit.php?post_type=bhela_booking' ),
				'btn'   => 'Open bookings',
			),
			array(
				'icon'  => '🧾',
				'title' => 'Send the invoice and WhatsApp confirmation',
				'cap'   => 'edit_bhela_bookings',
				'steps' => array(
					'Open the booking → "View / Print Invoice" → Print → Save as PDF',
					'Or copy the invoice link — it is private, safe to send',
					'Copy the ready-made WhatsApp confirmation from the same screen',
				),
				'link'  => admin_url( 'edit.php?post_type=bhela_booking' ),
				'btn'   => 'Open bookings',
			),
			array(
				'icon'  => '⭐',
				'title' => 'Approve guest reviews',
				'cap'   => 'edit_posts',
				'steps' => array(
					'Bookings → ⭐ Reviews — the menu shows how many are waiting',
					'Open one marked "Awaiting approval" → Publish (or trash it)',
					'To add one yourself: Add New → guest name as Title, words below, set the stars',
				),
				'link'  => admin_url( 'edit.php?post_type=bhela_review' ),
				'btn'   => 'Open reviews',
			),
			array(
				'icon'  => '📝',
				'title' => 'Approve an investor registration',
				'cap'   => 'bhela_investor_signup',
				'steps' => array(
					'Investors → 📝 Registrations',
					'Check the details and the NID / signature scans',
					'Approve to create their portal login — or Reject with a reason',
					'If it asks you to confirm by phone, call the person first',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-signups' ),
				'btn'   => 'Open Registrations',
			),
		),
		__( 'Trips and prices', 'bhela-booking' ) => array(
			array(
				'icon'  => '📅',
				'title' => 'Manage trip dates',
				'cap'   => 'bhela_manage_trips',
				'steps' => array(
					'Bookings → 📅 Trip Calendar → Add Trip → set a Start Date',
					'End Date blank = 2 days 1 night; set it for a Full Boat or longer charter',
					'Tick ছুটি for a holiday — Regular rate, no weekday discount',
					'Booked Cabins is a manual hold; paid bookings are counted automatically → Save',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-trips' ),
				'btn'   => 'Open Trip Calendar',
			),
			array(
				'icon'  => '💰',
				'title' => 'Rates, offers, coupons, payment numbers',
				'cap'   => 'manage_options',
				'steps' => array(
					'Setup → ⚙️ Settings',
					'Cabin Rates (per person, regular and weekday), child fee, advance %',
					'🎉 Discount Offer for a promotion; Coupon Codes for codes guests type',
					'bKash / Nagad numbers and the two payment QR images → Save',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-settings' ),
				'btn'   => 'Open Settings',
			),
		),
		__( 'Accounts and store', 'bhela-booking' ) => array(
			array(
				'icon'  => '🧾',
				'title' => 'Fill in a trip cost sheet',
				'cap'   => 'bhela_cost_prepare',
				'steps' => array(
					'Accounts → 🧾 Cost Sheets → Add New → set the trip date',
					'Fill income by head and every cost; B2B commission fills itself',
					'Submit → a checker Checks → the owner Approves (locks it)',
					'Only approved sheets count in the Monthly Statement',
				),
				'link'  => admin_url( 'edit.php?post_type=bhela_cost' ),
				'btn'   => 'Open Cost Sheets',
			),
			array(
				'icon'  => '📈',
				'title' => 'Close the month',
				'cap'   => 'bhela_view_statement',
				'steps' => array(
					'All cost sheets approved, expenses entered, salary sheet saved',
					'Capital → ➗ Profit: approve last month\'s investor periods',
					'Accounts → 📈 Monthly Statement → read the warnings at the top',
					'Print / PDF for your records',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-statement' ),
				'btn'   => 'Open Monthly Statement',
			),
			array(
				'icon'  => '🔧',
				'title' => 'Monthly stock count',
				'cap'   => 'bhela_inv_count',
				'steps' => array(
					'Store → 🔧 Monthly Stock → the month opens with last month\'s closing',
					'Enter movement and the physical count: Good / Repairable / Unrepairable / Damaged',
					'Submit → Check → Close; a closed month locks and becomes next month\'s opening',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-inv-month' ),
				'btn'   => 'Open Monthly Stock',
			),
		),
		__( 'Investors', 'bhela-booking' ) => array(
			array(
				'icon'  => '💠',
				'title' => 'Record a new investment',
				'cap'   => 'edit_bhela_investors',
				'steps' => array(
					'Investors → 👤 Investors → Add New (details, bank, nominee, mobile)',
					'Capital → 📑 Agreements → attach the signed agreement',
					'Capital → 💠 Investments → + নতুন বিনিয়োগ → terms → সংরক্ষণ',
					'Add the money received (প্রাপ্তি), then সক্রিয় করুন',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-investments' ),
				'btn'   => 'Open Investments',
			),
			array(
				'icon'  => '➗',
				'title' => 'Approve investor profit',
				'cap'   => 'bhela_investor_profit',
				'steps' => array(
					'Capital → ➗ Profit',
					'Tick the finished periods you have checked (nothing is pre-ticked)',
					'টিক দেওয়া সময়কালগুলো অনুমোদন করুন — now owed, and deducted in the statement',
					'A period can never be approved twice',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-profit' ),
				'btn'   => 'Open Profit',
			),
			array(
				'icon'  => '💸',
				'title' => 'Pay an investor (two people)',
				'cap'   => 'bhela_investors_view',
				'steps' => array(
					'Investors → 📇 Investor Report → pick the investor',
					'Record a movement → Payment → amount, date, method, reference → Submit',
					'A different person opens the same page and presses Approve',
					'Only then does the payment count; each has a Receipt button',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-investor-report' ),
				'btn'   => 'Open Investor Report',
			),
			array(
				'icon'  => '📜',
				'title' => 'Issue a certificate',
				'cap'   => 'bhela_investor_cert',
				'steps' => array(
					'Investors → 📜 Certificates → choose Investment or Profit Certificate',
					'Pick the investment → দেখুন → check the preview',
					'সনদ ইস্যু করুন — the figures are frozen from now on',
					'To correct one, issue again and choose it under পুরোনো সনদ সংশোধন (makes V2)',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-certificates' ),
				'btn'   => 'Open Certificates',
			),
		),
		__( 'Website and setup', 'bhela-booking' ) => array(
			array(
				'icon'  => '🖼️',
				'title' => 'Gallery photos',
				'cap'   => 'edit_posts',
				'steps' => array(
					'Many at once: Setup → ⬆️ Bulk Upload → ছবি বাছাই করুন',
					'One at a time: Setup → 🖼️ Gallery → new photo → Featured Image',
					'Caption = Title; set the Category and the Order',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-gallery-bulk' ),
				'btn'   => 'Bulk upload photos',
			),
			array(
				'icon'  => '🗺️',
				'title' => 'Trip spots',
				'cap'   => 'edit_posts',
				'steps' => array(
					'Setup → 🗺️ Spots',
					'Featured Image = the spot photo; Bangla name and a one-line description',
					'Type: included in the package, or optional; Order sets the sequence',
				),
				'link'  => admin_url( 'edit.php?post_type=bhela_spot' ),
				'btn'   => 'Manage spots',
			),
			array(
				'icon'  => '✏️',
				'title' => 'Change wording or images on the site',
				'cap'   => 'edit_pages',
				'steps' => array(
					'Pages → edit the page (Elementor pages: "Edit with Elementor")',
					'Homepage text and photos: Appearance → Customize → BHELA Homepage / Images',
					'Phones, WhatsApp, address: Setup → ⚙️ Settings — used everywhere',
					'Never add blocks to the Home page itself',
				),
				'link'  => admin_url( 'edit.php?post_type=page' ),
				'btn'   => 'Open pages',
			),
			array(
				'icon'  => '👥',
				'title' => 'Give a staff member access',
				'cap'   => 'manage_options',
				'steps' => array(
					'Users → Add New → their own name and email → a BHELA role',
					'Setup → 👥 Team to see or adjust what each role can do',
					'One account per person — approvals record who did them',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-team' ),
				'btn'   => 'Open Team',
			),
			array(
				'icon'  => '📋',
				'title' => 'Did it actually work?',
				'cap'   => 'edit_posts',
				'steps' => array(
					'Setup → 📋 Activity Log — bookings, emails, SMS, saves; ✅ worked, ❌ failed',
					'Store → 🔩 Audit Trail — who changed which figure, from what, and why',
				),
				'link'  => bhela_bm_admin_url( 'bhela-bm-log' ),
				'btn'   => 'Open Activity Log',
			),
		),
	);
}

function bhela_bm_guide_page() {
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'🎯',
			__( 'Quick Management Guide', 'bhela-booking' ),
			__( 'No coding needed. Pick what you want to do and follow the steps. The full manual covers everything in detail.', 'bhela-booking' ),
			'<a class="button button-primary" href="' . esc_url( bhela_bm_manual_url() ) . '" target="_blank" rel="noopener">📘 ' . esc_html__( 'Full owner\'s manual', 'bhela-booking' ) . '</a> '
			. '<a class="button" href="https://3s-soft.com" target="_blank" rel="noopener">' . esc_html__( 'Contact 3s-Soft', 'bhela-booking' ) . '</a>'
		);
		?>
		<div class="bha-callout">
			<?php esc_html_e( 'Money records are never deleted — a mistake is reversed. Nothing is owed until someone approves it, and a payment needs a second person. Take a backup before any update.', 'bhela-booking' ); ?>
		</div>
		<?php foreach ( bhela_bm_guide_cards() as $group => $cards ) : ?>
			<?php
			$cards = array_filter( $cards, function ( $c ) {
				return empty( $c['cap'] ) || current_user_can( $c['cap'] );
			} );
			if ( ! $cards ) {
				continue;
			}
			?>
			<h2 class="bha-panel__title" style="margin-top:22px"><?php echo esc_html( $group ); ?></h2>
			<div class="bha-guide">
				<?php foreach ( $cards as $c ) : ?>
					<div class="bha-guide__card">
						<h2><?php echo esc_html( $c['icon'] . ' ' . $c['title'] ); ?></h2>
						<ol>
							<?php foreach ( $c['steps'] as $s ) : ?>
								<li><?php echo esc_html( $s ); ?></li>
							<?php endforeach; ?>
						</ol>
						<a class="button button-primary" href="<?php echo esc_url( $c['link'] ); ?>"><?php echo esc_html( $c['btn'] ); ?></a>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}
