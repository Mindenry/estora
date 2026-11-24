<?php

use Estora\Core\Domain\Lead_Status;
use Estora\Core\Support\Validation_Exception;
use PHPUnit\Framework\TestCase;

final class LeadStatusTest extends TestCase {

	public function test_pipeline_accepts_documented_path(): void {
		$this->assertTrue( Lead_Status::can( Lead_Status::NEW, Lead_Status::CONTACTED ) );
		$this->assertTrue( Lead_Status::can( Lead_Status::CONTACTED, Lead_Status::QUALIFIED ) );
		$this->assertTrue( Lead_Status::can( Lead_Status::QUALIFIED, Lead_Status::NEGOTIATION ) );
		$this->assertTrue( Lead_Status::can( Lead_Status::NEGOTIATION, Lead_Status::WON ) );
	}

	public function test_lost_is_reachable_from_every_active_stage(): void {
		foreach ( Lead_Status::active() as $status ) {
			$this->assertTrue( Lead_Status::can( $status, Lead_Status::LOST ), "lost from {$status}" );
		}
	}

	public function test_winning_requires_negotiation(): void {
		$this->assertFalse( Lead_Status::can( Lead_Status::NEW, Lead_Status::WON ) );
		$this->assertFalse( Lead_Status::can( Lead_Status::QUALIFIED, Lead_Status::WON ) );
		$this->assertTrue( Lead_Status::can( Lead_Status::NEGOTIATION, Lead_Status::WON ) );
	}

	public function test_skip_is_rejected_with_explanation(): void {
		try {
			Lead_Status::assert_transition( Lead_Status::NEW, Lead_Status::WON );
			$this->fail( 'Expected a Validation_Exception.' );
		} catch ( Validation_Exception $e ) {
			$this->assertSame( 422, $e->get_status() );
			$this->assertArrayHasKey( 'status', $e->get_errors() );
			$this->assertStringContainsString( 'Contacted', $e->get_errors()['status'][0] );
		}
	}

	public function test_unknown_status_rejected(): void {
		$this->expectException( Validation_Exception::class );
		Lead_Status::assert_transition( Lead_Status::NEW, 'flying' );
	}

	public function test_unknown_current_status_rejected(): void {
		$this->expectException( Validation_Exception::class );
		Lead_Status::assert_transition( 'ghost', Lead_Status::CONTACTED );
	}

	public function test_labels_and_badges_cover_all_states(): void {
		foreach ( Lead_Status::all() as $status ) {
			$this->assertNotSame( '', Lead_Status::label( $status ) );
			$this->assertStringStartsWith( 'badge-', Lead_Status::badge( $status ) );
		}
	}
}
