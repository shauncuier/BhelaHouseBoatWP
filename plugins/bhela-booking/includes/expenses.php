<?php
/**
 * Business expenses that sit outside a trip — advertising, renovation, one-off
 * purchases.
 *
 * Reproduces the "Digital Marketing & Renovation Report" the owner keeps by
 * hand, and is what the Monthly Statement subtracts from trip profit. Keeping
 * it as records rather than two numbers typed into the statement means the
 * month can be explained afterwards, not just totalled.
 *
 * Types and payment methods are the owner's lists, not the code's — same
 * defaults-plus-override shape as the cost heads.
 *
 * @package BhelaBooking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================
 * EDITABLE LISTS
 * ========================================================= */

/** Expense types as shipped. Slug => label; the slug is what a record stores. */
function bhela_bm_expense_type_defaults() {
	return array(
		'boosting'   => 'Boosting',
		'renovation' => 'Renovation',
		'website'    => 'Website',
		'other'      => 'Other',
	);
}

/** Payment methods as shipped. */
function bhela_bm_expense_method_defaults() {
	return array(
		'bkash' => 'bKash',
		'nagad' => 'Nagad',
		'bank'  => 'Bank',
		'cash'  => 'Cash',
	);
}

/**
 * A stored list merged over its defaults.
 *
 * @param string $option          Option name.
 * @param array  $defaults        Shipped list.
 * @param bool   $include_retired Include entries hidden from new records.
 * @return array slug => label
 */
function bhela_bm_expense_list( $option, $defaults, $include_retired = false ) {
	$saved = get_option( $option, null );
	if ( ! is_array( $saved ) || ! $saved ) {
		return $defaults;
	}
	$out = array();
	foreach ( $saved as $slug => $row ) {
		$slug  = sanitize_key( $slug );
		$label = is_array( $row ) ? (string) ( $row['label'] ?? '' ) : (string) $row;
		if ( '' === $slug || '' === $label ) {
			continue;
		}
		if ( ! $include_retired && ! empty( $row['retired'] ) ) {
			continue;
		}
		$out[ $slug ] = $label;
	}
	return $out ? $out : $defaults;
}

function bhela_bm_expense_types( $include_retired = false ) {
	return bhela_bm_expense_list( 'bhela_bm_expense_types', bhela_bm_expense_type_defaults(), $include_retired );
}

function bhela_bm_expense_methods( $include_retired = false ) {
	return bhela_bm_expense_list( 'bhela_bm_expense_methods', bhela_bm_expense_method_defaults(), $include_retired );
}

/**
 * Save one of the editable lists.
 *
 * Mirrors bhela_bm_save_cost_heads(): a slug is minted once from the first
 * label and frozen, so renaming never orphans the records that used it.
 */
function bhela_bm_save_expense_list( $option, $posted ) {
	if ( ! is_array( $posted ) ) {
		return;
	}
	$out  = array();
	$seen = array();
	foreach ( $posted as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$label = sanitize_text_field( $row['label'] ?? '' );
		if ( '' === $label ) {
			continue;
		}
		$slug = sanitize_key( $row['slug'] ?? '' );
		if ( '' === $slug ) {
			$slug = sanitize_key( sanitize_title( $label ) ) ?: 'item';
		}
		$base = $slug;
		$n    = 2;
		while ( isset( $seen[ $slug ] ) ) {
			$slug = $base . '_' . $n;
			$n++;
		}
		$seen[ $slug ] = true;
		$out[ $slug ]  = array(
			'label'   => $label,
			'retired' => ! empty( $row['retired'] ) ? 1 : 0,
		);
	}
	if ( $out ) {
		update_option( $option, $out );
	}
}

/* =========================================================
 * POST TYPE
 * ========================================================= */

function bhela_bm_register_expense_cpt() {
	register_post_type( 'bhela_expense', array(
		'labels' => array(
			'name'          => __( 'Expenses', 'bhela-booking' ),
			'singular_name' => __( 'Expense', 'bhela-booking' ),
			'menu_name'     => __( '💸 Expenses', 'bhela-booking' ),
			'add_new'       => __( 'Add Expense', 'bhela-booking' ),
			'add_new_item'  => __( 'New Expense', 'bhela-booking' ),
			'edit_item'     => __( 'Expense', 'bhela-booking' ),
			'all_items'     => __( '💸 Expenses', 'bhela-booking' ),
			'not_found'     => __( 'No expenses recorded yet.', 'bhela-booking' ),
		),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		'show_in_menu'        => 'edit.php?post_type=bhela_booking',
		'show_in_rest'        => false,
		'rewrite'             => false,
		'capability_type'     => array( 'bhela_expense', 'bhela_expenses' ),
		'map_meta_cap'        => true,
		'has_archive'         => false,
		'supports'            => array( 'title' ),
	) );
}
add_action( 'init', 'bhela_bm_register_expense_cpt' );

