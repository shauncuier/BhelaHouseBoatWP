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

	// The capital side, under a rule of its own so nobody reads it as more cash.
	$h = bhela_bm_investor_holding( $post->ID );
	echo '<hr style="margin:.8rem 0">';
	$row( __( 'Current share value', 'bhela-booking' ), bhela_bm_money( $h['share_value'] ) );
	$row( __( 'Holding value', 'bhela-booking' ), bhela_bm_money( $h['holding'] ) );
	$row(
		__( 'Capital appreciation', 'bhela-booking' ),
		( $h['appreciation'] >= 0 ? '+' : '' ) . bhela_bm_money( $h['appreciation'] )
	);
	printf(
		'<p class="bha-note">%s</p>',
		esc_html(
			$h['valued']
				? sprintf(
					/* translators: %s: valuation date */
					__( 'Valued as at %s. Unrealised — not added to anything above.', 'bhela-booking' ),
					mysql2date( 'j M Y', $h['as_at'] )
				)
				: __( 'No approved valuation — this is the original issue price.', 'bhela-booking' )
		)
	);

	printf( '<p><a class="button" href="%s">%s</a></p>',
		esc_url( bhela_bm_admin_url( 'bhela-bm-investor-report', array( 'investor' => $post->ID ) ) ),
		esc_html__( 'Open ledger', 'bhela-booking' ) );
}

/**
 * Fields whose VALUE must never reach the audit trail.
 *
 * A bank account number and an NID are exactly what an audit trail is protecting. A
 * log that records the old and the new value in full becomes a second copy of the
 * data — one that is deliberately never deleted, readable by anyone who can open the
 * Audit Trail, and outside the investor record's own access control.
 *
 * So for these the trail records THAT the field changed, by whom and when. The values
 * themselves live on the record, where the permissions are.
 */
