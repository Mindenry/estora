<?php

use Estora\Core\Domain\Appointment_Status;
use Estora\Core\Support\Validation_Exception;
use PHPUnit\Framework\TestCase;

final class AppointmentStatusTest extends TestCase {

	public function test_confirmation_and_completion_path(): void {
		$this->assertTrue( Appointment_Status::can( Appointment_Status::PENDING, Appointment_Status::CONFIRMED ) );
		$this->assertTrue( Appointment_Status::can( Appointment_Status::CONFIRMED, Appointment_Status::COMPLETED ) );
		$this->assertTrue( Appointment_Status::can( Appointment_Status::CONFIRMED, Appointment_Status::RESCHEDULED ) );
	}

	public function test_terminal_states_are_immutable(): void {
		foreach ( array( Appointment_Status::COMPLETED, Appointment_Status::CANCELLED, Appointment_Status::REJECTED, Appointment_Status::NO_SHOW ) as $terminal ) {
			$this->assertSame( array(), Appointment_Status::transitions()[ $terminal ] );
		}
	}

	public function test_cannot_confirm_a_cancelled_appointment(): void {
		$this->assertFalse( Appointment_Status::can( Appointment_Status::CANCELLED, Appointment_Status::CONFIRMED ) );
		$this->expectException( Validation_Exception::class );
		Appointment_Status::assert_transition( Appointment_Status::CANCELLED, Appointment_Status::CONFIRMED );
	}

	public function test_active_predicate(): void {
		$this->assertTrue( Appointment_Status::is_active( Appointment_Status::PENDING ) );
		$this->assertTrue( Appointment_Status::is_active( Appointment_Status::CONFIRMED ) );
		$this->assertTrue( Appointment_Status::is_active( Appointment_Status::RESCHEDULED ) );
		$this->assertFalse( Appointment_Status::is_active( Appointment_Status::CANCELLED ) );
		$this->assertFalse( Appointment_Status::is_active( Appointment_Status::REJECTED ) );
		$this->assertFalse( Appointment_Status::is_active( Appointment_Status::NO_SHOW ) );
	}

	public function test_unknown_status_rejected(): void {
		$this->expectException( Validation_Exception::class );
		Appointment_Status::assert_transition( Appointment_Status::PENDING, 'teleported' );
	}
}