/* =========================================================
 * DATA
 * ========================================================= */

/** One expense, read back in a shape the statement and list can both use. */
function bhela_bm_expense_get( $post_id ) {
	$amount = (int) get_post_meta( $post_id, '_bhela_exp_amount', true );
	return array(
		'id'      => (int) $post_id,
		'date'    => (string) get_post_meta( $post_id, '_bhela_exp_date', true ),
		'type'    => (string) get_post_meta( $post_id, '_bhela_exp_type', true ),
		'amount'  => $amount,
		'method'  => (string) get_post_meta( $post_id, '_bhela_exp_method', true ),
		'paid_on' => (string) get_post_meta( $post_id, '_bhela_exp_paid_on', true ),
		'verify'  => (string) get_post_meta( $post_id, '_bhela_exp_verify', true ),
		'remark'  => (string) get_post_meta( $post_id, '_bhela_exp_remark', true ),
	);
}

/**
 * Expenses in a date range, with a total per type.
 *
 * The per-type breakdown is what lets the Monthly Statement grow a deduction
 * row whenever the owner adds a type — no code change, no hardcoded pair of
 * "marketing" and "renovation" fields.
 *
 * @param string $from Y-m-d inclusive.
 * @param string $to   Y-m-d inclusive.
 * @return array{rows:array,by_type:array,total:int}
 */
function bhela_bm_expense_rows( $from, $to ) {
	$empty = array( 'rows' => array(), 'by_type' => array(), 'total' => 0 );
	if ( ! $from || ! $to ) {
		return $empty;
	}
	$ids = get_posts( array(
		'post_type'      => 'bhela_expense',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_key'       => '_bhela_exp_date',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => '_bhela_exp_date',
				'value'   => array( $from, $to ),
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			),
		),
	) );

	$rows    = array();
	$by_type = array();
	$total   = 0;
	foreach ( $ids as $id ) {
		$row     = bhela_bm_expense_get( $id );
		$rows[]  = $row;
		$total  += $row['amount'];
		$key     = $row['type'] ?: 'other';
		$by_type[ $key ] = ( $by_type[ $key ] ?? 0 ) + $row['amount'];
	}
	return array( 'rows' => $rows, 'by_type' => $by_type, 'total' => $total );
}

/* =========================================================
 * EDIT SCREEN
 * ========================================================= */