function bhela_bm_investor_secret_fields() {
	return array( 'nid', 'bank_account', 'bank_account_name', 'bank_routing', 'nominee_nid' );
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

	// Everything is collected first and written second, so one pass can compare the
	// old value with the new. Auditing only the shareholding — which is all this did —
	// meant an investor's bank account could be changed with no record at all, and
	// changing where a payment lands is the highest-value tamper on this module.
	$writes = array();

	$writes['shares'] = max( 0, (int) ( $_POST['inv_shares'] ?? 0 ) );
	$writes['amount'] = max( 0, (int) ( $_POST['inv_amount'] ?? 0 ) );
	$writes['date']   = bhela_bm_report_date( $_POST['inv_date'] ?? '' );
	$status           = sanitize_key( $_POST['inv_status'] ?? 'active' );
	$writes['status'] = in_array( $status, array( 'active', 'suspended', 'exited' ), true ) ? $status : 'active';

	foreach ( bhela_bm_investor_fields() as $group ) {
		foreach ( $group['fields'] as $key => $def ) {
			if ( ! isset( $_POST[ 'inv_' . $key ] ) ) {
				continue;
			}
			$rawv = wp_unslash( $_POST[ 'inv_' . $key ] );
			$type = isset( $def['type'] ) ? $def['type'] : 'text';
			// A signature is a URL, an address keeps its line breaks, a date is only
			// a date. sanitize_text_field() on all of them would silently flatten the
			// addresses and let a junk URL through.
			if ( 'file' === $type ) {
				$writes[ $key ] = esc_url_raw( $rawv );
			} elseif ( 'textarea' === $type ) {
				$writes[ $key ] = sanitize_textarea_field( $rawv );
			} elseif ( 'email' === $type ) {
				$writes[ $key ] = sanitize_email( $rawv );
			} elseif ( 'date' === $type ) {
				$writes[ $key ] = bhela_bm_report_date( $rawv );
			} else {
				$writes[ $key ] = sanitize_text_field( $rawv );
			}
		}
	}

	$secret = bhela_bm_investor_secret_fields();
	$ref    = get_the_title( $post_id );

	foreach ( $writes as $key => $new ) {
		$old = get_post_meta( $post_id, '_bhela_inv_' . $key, true );
		if ( (string) $old === (string) $new ) {
			continue;
		}
		update_post_meta( $post_id, '_bhela_inv_' . $key, $new );

		$hide = in_array( $key, $secret, true );
		bhela_bm_audit( array(
			'channel'     => 'investor',
			'action'      => 'shares' === $key ? 'shares' : 'profile',
			'object_type' => 'investor',
			'object_id'   => $post_id,
			'object_ref'  => $ref,
			'field'       => $key,
			// Sensitive fields record the fact, never the figures.
			'old_value'   => $hide ? '' : (string) $old,
			'new_value'   => $hide ? '' : (string) $new,
			'reason'      => $hide
				? __( 'Value not recorded — this field holds bank or identity details.', 'bhela-booking' )
				: '',
		) );
	}

	bhela_bm_investor_save_login( $post_id );
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
			<p>
				<a class="button" href="<?php echo esc_url( bhela_bm_admin_url( 'bhela-bm-investor-report' ) ); ?>">← <?php esc_html_e( 'All investors', 'bhela-booking' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bhela_bm_ledger_csv&investor=' . (int) $one ), 'bhela_bm_ledger_csv' ) ); ?>"><?php esc_html_e( 'Download CSV', 'bhela-booking' ); ?></a>
			</p>
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

			<?php $h = bhela_bm_investor_holding( $one ); ?>
			<h3 class="bha-sheet__h"><?php esc_html_e( 'Capital value', 'bhela-booking' ); ?></h3>
			<div class="bha-cards">
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Current share value', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $h['share_value'] ) ); ?></span></div>
				<div class="bha-card"><span class="bha-card__label"><?php esc_html_e( 'Holding value', 'bhela-booking' ); ?></span><span class="bha-card__value"><?php echo esc_html( bhela_bm_money( $h['holding'] ) ); ?></span></div>
				<div class="bha-card">
					<span class="bha-card__label"><?php esc_html_e( 'Capital appreciation', 'bhela-booking' ); ?></span>
					<span class="bha-card__value <?php echo $h['appreciation'] < 0 ? 'is-danger' : 'is-good'; ?>"><?php echo esc_html( bhela_bm_money( $h['appreciation'] ) ); ?></span>
					<p class="bha-note"><?php echo esc_html( ( $h['appr_pct'] >= 0 ? '+' : '' ) . $h['appr_pct'] ); ?>%</p>
				</div>
			</div>
			<p class="bha-note">
				<?php
				echo $h['valued']
					? esc_html( sprintf(
						/* translators: 1: date */
						__( 'Based on the valuation approved as at %1$s. This is what the shares are worth, not money received — the figures above it are the cash side, and the two are never added together.', 'bhela-booking' ),
						mysql2date( 'j M Y', $h['as_at'] )
					) )
					: esc_html__( 'No valuation has been approved, so this uses the original issue price. Record one under Valuation to see what the holding is worth today.', 'bhela-booking' );
				?>
			</p>

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
							<div class="bha-field"><label><?php esc_html_e( 'Reference', 'bhela-booking' ); ?></label>
								<input type="text" name="reference" placeholder="<?php esc_attr_e( 'cheque / trx id', 'bhela-booking' ); ?>"></div>
							<div class="bha-field"><label><?php esc_html_e( 'Document URL', 'bhela-booking' ); ?></label>
								<input type="url" name="doc" placeholder="https://"></div>
							<div class="bha-field"><label><?php esc_html_e( 'Note', 'bhela-booking' ); ?></label>
								<input type="text" name="note"></div>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Submit', 'bhela-booking' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'A payment or an advance is raised as a request and moves no money until somebody else approves it — the investor’s outstanding does not change while it is waiting. An adjustment is a correction and is recorded straight away. Profit is never entered by hand: it comes from a distribution run, so the ledger always traces back to an approved month.', 'bhela-booking' ); ?></p>
					</form>
				</div>
			<?php endif; ?>

			<?php
			$pr_rows = bhela_bm_payreqs( '', $one );
			$pr_can  = current_user_can( 'bhela_investor_approve' );
			?>
			<?php if ( $pr_rows ) : ?>
				<div class="bha-panel">
					<h2><?php esc_html_e( 'Payment requests', 'bhela-booking' ); ?></h2>
					<div class="bha-scroll">
					<table class="widefat striped">
						<thead><tr>
							<th><?php esc_html_e( 'Date', 'bhela-booking' ); ?></th>
							<th><?php esc_html_e( 'Type', 'bhela-booking' ); ?></th>
							<th><?php esc_html_e( 'Reference', 'bhela-booking' ); ?></th>
							<th><?php esc_html_e( 'Raised by', 'bhela-booking' ); ?></th>
							<th class="bha-num"><?php esc_html_e( 'Amount', 'bhela-booking' ); ?></th>
							<th><?php esc_html_e( 'State', 'bhela-booking' ); ?></th>
							<th class="bha-noprint"></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $pr_rows as $pr ) : ?>
							<?php
							$st   = bhela_bm_payreq_states();
							$stn  = $st[ $pr['state'] ] ?? $st[''];
							$who  = get_userdata( $pr['by'] );
							$mine = get_current_user_id() === $pr['by'];
							?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'j M Y', $pr['date'] ) ); ?></td>
								<td><?php echo esc_html( $pr['type'] ); ?></td>
								<td>
									<?php echo esc_html( $pr['reference'] ); ?>
									<?php if ( $pr['doc'] ) : ?>
										<a href="<?php echo esc_url( $pr['doc'] ); ?>" target="_blank" rel="noopener">📎</a>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $who ? $who->display_name : '—' ); ?></td>
								<td class="bha-num"><?php echo esc_html( bhela_bm_money( $pr['amount'] ) ); ?></td>
								<td><?php echo bhela_bm_status_pill( $stn['label'], $stn['tone'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td class="bha-noprint">
									<?php if ( 'requested' !== $pr['state'] ) : ?>
										<span style="opacity:.6"><?php echo esc_html( $pr['reason'] ); ?></span>
									<?php elseif ( ! $pr_can ) : ?>
										<span style="opacity:.6"><?php esc_html_e( 'waiting on an approver', 'bhela-booking' ); ?></span>
									<?php elseif ( $mine ) : ?>
										<?php /* The whole point of the second signature. */ ?>
										<span style="opacity:.6"><?php esc_html_e( 'you raised this — somebody else must approve it', 'bhela-booking' ); ?></span>
									<?php else : ?>
										<form method="post" style="display:flex;gap:4px;align-items:center">
											<?php wp_nonce_field( 'bhela_bm_payreq', 'bhela_pr_nonce' ); ?>
											<input type="hidden" name="request" value="<?php echo esc_attr( $pr['id'] ); ?>">
											<button class="button button-primary button-small"><?php esc_html_e( 'Approve & pay', 'bhela-booking' ); ?></button>
											<input type="text" name="pr_reason" placeholder="<?php esc_attr_e( 'reason', 'bhela-booking' ); ?>" style="width:110px">
											<button class="button button-small" name="pr_reject" value="1"><?php esc_html_e( 'Reject', 'bhela-booking' ); ?></button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					</div>
					<p class="description"><?php esc_html_e( 'A request appears in the ledger only once it is approved, so nothing here counts toward the figures above until somebody releases it.', 'bhela-booking' ); ?></p>
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
						<th class="bha-num"><?php esc_html_e( 'Holding value', 'bhela-booking' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( bhela_bm_investors() as $id ) : ?>
						<?php $r = bhela_bm_investor_roi( $id ); ?>
						<?php $rh = bhela_bm_investor_holding( $id ); ?>
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
							<td class="bha-num"><?php echo esc_html( bhela_bm_money( $rh['holding'] ) ); ?></td>
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
/**
 * Carry a refusal to the next page load.
 *
 * Every guard in this module returned a WP_Error that the handler then dropped on the
 * floor. That was survivable while the only refusals were malformed input somebody
 * could see was malformed — but a payment request against an exited investor, or an
 * approval that lost a race, would reload the page with nothing created and nothing
 * said. A correct policy that looks like a broken form is worse than no policy: the
 * operator retries, and then rings somebody.
 *
 * A transient keyed to the user, because this runs on admin_init and the screen is
 * rendered after a redirect-free POST — there is no request-scoped place to put it
 * that the next page load can still see.
 *
 * @param mixed $result Whatever a writer returned. Non-WP_Error values are ignored.
 */
function bhela_bm_investor_notice( $result ) {
	if ( ! is_wp_error( $result ) ) {
		return;
	}
	set_transient(
		'bhela_bm_inv_notice_' . get_current_user_id(),
		$result->get_error_message(),
		60
	);
}

/** Print and clear anything bhela_bm_investor_notice() left. */
function bhela_bm_investor_print_notice() {
	$key = 'bhela_bm_inv_notice_' . get_current_user_id();
	$msg = get_transient( $key );
	if ( ! $msg ) {
		return;
	}
	delete_transient( $key );
	printf(
		'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
		esc_html( $msg )
	);
}
add_action( 'all_admin_notices', 'bhela_bm_investor_print_notice' );

function bhela_bm_investor_admin_post() {
	if ( ! is_admin() || ! current_user_can( 'bhela_investor_pay' ) ) {
		return;
	}
	if ( isset( $_POST['bhela_ledger_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_ledger_nonce'] ) ), 'bhela_bm_ledger' ) ) {
		$pr_type = sanitize_key( $_POST['type'] ?? '' );
		$pr_args = array(
			'investor'  => (int) ( $_POST['investor'] ?? 0 ),
			'type'      => $pr_type,
			'amount'    => (int) ( $_POST['amount'] ?? 0 ),
			'date'      => sanitize_text_field( $_POST['date'] ?? '' ),
			'method'    => sanitize_text_field( $_POST['method'] ?? '' ),
			'reference' => sanitize_text_field( $_POST['reference'] ?? '' ),
			'doc'       => esc_url_raw( $_POST['doc'] ?? '' ),
			'note'      => sanitize_text_field( $_POST['note'] ?? '' ),
		);
		// Money OUT goes through approval; a correction does not. An adjustment is a
		// signed fix that already leaves its own trail and reverses cleanly, whereas a
		// payment is somebody deciding to hand over cash.
		$pr_result = in_array( $pr_type, array( 'payment', 'advance' ), true )
			? bhela_bm_payreq_add( $pr_args )
			: bhela_bm_ledger_add( $pr_args );
		bhela_bm_investor_notice( $pr_result );
	}
	if ( isset( $_POST['bhela_pr_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bhela_pr_nonce'] ) ), 'bhela_bm_payreq' ) ) {
		$pr_id = (int) ( $_POST['request'] ?? 0 );
		bhela_bm_investor_notice(
			! empty( $_POST['pr_reject'] )
				? bhela_bm_payreq_reject( $pr_id, sanitize_text_field( $_POST['pr_reason'] ?? '' ) )
				: bhela_bm_payreq_approve( $pr_id )
		);
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
		<?php
		bhela_bm_screen_header(
			'🏦',
			$def['label'],
			$def['blurb'],
			sprintf(
				'<a class="button" href="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=bhela_bm_fund_csv&fund=' . rawurlencode( $fund ) ), 'bhela_bm_fund_csv' ) ),
				esc_html__( 'Download CSV', 'bhela-booking' )
			)
		);
		?>

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

