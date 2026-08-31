<?php
/**
 * The investor screens: the record, the monthly distribution, and the report.
 *
 * The Distribution screen shows bhela_bm_dist_preview() and commits exactly what it
 * showed. It does not recompute anything for display — a screen that derives its own
 * version of a figure is how a screen and a ledger start disagreeing, and here the
 * ledger is what somebody gets paid from.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * The investor record
 * ========================================================= */

/**
 * The record, field for field as the onboarding form asks for it.
 *
 * These mirror BHELA's live "Investor Information & Nominee Declaration Form", so a
 * completed form can be typed in without anything being dropped or invented. Where
 * the form separates two things - present and permanent address, father and mother,
 * account name and number - this does too. Merging them loses information that a
 * bank transfer or a succession claim later needs exactly as it was written down.
 */
function bhela_bm_investor_fields() {
	return array(
		'identity' => array(
			'label'  => __( 'Section A - Investor Details', 'bhela-booking' ),
			'fields' => array(
				'code'      => array( 'label' => __( 'Investor ID', 'bhela-booking' ) ),
				'father'    => array( 'label' => __( 'Father name', 'bhela-booking' ) ),
				'mother'    => array( 'label' => __( 'Mother name', 'bhela-booking' ) ),
				'dob'       => array( 'label' => __( 'Date of birth', 'bhela-booking' ), 'type' => 'date' ),
				'nid'       => array( 'label' => __( 'NID / Passport / Birth certificate', 'bhela-booking' ) ),
				'address'   => array( 'label' => __( 'Present address', 'bhela-booking' ), 'type' => 'textarea' ),
				'address_p' => array( 'label' => __( 'Permanent address', 'bhela-booking' ), 'type' => 'textarea' ),
				'mobile'    => array( 'label' => __( 'Mobile', 'bhela-booking' ) ),
				'email'     => array( 'label' => __( 'Email', 'bhela-booking' ), 'type' => 'email' ),
			),
		),
		'bank'     => array(
			'label'  => __( 'Section B - Payment and Bank', 'bhela-booking' ),
			'fields' => array(
				'pay_mode'          => array(
					'label'   => __( 'Mode of payment', 'bhela-booking' ),
					'type'    => 'select',
					'options' => array(
						''       => __( 'Not recorded', 'bhela-booking' ),
						'cash'   => __( 'Cash', 'bhela-booking' ),
						'bank'   => __( 'Bank transfer', 'bhela-booking' ),
						'cheque' => __( 'Cheque', 'bhela-booking' ),
						'other'  => __( 'Other', 'bhela-booking' ),
					),
				),
				'pay_mode_other'    => array( 'label' => __( 'If other, specify', 'bhela-booking' ) ),
				'bank_name'         => array( 'label' => __( 'Bank name', 'bhela-booking' ) ),
				'bank_branch'       => array( 'label' => __( 'Branch name', 'bhela-booking' ) ),
				'bank_account_name' => array( 'label' => __( 'Account name', 'bhela-booking' ) ),
				'bank_account'      => array( 'label' => __( 'Account number', 'bhela-booking' ) ),
				'bank_routing'      => array( 'label' => __( 'Routing number', 'bhela-booking' ) ),
			),
		),
		'nominee'  => array(
			'label'  => __( 'Section C - Nominee', 'bhela-booking' ),
			'fields' => array(
				'nominee_name'     => array( 'label' => __( 'Full name', 'bhela-booking' ) ),
				'nominee_relation' => array( 'label' => __( 'Relation to investor', 'bhela-booking' ) ),
				'nominee_dob'      => array( 'label' => __( 'Date of birth', 'bhela-booking' ), 'type' => 'date' ),
				'nominee_nid'      => array( 'label' => __( 'NID / Passport / Birth certificate', 'bhela-booking' ) ),
				'nominee_mobile'   => array( 'label' => __( 'Mobile', 'bhela-booking' ) ),
				'nominee_address'  => array( 'label' => __( 'Address', 'bhela-booking' ), 'type' => 'textarea' ),
			),
		),
		'declaration' => array(
			'label'  => __( 'Section D - Declaration', 'bhela-booking' ),
			'fields' => array(
				'declared'     => array(
					'label'   => __( 'Declaration signed', 'bhela-booking' ),
					'type'    => 'select',
					'options' => array(
						''    => __( 'Not recorded', 'bhela-booking' ),
						'yes' => __( 'Yes - nominee rights confirmed', 'bhela-booking' ),
						'no'  => __( 'No', 'bhela-booking' ),
					),
					'help'    => __( 'The declaration on the form: the information is correct, and the nominee holds all rights to this investment in the investor absence.', 'bhela-booking' ),
				),
				'declared_on'  => array( 'label' => __( 'Date signed', 'bhela-booking' ), 'type' => 'date' ),
				'sig_investor' => array(
					'label' => __( 'Investor signature', 'bhela-booking' ),
					'type'  => 'file',
					'help'  => __( 'Upload the scan to the Media Library and paste its URL here. The record keeps a link, not a second copy.', 'bhela-booking' ),
				),
				'sig_nominee'  => array( 'label' => __( 'Nominee signature', 'bhela-booking' ), 'type' => 'file' ),
				'agreement'    => array( 'label' => __( 'Agreement / KYC document', 'bhela-booking' ), 'type' => 'file' ),
			),
		),
	);
}

function bhela_bm_investor_boxes() {
	add_meta_box( 'bhela-inv-money', __( 'Investment', 'bhela-booking' ), 'bhela_bm_investor_money_box', 'bhela_investor', 'normal', 'high' );
	add_meta_box( 'bhela-inv-detail', __( 'Investor Details', 'bhela-booking' ), 'bhela_bm_investor_detail_box', 'bhela_investor', 'normal', 'default' );
	add_meta_box( 'bhela-inv-position', __( 'Position', 'bhela-booking' ), 'bhela_bm_investor_position_box', 'bhela_investor', 'side', 'default' );
	add_meta_box( 'bhela-inv-login', __( 'Portal Login', 'bhela-booking' ), 'bhela_bm_investor_login_box', 'bhela_investor', 'side', 'default' );
}
add_action( 'add_meta_boxes_bhela_investor', 'bhela_bm_investor_boxes' );

