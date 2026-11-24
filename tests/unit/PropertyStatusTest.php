<?php

use Estora\Core\Domain\Property_Status;
use Estora\Core\Support\Validation_Exception;
use PHPUnit\Framework\TestCase;

final class PropertyStatusTest extends TestCase {

	public function test_availability_rules(): void {
		$this->assertTrue( Property_Status::accepts_inquiries( Property_Status::AVAILABLE ) );
		$this->assertTrue( Property_Status::accepts_inquiries( Property_Status::RESERVED ) );
		$this->assertFalse( Property_Status::accepts_inquiries( Property_Status::SOLD ) );
		$this->assertFalse( Property_Status::accepts_inquiries( Property_Status::RENTED ) );
		$this->assertFalse( Property_Status::accepts_inquiries( Property_Status::UNAVAILABLE ) );
	}

	public function test_sold_property_can_return_to_available(): void {
		Property_Status::assert_availability( Property_Status::SOLD, Property_Status::AVAILABLE );
		$this->assertTrue( true );
	}

	public function test_availability_cannot_go_directly_to_reserved_from_sold(): void {
		$this->expectException( Validation_Exception::class );
		Property_Status::assert_availability( Property_Status::SOLD, Property_Status::RESERVED );
	}

	public function test_unknown_availability_rejected(): void {
		$this->expectException( Validation_Exception::class );
		Property_Status::assert_availability( Property_Status::AVAILABLE, 'haunted' );
	}

	public function test_labels_and_badges(): void {
		foreach ( Property_Status::availability_values() as $value ) {
			$this->assertNotSame( '', Property_Status::availability_label( $value ) );
			$this->assertStringStartsWith( 'badge-', Property_Status::availability_badge( $value ) );
		}
		foreach ( array( 'draft', 'pending', 'published', 'archived' ) as $status ) {
			$this->assertNotSame( '', Property_Status::publication_label( $status ) );
		}
	}
}