/* =========================================================
 * The investor list table
 * ========================================================= */

/**
 * Columns worth the width.
 *
 * The list shipped with Title and Date and nothing else, which told you an investor
 * exists and when the record was typed — neither of which anybody opens this screen
 * to find out. The questions it should answer at a glance are how much someone holds,
 * what they are owed, and whether they can actually sign in.
 */
function bhela_bm_investor_columns( $columns ) {
	return array(
		'cb'          => $columns['cb'] ?? '',
		'title'       => __( 'Investor', 'bhela-booking' ),
		'inv_code'    => __( 'ID', 'bhela-booking' ),
		'inv_shares'  => __( 'Shares', 'bhela-booking' ),
		'inv_amount'  => __( 'Invested', 'bhela-booking' ),
		'inv_profit'  => __( 'Declared', 'bhela-booking' ),
		'inv_paid'    => __( 'Received', 'bhela-booking' ),
		'inv_due'     => __( 'Outstanding', 'bhela-booking' ),
		'inv_roi'     => __( 'ROI', 'bhela-booking' ),
			'inv_value'   => __( 'Holding value', 'bhela-booking' ),
		'inv_login'   => __( 'Portal', 'bhela-booking' ),
		'inv_status'  => __( 'Status', 'bhela-booking' ),
	);
}
add_filter( 'manage_bhela_investor_posts_columns', 'bhela_bm_investor_columns' );