function bhela_bm_investor_money_box( $post ) {
	wp_nonce_field( 'bhela_bm_investor_save', 'bhela_bm_investor_nonce' );
	$m   = function ( $k ) use ( $post ) {
		return get_post_meta( $post->ID, '_bhela_inv_' . $k, true );
	};
	$cfg = bhela_bm_share_config();
	$tot = bhela_bm_share_totals();
	?>
	<table class="form-table">
		<tr><th><?php esc_html_e( 'Shares', 'bhela-booking' ); ?></th>
			<td><input type="number" min="0" name="inv_shares" value="<?php echo esc_attr( $m( 'shares' ) ); ?>" style="width:120px">
				<p class="description"><?php
					printf(
						/* translators: 1: this holding's %, 2: configured total shares */
						esc_html__( '%1$s%% of the %2$d configured shares.', 'bhela-booking' ),
						esc_html( (string) bhela_bm_investor_share_pct( $post->ID ) ),
						(int) $cfg['total_shares']
					);
				?></p></td></tr>
		<tr><th><?php esc_html_e( 'Amount invested (৳)', 'bhela-booking' ); ?></th>
			<td><input type="number" min="0" name="inv_amount" value="<?php echo esc_attr( $m( 'amount' ) ); ?>" style="width:160px">
				<p class="description"><?php
					printf(
						/* translators: %s: per-share value */
						esc_html__( 'Leave blank to use shares × %s.', 'bhela-booking' ),
						esc_html( bhela_bm_money( $cfg['per_share'] ) )
					);
				?></p></td></tr>
		<tr><th><?php esc_html_e( 'Investment date', 'bhela-booking' ); ?></th>
			<td><input type="date" name="inv_date" value="<?php echo esc_attr( $m( 'date' ) ); ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Status', 'bhela-booking' ); ?></th>
			<td><select name="inv_status">
				<?php foreach ( array(
					'active'    => __( 'Active', 'bhela-booking' ),
					'suspended' => __( 'Suspended', 'bhela-booking' ),
					'exited'    => __( 'Exited', 'bhela-booking' ),
				) as $k => $lab ) : ?>
					<option value="<?php echo esc_attr( $k ); ?>" <?php selected( bhela_bm_investor_status( $post->ID ), $k ); ?>><?php echo esc_html( $lab ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'An exited investor keeps every past ledger row but takes no share of new profit.', 'bhela-booking' ); ?></p></td></tr>
	</table>
	<?php if ( $tot['over'] ) : ?>
		<p class="bha-callout bha-callout--attention"><strong><?php
			printf(
				/* translators: 1: issued, 2: configured */
				esc_html__( '%1$d shares are issued but only %2$d are configured.', 'bhela-booking' ),
				(int) $tot['issued'],
				(int) $tot['configured']
			);
		?></strong> <?php esc_html_e( 'Distribution is blocked until this is resolved — the percentages already add to more than 100%.', 'bhela-booking' ); ?></p>
	<?php endif; ?>
	<?php
}