function bhela_bm_expense_meta_box() {
	add_meta_box( 'bhela_expense_details', __( 'Expense', 'bhela-booking' ), 'bhela_bm_expense_meta_cb', 'bhela_expense', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'bhela_bm_expense_meta_box' );

function bhela_bm_expense_meta_cb( $post ) {
	wp_nonce_field( 'bhela_bm_expense_save', 'bhela_bm_expense_nonce' );
	$e       = bhela_bm_expense_get( $post->ID );
	$types   = bhela_bm_expense_types( true );
	$methods = bhela_bm_expense_methods( true );
	?>
	<div class="bha-grid">
		<div class="bha-field bha-field--caps">
			<label for="exp_date"><?php esc_html_e( 'Date', 'bhela-booking' ); ?></label>
			<input type="date" id="exp_date" name="exp_date" value="<?php echo esc_attr( $e['date'] ); ?>" required>
		</div>
		<div class="bha-field bha-field--caps">
			<label for="exp_type"><?php esc_html_e( 'Type', 'bhela-booking' ); ?></label>
			<select id="exp_type" name="exp_type">
				<?php foreach ( $types as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $e['type'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="bha-field bha-field--caps">
			<label for="exp_amount"><?php esc_html_e( 'Amount (৳)', 'bhela-booking' ); ?></label>
			<input type="number" id="exp_amount" name="exp_amount" min="0" step="1" value="<?php echo esc_attr( $e['amount'] ?: '' ); ?>">
		</div>
		<div class="bha-field bha-field--caps">
			<label for="exp_method"><?php esc_html_e( 'Payment method', 'bhela-booking' ); ?></label>
			<select id="exp_method" name="exp_method">
				<option value="">—</option>
				<?php foreach ( $methods as $slug => $label ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $e['method'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="bha-field bha-field--caps">
			<label for="exp_paid_on"><?php esc_html_e( 'Payment date', 'bhela-booking' ); ?></label>
			<input type="date" id="exp_paid_on" name="exp_paid_on" value="<?php echo esc_attr( $e['paid_on'] ); ?>">
		</div>
		<div class="bha-field bha-field--caps">
			<label for="exp_verify"><?php esc_html_e( 'Means of verification', 'bhela-booking' ); ?></label>
			<input type="text" id="exp_verify" name="exp_verify" value="<?php echo esc_attr( $e['verify'] ); ?>" placeholder="<?php esc_attr_e( 'Invoice, WhatsApp, screenshot…', 'bhela-booking' ); ?>">
		</div>
		<div class="bha-field bha-field--caps bha-grid__wide">
			<label for="exp_remark"><?php esc_html_e( 'Remark', 'bhela-booking' ); ?></label>
			<input type="text" id="exp_remark" name="exp_remark" value="<?php echo esc_attr( $e['remark'] ); ?>">
		</div>
	</div>
	<p class="description" style="margin-top:12px">
		<?php esc_html_e( 'The date decides which month this lands in on the Monthly Statement — not the payment date.', 'bhela-booking' ); ?>
	</p>
	<?php
}

function bhela_bm_expense_save( $post_id, $post ) {
	if ( 'bhela_expense' !== $post->post_type ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['bhela_bm_expense_nonce'] ) || ! wp_verify_nonce( $_POST['bhela_bm_expense_nonce'], 'bhela_bm_expense_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$date    = bhela_bm_cost_date( wp_unslash( $_POST['exp_date'] ?? '' ) );
	$paid_on = bhela_bm_cost_date( wp_unslash( $_POST['exp_paid_on'] ?? '' ) );
	$types   = bhela_bm_expense_types( true );
	$methods = bhela_bm_expense_methods( true );

	$type   = sanitize_key( $_POST['exp_type'] ?? '' );
	$method = sanitize_key( $_POST['exp_method'] ?? '' );

	update_post_meta( $post_id, '_bhela_exp_date', $date );
	update_post_meta( $post_id, '_bhela_exp_type', isset( $types[ $type ] ) ? $type : 'other' );
	update_post_meta( $post_id, '_bhela_exp_amount', max( 0, (int) ( $_POST['exp_amount'] ?? 0 ) ) );
	update_post_meta( $post_id, '_bhela_exp_method', isset( $methods[ $method ] ) ? $method : '' );
	update_post_meta( $post_id, '_bhela_exp_paid_on', $paid_on );
	update_post_meta( $post_id, '_bhela_exp_verify', sanitize_text_field( wp_unslash( $_POST['exp_verify'] ?? '' ) ) );
	update_post_meta( $post_id, '_bhela_exp_remark', sanitize_text_field( wp_unslash( $_POST['exp_remark'] ?? '' ) ) );

	// Name it after itself, so the list is readable without opening anything.
	$label = $types[ $type ] ?? __( 'Expense', 'bhela-booking' );
	$title = trim( $label . ' — ' . ( $date ? mysql2date( 'j M Y', $date ) : '' ) );
	if ( $title !== $post->post_title ) {
		remove_action( 'save_post', 'bhela_bm_expense_save', 10 );
		wp_update_post( array( 'ID' => $post_id, 'post_title' => $title ) );
		add_action( 'save_post', 'bhela_bm_expense_save', 10, 2 );
	}
}
add_action( 'save_post', 'bhela_bm_expense_save', 10, 2 );

/* =========================================================
 * LIST TABLE
 * ========================================================= */

function bhela_bm_expense_columns( $columns ) {
	return array(
		'cb'      => $columns['cb'] ?? '',
		'exdate'  => __( 'Date', 'bhela-booking' ),
		'extype'  => __( 'Type', 'bhela-booking' ),
		'examt'   => __( 'Amount', 'bhela-booking' ),
		'exmeth'  => __( 'Paid by', 'bhela-booking' ),
		'exver'   => __( 'Verification', 'bhela-booking' ),
		'exrem'   => __( 'Remark', 'bhela-booking' ),
	);
}
add_filter( 'manage_bhela_expense_posts_columns', 'bhela_bm_expense_columns' );

function bhela_bm_expense_column( $column, $post_id ) {
	$e = bhela_bm_expense_get( $post_id );
	switch ( $column ) {
		case 'exdate':
			echo $e['date'] ? '<strong>' . esc_html( mysql2date( 'j M Y', $e['date'] ) ) . '</strong>' : '—';
			break;
		case 'extype':
			$types = bhela_bm_expense_types( true );
			echo esc_html( $types[ $e['type'] ] ?? $e['type'] ?: '—' );
			break;
		case 'examt':
			echo '<strong class="bha-num">' . esc_html( bhela_bm_money( $e['amount'] ) ) . '</strong>';
			break;
		case 'exmeth':
			$methods = bhela_bm_expense_methods( true );
			echo esc_html( $methods[ $e['method'] ] ?? '—' );
			if ( $e['paid_on'] ) {
				echo '<span class="bha-sub">' . esc_html( mysql2date( 'j M', $e['paid_on'] ) ) . '</span>';
			}
			break;
		case 'exver':
			echo esc_html( $e['verify'] ?: '—' );
			break;
		case 'exrem':
			echo esc_html( $e['remark'] ?: '—' );
			break;
	}
}
add_action( 'manage_bhela_expense_posts_custom_column', 'bhela_bm_expense_column', 10, 2 );

function bhela_bm_expense_sortable( $columns ) {
	$columns['exdate'] = 'exdate';
	return $columns;
}
add_filter( 'manage_edit-bhela_expense_sortable_columns', 'bhela_bm_expense_sortable' );

/** Month filter above the list, plus a running total for what is shown. */
function bhela_bm_expense_filter() {
	global $typenow;
	if ( 'bhela_expense' !== $typenow ) {
		return;
	}
	$month = isset( $_GET['bhela_exp_month'] ) ? sanitize_text_field( wp_unslash( $_GET['bhela_exp_month'] ) ) : '';
	$type  = isset( $_GET['bhela_exp_type'] ) ? sanitize_key( $_GET['bhela_exp_type'] ) : '';
	printf(
		'<input type="month" name="bhela_exp_month" value="%s" style="height:30px;vertical-align:top">',
		esc_attr( $month )
	);
	echo '<select name="bhela_exp_type"><option value="">' . esc_html__( 'All types', 'bhela-booking' ) . '</option>';
	foreach ( bhela_bm_expense_types( true ) as $slug => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $type, $slug, false ), esc_html( $label ) );
	}
	echo '</select>';

	if ( $month && preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
		$data = bhela_bm_expense_rows( $month . '-01', gmdate( 'Y-m-t', strtotime( $month . '-01' ) ) );
		printf(
			'<span style="margin-left:10px;line-height:30px"><strong>%s</strong> %s</span>',
			esc_html( bhela_bm_money( $data['total'] ) ),
			esc_html( sprintf( _n( 'across %d expense', 'across %d expenses', count( $data['rows'] ), 'bhela-booking' ), count( $data['rows'] ) ) )
		);
	}
}
add_action( 'restrict_manage_posts', 'bhela_bm_expense_filter' );

function bhela_bm_expense_filter_query( $query ) {
	global $pagenow;
	if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query()
		|| 'bhela_expense' !== ( $_GET['post_type'] ?? '' ) ) {
		return;
	}
	$meta = array();
	$month = isset( $_GET['bhela_exp_month'] ) ? sanitize_text_field( wp_unslash( $_GET['bhela_exp_month'] ) ) : '';
	if ( $month && preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
		$meta[] = array(
			'key'     => '_bhela_exp_date',
			'value'   => array( $month . '-01', gmdate( 'Y-m-t', strtotime( $month . '-01' ) ) ),
			'compare' => 'BETWEEN',
			'type'    => 'DATE',
		);
	}
	if ( ! empty( $_GET['bhela_exp_type'] ) ) {
		$meta[] = array( 'key' => '_bhela_exp_type', 'value' => sanitize_key( $_GET['bhela_exp_type'] ) );
	}
	if ( $meta ) {
		$query->set( 'meta_query', $meta );
	}
	if ( ! $query->get( 'orderby' ) ) {
		$query->set( 'meta_key', '_bhela_exp_date' );
		$query->set( 'orderby', 'meta_value' );
		$query->set( 'order', 'DESC' );
	}
}
add_action( 'pre_get_posts', 'bhela_bm_expense_filter_query' );