function bhela_bm_investor_column_content( $column, $post_id ) {
	// bhela_bm_investor_roi() replays the whole ledger, and every money column needs
	// the same answer. Once per row, cached, rather than five times.
	static $cache = array();
	if ( ! isset( $cache[ $post_id ] ) ) {
		$cache[ $post_id ] = bhela_bm_investor_roi( $post_id );
	}
	$r = $cache[ $post_id ];

	switch ( $column ) {
		case 'inv_code':
			$code = (string) get_post_meta( $post_id, '_bhela_inv_code', true );
			echo $code ? esc_html( $code ) : '<span style="opacity:.4">—</span>';
			break;

		case 'inv_shares':
			$held = bhela_bm_investor_shares( $post_id );
			if ( $held <= 0 ) {
				// Zero shares means this record takes no part in any distribution.
				// That is almost always an unfinished entry, so it says so.
				echo bhela_bm_status_pill( __( 'no shares', 'bhela-booking' ), 'attention' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			}
			printf(
				'<strong>%d</strong><br><span style="opacity:.6;font-size:11px">%s%%</span>',
				(int) $held,
				esc_html( (string) bhela_bm_investor_share_pct( $post_id ) )
			);
			break;

		case 'inv_amount':
			echo esc_html( bhela_bm_money( $r['investment'] ) );
			break;

		case 'inv_profit':
			echo esc_html( bhela_bm_money( $r['declared'] ) );
			break;

		case 'inv_paid':
			echo esc_html( bhela_bm_money( $r['received'] ) );
			break;

		case 'inv_due':
			// The one figure somebody is chasing. Emphasised when there is any.
			echo $r['outstanding'] > 0
				? '<strong>' . esc_html( bhela_bm_money( $r['outstanding'] ) ) . '</strong>'
				: '<span style="opacity:.5">' . esc_html( bhela_bm_money( 0 ) ) . '</span>';
			break;

		case 'inv_roi':
			printf(
				'<strong>%s%%</strong><br><span style="opacity:.6;font-size:11px">%s</span>',
				esc_html( (string) $r['roi'] ),
				/* translators: %s: ROI on profit declared but not yet paid */
				esc_html( sprintf( __( '%s%% declared', 'bhela-booking' ), $r['roi_declared'] ) )
			);
			break;

		case 'inv_value':
			// Cost basis and current value, one above the other, because either alone
			// invites the wrong conclusion.
			$h = bhela_bm_investor_holding( $post_id );
			printf(
				'<strong>%s</strong><br><span style="opacity:.6;font-size:11px">%s%s</span>',
				esc_html( bhela_bm_money( $h['holding'] ) ),
				$h['appreciation'] >= 0 ? '+' : '',
				esc_html( bhela_bm_money( $h['appreciation'] ) )
			);
			if ( ! $h['valued'] ) {
				echo '<br><span style="opacity:.5;font-size:11px">' . esc_html__( 'issue price', 'bhela-booking' ) . '</span>';
			}
			break;

		case 'inv_login':
			$uid  = bhela_bm_investor_user( $post_id );
			$user = $uid ? get_userdata( $uid ) : null;
			if ( ! $user ) {
				echo bhela_bm_status_pill( __( 'no login', 'bhela-booking' ), 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			}
			// A linked account that is not an investor role has wider access than the
			// portal, which is worth seeing from the list rather than one record at a
			// time.
			$ok = in_array( 'bhela_investor', (array) $user->roles, true );
			echo bhela_bm_status_pill( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$ok ? __( 'linked', 'bhela-booking' ) : __( 'wider access', 'bhela-booking' ),
				$ok ? 'good' : 'attention'
			);
			printf( '<br><span style="opacity:.6;font-size:11px">%s</span>', esc_html( $user->user_email ) );
			break;

		case 'inv_status':
			$s     = bhela_bm_investor_status( $post_id );
			$tones = array( 'active' => 'good', 'suspended' => 'attention', 'exited' => 'neutral' );
			echo bhela_bm_status_pill( $s, $tones[ $s ] ?? 'neutral' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			break;
	}
}
add_action( 'manage_bhela_investor_posts_custom_column', 'bhela_bm_investor_column_content', 10, 2 );

/** Shares and the amount are real numbers, so sort them as numbers. */
function bhela_bm_investor_sortable( $columns ) {
	$columns['inv_shares'] = 'inv_shares';
	$columns['inv_amount'] = 'inv_amount';
	return $columns;
}
add_filter( 'manage_edit-bhela_investor_sortable_columns', 'bhela_bm_investor_sortable' );

/**
 * Sorting, and the default order.
 *
 * Largest holding first by default: the register is read to see who owns what, and
 * alphabetical order buries that. The money columns are NOT sortable — every one of
 * them is replayed from the ledger rather than stored, so there is no meta for the
 * database to sort on, and faking one would be the cached-balance mistake all over
 * again.
 */
function bhela_bm_investor_orderby( $query ) {
	global $pagenow;
	if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query()
		|| 'bhela_investor' !== ( $_GET['post_type'] ?? '' ) ) {
		return;
	}
	$orderby = $query->get( 'orderby' );
	if ( 'inv_shares' === $orderby || ( '' === $orderby && ! isset( $_GET['orderby'] ) ) ) {
		$query->set( 'meta_key', '_bhela_inv_shares' );
		$query->set( 'orderby', 'meta_value_num' );
		if ( '' === $orderby ) {
			$query->set( 'order', 'DESC' );
		}
	} elseif ( 'inv_amount' === $orderby ) {
		$query->set( 'meta_key', '_bhela_inv_amount' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
add_action( 'pre_get_posts', 'bhela_bm_investor_orderby' );

/**
 * A summary line above the list.
 *
 * Whether the register adds up to the configured share total is the first thing to
 * know and the easiest to get wrong, so it is stated here rather than discovered on
 * the Distribution screen when a payout is already being prepared.
 */
function bhela_bm_investor_list_summary() {
	global $typenow;
	if ( 'bhela_investor' !== $typenow || 'edit.php' !== ( $GLOBALS['pagenow'] ?? '' ) ) {
		return;
	}
	$t = bhela_bm_share_totals();
	if ( $t['over'] ) {
		$tone = 'notice-error';
		$msg  = sprintf(
			/* translators: 1: issued, 2: configured */
			__( '%1$d shares are issued against %2$d configured. Distribution is blocked until this is resolved — the percentages already add to more than 100%%.', 'bhela-booking' ),
			$t['issued'],
			$t['configured']
		);
	} elseif ( $t['under'] ) {
		$tone = 'notice-warning';
		$msg  = sprintf(
			/* translators: 1: issued, 2: configured, 3: shortfall */
			__( '%1$d of %2$d shares issued. The remaining %3$d shares take no part in a distribution, so their portion of the investor pool stays with the business.', 'bhela-booking' ),
			$t['issued'],
			$t['configured'],
			abs( $t['gap'] )
		);
	} else {
		$tone = 'notice-success';
		$msg  = sprintf(
			/* translators: 1: issued, 2: investor count */
			__( 'All %1$d shares are issued across %2$d investors. The register balances.', 'bhela-booking' ),
			$t['issued'],
			$t['investors']
		);
	}
	printf( '<div class="notice %s inline" style="margin:12px 0"><p>%s</p></div>', esc_attr( $tone ), esc_html( $msg ) );
}
add_action( 'all_admin_notices', 'bhela_bm_investor_list_summary' );