function bhela_bm_investor_detail_box( $post ) {
	foreach ( bhela_bm_investor_fields() as $group ) {
		echo '<h4 style="margin:1.2em 0 .2em">' . esc_html( $group['label'] ) . '</h4><table class="form-table">';
		foreach ( $group['fields'] as $key => $def ) {
			$val  = get_post_meta( $post->ID, '_bhela_inv_' . $key, true );
			$type = isset( $def['type'] ) ? $def['type'] : 'text';
			echo '<tr><th>' . esc_html( $def['label'] ) . '</th><td>';
			if ( 'textarea' === $type ) {
				printf(
					'<textarea class="large-text" rows="2" name="inv_%s">%s</textarea>',
					esc_attr( $key ),
					esc_textarea( $val )
				);
			} elseif ( 'select' === $type ) {
				printf( '<select name="inv_%s">', esc_attr( $key ) );
				foreach ( $def['options'] as $ov => $ol ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $ov ),
						selected( $val, $ov, false ),
						esc_html( $ol )
					);
				}
				echo '</select>';
			} elseif ( 'file' === $type ) {
				printf(
					'<input type="url" class="large-text" name="inv_%s" value="%s" placeholder="%s">',
					esc_attr( $key ),
					esc_attr( $val ),
					esc_attr__( 'Media Library URL', 'bhela-booking' )
				);
				if ( $val ) {
					printf(
						' <a href="%s" target="_blank" rel="noopener">%s</a>',
						esc_url( $val ),
						esc_html__( 'view', 'bhela-booking' )
					);
				}
			} else {
				printf(
					'<input type="%s" class="regular-text" name="inv_%s" value="%s">',
					esc_attr( ( 'date' === $type || 'email' === $type ) ? $type : 'text' ),
					esc_attr( $key ),
					esc_attr( $val )
				);
			}
			if ( ! empty( $def['help'] ) ) {
				echo '<p class="description">' . esc_html( $def['help'] ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}
}

function bhela_bm_investor_position_box( $post ) {
	$pos = bhela_bm_investor_position( $post->ID );
	$roi = bhela_bm_investor_roi( $post->ID );
	$row = function ( $label, $value ) {
		printf( '<p style="display:flex;justify-content:space-between;margin:.3rem 0"><span>%s</span><strong>%s</strong></p>',
			esc_html( $label ), esc_html( $value ) );
	};
	$row( __( 'Invested', 'bhela-booking' ), bhela_bm_money( $roi['investment'] ) );
	$row( __( 'Profit declared', 'bhela-booking' ), bhela_bm_money( $pos['profit'] ) );
	$row( __( 'Received', 'bhela-booking' ), bhela_bm_money( $pos['received'] ) );
	$row( __( 'Outstanding', 'bhela-booking' ), bhela_bm_money( $pos['outstanding'] ) );
	$row( __( 'ROI (received)', 'bhela-booking' ), $roi['roi'] . '%' );
	$row( __( 'ROI (declared)', 'bhela-booking' ), $roi['roi_declared'] . '%' );
	printf( '<p><a class="button" href="%s">%s</a></p>',
		esc_url( bhela_bm_admin_url( 'bhela-bm-investor-report', array( 'investor' => $post->ID ) ) ),
		esc_html__( 'Open ledger', 'bhela-booking' ) );
}

function bhela_bm_investor_save( $post_id ) {
	if ( ! isset( $_POST['bhela_bm_investor_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_bm_investor_nonce'] ) ), 'bhela_bm_investor_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$shares = max( 0, (int) ( $_POST['inv_shares'] ?? 0 ) );
	$before = bhela_bm_investor_shares( $post_id );
	update_post_meta( $post_id, '_bhela_inv_shares', $shares );
	update_post_meta( $post_id, '_bhela_inv_amount', max( 0, (int) ( $_POST['inv_amount'] ?? 0 ) ) );
	update_post_meta( $post_id, '_bhela_inv_date', bhela_bm_report_date( $_POST['inv_date'] ?? '' ) );
	$status = sanitize_key( $_POST['inv_status'] ?? 'active' );
	update_post_meta( $post_id, '_bhela_inv_status', in_array( $status, array( 'active', 'suspended', 'exited' ), true ) ? $status : 'active' );

	foreach ( bhela_bm_investor_fields() as $group ) {
		foreach ( $group['fields'] as $key => $def ) {
			if ( ! isset( $_POST[ 'inv_' . $key ] ) ) {
				continue;
			}
			$raw  = wp_unslash( $_POST[ 'inv_' . $key ] );
			$type = isset( $def['type'] ) ? $def['type'] : 'text';
			// A signature is a URL, an address keeps its line breaks, a date is only
			// a date. sanitize_text_field() on all of them would silently flatten the
			// addresses and let a junk URL through.
			if ( 'file' === $type ) {
				$clean = esc_url_raw( $raw );
			} elseif ( 'textarea' === $type ) {
				$clean = sanitize_textarea_field( $raw );
			} elseif ( 'email' === $type ) {
				$clean = sanitize_email( $raw );
			} elseif ( 'date' === $type ) {
				$clean = bhela_bm_report_date( $raw );
			} else {
				$clean = sanitize_text_field( $raw );
			}
			update_post_meta( $post_id, '_bhela_inv_' . $key, $clean );
		}
	}

	bhela_bm_investor_save_login( $post_id );

	// A shareholding change moves everybody's percentage and every future payout, so
	// it is recorded with both figures rather than just the new one.
	if ( $before !== $shares ) {
		bhela_bm_audit( array(
			'channel'     => 'investor',
			'action'      => 'shares',
			'object_type' => 'investor',
			'object_id'   => $post_id,
			'object_ref'  => get_the_title( $post_id ),
			'field'       => 'shares',
			'old_value'   => (string) $before,
			'new_value'   => (string) $shares,
		) );
	}
}
add_action( 'save_post_bhela_investor', 'bhela_bm_investor_save' );

/**
 * The portal login for this investor.
 *
 * Linking is one-to-one and enforced at read time by bhela_bm_current_investor(),
 * which refuses when a user id resolves to more than one record. Creating the
 * account here rather than by hand means the role is always right — an investor
 * account created through Users → Add New could be given any role at all.
 */
function bhela_bm_investor_login_box( $post ) {
	$uid  = bhela_bm_investor_user( $post->ID );
	$user = $uid ? get_userdata( $uid ) : null;
	?>
	<?php if ( $user ) : ?>
		<p><strong><?php echo esc_html( $user->user_login ); ?></strong><br>
			<span class="description"><?php echo esc_html( $user->user_email ); ?></span></p>
		<?php if ( ! in_array( 'bhela_investor', (array) $user->roles, true ) ) : ?>
			<p class="bha-callout bha-callout--attention"><?php esc_html_e( 'This account does not hold the Investor role, so it may have wider access than the portal.', 'bhela-booking' ); ?></p>
		<?php endif; ?>
		<p><label><input type="checkbox" name="inv_unlink" value="1">
			<?php esc_html_e( 'Unlink this login', 'bhela-booking' ); ?></label>
			<span class="description"><?php esc_html_e( 'The WordPress account is kept; it simply stops resolving to this record.', 'bhela-booking' ); ?></span></p>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'No portal login yet. Enter an email to create one — the investor sets their own password from the reset link.', 'bhela-booking' ); ?></p>
		<p><input type="email" class="widefat" name="inv_new_login" placeholder="<?php esc_attr_e( 'investor@example.com', 'bhela-booking' ); ?>"></p>
		<p><label><input type="checkbox" name="inv_send_reset" value="1" checked>
			<?php esc_html_e( 'Email them a set-password link', 'bhela-booking' ); ?></label></p>
	<?php endif; ?>
	<p class="description"><?php esc_html_e( 'The portal is read-only and shows this investor nothing but their own position.', 'bhela-booking' ); ?></p>
	<?php
}

/** Create or unlink the portal account. Runs inside the record's own save. */
function bhela_bm_investor_save_login( $post_id ) {
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! empty( $_POST['inv_unlink'] ) ) {
		$was = bhela_bm_investor_user( $post_id );
		delete_post_meta( $post_id, '_bhela_inv_user' );
		if ( $was ) {
			bhela_bm_audit( array(
				'channel' => 'investor', 'action' => 'unlink', 'object_type' => 'investor',
				'object_id' => $post_id, 'object_ref' => get_the_title( $post_id ),
				'field' => 'user', 'old_value' => (string) $was, 'new_value' => '',
			) );
		}
		return;
	}

	$email = sanitize_email( wp_unslash( $_POST['inv_new_login'] ?? '' ) );
	if ( '' === $email || bhela_bm_investor_user( $post_id ) ) {
		return;
	}
	// Only an administrator may mint an account: creating users is a bigger act than
	// editing an investor record, and Investor Relations does not need it.
	if ( ! current_user_can( 'create_users' ) ) {
		return;
	}

	$uid = email_exists( $email );
	if ( ! $uid ) {
		$uid = wp_insert_user( array(
			'user_login'   => $email,
			'user_email'   => $email,
			// Never a chosen or guessable password. The investor sets their own from
			// the reset link; nobody at BHELA ever knows it.
			'user_pass'    => wp_generate_password( 24, true, true ),
			'display_name' => get_the_title( $post_id ),
			'role'         => 'bhela_investor',
		) );
		if ( is_wp_error( $uid ) ) {
			return;
		}
		if ( ! empty( $_POST['inv_send_reset'] ) ) {
			retrieve_password( $email );
		}
	}

	// Refuse to link an account that already belongs to another investor: one login,
	// one record, checked before the link rather than discovered at read time.
	$taken = get_posts( array(
		'post_type' => 'bhela_investor', 'post_status' => 'any', 'posts_per_page' => 1,
		'fields' => 'ids', 'no_found_rows' => true, 'post__not_in' => array( $post_id ),
		'meta_key' => '_bhela_inv_user', 'meta_value' => (int) $uid,
	) );
	if ( $taken ) {
		return;
	}

	update_post_meta( $post_id, '_bhela_inv_user', (int) $uid );
	bhela_bm_audit( array(
		'channel' => 'investor', 'action' => 'link', 'object_type' => 'investor',
		'object_id' => $post_id, 'object_ref' => get_the_title( $post_id ),
		'field' => 'user', 'new_value' => (string) $uid,
	) );
}


