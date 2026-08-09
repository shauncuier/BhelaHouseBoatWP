<?php
/**
 * Seed realistic dummy data for July and August 2026 financial reports and Salary Sheets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bhela_bm_seed_reports_data() {
	if ( ! isset( $_GET['bhela_seed_demo'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// 1. Setup Trips in options
	$trips = array(
		// July 2026
		array( 'date' => '2026-07-03', 'end' => '2026-07-04', 'label' => '3 – 4 Jul 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available', 'holiday' => false ),
		array( 'date' => '2026-07-10', 'end' => '2026-07-11', 'label' => '10 – 11 Jul 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available', 'holiday' => false ),
		array( 'date' => '2026-07-17', 'end' => '2026-07-18', 'label' => '17 – 18 Jul 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available', 'holiday' => false ),
		array( 'date' => '2026-07-24', 'end' => '2026-07-25', 'label' => '24 – 25 Jul 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available', 'holiday' => false ),
		array( 'date' => '2026-07-31', 'end' => '2026-08-01', 'label' => '31 Jul – 1 Aug 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available', 'holiday' => false ),
		
		// August 2026
		array( 'date' => '2026-08-02', 'end' => '2026-08-03', 'label' => '2 – 3 Aug 2026', 'days' => 'রবি – সোম', 'note' => '', 'status' => 'available', 'holiday' => false ),
		array( 'date' => '2026-08-07', 'end' => '2026-08-08', 'label' => '7 – 8 Aug 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available', 'holiday' => false ),
		array( 'date' => '2026-08-09', 'end' => '2026-08-10', 'label' => '9 – 10 Aug 2026', 'days' => 'রবি – সোম', 'note' => '', 'status' => 'available', 'holiday' => false ),
		array( 'date' => '2026-08-14', 'end' => '2026-08-15', 'label' => '14 – 15 Aug 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available', 'holiday' => false ),
		array( 'date' => '2026-08-21', 'end' => '2026-08-22', 'label' => '21 – 22 Aug 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available', 'holiday' => false ),
		array( 'date' => '2026-08-28', 'end' => '2026-08-29', 'label' => '28 – 29 Aug 2026', 'days' => 'শুক্র – শনি', 'note' => '', 'status' => 'available', 'holiday' => false ),
	);

	update_option( 'bhela_bm_trips', $trips );

	// Setup Staff Roster in Settings
	$staff_roster = array(
		'kader_ali' => array(
			'name' => 'Master Kader Ali',
			'designation' => 'Boat Captain / Master',
			'type' => 'monthly',
			'rate' => 0,
			'monthly' => 30000,
			'account' => 'Dutch-Bangla Bank (115.120.9821)',
			'retired' => 0,
		),
		'abdul_gafur' => array(
			'name' => 'Abdul Gafur',
			'designation' => 'Head Chef',
			'type' => 'both',
			'rate' => 1500,
			'monthly' => 18000,
			'account' => 'bKash (01712-445566)',
			'retired' => 0,
		),
		'rahmat_ullah' => array(
			'name' => 'Rahmat Ullah',
			'designation' => 'Assistant Chef / Kitchen Staff',
			'type' => 'trip',
			'rate' => 2000,
			'monthly' => 0,
			'account' => 'bKash (01815-667788)',
			'retired' => 0,
		),
		'sohel_mia' => array(
			'name' => 'Sohel Mia',
			'designation' => 'Senior Deckhand & Anchor',
			'type' => 'trip',
			'rate' => 1800,
			'monthly' => 0,
			'account' => 'Nagad (01918-990011)',
			'retired' => 0,
		),
		'jamal_hossain' => array(
			'name' => 'Jamal Hossain',
			'designation' => 'Cabin Steward & Housekeeping',
			'type' => 'trip',
			'rate' => 1800,
			'monthly' => 0,
			'account' => 'bKash (01677-889900)',
			'retired' => 0,
		),
		'anwar_parvez' => array(
			'name' => 'Anwar Parvez',
			'designation' => 'Tanguar Haor Naturalist Guide',
			'type' => 'trip',
			'rate' => 2500,
			'monthly' => 0,
			'account' => 'bKash (01730-001122)',
			'retired' => 0,
		),
		'uttam_kumar' => array(
			'name' => 'Uttam Kumar',
			'designation' => 'Operations Manager',
			'type' => 'monthly',
			'rate' => 0,
			'monthly' => 35000,
			'account' => 'City Bank (220.154.8871)',
			'retired' => 0,
		),
	);
	update_option( 'bhela_bm_staff', $staff_roster );

	// Get Admin User ID for signatures
	$admin_user = get_users( array( 'role' => 'administrator', 'number' => 1 ) );
	$admin_id = ! empty( $admin_user ) ? $admin_user[0]->ID : 1;

	// Clean up old cost sheets, expenses, salaries, bookings
	$old_costs = get_posts( array( 'post_type' => 'bhela_cost', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) );
	foreach ( $old_costs as $cid ) {
		wp_delete_post( $cid, true );
	}

	$old_expenses = get_posts( array( 'post_type' => 'bhela_expense', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) );
	foreach ( $old_expenses as $eid ) {
		wp_delete_post( $eid, true );
	}

	$old_salaries = get_posts( array( 'post_type' => 'bhela_salary', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) );
	foreach ( $old_salaries as $sid ) {
		wp_delete_post( $sid, true );
	}

	$old_bookings = get_posts( array( 'post_type' => 'bhela_booking', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) );
	foreach ( $old_bookings as $bid ) {
		wp_delete_post( $bid, true );
	}

	// 2. Define Trip details with Bookings and Costs
	$trip_plans = array(
		// --- JULY 2026 ---
		array(
			'date' => '2026-07-03',
			'label' => '3 – 4 Jul 2026',
			'trip_id' => 'TRIP-260703',
			'bookings' => array(
				array( 'name' => 'Dr. Rafiqul Islam', 'phone' => '01711100201', 'email' => 'rafiq.islam@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0), array('adults'=>2, 'c48'=>1, 'c04'=>0) ), 'status' => 'completed', 'paid' => 57000 ),
				array( 'name' => 'Syed Mahbubur Rahman', 'phone' => '01819223344', 'email' => 'mahbub.rahman@yahoo.com', 'cabins' => array( array('adults'=>3, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 36000 ),
				array( 'name' => 'Farhana Yasmin', 'phone' => '01911445566', 'email' => 'farhana.y@outlook.com', 'cabins' => array( array('adults'=>4, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 40000 ),
				array( 'name' => 'Tanvir Chowdhury', 'phone' => '01671556677', 'email' => 'tanvir.chowdhury@gmail.com', 'cabins' => array( array('adults'=>5, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 45000 ),
				array( 'name' => 'Kazi Imtiaz', 'phone' => '01722889900', 'email' => 'imtiaz.kazi@hotmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 26000 ),
			),
			'costs' => array(
				'engine_fuel' => array( 'p1' => 16000, 'remark' => '135 Liters Diesel' ),
				'electricity' => array( 'p1' => 1500, 'remark' => 'Generator fuel' ),
				'groceries' => array( 'p1' => 6500, 'remark' => 'Miniket rice, spices, oil' ),
				'meat' => array( 'p1' => 9500, 'remark' => 'Duck & Deshi Chicken' ),
				'fish' => array( 'p1' => 8000, 'remark' => 'Fresh Haor Boal & Rui' ),
				'kitchen_market' => array( 'p1' => 3200, 'remark' => 'Vegetables & salads' ),
				'gas' => array( 'p1' => 1400, 'remark' => '1 Cylinder LP Gas' ),
				'staff_convency' => array( 'p1' => 4000, 'remark' => 'Boat crew daily allowance' ),
				'jetty_charge' => array( 'p1' => 1500, 'remark' => 'Anwarpur Ghat docking' ),
				'water' => array( 'p1' => 1200, 'remark' => '10 Jars Mineral Water' ),
				'fruits' => array( 'p1' => 1800, 'remark' => 'Seasonal Mango & Pineapple' ),
				'laundry' => array( 'p1' => 1500, 'remark' => 'Bedsheets & Towels wash' ),
				'ice' => array( 'p1' => 600, 'remark' => 'Ice blocks' ),
			)
		),
		array(
			'date' => '2026-07-10',
			'label' => '10 – 11 Jul 2026',
			'trip_id' => 'TRIP-260710',
			'bookings' => array(
				array( 'name' => 'Engr. Mahmudul Hasan', 'phone' => '01712334455', 'email' => 'mahmud.engr@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0), array('adults'=>3, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 62000 ),
				array( 'name' => 'Nusrat Jahan', 'phone' => '01817667788', 'email' => 'nusrat.jahan@gmail.com', 'cabins' => array( array('adults'=>4, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 40000 ),
				array( 'name' => 'Shakil Ahmed', 'phone' => '01912998877', 'email' => 'shakil.ahmed@live.com', 'cabins' => array( array('adults'=>6, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 48000 ),
				array( 'name' => 'Asif Karim', 'phone' => '01688112233', 'email' => 'asif.karim@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>1, 'c04'=>0), array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 57000 ),
			),
			'costs' => array(
				'engine_fuel' => array( 'p1' => 15500, 'remark' => '130 Liters Diesel' ),
				'electricity' => array( 'p1' => 1500, 'remark' => 'Generator & Solar' ),
				'groceries' => array( 'p1' => 6800, 'remark' => 'Rice, Dal, Polao Rice' ),
				'meat' => array( 'p1' => 10200, 'remark' => 'Beef Kacchi & Deshi Duck' ),
				'fish' => array( 'p1' => 8500, 'remark' => 'Haor Shorputi & Chital' ),
				'kitchen_market' => array( 'p1' => 3500, 'remark' => 'Local organic vegetables' ),
				'gas' => array( 'p1' => 1400, 'remark' => 'LPG Refill' ),
				'staff_convency' => array( 'p1' => 4500, 'remark' => 'Chef + 3 Crew allowance' ),
				'jetty_charge' => array( 'p1' => 1500, 'remark' => 'Tahirpur ghat' ),
				'water' => array( 'p1' => 1200, 'remark' => 'Drinking water' ),
				'fruits' => array( 'p1' => 2000, 'remark' => 'Fruits & dessert' ),
				'laundry' => array( 'p1' => 1600, 'remark' => 'Linen wash' ),
				'ice' => array( 'p1' => 700, 'remark' => 'Ice blocks' ),
			)
		),
		array(
			'date' => '2026-07-17',
			'label' => '17 – 18 Jul 2026',
			'trip_id' => 'TRIP-260717',
			'bookings' => array(
				array( 'name' => 'Shahidul Alam', 'phone' => '01715443322', 'email' => 'shahidul.a@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 26000 ),
				array( 'name' => 'Mehnaz Parveen', 'phone' => '01811554433', 'email' => 'mehnaz.p@gmail.com', 'cabins' => array( array('adults'=>3, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 36000 ),
				array( 'name' => 'Zubair Hossain', 'phone' => '01913776655', 'email' => 'zubair.hossain@bankbd.com', 'cabins' => array( array('adults'=>4, 'c48'=>0, 'c04'=>0), array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 66000 ),
				array( 'name' => 'Anisur Rahman', 'phone' => '01672332211', 'email' => 'anis.rahman@gmail.com', 'cabins' => array( array('adults'=>5, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 45000 ),
			),
			'costs' => array(
				'engine_fuel' => array( 'p1' => 15000, 'remark' => '125 Liters Diesel' ),
				'electricity' => array( 'p1' => 1200, 'remark' => 'Generator fuel' ),
				'groceries' => array( 'p1' => 6000, 'remark' => 'Rice, spices, oil' ),
				'meat' => array( 'p1' => 9000, 'remark' => 'Duck & Chicken' ),
				'fish' => array( 'p1' => 7500, 'remark' => 'Local Haor Fish' ),
				'kitchen_market' => array( 'p1' => 3000, 'remark' => 'Vegetables' ),
				'gas' => array( 'p1' => 1400, 'remark' => 'LPG' ),
				'staff_convency' => array( 'p1' => 4000, 'remark' => 'Staff allowance' ),
				'jetty_charge' => array( 'p1' => 1500, 'remark' => 'Jetty fee' ),
				'water' => array( 'p1' => 1000, 'remark' => 'Water jars' ),
				'fruits' => array( 'p1' => 1600, 'remark' => 'Fresh fruits' ),
				'laundry' => array( 'p1' => 1500, 'remark' => 'Laundry' ),
				'ice' => array( 'p1' => 600, 'remark' => 'Ice' ),
			)
		),
		array(
			'date' => '2026-07-24',
			'label' => '24 – 25 Jul 2026',
			'trip_id' => 'TRIP-260724',
			'bookings' => array(
				array( 'name' => 'Saifuddin Ahmed', 'phone' => '01718889900', 'email' => 'saif.ahmed@corporate.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0), array('adults'=>2, 'c48'=>0, 'c04'=>0), array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 78000 ),
				array( 'name' => 'Tamanna Sultana', 'phone' => '01819998877', 'email' => 'tamanna.s@gmail.com', 'cabins' => array( array('adults'=>4, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 40000 ),
				array( 'name' => 'Fahim Morshed', 'phone' => '01914112233', 'email' => 'fahim.morshed@gmail.com', 'cabins' => array( array('adults'=>6, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 48000 ),
				array( 'name' => 'Mominul Huq', 'phone' => '01673445566', 'email' => 'mominul.huq@yahoo.com', 'cabins' => array( array('adults'=>5, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 45000 ),
			),
			'costs' => array(
				'engine_fuel' => array( 'p1' => 16500, 'remark' => '140 Liters Diesel' ),
				'electricity' => array( 'p1' => 1500, 'remark' => 'Gen fuel' ),
				'groceries' => array( 'p1' => 7000, 'remark' => 'Groceries' ),
				'meat' => array( 'p1' => 11000, 'remark' => 'Beef, Duck, Chicken' ),
				'fish' => array( 'p1' => 9000, 'remark' => 'Haor Rui & Pabda' ),
				'kitchen_market' => array( 'p1' => 3800, 'remark' => 'Salad & vegetables' ),
				'gas' => array( 'p1' => 1400, 'remark' => 'Gas' ),
				'staff_convency' => array( 'p1' => 4500, 'remark' => 'Crew convency' ),
				'jetty_charge' => array( 'p1' => 1500, 'remark' => 'Ghat charge' ),
				'water' => array( 'p1' => 1400, 'remark' => 'Water' ),
				'fruits' => array( 'p1' => 2000, 'remark' => 'Fruits' ),
				'laundry' => array( 'p1' => 1800, 'remark' => 'Linen' ),
				'ice' => array( 'p1' => 800, 'remark' => 'Ice' ),
			)
		),
		array(
			'date' => '2026-07-31',
			'label' => '31 Jul – 1 Aug 2026',
			'trip_id' => 'TRIP-260731',
			'bookings' => array(
				array( 'name' => 'Tariq Manzoor', 'phone' => '01716778899', 'email' => 'tariq.m@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0), array('adults'=>3, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 62000 ),
				array( 'name' => 'Sadia Afrin', 'phone' => '01812334455', 'email' => 'sadia.afrin@gmail.com', 'cabins' => array( array('adults'=>4, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 40000 ),
				array( 'name' => 'Muniruzzaman', 'phone' => '01915667788', 'email' => 'munir.zaman@gmail.com', 'cabins' => array( array('adults'=>3, 'c48'=>0, 'c04'=>0), array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 62000 ),
				array( 'name' => 'Naimur Rahman', 'phone' => '01674556677', 'email' => 'naimur.r@gmail.com', 'cabins' => array( array('adults'=>5, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 45000 ),
			),
			'costs' => array(
				'engine_fuel' => array( 'p1' => 16000, 'remark' => '135 Liters Diesel' ),
				'electricity' => array( 'p1' => 1500, 'remark' => 'Gen fuel' ),
				'groceries' => array( 'p1' => 6500, 'remark' => 'Groceries' ),
				'meat' => array( 'p1' => 10000, 'remark' => 'Duck & Chicken roast' ),
				'fish' => array( 'p1' => 8500, 'remark' => 'Haor Boal' ),
				'kitchen_market' => array( 'p1' => 3500, 'remark' => 'Bazar' ),
				'gas' => array( 'p1' => 1400, 'remark' => 'LPG' ),
				'staff_convency' => array( 'p1' => 4500, 'remark' => 'Staff bill' ),
				'jetty_charge' => array( 'p1' => 1500, 'remark' => 'Docking' ),
				'water' => array( 'p1' => 1200, 'remark' => 'Water' ),
				'fruits' => array( 'p1' => 1800, 'remark' => 'Fruits' ),
				'laundry' => array( 'p1' => 1600, 'remark' => 'Laundry' ),
				'ice' => array( 'p1' => 700, 'remark' => 'Ice' ),
			)
		),

		// --- AUGUST 2026 ---
		array(
			'date' => '2026-08-02',
			'label' => '2 – 3 Aug 2026',
			'trip_id' => 'TRIP-260802',
			'bookings' => array(
				array( 'name' => 'Hasibul Hasan', 'phone' => '01717112233', 'email' => 'hasib.hasan@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0), array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 41600 ),
				array( 'name' => 'Rokeya Begum', 'phone' => '01813445566', 'email' => 'rokeya.b@gmail.com', 'cabins' => array( array('adults'=>3, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 28800 ),
				array( 'name' => 'Jannatul Ferdous', 'phone' => '01916778899', 'email' => 'jannat.f@gmail.com', 'cabins' => array( array('adults'=>4, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 32000 ),
				array( 'name' => 'Kamrul Ahsan', 'phone' => '01675667788', 'email' => 'kamrul.ahsan@gmail.com', 'cabins' => array( array('adults'=>5, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 36000 ),
			),
			'costs' => array(
				'engine_fuel' => array( 'p1' => 14500, 'remark' => '120 Liters Diesel' ),
				'electricity' => array( 'p1' => 1200, 'remark' => 'Generator' ),
				'groceries' => array( 'p1' => 5800, 'remark' => 'Rice & spices' ),
				'meat' => array( 'p1' => 8500, 'remark' => 'Duck & Chicken' ),
				'fish' => array( 'p1' => 7000, 'remark' => 'Haor Fish' ),
				'kitchen_market' => array( 'p1' => 2800, 'remark' => 'Vegetables' ),
				'gas' => array( 'p1' => 1400, 'remark' => 'Gas' ),
				'staff_convency' => array( 'p1' => 4000, 'remark' => 'Crew allowance' ),
				'jetty_charge' => array( 'p1' => 1500, 'remark' => 'Jetty' ),
				'water' => array( 'p1' => 1000, 'remark' => 'Water' ),
				'fruits' => array( 'p1' => 1500, 'remark' => 'Fruits' ),
				'laundry' => array( 'p1' => 1400, 'remark' => 'Laundry' ),
				'ice' => array( 'p1' => 600, 'remark' => 'Ice' ),
			)
		),
		array(
			'date' => '2026-08-07',
			'label' => '7 – 8 Aug 2026',
			'trip_id' => 'TRIP-260807',
			'bookings' => array(
				array( 'name' => 'Golam Mostafa', 'phone' => '01718223344', 'email' => 'mostafa.g@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0), array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 52000 ),
				array( 'name' => 'Farzana Haque', 'phone' => '01814556677', 'email' => 'farzana.h@gmail.com', 'cabins' => array( array('adults'=>3, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 36000 ),
				array( 'name' => 'Imran Nazir', 'phone' => '01917889900', 'email' => 'imran.nazir@gmail.com', 'cabins' => array( array('adults'=>4, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 40000 ),
				array( 'name' => 'Tariqul Islam', 'phone' => '01676778899', 'email' => 'tariqul.i@gmail.com', 'cabins' => array( array('adults'=>6, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 48000 ),
				array( 'name' => 'Rubel Mia', 'phone' => '01729990011', 'email' => 'rubel.mia@gmail.com', 'cabins' => array( array('adults'=>3, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 36000 ),
			),
			'costs' => array(
				'engine_fuel' => array( 'p1' => 16000, 'remark' => '135 Liters Diesel' ),
				'electricity' => array( 'p1' => 1500, 'remark' => 'Gen fuel' ),
				'groceries' => array( 'p1' => 6800, 'remark' => 'Groceries' ),
				'meat' => array( 'p1' => 10500, 'remark' => 'Beef & Duck' ),
				'fish' => array( 'p1' => 8800, 'remark' => 'Haor Chital & Rui' ),
				'kitchen_market' => array( 'p1' => 3500, 'remark' => 'Kitchen market' ),
				'gas' => array( 'p1' => 1400, 'remark' => 'Gas' ),
				'staff_convency' => array( 'p1' => 4500, 'remark' => 'Staff' ),
				'jetty_charge' => array( 'p1' => 1500, 'remark' => 'Jetty' ),
				'water' => array( 'p1' => 1200, 'remark' => 'Water' ),
				'fruits' => array( 'p1' => 1800, 'remark' => 'Fruits' ),
				'laundry' => array( 'p1' => 1600, 'remark' => 'Laundry' ),
				'ice' => array( 'p1' => 700, 'remark' => 'Ice' ),
			)
		),
		array(
			'date' => '2026-08-09',
			'label' => '9 – 10 Aug 2026',
			'trip_id' => 'TRIP-260809',
			'bookings' => array(
				array( 'name' => 'Jon Doe', 'phone' => '01711223344', 'email' => 'jon.doe@example.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 20800 ),
				array( 'name' => 'Ahsan Habib', 'phone' => '01815667788', 'email' => 'ahsan.habib@gmail.com', 'cabins' => array( array('adults'=>3, 'c48'=>0, 'c04'=>0), array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 49600 ),
				array( 'name' => 'Laila Arjumand', 'phone' => '01918990011', 'email' => 'laila.a@gmail.com', 'cabins' => array( array('adults'=>4, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 32000 ),
				array( 'name' => 'Firoz Mahmud', 'phone' => '01677889900', 'email' => 'firoz.m@gmail.com', 'cabins' => array( array('adults'=>5, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 36000 ),
				array( 'name' => 'Shamim Osman', 'phone' => '01730001122', 'email' => 'shamim.o@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 20800 ),
			),
			'costs' => array(
				'engine_fuel' => array( 'p1' => 15000, 'remark' => '125 Liters Diesel' ),
				'electricity' => array( 'p1' => 1400, 'remark' => 'Gen fuel' ),
				'groceries' => array( 'p1' => 6200, 'remark' => 'Groceries' ),
				'meat' => array( 'p1' => 9200, 'remark' => 'Duck & Chicken' ),
				'fish' => array( 'p1' => 7800, 'remark' => 'Haor fish' ),
				'kitchen_market' => array( 'p1' => 3000, 'remark' => 'Bazar' ),
				'gas' => array( 'p1' => 1400, 'remark' => 'Gas' ),
				'staff_convency' => array( 'p1' => 4200, 'remark' => 'Staff' ),
				'jetty_charge' => array( 'p1' => 1500, 'remark' => 'Jetty' ),
				'water' => array( 'p1' => 1100, 'remark' => 'Water' ),
				'fruits' => array( 'p1' => 1600, 'remark' => 'Fruits' ),
				'laundry' => array( 'p1' => 1500, 'remark' => 'Laundry' ),
				'ice' => array( 'p1' => 600, 'remark' => 'Ice' ),
			)
		),
		array(
			'date' => '2026-08-14',
			'label' => '14 – 15 Aug 2026',
			'trip_id' => 'TRIP-260814',
			'bookings' => array(
				array( 'name' => 'Nasir Uddin', 'phone' => '01719334455', 'email' => 'nasir.u@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0), array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 52000 ),
				array( 'name' => 'Dilruba Khanam', 'phone' => '01816778899', 'email' => 'dilruba.k@gmail.com', 'cabins' => array( array('adults'=>3, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 36000 ),
				array( 'name' => 'Babor Ali', 'phone' => '01919001122', 'email' => 'babor.ali@gmail.com', 'cabins' => array( array('adults'=>4, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 40000 ),
				array( 'name' => 'Sohail Tanvir', 'phone' => '01678990011', 'email' => 'sohail.t@gmail.com', 'cabins' => array( array('adults'=>6, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 48000 ),
				array( 'name' => 'Al Amin', 'phone' => '01731112233', 'email' => 'alamin.tour@gmail.com', 'cabins' => array( array('adults'=>5, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 45000 ),
				array( 'name' => 'Tasnim Ahmed', 'phone' => '01822223344', 'email' => 'tasnim.ahmed@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 26000 ),
			),
			'costs' => array(
				'engine_fuel' => array( 'p1' => 17000, 'remark' => '145 Liters Diesel' ),
				'electricity' => array( 'p1' => 1600, 'remark' => 'Gen fuel' ),
				'groceries' => array( 'p1' => 7500, 'remark' => 'Full boat provisions' ),
				'meat' => array( 'p1' => 12000, 'remark' => 'Mutton Kacchi, Duck, Chicken' ),
				'fish' => array( 'p1' => 9500, 'remark' => 'Fresh Haor Chital & Boal' ),
				'kitchen_market' => array( 'p1' => 4000, 'remark' => 'Bazar' ),
				'gas' => array( 'p1' => 1400, 'remark' => 'Gas' ),
				'staff_convency' => array( 'p1' => 5000, 'remark' => 'Staff bonus' ),
				'jetty_charge' => array( 'p1' => 1500, 'remark' => 'Jetty' ),
				'water' => array( 'p1' => 1500, 'remark' => 'Water' ),
				'fruits' => array( 'p1' => 2200, 'remark' => 'Premium fruits' ),
				'laundry' => array( 'p1' => 2000, 'remark' => 'Full laundry' ),
				'ice' => array( 'p1' => 900, 'remark' => 'Ice' ),
			)
		),
		array(
			'date' => '2026-08-21',
			'label' => '21 – 22 Aug 2026',
			'trip_id' => 'TRIP-260821',
			'bookings' => array(
				array( 'name' => 'Mustafizur Rahman', 'phone' => '01710445566', 'email' => 'mustafiz.r@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0), array('adults'=>3, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 62000 ),
				array( 'name' => 'Sharmin Akter', 'phone' => '01817889900', 'email' => 'sharmin.a@gmail.com', 'cabins' => array( array('adults'=>4, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 40000 ),
				array( 'name' => 'Jubayer Al Mahmud', 'phone' => '01910112233', 'email' => 'jubayer.m@gmail.com', 'cabins' => array( array('adults'=>5, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 45000 ),
				array( 'name' => 'Rezaul Karim', 'phone' => '01679001122', 'email' => 'rezaul.k@gmail.com', 'cabins' => array( array('adults'=>6, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 48000 ),
				array( 'name' => 'Monirul Islam', 'phone' => '01732223344', 'email' => 'monir.islam@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 26000 ),
			),
			'costs' => array(
				'engine_fuel' => array( 'p1' => 16000, 'remark' => '135 Liters Diesel' ),
				'electricity' => array( 'p1' => 1500, 'remark' => 'Gen fuel' ),
				'groceries' => array( 'p1' => 6500, 'remark' => 'Groceries' ),
				'meat' => array( 'p1' => 10000, 'remark' => 'Duck & Chicken' ),
				'fish' => array( 'p1' => 8500, 'remark' => 'Haor fish' ),
				'kitchen_market' => array( 'p1' => 3500, 'remark' => 'Bazar' ),
				'gas' => array( 'p1' => 1400, 'remark' => 'Gas' ),
				'staff_convency' => array( 'p1' => 4500, 'remark' => 'Staff' ),
				'jetty_charge' => array( 'p1' => 1500, 'remark' => 'Jetty' ),
				'water' => array( 'p1' => 1200, 'remark' => 'Water' ),
				'fruits' => array( 'p1' => 1800, 'remark' => 'Fruits' ),
				'laundry' => array( 'p1' => 1600, 'remark' => 'Laundry' ),
				'ice' => array( 'p1' => 700, 'remark' => 'Ice' ),
			)
		),
		array(
			'date' => '2026-08-28',
			'label' => '28 – 29 Aug 2026',
			'trip_id' => 'TRIP-260828',
			'bookings' => array(
				array( 'name' => 'Tawhidul Alam', 'phone' => '01711556677', 'email' => 'tawhid.alam@gmail.com', 'cabins' => array( array('adults'=>2, 'c48'=>0, 'c04'=>0), array('adults'=>2, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 52000 ),
				array( 'name' => 'Ayesha Siddiqua', 'phone' => '01818990011', 'email' => 'ayesha.s@gmail.com', 'cabins' => array( array('adults'=>3, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 36000 ),
				array( 'name' => 'Sajjad Hossain', 'phone' => '01911223344', 'email' => 'sajjad.h@gmail.com', 'cabins' => array( array('adults'=>4, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 40000 ),
				array( 'name' => 'Golam Rabbani', 'phone' => '01670112233', 'email' => 'rabbani.g@gmail.com', 'cabins' => array( array('adults'=>5, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 45000 ),
				array( 'name' => 'Mahinur Rashid', 'phone' => '01733334455', 'email' => 'mahin.rashid@gmail.com', 'cabins' => array( array('adults'=>3, 'c48'=>0, 'c04'=>0) ), 'status' => 'completed', 'paid' => 36000 ),
			),
			'costs' => array(
				'engine_fuel' => array( 'p1' => 16000, 'remark' => '135 Liters Diesel' ),
				'electricity' => array( 'p1' => 1500, 'remark' => 'Gen fuel' ),
				'groceries' => array( 'p1' => 6500, 'remark' => 'Groceries' ),
				'meat' => array( 'p1' => 10000, 'remark' => 'Duck & Chicken' ),
				'fish' => array( 'p1' => 8500, 'remark' => 'Haor fish' ),
				'kitchen_market' => array( 'p1' => 3500, 'remark' => 'Bazar' ),
				'gas' => array( 'p1' => 1400, 'remark' => 'Gas' ),
				'staff_convency' => array( 'p1' => 4500, 'remark' => 'Staff' ),
				'jetty_charge' => array( 'p1' => 1500, 'remark' => 'Jetty' ),
				'water' => array( 'p1' => 1200, 'remark' => 'Water' ),
				'fruits' => array( 'p1' => 1800, 'remark' => 'Fruits' ),
				'laundry' => array( 'p1' => 1600, 'remark' => 'Laundry' ),
				'ice' => array( 'p1' => 700, 'remark' => 'Ice' ),
			)
		),
	);

	// Process Bookings & Cost Sheets for each trip
	$inv_counter = 50;

	foreach ( $trip_plans as $tp ) {
		$date = $tp['date'];
		$trip_total_rev = 0;
		$trip_total_guests = 0;

		foreach ( $tp['bookings'] as $b ) {
			$inv_counter++;
			$inv_no = 'BH-' . date( 'Y', strtotime( $date ) ) . '-' . str_pad( $inv_counter, 4, '0', STR_PAD_LEFT );
			
			$cprice = bhela_bm_calc_multi( $b['cabins'], $date );
			$total = ! is_wp_error( $cprice ) ? $cprice['total'] : $b['paid'];
			$guests = ! is_wp_error( $cprice ) ? $cprice['guests'] : 2;
			$day_type = ! is_wp_error( $cprice ) ? $cprice['day_type'] : 'weekend';
			$advance = (int) ceil( $total * 0.5 );
			$paid = (int) $b['paid'];

			$bid = wp_insert_post( array(
				'post_type'   => 'bhela_booking',
				'post_status' => 'publish',
				'post_title'  => $b['name'] . ' — ' . $inv_no,
			) );

			update_post_meta( $bid, '_bhela_invoice_no', $inv_no );
			update_post_meta( $bid, '_bhela_phone', $b['phone'] );
			update_post_meta( $bid, '_bhela_email', $b['email'] );
			update_post_meta( $bid, '_bhela_travel_date', $date );
			update_post_meta( $bid, '_bhela_guests', $guests );
			update_post_meta( $bid, '_bhela_day_type', $day_type );
			update_post_meta( $bid, '_bhela_total', $total );
			update_post_meta( $bid, '_bhela_advance', $advance );
			update_post_meta( $bid, '_bhela_paid_amount', $paid );
			update_post_meta( $bid, '_bhela_pay_method', 'bkash' );
			update_post_meta( $bid, '_bhela_status', $b['status'] );
			update_post_meta( $bid, '_bhela_cabins_json', wp_json_encode( $b['cabins'], JSON_UNESCAPED_UNICODE ) );
			update_post_meta( $bid, '_bhela_cabin_count', count( $b['cabins'] ) );

			$parts = array();
			if ( ! is_wp_error( $cprice ) ) {
				foreach ( $cprice['lines'] as $l ) {
					$parts[] = $l['label'] . ' (' . $l['who'] . ')';
				}
			}
			update_post_meta( $bid, '_bhela_cabin_type', implode( ' + ', $parts ) );

			$trip_total_rev += $total;
			$trip_total_guests += $guests;
		}

		// Create Linked Trip Cost Sheet
		$cid = wp_insert_post( array(
			'post_type'   => 'bhela_cost',
			'post_status' => 'publish',
			'post_title'  => mysql2date( 'j M Y', $date ) . ' — Trip Cost',
		) );

		$cost_total = 0;
		$lines = array();
		$heads = bhela_bm_cost_heads( true );
		foreach ( $tp['costs'] as $hkey => $citem ) {
			$label = $heads[ $hkey ] ?? $hkey;
			$lines[ $hkey ] = array(
				'label' => $label,
				'p1' => $citem['p1'],
				'p2' => 0,
				'p3' => 0,
				'remark' => $citem['remark'] ?? '',
			);
			$cost_total += $citem['p1'];
		}

		$header = array(
			'trip_id' => $tp['trip_id'],
			'boat_name' => 'BHELA',
			'total_guest' => (string) $trip_total_guests,
			'starting_date' => $date,
			'ending_date' => date( 'Y-m-d', strtotime( '+1 day', strtotime( $date ) ) ),
			'total_collection' => (string) $trip_total_rev,
		);

		update_post_meta( $cid, '_bhela_cost_trip_date', $date );
		update_post_meta( $cid, '_bhela_cost_header', wp_json_encode( $header, JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $cid, '_bhela_cost_lines', wp_json_encode( $lines, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT ) );
		update_post_meta( $cid, '_bhela_cost_total', $cost_total );
		update_post_meta( $cid, '_bhela_cost_earnings', $trip_total_rev );
		update_post_meta( $cid, '_bhela_cost_earnings_auto', $trip_total_rev );
		update_post_meta( $cid, '_bhela_cost_status', 'approved' );
		update_post_meta( $cid, '_bhela_cost_prepared_by', $admin_id );
		update_post_meta( $cid, '_bhela_cost_prepared_at', $date . ' 18:00:00' );
		update_post_meta( $cid, '_bhela_cost_checked_by', $admin_id );
		update_post_meta( $cid, '_bhela_cost_checked_at', $date . ' 19:00:00' );
		update_post_meta( $cid, '_bhela_cost_approved_by', $admin_id );
		update_post_meta( $cid, '_bhela_cost_approved_at', $date . ' 20:00:00' );
	}

	update_option( 'bhela_bm_invoice_counter', $inv_counter );

	// 3. Setup Monthly General Expenses
	$monthly_expenses = array(
		// July 2026 Expenses
		array( 'title' => 'Facebook Campaign — Monsoon Season Launch', 'date' => '2026-07-05', 'type' => 'boosting', 'amount' => 35000, 'method' => 'bank', 'paid_on' => '2026-07-05', 'verify' => 'TXN-FB-2607', 'remark' => 'Targeted ads across Dhaka & Sylhet' ),
		array( 'title' => 'Houseboat Season Opening Renovation & Varnish', 'date' => '2026-07-08', 'type' => 'renovation', 'amount' => 55000, 'method' => 'bank', 'paid_on' => '2026-07-08', 'verify' => 'CHQ-55421', 'remark' => 'Teak wood polishing, rooftop canopy repairs' ),
		array( 'title' => 'Domain, Cloud Hosting & SSL Annual Renewal', 'date' => '2026-07-15', 'type' => 'website', 'amount' => 12000, 'method' => 'bank', 'paid_on' => '2026-07-15', 'verify' => 'INV-WEB-887', 'remark' => 'Dedicated VPS & Domain renewal' ),
		array( 'title' => 'Tahirpur Anwarpur Ghat Docking Lease & License', 'date' => '2026-07-20', 'type' => 'other', 'amount' => 28000, 'method' => 'bank', 'paid_on' => '2026-07-20', 'verify' => 'LEAS-GHAT-01', 'remark' => 'Monthly docking priority rights & security fee' ),

		// August 2026 Expenses
		array( 'title' => 'Facebook & Instagram Reels Video Ad Boost', 'date' => '2026-08-04', 'type' => 'boosting', 'amount' => 42000, 'method' => 'bank', 'paid_on' => '2026-08-04', 'verify' => 'TXN-FB-2608', 'remark' => 'Video reel boosts reaching 350k haor travelers' ),
		array( 'title' => 'Life Jackets & Safety Ring Buoy Replacement', 'date' => '2026-08-10', 'type' => 'renovation', 'amount' => 25000, 'method' => 'cash', 'paid_on' => '2026-08-10', 'verify' => 'VOUCH-8821', 'remark' => '15 brand new Solas-certified life jackets' ),
		array( 'title' => 'SMS Gateway Top-up & OTP Credits', 'date' => '2026-08-16', 'type' => 'website', 'amount' => 8000, 'method' => 'bkash', 'paid_on' => '2026-08-16', 'verify' => 'BK-SMS-9921', 'remark' => '50,000 SMS credits for customer confirmation & OTP' ),
		array( 'title' => 'Local Community Guide Fee & Emergency Contingency', 'date' => '2026-08-22', 'type' => 'other', 'amount' => 15000, 'method' => 'cash', 'paid_on' => '2026-08-22', 'verify' => 'VOUCH-9014', 'remark' => 'Tanguar Haor local guide association monthly fund' ),
	);

	foreach ( $monthly_expenses as $exp ) {
		$eid = wp_insert_post( array(
			'post_type'   => 'bhela_expense',
			'post_status' => 'publish',
			'post_title'  => $exp['title'],
		) );

		update_post_meta( $eid, '_bhela_exp_date', $exp['date'] );
		update_post_meta( $eid, '_bhela_exp_type', $exp['type'] );
		update_post_meta( $eid, '_bhela_exp_amount', $exp['amount'] );
		update_post_meta( $eid, '_bhela_exp_method', $exp['method'] );
		update_post_meta( $eid, '_bhela_exp_paid_on', $exp['paid_on'] );
		update_post_meta( $eid, '_bhela_exp_verify', $exp['verify'] );
		update_post_meta( $eid, '_bhela_exp_remark', $exp['remark'] );
	}

	// 4. Setup Salary Sheets for July & August 2026
	$july_sal_id = wp_insert_post( array(
		'post_type'   => 'bhela_salary',
		'post_status' => 'publish',
		'post_title'  => 'Salary — July 2026',
	) );
	update_post_meta( $july_sal_id, '_bhela_salary_month', '2026-07' );
	$july_rows = array(
		'kader_ali'    => array( 'name' => 'Master Kader Ali', 'designation' => 'Boat Captain / Master', 'type' => 'monthly', 'account' => 'Dutch-Bangla Bank (115.120.9821)', 'rate' => 0, 'monthly' => 30000, 'trips' => 5, 'advance' => 5000, 'settlement' => 'PAID (DBBL)', 'adjustment' => 'None', 'verify' => 'TXN-DBBL-9921' ),
		'abdul_gafur'  => array( 'name' => 'Abdul Gafur', 'designation' => 'Head Chef', 'type' => 'both', 'account' => 'bKash (01712-445566)', 'rate' => 1500, 'monthly' => 18000, 'trips' => 5, 'advance' => 5000, 'settlement' => 'PAID (bKash)', 'adjustment' => 'None', 'verify' => 'BK-SAL-7781' ),
		'rahmat_ullah' => array( 'name' => 'Rahmat Ullah', 'designation' => 'Assistant Chef / Kitchen Staff', 'type' => 'trip', 'account' => 'bKash (01815-667788)', 'rate' => 2000, 'monthly' => 0, 'trips' => 5, 'advance' => 0, 'settlement' => 'PAID (bKash)', 'adjustment' => 'None', 'verify' => 'BK-SAL-7782' ),
		'sohel_mia'    => array( 'name' => 'Sohel Mia', 'designation' => 'Senior Deckhand & Anchor', 'type' => 'trip', 'account' => 'Nagad (01918-990011)', 'rate' => 1800, 'monthly' => 0, 'trips' => 5, 'advance' => 0, 'settlement' => 'PAID (Nagad)', 'adjustment' => 'None', 'verify' => 'NG-SAL-4412' ),
		'jamal_hossain'=> array( 'name' => 'Jamal Hossain', 'designation' => 'Cabin Steward & Housekeeping', 'type' => 'trip', 'account' => 'bKash (01677-889900)', 'rate' => 1800, 'monthly' => 0, 'trips' => 5, 'advance' => 0, 'settlement' => 'PAID (bKash)', 'adjustment' => 'None', 'verify' => 'BK-SAL-7783' ),
		'anwar_parvez' => array( 'name' => 'Anwar Parvez', 'designation' => 'Tanguar Haor Naturalist Guide', 'type' => 'trip', 'account' => 'bKash (01730-001122)', 'rate' => 2500, 'monthly' => 0, 'trips' => 5, 'advance' => 0, 'settlement' => 'PAID (bKash)', 'adjustment' => 'None', 'verify' => 'BK-SAL-7784' ),
		'uttam_kumar'  => array( 'name' => 'Uttam Kumar', 'designation' => 'Operations Manager', 'type' => 'monthly', 'account' => 'City Bank (220.154.8871)', 'rate' => 0, 'monthly' => 35000, 'trips' => 5, 'advance' => 0, 'settlement' => 'PAID (Bank)', 'adjustment' => 'None', 'verify' => 'TXN-CBL-1102' ),
	);
	update_post_meta( $july_sal_id, '_bhela_salary_rows', wp_json_encode( $july_rows, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT ) );
	$july_totals = bhela_bm_salary_totals( bhela_bm_salary_rows( $july_sal_id, '2026-07' ) );
	update_post_meta( $july_sal_id, '_bhela_salary_total', $july_totals['payable'] );

	$aug_sal_id = wp_insert_post( array(
		'post_type'   => 'bhela_salary',
		'post_status' => 'publish',
		'post_title'  => 'Salary — August 2026',
	) );
	update_post_meta( $aug_sal_id, '_bhela_salary_month', '2026-08' );
	$aug_rows = array(
		'kader_ali'    => array( 'name' => 'Master Kader Ali', 'designation' => 'Boat Captain / Master', 'type' => 'monthly', 'account' => 'Dutch-Bangla Bank (115.120.9821)', 'rate' => 0, 'monthly' => 30000, 'trips' => 6, 'advance' => 10000, 'settlement' => 'PAID (DBBL)', 'adjustment' => 'None', 'verify' => 'TXN-DBBL-9988' ),
		'abdul_gafur'  => array( 'name' => 'Abdul Gafur', 'designation' => 'Head Chef', 'type' => 'both', 'account' => 'bKash (01712-445566)', 'rate' => 1500, 'monthly' => 18000, 'trips' => 6, 'advance' => 5000, 'settlement' => 'PAID (bKash)', 'adjustment' => 'None', 'verify' => 'BK-SAL-8891' ),
		'rahmat_ullah' => array( 'name' => 'Rahmat Ullah', 'designation' => 'Assistant Chef / Kitchen Staff', 'type' => 'trip', 'account' => 'bKash (01815-667788)', 'rate' => 2000, 'monthly' => 0, 'trips' => 6, 'advance' => 0, 'settlement' => 'PAID (bKash)', 'adjustment' => 'None', 'verify' => 'BK-SAL-8892' ),
		'sohel_mia'    => array( 'name' => 'Sohel Mia', 'designation' => 'Senior Deckhand & Anchor', 'type' => 'trip', 'account' => 'Nagad (01918-990011)', 'rate' => 1800, 'monthly' => 0, 'trips' => 6, 'advance' => 0, 'settlement' => 'PAID (Nagad)', 'adjustment' => 'None', 'verify' => 'NG-SAL-5521' ),
		'jamal_hossain'=> array( 'name' => 'Jamal Hossain', 'designation' => 'Cabin Steward & Housekeeping', 'type' => 'trip', 'account' => 'bKash (01677-889900)', 'rate' => 1800, 'monthly' => 0, 'trips' => 6, 'advance' => 0, 'settlement' => 'PAID (bKash)', 'adjustment' => 'None', 'verify' => 'BK-SAL-8893' ),
		'anwar_parvez' => array( 'name' => 'Anwar Parvez', 'designation' => 'Tanguar Haor Naturalist Guide', 'type' => 'trip', 'account' => 'bKash (01730-001122)', 'rate' => 2500, 'monthly' => 0, 'trips' => 6, 'advance' => 0, 'settlement' => 'PAID (bKash)', 'adjustment' => 'None', 'verify' => 'BK-SAL-8894' ),
		'uttam_kumar'  => array( 'name' => 'Uttam Kumar', 'designation' => 'Operations Manager', 'type' => 'monthly', 'account' => 'City Bank (220.154.8871)', 'rate' => 0, 'monthly' => 35000, 'trips' => 6, 'advance' => 0, 'settlement' => 'PAID (Bank)', 'adjustment' => 'None', 'verify' => 'TXN-CBL-1188' ),
	);
	update_post_meta( $aug_sal_id, '_bhela_salary_rows', wp_json_encode( $aug_rows, JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT ) );
	$aug_totals = bhela_bm_salary_totals( bhela_bm_salary_rows( $aug_sal_id, '2026-08' ) );
	update_post_meta( $aug_sal_id, '_bhela_salary_total', $aug_totals['payable'] );

	wp_die(
		'<div style="max-width:600px;margin:40px auto;background:#fff;padding:24px;border:1px solid #ccd0d4;border-radius:6px;font-family:sans-serif;">'
		. '<h2 style="color:#137A74;margin-top:0;">✓ Financial Data & Salary Sheets Seeded Successfully</h2>'
		. '<p><strong>July 2026:</strong> 5 Trips, 95 Guests, ৳1,004,000 Rev, ৳584,200 Gross Profit. Salary: <strong>৳141,000</strong></p>'
		. '<p><strong>August 2026:</strong> 6 Trips, 119 Guests, ৳1,186,600 Rev, ৳747,600 Gross Profit. Salary: <strong>৳150,600</strong></p>'
		. '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=bhela_salary' ) ) . '" style="display:inline-block;padding:8px 16px;background:#137A74;color:#fff;text-decoration:none;border-radius:4px;font-weight:600;margin-right:8px;">View Salary Sheets</a></p>'
		. '</div>'
	);
}
add_action( 'admin_init', 'bhela_bm_seed_reports_data' );
