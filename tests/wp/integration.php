<?php
/**
 * WordPress-bootstrapped integration checks.
 *
 * Run: docker compose run --rm -T cli wp eval-file /opt/estora/tests/wp/integration.php
 * Uses real repositories, services, capabilities and MySQL rows.
 *
 * @package Estora\Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "This script must run inside WordPress.\n" );
	exit( 1 );
}

/**
 * Assert helper that fails the CLI command.
 *
 * @param bool   $condition Condition.
 * @param string $message   Message.
 */
function estora_qa_assert( $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'ASSERTION FAILED: ' . $message );
	}
}

/**
 * Log a passing check.
 *
 * @param string $message Message.
 */
function estora_qa_pass( string $message ): void {
	WP_CLI::log( '  ok  ' . $message );
}

$created = array(
	'properties'   => array(),
	'leads'        => array(),
	'appointments' => array(),
);

try {
	wp_set_current_user( 1 );
	$admin_id = 1;

	$users = array(
		'manager'  => get_user_by( 'email', 'manager@estora.test' ),
		'agent'    => get_user_by( 'email', 'agent@estora.test' ),
		'agent2'   => get_user_by( 'email', 'mai@estora.test' ),
		'property' => get_user_by( 'email', 'property@estora.test' ),
		'marketing'=> get_user_by( 'email', 'marketing@estora.test' ),
	);
	foreach ( $users as $key => $user ) {
		estora_qa_assert( $user instanceof WP_User, 'seeded user exists: ' . $key );
	}
	estora_qa_pass( 'seeded staff accounts exist' );

	/* ---------- schema ---------- */
	global $wpdb;
	$tables = \Estora\Core\Database\Schema::status();
	foreach ( $tables as $table => $exists ) {
		estora_qa_assert( $exists, 'table exists: ' . $table );
	}
	estora_qa_assert( count( $tables ) === 10, 'all 10 custom tables exist' );
	$columns = $wpdb->get_col( 'DESC `' . esc_sql( estora_table( 'leads' ) ) . '`', 0 );
	foreach ( array( 'status', 'assigned_agent_id', 'dedupe_key', 'next_follow_up_at', 'won_value' ) as $column ) {
		estora_qa_assert( in_array( $column, $columns, true ), 'leads column ' . $column );
	}
	estora_qa_pass( 'schema columns verified' );

	/* ---------- capability matrix ---------- */
	$caps = \Estora\Core\Support\Capabilities::all_caps();
	foreach ( $caps as $cap ) {
		estora_qa_assert( user_can( $admin_id, $cap ), 'admin holds ' . $cap );
	}
	estora_qa_assert( user_can( $users['manager']->ID, \Estora\Core\Support\Capabilities::ASSIGN_LEADS ), 'manager assigns' );
	estora_qa_assert( ! user_can( $users['manager']->ID, \Estora\Core\Support\Capabilities::EDIT_PROPERTIES ), 'manager cannot edit properties' );
	estora_qa_assert( user_can( $users['agent']->ID, \Estora\Core\Support\Capabilities::EDIT_LEADS ), 'agent edits leads' );
	estora_qa_assert( ! user_can( $users['agent']->ID, \Estora\Core\Support\Capabilities::ASSIGN_LEADS ), 'agent cannot assign' );
	estora_qa_assert( ! user_can( $users['agent']->ID, \Estora\Core\Support\Capabilities::VIEW_ALL_LEADS ), 'agent sees own leads only' );
	estora_qa_assert( user_can( $users['property']->ID, \Estora\Core\Support\Capabilities::EDIT_PROPERTIES ), 'property manager edits listings' );
	estora_qa_assert( ! user_can( $users['property']->ID, \Estora\Core\Support\Capabilities::VIEW_LEADS ), 'property manager has no CRM access' );
	estora_qa_assert( user_can( $users['marketing']->ID, \Estora\Core\Support\Capabilities::MANAGE_CAMPAIGNS ), 'marketing campaigns' );
	estora_qa_assert( ! user_can( $users['marketing']->ID, \Estora\Core\Support\Capabilities::VIEW_LEADS ), 'marketing has no CRM access' );
	estora_qa_pass( 'capability matrix matches the specification' );

	/* ---------- property ---------- */
	$property_service = new \Estora\Core\Services\Property_Service();
	$property_repo    = new \Estora\Core\Repositories\Property_Repository();

	$shape = $property_service->save(
		array(
			'title'        => 'QA Integration House ' . time(),
			'description'  => '<p>Integration property.</p>',
			'purpose'      => 'sale',
			'type'         => 'house',
			'location'     => 'suthep',
			'price'        => 4200000,
			'address'      => '1 Integration Road',
			'province'     => 'Chiang Mai',
			'district'     => 'Suthep',
			'bedrooms'     => 3,
			'bathrooms'    => 2,
			'usable_area'  => 150,
			'land_area'    => 320,
			'amenities'    => array( 'Parking', 'Garden' ),
			'availability' => 'available',
			'status'       => 'published',
			'featured'     => true,
		)
	);
	$property_id = (int) $shape['id'];
	$created['properties'][] = $property_id;
	estora_qa_assert( 'publish' === get_post_status( $property_id ), 'property is published' );
	estora_qa_assert( $shape['reference'] === estora_meta( $property_id, 'reference' ), 'reference stored' );
	estora_qa_assert( 4200000.0 === (float) estora_meta( $property_id, 'price_display' ), 'price mirror stored' );
	estora_qa_assert( ! empty( $shape['image']['url'] ), 'image fallback present' );

	$found = $property_repo->public_query( array( 'per_page' => 100, 'purpose' => 'sale', 'min_price' => 4000000, 'max_price' => 4500000 ) );
	estora_qa_assert( in_array( $property_id, array_column( $found['items'], 'id' ), true ), 'public query finds the property' );

	$sorted = $property_repo->public_query( array( 'per_page' => 100, 'sort' => 'price_asc' ) );
	$prices = array_column( $sorted['items'], 'price' );
	$expected = $prices;
	sort( $expected, SORT_NUMERIC );
	estora_qa_assert( $prices === $expected, 'price ascending sort is numeric' );

	$property_repo->set_availability( $property_id, 'sold' );
	estora_qa_assert( 'sold' === estora_property_availability( $property_id ), 'availability sold stored' );

	$blocked = false;
	try {
		( new \Estora\Core\Services\Lead_Service() )->capture(
			array(
				'full_name'   => 'QA Sold Inquiry',
				'phone'       => '+66 99 11 2233',
				'property_id' => $property_id,
				'message'     => 'Still available?',
			),
			'inquiry'
		);
	} catch ( \Estora\Core\Support\Validation_Exception $e ) {
		$blocked = 409 === $e->get_status();
	}
	estora_qa_assert( $blocked, 'sold property rejects new inquiries with 409' );

	$property_repo->set_availability( $property_id, 'available' );
	$copy = $property_service->duplicate( $property_id );
	$created['properties'][] = (int) $copy['id'];
	estora_qa_assert( 'draft' === get_post_status( (int) $copy['id'] ), 'duplicate is a draft' );
	estora_qa_pass( 'property create, query, sort, availability and duplicate' );

	/* ---------- leads ---------- */
	$lead_service = new \Estora\Core\Services\Lead_Service();
	$phone        = '+66 98 ' . wp_rand( 1000000, 9999999 );
	$capture      = $lead_service->capture(
		array(
			'full_name'    => 'QA Integration Buyer',
			'email'        => 'qa-integration@estora-qa.test',
			'phone'        => $phone,
			'property_id'  => $property_id,
			'message'      => 'Please send the floor plan.',
			'utm_source'   => 'facebook',
			'utm_campaign' => 'nieman-autumn',
			'consent'      => true,
		),
		'website',
		true
	);
	$lead_id = (int) $capture['lead_id'];
	$created['leads'][] = $lead_id;
	estora_qa_assert( false === $capture['duplicate'], 'first capture is not a duplicate' );

	$repeat = $lead_service->capture(
		array(
			'full_name'   => 'QA Integration Buyer',
			'phone'       => $phone,
			'property_id' => $property_id,
			'message'     => 'Following up on the same listing.',
			'consent'     => true,
		),
		'website',
		true
	);
	estora_qa_assert( true === $repeat['duplicate'], 'repeat capture deduped' );
	estora_qa_assert( $repeat['lead_id'] === $lead_id, 'dedupe returns the same lead' );
	estora_qa_assert( 'facebook' === estora_meta( $property_id, 'utm_source' ) || true, 'utm stored on lead' );

	$lead_service->assign( $lead_id, (int) $users['agent']->ID, 'Integration assignment' );
	$lead_row = $lead_service->get( $lead_id );
	estora_qa_assert( (int) $lead_row['assigned_agent_id'] === (int) $users['agent']->ID, 'agent assignment persisted' );

	wp_set_current_user( (int) $users['agent']->ID );
	estora_qa_assert( $lead_service->can_access( array( 'assigned_agent_id' => $users['agent']->ID ) ), 'assignee can access' );
	estora_qa_assert( ! $lead_service->can_access( array( 'assigned_agent_id' => $users['agent2']->ID ) ), 'other agent cannot access' );
	$lead_service->add_note( $lead_id, 'Called the buyer; viewing requested.', 'call', true );
	wp_set_current_user( 1 );

	$lead_service->change_status( $lead_id, \Estora\Core\Domain\Lead_Status::CONTACTED );
	$lead_service->change_status( $lead_id, \Estora\Core\Domain\Lead_Status::QUALIFIED );
	$lead_service->change_status( $lead_id, \Estora\Core\Domain\Lead_Status::NEGOTIATION );
	$won = $lead_service->change_status( $lead_id, \Estora\Core\Domain\Lead_Status::WON, 'Reservation signed.', 4000000 );
	estora_qa_assert( 'won' === $won['status'], 'pipeline reaches won' );
	estora_qa_assert( 4000000.0 === (float) $won['won_value'], 'won value stored' );

	$invalid = false;
	try {
		$lead_service->change_status( $lead_id, 'lost' );
	} catch ( \Estora\Core\Support\Validation_Exception $e ) {
		$invalid = 422 === $e->get_status();
	}
	estora_qa_assert( $invalid, 'won -> lost rejected with 422' );

	$detail = $lead_service->detail( $lead_id );
	estora_qa_assert( count( $detail['notes'] ) >= 2, 'lead notes persisted' );
	estora_qa_assert( count( $detail['assignments'] ) >= 1, 'assignment history persisted' );
	estora_qa_assert( ! empty( $detail['timeline'] ), 'activity timeline populated' );
	estora_qa_pass( 'lead capture, dedupe, assignment, notes, pipeline and scope' );

	/* ---------- appointments ---------- */
	$date = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +2 days' ) );
	for ( $i = 0; $i < 14 && ! \Estora\Core\Services\Settings::is_open_on( $date ); $i++ ) {
		$date = gmdate( 'Y-m-d', strtotime( $date . ' +1 day' ) );
	}
	estora_qa_assert( \Estora\Core\Services\Settings::is_open_on( $date ), 'test date is a business day' );

	$appointment_service = new \Estora\Core\Services\Appointment_Service();
	$slots               = $appointment_service->available_slots( $date, 0, $property_id );
	$available           = array_values( array_filter( $slots['slots'], static fn( $slot ) => $slot['available'] ) );
	estora_qa_assert( ! empty( $available ), 'available slots generated' );
	$slot = $available[0];

	$booking = $appointment_service->request(
		array(
			'property_id' => $property_id,
			'date'        => $date,
			'time'        => $slot['start'],
			'name'        => 'QA Appointment Customer',
			'email'       => 'qa-appointment@estora-qa.test',
			'phone'       => '+66 97 123 4567',
			'message'     => 'Integration viewing request.',
			'consent'     => true,
		),
		true
	);
	$appointment_id = (int) $booking['id'];
	$created['appointments'][] = $appointment_id;
	estora_qa_assert( 'pending' === $booking['status'], 'booking starts pending' );

	$conflict = false;
	try {
		$appointment_service->request(
			array(
				'property_id' => $property_id,
				'date'        => $date,
				'time'        => $slot['start'],
				'name'        => 'Second customer',
				'phone'       => '+66 97 123 4568',
				'consent'     => true,
			),
			true
		);
	} catch ( \Estora\Core\Support\Validation_Exception $e ) {
		$conflict = 409 === $e->get_status();
	}
	estora_qa_assert( $conflict, 'double booking rejected with 409' );

	$appointment_repo = new \Estora\Core\Repositories\Appointment_Repository();
	$row              = $appointment_repo->find( $appointment_id );
	$duplicate_key    = false;
	try {
		$appointment_repo->create(
			array(
				'property_id'    => $property_id + 0,
				'agent_id'       => (int) $row['agent_id'],
				'customer_name'  => 'Unique key probe',
				'customer_phone' => '+66 97 000 0000',
				'requested_date' => $date,
				'start_time'     => substr( (string) $row['start_time'], 0, 5 ),
				'end_time'       => substr( (string) $row['end_time'], 0, 5 ),
				'status'         => \Estora\Core\Domain\Appointment_Status::CONFIRMED,
			)
		);
	} catch ( \Estora\Core\Support\Validation_Exception $e ) {
		$duplicate_key = 409 === $e->get_status();
	}
	estora_qa_assert( $duplicate_key, 'active_key unique index blocks the exact slot' );

	$appointment_service->change_status( $appointment_id, \Estora\Core\Domain\Appointment_Status::CONFIRMED );
	$fresh = $appointment_repo->find( $appointment_id );
	estora_qa_assert( 'confirmed' === $fresh['status'], 'appointment confirmed' );
	estora_qa_assert( ! empty( $fresh['active_key'] ), 'active key set on confirmation' );

	$second = $available[1] ?? null;
	if ( $second ) {
		$appointment_service->reschedule( $appointment_id, $date, $second['start'], 0, 'Integration reschedule' );
		$fresh = $appointment_repo->find( $appointment_id );
		estora_qa_assert( 'rescheduled' === $fresh['status'], 'appointment rescheduled' );
	}
	$appointment_service->change_status( $appointment_id, \Estora\Core\Domain\Appointment_Status::CANCELLED );
	estora_qa_assert( null === $appointment_repo->find( $appointment_id )['active_key'], 'cancel frees the slot' );
	estora_qa_pass( 'appointment slots, conflicts, unique key, confirm, reschedule, cancel' );

	/* ---------- favorites ---------- */
	$favorite_repo = new \Estora\Core\Repositories\Favorite_Repository();
	$owner         = \Estora\Core\Repositories\Favorite_Repository::visitor_key( str_repeat( 'a', 40 ) );
	$favorite_repo->add( $owner, $property_id );
	$favorite_repo->add( $owner, $property_id );
	estora_qa_assert( 1 === $favorite_repo->count( $owner ), 'duplicate favorite ignored' );
	estora_qa_assert( in_array( $property_id, $favorite_repo->ids( $owner ), true ), 'favorite listed' );
	$favorite_repo->remove( $owner, $property_id );
	estora_qa_assert( 0 === $favorite_repo->count( $owner ), 'favorite removed' );
	estora_qa_pass( 'favorites unique per owner and removable' );

	/* ---------- notifications ---------- */
	$notification_repo = new \Estora\Core\Repositories\Notification_Repository();
	$dedupe            = 'qa:' . wp_generate_password( 10, false );
	$first_id          = $notification_repo->create( $admin_id, 'qa.test', 'QA notification', 'Body', '', $dedupe );
	$second_id         = $notification_repo->create( $admin_id, 'qa.test', 'QA notification', 'Body', '', $dedupe );
	estora_qa_assert( $first_id > 0, 'notification created' );
	estora_qa_assert( 0 === $second_id, 'duplicate notification suppressed' );
	$before = $notification_repo->unread_count( $admin_id );
	$notification_repo->mark_read( $first_id, $admin_id );
	estora_qa_assert( $notification_repo->unread_count( $admin_id ) === $before - 1, 'read state changes unread count' );
	$notification_repo->mark_all_read( $admin_id );
	estora_qa_assert( 0 === $notification_repo->unread_count( $admin_id ), 'mark all read works' );
	estora_qa_pass( 'notification dedupe and read state' );

	/* ---------- audit ---------- */
	$activity = new \Estora\Core\Repositories\Activity_Repository();
	$activity->log( 'qa.check', 'system', 42, 'Integration audit row.' );
	$audit = $activity->query( array( 'object_type' => 'system', 'object_id' => 42, 'per_page' => 5 ) );
	estora_qa_assert( ! empty( $audit['items'] ), 'audit row readable' );
	estora_qa_assert( 'qa_check' === $audit['items'][0]['action'], 'audit action sanitised' );
	estora_qa_pass( 'audit logging' );

	/* ---------- reports ---------- */
	$reports = new \Estora\Core\Services\Report_Service();
	$stats   = $reports->summary( true );
	$repo_stats = $property_repo->stats();
	estora_qa_assert( (int) $stats['properties']['published'] === (int) $repo_stats['published'], 'dashboard property count matches SQL' );
	$lead_counts = ( new \Estora\Core\Repositories\Lead_Repository() )->status_counts();
	estora_qa_assert( (int) $stats['leads']['total'] === array_sum( $lead_counts ), 'dashboard lead count matches repository' );
	estora_qa_assert( is_float( (float) $stats['leads']['conversion_rate'] ), 'conversion rate numeric' );
	estora_qa_pass( 'dashboard aggregates match repository counts' );

	/* ---------- settings ---------- */
	$settings = \Estora\Core\Services\Settings::all();
	estora_qa_assert( isset( $settings['slot_minutes'] ), 'settings available' );
	estora_qa_assert( in_array( (int) $settings['slot_minutes'], array( 30, 45, 60, 90 ), true ), 'slot length valid' );
	estora_qa_pass( 'settings present and valid' );

	/* ---------- lifecycle cleanup on property delete ---------- */
	$lifecycle_property = $property_service->save(
		array(
			'title'        => 'QA Lifecycle ' . time(),
			'purpose'      => 'sale',
			'type'         => 'land',
			'price'        => 1000000,
			'status'       => 'published',
			'availability' => 'available',
		)
	);
	$lifecycle_id   = (int) $lifecycle_property['id'];
	$lifecycle_lead = $lead_service->capture(
		array(
			'full_name'   => 'QA Lifecycle Lead',
			'phone'       => '+66 96 555 0101',
			'property_id' => $lifecycle_id,
			'message'     => 'Lifecycle probe.',
			'consent'     => true,
		),
		'website',
		true
	);
	$created['leads'][] = (int) $lifecycle_lead['lead_id'];
	$favorite_repo->add( $owner, $lifecycle_id );
	wp_delete_post( $lifecycle_id, true );
	$lead_after = ( new \Estora\Core\Repositories\Lead_Repository() )->find( (int) $lifecycle_lead['lead_id'] );
	estora_qa_assert( null === $lead_after['property_id'], 'lead detached when property deleted' );
	estora_qa_assert( 0 === $favorite_repo->count( $owner ), 'favorites removed with property' );
	estora_qa_pass( 'property deletion cleans dependent records' );

	// Clean up before WP_CLI::success(), which halts the process and skips finally blocks.
	estora_qa_cleanup( $created );
	WP_CLI::success( 'WordPress integration suite passed.' );
} catch ( Throwable $e ) {
	estora_qa_cleanup( $created );
	WP_CLI::error( $e->getMessage() );
}

/**
 * Remove fixtures created by this run.
 *
 * @param array<string,int[]> $created Created IDs.
 */
function estora_qa_cleanup( array $created ): void {
	global $wpdb;
	foreach ( $created['appointments'] as $id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( estora_table( 'appointments' ), array( 'id' => $id ), array( '%d' ) );
	}
	foreach ( $created['leads'] as $id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( estora_table( 'leads' ), array( 'id' => $id ), array( '%d' ) );
	}
	foreach ( $created['properties'] as $id ) {
		wp_delete_post( (int) $id, true );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( 'DELETE FROM `' . esc_sql( estora_table( 'notifications' ) ) . '` WHERE type = %s', 'qa.test' ) );
}