/* =========================================================
 * Screens
 * ========================================================= */

function bhela_bm_investor_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'investors' ),
		__( 'Distribution', 'bhela-booking' ),
		__( '💰 Distribution', 'bhela-booking' ),
		'bhela_investors_view',
		'bhela-bm-dist',
		'bhela_bm_dist_page'
	);
	add_submenu_page(
		bhela_bm_menu_parent( 'investors' ),
		__( 'Investor Report', 'bhela-booking' ),
		__( '📊 Investor Report', 'bhela-booking' ),
		'bhela_investors_view',
		'bhela-bm-investor-report',
		'bhela_bm_investor_report_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_investor_menu' );

/** Distribution: preview a month, then commit exactly what was previewed. */
function bhela_bm_dist_page() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		return;
	}
	$month  = preg_match( '/^\d{4}-\d{2}$/', (string) ( $_GET['month'] ?? '' ) ) ? $_GET['month'] : gmdate( 'Y-m', strtotime( '-1 month' ) );
	$notice = '';

	if ( isset( $_POST['bhela_dist_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_dist_nonce'] ) ), 'bhela_bm_dist' ) ) {
		$month = preg_match( '/^\d{4}-\d{2}$/', (string) ( $_POST['month'] ?? '' ) ) ? $_POST['month'] : $month;
		$res   = bhela_bm_dist_commit(
			$month,
			isset( $_POST['reserve_pct'] ) ? (int) $_POST['reserve_pct'] : null,
			sanitize_textarea_field( $_POST['note'] ?? '' )
		);
		$notice = is_wp_error( $res )
			? '<div class="notice notice-error"><p>' . esc_html( $res->get_error_message() ) . '</p></div>'
			: '<div class="notice notice-success"><p>' . esc_html__( 'Distribution committed. Every investor now has a profit row in their ledger.', 'bhela-booking' ) . '</p></div>';
	}

	$reserve = isset( $_GET['reserve_pct'] ) ? (int) $_GET['reserve_pct'] : null;
	$p       = bhela_bm_dist_preview( $month, $reserve );
	$run     = bhela_bm_dist_run( $month );
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'💰',
			__( 'Profit Distribution', 'bhela-booking' ),
			__( 'Closes a month and writes what each investor is owed. Computed only from APPROVED cost sheets — an unapproved trip pays nobody.', 'bhela-booking' )
		);
		echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>

		<div class="bha-bar">
			<?php // No hidden post_type: this page hangs off admin.php (§13.14). ?>
			<form method="get">
				<input type="hidden" name="page" value="bhela-bm-dist">
				<div class="bha-field">
					<label for="dist-month"><?php esc_html_e( 'Month', 'bhela-booking' ); ?></label>
					<input type="month" id="dist-month" name="month" value="<?php echo esc_attr( $month ); ?>">
				</div>
				<div class="bha-field">
					<label for="dist-res"><?php esc_html_e( 'Reserve %', 'bhela-booking' ); ?></label>
					<input type="number" id="dist-res" name="reserve_pct" min="0" max="90" value="<?php echo esc_attr( $p['reserve_pct'] ); ?>" style="width:80px">
				</div>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Preview', 'bhela-booking' ); ?></button>
			</form>
		</div>

		<?php if ( $p['shares']['over'] ) : ?>
			<p class="bha-callout bha-callout--attention"><strong><?php esc_html_e( 'More shares are issued than configured.', 'bhela-booking' ); ?></strong>
				<?php printf(
					/* translators: 1: issued, 2: configured */
					esc_html__( '%1$d issued against %2$d configured — distribution is blocked until that is resolved.', 'bhela-booking' ),
					(int) $p['shares']['issued'],
					(int) $p['shares']['configured']
				); ?></p>
		<?php elseif ( $p['shares']['under'] ) : ?>
			<p class="bha-callout"><?php printf(
				/* translators: 1: issued, 2: configured, 3: money */
				esc_html__( '%1$d of %2$d shares are issued, so %3$s of the investor pool stays with the business rather than being paid out.', 'bhela-booking' ),
				(int) $p['shares']['issued'],
				(int) $p['shares']['configured'],
				esc_html( bhela_bm_money( $p['unallocated'] ) )
			); ?></p>
		<?php endif; ?>

		<div class="bha-cards">
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Approved trips', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $p['trips'] ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Gross profit', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $p['gross'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php printf( esc_html__( 'Reserve %d%%', 'bhela-booking' ), (int) $p['reserve_pct'] ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $p['reserve'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php printf( esc_html__( 'Investor pool %d%%', 'bhela-booking' ), (int) $p['investor_pct'] ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $p['investor'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Management', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $p['management'] ) ); ?></span></div>
		</div>

		<div class="bha-panel">
			<h2><?php echo $run
				? esc_html__( 'Committed', 'bhela-booking' )
				: esc_html__( 'Preview — nothing is written yet', 'bhela-booking' ); ?></h2>
			<div class="bha-scroll">
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Shares', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Share %', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Profit', 'bhela-booking' ); ?></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $p['rows'] ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No investors hold shares yet.', 'bhela-booking' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $p['rows'] as $r ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_post_link( $r['investor'] ) ); ?>"><?php echo esc_html( $r['name'] ); ?></a></td>
						<td class="bha-num"><?php echo esc_html( $r['shares'] ); ?></td>
						<td class="bha-num"><?php echo esc_html( $r['pct'] ); ?>%</td>
						<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $r['amount'] ) ); ?></strong></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot><tr class="bha-row--total">
					<td colspan="3"><?php esc_html_e( 'Allocated', 'bhela-booking' ); ?></td>
					<td class="bha-num"><?php echo esc_html( bhela_bm_money( $p['allocated'] ) ); ?></td>
				</tr></tfoot>
			</table>
			</div>

			<?php if ( $run ) : ?>
				<?php $d = bhela_bm_dist_data( $run ); ?>
				<p class="bha-callout"><?php printf(
					/* translators: 1: date, 2: user */
					esc_html__( 'Committed %1$s by %2$s. A committed month cannot be re-run or deleted — a mistake is corrected with a reversal on the investor’s ledger, which leaves a record of why.', 'bhela-booking' ),
					esc_html( $d['at'] ),
					esc_html( get_the_author_meta( 'display_name', $d['by'] ) )
				); ?></p>
			<?php elseif ( current_user_can( 'bhela_dist_run' ) && $p['gross'] > 0 && ! $p['shares']['over'] ) : ?>
				<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Commit this distribution? It cannot be re-run or undone.', 'bhela-booking' ) ); ?>');">
					<?php wp_nonce_field( 'bhela_bm_dist', 'bhela_dist_nonce' ); ?>
					<input type="hidden" name="month" value="<?php echo esc_attr( $month ); ?>">
					<input type="hidden" name="reserve_pct" value="<?php echo esc_attr( $p['reserve_pct'] ); ?>">
					<p><label><?php esc_html_e( 'Note (optional)', 'bhela-booking' ); ?><br>
						<input type="text" class="large-text" name="note" placeholder="<?php esc_attr_e( 'e.g. approved at the 5 Sept board meeting', 'bhela-booking' ); ?>"></label></p>
					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Commit distribution', 'bhela-booking' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/** Investor report: everyone at a glance, or one investor's full ledger. */
function bhela_bm_investor_report_page() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		return;
	}
	$one = (int) ( $_GET['investor'] ?? 0 );
	if ( $one && 'bhela_investor' !== get_post_type( $one ) ) {
		$one = 0;
	}
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'📊',
			$one ? get_the_title( $one ) : __( 'Investor Report', 'bhela-booking' ),
			$one
				? __( 'Every movement on this investor’s account, oldest first.', 'bhela-booking' )
				: __( 'What each investor has been declared, paid, and is still owed.', 'bhela-booking' )
		);
		?>
		<?php if ( $one ) : ?>
			<p><a class="button" href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-investor-report' ) ); ?>">← <?php esc_html_e( 'All investors', 'bhela-booking' ); ?></a></p>
			<?php
			$led = bhela_bm_investor_ledger( $one );
			$roi = bhela_bm_investor_roi( $one );
			?>
			<div class="bha-cards">
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Invested', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $roi['investment'] ) ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Declared', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $roi['declared'] ) ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Received', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $roi['received'] ) ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Outstanding', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $roi['outstanding'] ) ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'ROI', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $roi['roi'] ); ?>%</span></div>
			</div>

			<?php if ( current_user_can( 'bhela_investor_pay' ) ) : ?>
				<div class="bha-panel">
					<h2><?php esc_html_e( 'Record a movement', 'bhela-booking' ); ?></h2>
					<form method="post">
						<?php wp_nonce_field( 'bhela_bm_ledger', 'bhela_ledger_nonce' ); ?>
						<input type="hidden" name="investor" value="<?php echo esc_attr( $one ); ?>">
						<div class="bha-bar">
							<div class="bha-field"><label><?php esc_html_e( 'Type', 'bhela-booking' ); ?></label>
								<select name="type">
									<?php foreach ( bhela_bm_ledger_types() as $k => $t ) : ?>
										<?php if ( 'profit' === $k ) { continue; } // profit comes from a distribution run, never by hand ?>
										<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $t['label'] ); ?></option>
									<?php endforeach; ?>
								</select></div>
							<div class="bha-field"><label><?php esc_html_e( 'Amount ৳', 'bhela-booking' ); ?></label>
								<input type="number" name="amount" required></div>
							<div class="bha-field"><label><?php esc_html_e( 'Date', 'bhela-booking' ); ?></label>
								<input type="date" name="date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></div>
							<div class="bha-field"><label><?php esc_html_e( 'Method', 'bhela-booking' ); ?></label>
								<input type="text" name="method" placeholder="<?php esc_attr_e( 'bank / cash', 'bhela-booking' ); ?>"></div>
							<div class="bha-field"><label><?php esc_html_e( 'Note', 'bhela-booking' ); ?></label>
								<input type="text" name="note"></div>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Record', 'bhela-booking' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Profit is never entered by hand — it comes from a distribution run, so the ledger always traces back to an approved month.', 'bhela-booking' ); ?></p>
					</form>
				</div>
			<?php endif; ?>

			<div class="bha-panel">
				<h2><?php esc_html_e( 'Ledger', 'bhela-booking' ); ?></h2>
				<div class="bha-scroll">
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Date', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bhela-booking' ); ?></th>
						<th><?php esc_html_e( 'Note', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Amount', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Balance', 'bhela-booking' ); ?></th>
						<th class="bha-noprint"></th>
					</tr></thead>
					<tbody>
					<?php if ( ! $led['rows'] ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'Nothing recorded yet.', 'bhela-booking' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $led['rows'] as $r ) : ?>
						<?php $undone = bhela_bm_ledger_reversal_of( $r['id'] ); ?>
						<tr<?php echo $undone ? ' style="opacity:.55"' : ''; ?>>
							<td><?php echo esc_html( mysql2date( 'j M Y', $r['date'] ) ); ?></td>
							<td><?php echo esc_html( $r['label'] ); ?></td>
							<td><?php echo esc_html( $r['note'] ); ?></td>
							<td class="bha-num"><?php echo esc_html( ( $r['signed'] > 0 ? '+' : '' ) . bhela_bm_money( $r['signed'] ) ); ?></td>
							<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $r['balance'] ) ); ?></strong></td>
							<td class="bha-noprint">
								<?php if ( ! $undone && ! $r['reverses'] && current_user_can( 'bhela_investor_pay' ) ) : ?>
									<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Reverse this entry?', 'bhela-booking' ) ); ?>');">
										<?php wp_nonce_field( 'bhela_bm_ledger_rev', 'bhela_rev_nonce' ); ?>
										<input type="hidden" name="row" value="<?php echo esc_attr( $r['id'] ); ?>">
										<input type="text" name="reason" required placeholder="<?php esc_attr_e( 'reason', 'bhela-booking' ); ?>" style="width:130px">
										<button class="button button-small"><?php esc_html_e( 'Reverse', 'bhela-booking' ); ?></button>
									</form>
								<?php elseif ( $undone ) : ?>
									<?php echo bhela_bm_status_pill( __( 'reversed', 'bhela-booking' ), 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			</div>
		<?php else : ?>
			<?php $tot = bhela_bm_share_totals(); ?>
			<div class="bha-cards">
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Investors', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $tot['investors'] ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Shares issued', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( $tot['issued'] . ' / ' . $tot['configured'] ); ?></span></div>
			</div>
			<div class="bha-panel">
				<div class="bha-scroll">
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Investor', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Shares', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Invested', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Declared', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Received', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'Outstanding', 'bhela-booking' ); ?></th>
						<th class="bha-num"><?php esc_html_e( 'ROI', 'bhela-booking' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( bhela_bm_investors() as $id ) : ?>
						<?php $r = bhela_bm_investor_roi( $id ); ?>
						<tr>
							<td><a href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-investor-report', array( 'investor' => $id ) ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a>
								<?php if ( 'active' !== bhela_bm_investor_status( $id ) ) : ?>
									<?php echo bhela_bm_status_pill( bhela_bm_investor_status( $id ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php endif; ?></td>
							<td class="bha-num"><?php echo esc_html( bhela_bm_investor_shares( $id ) ); ?></td>
							<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['investment'] ) ); ?></td>
							<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['declared'] ) ); ?></td>
							<td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['received'] ) ); ?></td>
							<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $r['outstanding'] ) ); ?></strong></td>
							<td class="bha-num"><?php echo esc_html( $r['roi'] ); ?>%</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				</div>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/** Ledger writes from the report screen. */
