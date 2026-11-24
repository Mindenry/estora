<?php

use Estora\Core\Domain\Availability;
use PHPUnit\Framework\TestCase;

final class AvailabilityTest extends TestCase {

	public function test_grid_respects_duration_and_closing_time(): void {
		$slots = Availability::slots( '2026-12-01', '09:00', '18:00', 45, null, null, array() );
		$this->assertSame( '09:00', $slots[0]['start'] );
		$this->assertSame( '09:45', $slots[0]['end'] );
		$this->assertSame( '17:15', end( $slots )['start'] );
		$this->assertSame( '18:00', end( $slots )['end'] );
		$this->assertCount( 12, $slots );
	}

	public function test_lunch_is_excluded_not_offered(): void {
		$slots = Availability::slots( '2026-12-01', '09:00', '18:00', 45, '12:00', '13:00', array() );
		foreach ( $slots as $slot ) {
			$this->assertFalse(
				Availability::overlaps( $slot['start'], $slot['end'], '12:00', '13:00' ),
				'No slot may overlap the break'
			);
		}
		$starts = array_column( $slots, 'start' );
		$this->assertNotContains( '11:30', $starts );
		$this->assertNotContains( '12:00', $starts );
		$this->assertNotContains( '12:45', $starts );
		$this->assertContains( '13:30', $starts );
	}

	public function test_booked_slots_are_unavailable_with_reason(): void {
		$slots = Availability::slots(
			'2026-12-01',
			'09:00',
			'12:00',
			45,
			null,
			null,
			array( array( 'start' => '10:30', 'end' => '11:15' ) )
		);
		$by_start = array_column( $slots, null, 'start' );
		$this->assertFalse( $by_start['10:30']['available'] );
		$this->assertSame( 'booked', $by_start['10:30']['reason'] );
		$this->assertTrue( $by_start['09:00']['available'] );
		$this->assertTrue( $by_start['11:15']['available'] );
	}

	public function test_past_time_on_today_is_blocked(): void {
		$slots = Availability::slots( '2026-12-01', '09:00', '12:00', 45, null, null, array(), '2026-12-01 10:00:00' );
		$by_start = array_column( $slots, null, 'start' );
		$this->assertFalse( $by_start['09:00']['available'] );
		$this->assertSame( 'past', $by_start['09:00']['reason'] );
		$this->assertFalse( $by_start['09:45']['available'] );
		$this->assertTrue( $by_start['10:30']['available'] );
	}

	public function test_other_day_is_not_affected_by_now(): void {
		$slots = Availability::slots( '2026-12-02', '09:00', '12:00', 45, null, null, array(), '2026-12-01 15:00:00' );
		$this->assertTrue( $slots[0]['available'] );
	}

	public function test_overlap_boundaries(): void {
		$this->assertFalse( Availability::overlaps( '09:00', '09:45', '09:45', '10:30' ) );
		$this->assertTrue( Availability::overlaps( '09:00', '09:45', '09:30', '10:15' ) );
		$this->assertFalse( Availability::overlaps( '10:00', '10:45', '09:00', '10:00' ) );
	}

	public function test_time_conversion(): void {
		$this->assertSame( 585, Availability::to_minutes( '09:45' ) );
		$this->assertSame( '10:30', Availability::from_minutes( 630 ) );
		$this->assertSame( '10:45', Availability::end_time( '10:00', 45 ) );
	}

	public function test_invalid_slot_length_returns_no_slots(): void {
		$this->assertSame( array(), Availability::slots( '2026-12-01', '09:00', '18:00', 0, null, null, array() ) );
	}
}
