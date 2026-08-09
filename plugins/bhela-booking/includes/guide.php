<?php
/**
 * No-coder management guide — a friendly control panel inside wp-admin.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_guide_menu() {
	add_submenu_page(
		'edit.php?post_type=bhela_booking',
		'Quick Guide',
		'🎯 Quick Guide',
		'edit_posts',
		'bhela-bm-guide',
		'bhela_bm_guide_page',
		0
	);
}
add_action( 'admin_menu', 'bhela_bm_guide_menu' );

function bhela_bm_guide_page() {
	$cards = array(
		array(
			'icon'  => '📊',
			'title' => 'Dashboard — everything at a glance',
			'steps' => array(
				'Left menu: Bookings → 📊 Dashboard',
				'Booking counts, revenue, upcoming trips and recent activity on one page',
				'Start any job in one click from "Quick Actions"',
				'Anything still showing ⬜ in the Setup Checklist needs finishing',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-dashboard' ),
			'btn'   => 'Open Dashboard',
		),
		array(
			'icon'  => '📋',
			'title' => 'Review and confirm a new booking',
			'steps' => array(
				'You get an email whenever a new request arrives',
				'All Bookings → click the guest name',
				'When the advance arrives, fill in Paid Amount and TXN ID',
				'Set Status → Confirmed and press Update — the guest is emailed automatically',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking' ),
			'btn'   => 'Open bookings list',
		),
		array(
			'icon'  => '🧾',
			'title' => 'Print or send an invoice',
			'steps' => array(
				'Open the booking → "View / Print Invoice" on the right',
				'Press Print and save it as a PDF',
				'Or copy the link and send it on WhatsApp',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking' ),
			'btn'   => 'Open bookings list',
		),
		array(
			'icon'  => '📅',
			'title' => 'Manage trip dates',
			'steps' => array(
				'Press "Add Trip" and set a Start Date',
				'Leave End Date empty for the standard 2 days 1 night — extend it for a Full Boat or longer charter',
				'Label and Days fill in on their own',
				'Tick "Holiday" for holiday departures — the regular rate applies, with no weekday discount',
				'As it fills up set Status → Filling Fast / Booked; tick Delete to remove a row; then Save',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-trips' ),
			'btn'   => 'Open Trip Calendar',
		),
		array(
			'icon'  => '💰',
			'title' => 'Rates and payment numbers',
			'steps' => array(
				'Type new prices into the cabin rate table to change them',
				'The child fee (ages 4–8) and the advance percentage live here too',
				'So do the bKash / Nagad numbers and the QR image URLs',
				'Holidays are set on the Trip Calendar now, using the "Holiday" tick',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-settings' ),
			'btn'   => 'Open Settings',
		),
		array(
			'icon'  => '🖼️',
			'title' => 'Add or change gallery photos',
			'steps' => array(
				'Many at once: Bookings → 🖼️ Bulk Upload → "Select photos"',
				'One at a time: 🖼️ Gallery → new photo → set a Featured Image',
				'Caption = Title. Set the Category and the Order',
				'Upload payment QR images to Media, then paste the URL into Settings',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-gallery-bulk' ),
			'btn'   => 'Bulk upload photos',
		),
		array(
			'icon'  => '🗺️',
			'title' => 'Manage trip spots',
			'steps' => array(
				'Left menu: Bookings → 🗺️ Spots',
				'Featured Image = the spot photo; add a Bengali name and a one-line description',
				'Pick a Type: Included in the package, or Optional (guest pays their own way)',
				'Page Attributes → Order sets the sequence on the Spots page',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_spot' ),
			'btn'   => 'Manage spots',
		),
		array(
			'icon'  => '⭐',
			'title' => 'Add or change guest reviews',
			'steps' => array(
				'Left menu: All Reviews → Add New',
				'Guest name goes in the Title, the review text goes below',
				'Set the star rating and Trip Type on the right',
				'Press Publish and it appears on the homepage',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_review' ),
			'btn'   => 'Manage reviews',
		),
		array(
			'icon'  => '📋',
			'title' => 'Check whether something actually worked',
			'steps' => array(
				'Bookings → Activity Log',
				'Every booking, email, SMS, trip and settings change is recorded here',
				'✅ means it worked, ❌ means it failed — newest at the top',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-log' ),
			'btn'   => 'Open Activity Log',
		),
		array(
			'icon'  => '✏️',
			'title' => 'Change the wording on a page',
			'steps' => array(
				'Pages → Edit the page you want to change',
				'With Elementor installed, use "Edit with Elementor" for drag-and-drop',
				'Homepage headline text: Appearance → Customize → BHELA Homepage',
			),
			'link'  => admin_url( 'edit.php?post_type=page' ),
			'btn'   => 'Open pages list',
		),
		array(
			'icon'  => '📞',
			'title' => 'Change the phone / WhatsApp number',
			'steps' => array(
				'Bookings → Settings, and edit the numbers there',
				'The whole website, the booking form and invoices all update automatically',
			),
			'link'  => admin_url( 'edit.php?post_type=bhela_booking&page=bhela-bm-settings' ),
			'btn'   => 'Open Settings',
		),
	);
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'🎯',
			__( 'Quick Management Guide', 'bhela-booking' ),
			__( 'No coding needed. Pick whatever you want to do and follow the steps.', 'bhela-booking' ),
			'<a class="button" href="https://3s-soft.com" target="_blank" rel="noopener">' . esc_html__( 'Contact 3s-Soft', 'bhela-booking' ) . '</a>'
		);
		?>
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
	</div>
	<?php
}