function bhela_bm_investor_admin_post() {
	if ( ! is_admin() || ! current_user_can( 'bhela_investor_pay' ) ) {
		return;
	}
	if ( isset( $_POST['bhela_ledger_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_ledger_nonce'] ) ), 'bhela_bm_ledger' ) ) {
		bhela_bm_ledger_add( array(
			'investor' => (int) ( $_POST['investor'] ?? 0 ),
			'type'     => sanitize_key( $_POST['type'] ?? '' ),
			'amount'   => (int) ( $_POST['amount'] ?? 0 ),
			'date'     => sanitize_text_field( $_POST['date'] ?? '' ),
			'method'   => sanitize_text_field( $_POST['method'] ?? '' ),
			'note'     => sanitize_text_field( $_POST['note'] ?? '' ),
		) );
	}
	if ( isset( $_POST['bhela_rev_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_rev_nonce'] ) ), 'bhela_bm_ledger_rev' ) ) {
		bhela_bm_ledger_reverse( (int) ( $_POST['row'] ?? 0 ), sanitize_text_field( $_POST['reason'] ?? '' ) );
	}
}
add_action( 'admin_init', 'bhela_bm_investor_admin_post' );

/* =========================================================
 * Funds and cash flow
 * ========================================================= */

function bhela_bm_funds_menu() {
	add_submenu_page(
		bhela_bm_menu_parent( 'investors' ),
		__( 'Funds', 'bhela-booking' ),
		__( '🏦 Funds', 'bhela-booking' ),
		'bhela_investors_view',
		'bhela-bm-funds',
		'bhela_bm_funds_page'
	);
	add_submenu_page(
		bhela_bm_menu_parent( 'investors' ),
		__( 'Cash Flow', 'bhela-booking' ),
		__( '💵 Cash Flow', 'bhela-booking' ),
		'bhela_view_statement',
		'bhela-bm-cashflow',
		'bhela_bm_cashflow_page'
	);
}
add_action( 'admin_menu', 'bhela_bm_funds_menu' );

/** Reserve and management: what came in, what was spent, what is left. */
function bhela_bm_funds_page() {
	if ( ! current_user_can( 'bhela_investors_view' ) ) {
		return;
	}
	$funds = bhela_bm_funds();
	$fund  = sanitize_key( $_GET['fund'] ?? 'reserve' );
	if ( ! isset( $funds[ $fund ] ) ) {
		$fund = 'reserve';
	}
	$range = bhela_bm_b2b_range( $_GET['from'] ?? '', $_GET['to'] ?? '' );
	$led   = bhela_bm_fund_ledger( $fund, $range['all'] ? '' : $range['from'], $range['all'] ? '' : $range['to'] );
	$def   = $funds[ $fund ];
	?>
	<div class="wrap bha-page">
		<?php bhela_bm_screen_header( '🏦', $def['label'], $def['blurb'] ); ?>

		<div class="bha-bar">
			<?php // No hidden post_type: this hangs off admin.php (§13.14). ?>
			<form method="get">
				<input type="hidden" name="page" value="bhela-bm-funds">
				<div class="bha-field">
					<label for="fnd-fund"><?php esc_html_e( 'Fund', 'bhela-booking' ); ?></label>
					<select id="fnd-fund" name="fund">
						<?php foreach ( $funds as $k => $f ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $fund, $k ); ?>><?php echo esc_html( $f['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="bha-field"><label for="fnd-from"><?php esc_html_e( 'From', 'bhela-booking' ); ?></label>
					<input type="date" id="fnd-from" name="from" value="<?php echo esc_attr( $range['all'] ? '' : $range['from'] ); ?>"></div>
				<div class="bha-field"><label for="fnd-to"><?php esc_html_e( 'To', 'bhela-booking' ); ?></label>
					<input type="date" id="fnd-to" name="to" value="<?php echo esc_attr( $range['all'] ? '' : $range['to'] ); ?>"></div>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'View', 'bhela-booking' ); ?></button>
			</form>
		</div>

		<div class="bha-cards">
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Opening', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $led['opening'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Allocated in', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $led['allocated'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Spent', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $led['used'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Closing', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $led['closing'] ) ); ?></span></div>
		</div>

		<?php if ( $led['closing'] < 0 ) : ?>
			<p class="bha-callout bha-callout--attention"><strong><?php esc_html_e( 'This fund is overdrawn.', 'bhela-booking' ); ?></strong>
				<?php esc_html_e( 'More has been spent against it than was ever allocated. That is recorded rather than blocked — the spending happened — but it means the money came from somewhere else.', 'bhela-booking' ); ?></p>
		<?php endif; ?>

		<?php if ( current_user_can( 'bhela_investor_pay' ) ) : ?>
			<div class="bha-panel">
				<h2><?php esc_html_e( 'Record spending', 'bhela-booking' ); ?></h2>
				<form method="post">
					<?php wp_nonce_field( 'bhela_bm_fund', 'bhela_fund_nonce' ); ?>
					<input type="hidden" name="fund" value="<?php echo esc_attr( $fund ); ?>">
					<div class="bha-bar">
						<div class="bha-field"><label><?php esc_html_e( 'Head', 'bhela-booking' ); ?></label>
							<select name="head">
								<?php foreach ( $def['heads'] as $hk => $hl ) : ?>
									<option value="<?php echo esc_attr( $hk ); ?>"><?php echo esc_html( $hl ); ?></option>
								<?php endforeach; ?>
							</select></div>
						<div class="bha-field"><label><?php esc_html_e( 'Amount ৳', 'bhela-booking' ); ?></label>
							<input type="number" min="1" name="amount" required></div>
						<div class="bha-field"><label><?php esc_html_e( 'Date', 'bhela-booking' ); ?></label>
							<input type="date" name="date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></div>
						<div class="bha-field"><label><?php esc_html_e( 'Note', 'bhela-booking' ); ?></label>
							<input type="text" name="note"></div>
						<div class="bha-field"><label><?php esc_html_e( 'Bill / receipt URL', 'bhela-booking' ); ?></label>
							<input type="url" name="doc" placeholder="<?php esc_attr_e( 'Media Library URL', 'bhela-booking' ); ?>"></div>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Record', 'bhela-booking' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Allocations are never entered here — they are written by the monthly distribution, because the reserve exists only because a percentage was taken off a month.', 'bhela-booking' ); ?></p>
				</form>
			</div>
		<?php endif; ?>

		<?php if ( $led['by_head'] ) : ?>
			<div class="bha-panel">
				<h2><?php esc_html_e( 'Spending by head', 'bhela-booking' ); ?></h2>
				<table class="widefat striped" style="max-width:520px">
					<tbody>
					<?php foreach ( $led['by_head'] as $hk => $amt ) : ?>
						<tr>
							<td><?php echo esc_html( $def['heads'][ $hk ] ?? $hk ); ?></td>
							<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $amt ) ); ?></strong></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<div class="bha-panel">
			<h2><?php esc_html_e( 'Movements', 'bhela-booking' ); ?></h2>
			<div class="bha-scroll">
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Date', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Type', 'bhela-booking' ); ?></th>
					<th><?php esc_html_e( 'Head / note', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Amount', 'bhela-booking' ); ?></th>
					<th class="bha-num"><?php esc_html_e( 'Balance', 'bhela-booking' ); ?></th>
					<th class="bha-noprint"></th>
				</tr></thead>
				<tbody>
				<?php if ( ! $led['rows'] ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'Nothing yet. The fund fills when a month is distributed.', 'bhela-booking' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $led['rows'] as $r ) : ?>
					<?php $undone = bhela_bm_fund_reversal_of( $r['id'] ); ?>
					<tr<?php echo $undone ? ' style="opacity:.55"' : ''; ?>>
						<td><?php echo esc_html( mysql2date( 'j M Y', $r['date'] ) ); ?></td>
						<td><?php
							$labels = array(
								'allocation'  => __( 'Allocation', 'bhela-booking' ),
								'utilisation' => __( 'Spending', 'bhela-booking' ),
								'adjustment'  => __( 'Adjustment', 'bhela-booking' ),
							);
							echo esc_html( $labels[ $r['type'] ] ?? $r['type'] );
						?></td>
						<td><?php echo esc_html( $r['head'] ? ( $def['heads'][ $r['head'] ] ?? $r['head'] ) : '' ); ?>
							<?php if ( $r['note'] ) : ?><span class="bha-sub"><?php echo esc_html( $r['note'] ); ?></span><?php endif; ?>
							<?php if ( $r['doc'] ) : ?> <a href="<?php echo esc_url( $r['doc'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'bill', 'bhela-booking' ); ?></a><?php endif; ?></td>
						<td class="bha-num"><?php echo esc_html( ( $r['signed'] > 0 ? '+' : '' ) . bhela_bm_money( $r['signed'] ) ); ?></td>
						<td class="bha-num"><strong><?php echo esc_html( bhela_bm_money( $r['balance'] ) ); ?></strong></td>
						<td class="bha-noprint">
							<?php if ( 'utilisation' === $r['type'] && ! $undone && current_user_can( 'bhela_investor_pay' ) ) : ?>
								<form method="post">
									<?php wp_nonce_field( 'bhela_bm_fund_rev', 'bhela_fund_rev_nonce' ); ?>
									<input type="hidden" name="row" value="<?php echo esc_attr( $r['id'] ); ?>">
									<input type="text" name="reason" required placeholder="<?php esc_attr_e( 'reason', 'bhela-booking' ); ?>" style="width:120px">
									<button class="button button-small"><?php esc_html_e( 'Reverse', 'bhela-booking' ); ?></button>
								</form>
							<?php elseif ( $undone ) : ?>
								<?php echo bhela_bm_status_pill( __( 'reversed', 'bhela-booking' ), 'danger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</div>
	</div>
	<?php
}

/** Cash flow over a range. */
function bhela_bm_cashflow_page() {
	if ( ! current_user_can( 'bhela_view_statement' ) ) {
		return;
	}
	$from = bhela_bm_report_date( $_GET['from'] ?? '' );
	$to   = bhela_bm_report_date( $_GET['to'] ?? '' );
	if ( '' === $from ) {
		$from = gmdate( 'Y-m-01', strtotime( current_time( 'Y-m-d' ) ) );
	}
	if ( '' === $to || $to < $from ) {
		$to = gmdate( 'Y-m-t', strtotime( $from ) );
	}
	$cf = bhela_bm_cashflow( $from, $to );

	$csv = wp_nonce_url(
		add_query_arg( array( 'action' => 'bhela_bm_cashflow_csv', 'from' => $from, 'to' => $to ), admin_url( 'admin-post.php' ) ),
		'bhela_bm_cashflow_csv'
	);
	?>
	<div class="wrap bha-page">
		<?php
		bhela_bm_screen_header(
			'💵',
			__( 'Cash Flow', 'bhela-booking' ),
			__( 'Where the money actually went. Not the Monthly Statement in another hat — that answers whether trading was profitable, this answers whether cash moved, and a business can be profitable and short of cash at the same time.', 'bhela-booking' ),
			'<button type="button" class="button" onclick="window.print()">🖨️ ' . esc_html__( 'Print', 'bhela-booking' ) . '</button>'
			. ' <a class="button" href="' . esc_url( $csv ) . '">📊 ' . esc_html__( 'Download CSV', 'bhela-booking' ) . '</a>'
		);
		?>
		<div class="bha-bar">
			<form method="get">
				<input type="hidden" name="page" value="bhela-bm-cashflow">
				<div class="bha-field"><label for="cf-from"><?php esc_html_e( 'From', 'bhela-booking' ); ?></label>
					<input type="date" id="cf-from" name="from" value="<?php echo esc_attr( $from ); ?>"></div>
				<div class="bha-field"><label for="cf-to"><?php esc_html_e( 'To', 'bhela-booking' ); ?></label>
					<input type="date" id="cf-to" name="to" value="<?php echo esc_attr( $to ); ?>"></div>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'View', 'bhela-booking' ); ?></button>
			</form>
		</div>

		<div class="bha-cards">
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Cash in', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $cf['in_total'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Cash out', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $cf['out_total'] ) ); ?></span></div>
			<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Net movement', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $cf['net'] ) ); ?></span></div>
		</div>

		<div class="bha-panel">
			<table class="widefat striped" style="max-width:640px">
				<thead><tr><th><?php esc_html_e( 'Cash in', 'bhela-booking' ); ?></th><th class="bha-num">৳</th></tr></thead>
				<tbody>
				<?php foreach ( $cf['in'] as $r ) : ?>
					<tr><td><?php echo esc_html( $r['label'] ); ?></td><td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['amount'] ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot><tr class="bha-row--total"><td><?php esc_html_e( 'Total in', 'bhela-booking' ); ?></td><td class="bha-num"><?php echo esc_html( bhela_bm_money( $cf['in_total'] ) ); ?></td></tr></tfoot>
			</table>

			<table class="widefat striped" style="max-width:640px;margin-top:1.4rem">
				<thead><tr><th><?php esc_html_e( 'Cash out', 'bhela-booking' ); ?></th><th class="bha-num">৳</th></tr></thead>
				<tbody>
				<?php foreach ( $cf['out'] as $r ) : ?>
					<tr><td><?php echo esc_html( $r['label'] ); ?></td><td class="bha-num"><?php echo esc_html( bhela_bm_money( $r['amount'] ) ); ?></td></tr>
				<?php endforeach; ?>
				</tbody>
				<tfoot><tr class="bha-row--total"><td><?php esc_html_e( 'Total out', 'bhela-booking' ); ?></td><td class="bha-num"><?php echo esc_html( bhela_bm_money( $cf['out_total'] ) ); ?></td></tr></tfoot>
			</table>
			<p class="bha-callout"><?php esc_html_e( 'A fund allocation is not cash out — it is an internal earmark. Counting it would double up against the trip costs and salaries it eventually pays for.', 'bhela-booking' ); ?></p>
		</div>
	</div>
	<?php
}

/** CSV of the cash flow. */
function bhela_bm_cashflow_csv() {
	if ( ! current_user_can( 'bhela_view_statement' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'bhela-booking' ), 403 );
	}
	check_admin_referer( 'bhela_bm_cashflow_csv' );
	$from = bhela_bm_report_date( $_GET['from'] ?? '' );
	$to   = bhela_bm_report_date( $_GET['to'] ?? '' );
	$cf   = bhela_bm_cashflow( $from, $to );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="bhela-cashflow-' . $from . '_to_' . $to . '.csv"' );
	$fh = fopen( 'php://output', 'w' );
	fwrite( $fh, "\xEF\xBB\xBF" );
	fputcsv( $fh, array( 'Direction', 'Line', 'Amount' ) );
	foreach ( $cf['in'] as $r ) {
		// Every free-text cell through bhela_bm_csv_cell(): a spreadsheet executes a
		// cell that opens with '='.
		fputcsv( $fh, array( 'In', bhela_bm_csv_cell( $r['label'] ), $r['amount'] ) );
	}
	fputcsv( $fh, array( 'In', 'TOTAL', $cf['in_total'] ) );
	foreach ( $cf['out'] as $r ) {
		fputcsv( $fh, array( 'Out', bhela_bm_csv_cell( $r['label'] ), $r['amount'] ) );
	}
	fputcsv( $fh, array( 'Out', 'TOTAL', $cf['out_total'] ) );
	fputcsv( $fh, array() );
	fputcsv( $fh, array( '', 'NET', $cf['net'] ) );
	fclose( $fh );
	exit;
}
add_action( 'admin_post_bhela_bm_cashflow_csv', 'bhela_bm_cashflow_csv' );

/** Fund writes from the funds screen. */
function bhela_bm_funds_admin_post() {
	if ( ! is_admin() || ! current_user_can( 'bhela_investor_pay' ) ) {
		return;
	}
	if ( isset( $_POST['bhela_fund_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_fund_nonce'] ) ), 'bhela_bm_fund' ) ) {
		bhela_bm_fund_add( array(
			'fund'   => sanitize_key( $_POST['fund'] ?? '' ),
			'type'   => 'utilisation',
			'amount' => (int) ( $_POST['amount'] ?? 0 ),
			'head'   => sanitize_key( $_POST['head'] ?? '' ),
			'date'   => sanitize_text_field( $_POST['date'] ?? '' ),
			'note'   => sanitize_text_field( $_POST['note'] ?? '' ),
			'doc'    => esc_url_raw( $_POST['doc'] ?? '' ),
		) );
	}
	if ( isset( $_POST['bhela_fund_rev_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_fund_rev_nonce'] ) ), 'bhela_bm_fund_rev' ) ) {
		bhela_bm_fund_reverse( (int) ( $_POST['row'] ?? 0 ), sanitize_text_field( $_POST['reason'] ?? '' ) );
	}
}
add_action( 'admin_init', 'bhela_bm_funds_admin_post' );
