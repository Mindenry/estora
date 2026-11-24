<?php

use Estora\Core\Support\Validation_Exception;
use Estora\Core\Support\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase {

	public function test_required_and_length(): void {
		$errors = Validator::validate(
			array( 'full_name' => '  ', 'message' => str_repeat( 'a', 30 ) ),
			array(
				'full_name' => array( 'required', 'max_length:150' ),
				'message'   => array( 'max_length:20' ),
			)
		);
		$this->assertArrayHasKey( 'full_name', $errors );
		$this->assertArrayHasKey( 'message', $errors );
	}

	public function test_email_and_phone(): void {
		$this->assertSame( array(), Validator::validate( array( 'email' => 'buyer@example.com' ), array( 'email' => array( 'email' ) ) ) );
		$this->assertNotSame( array(), Validator::validate( array( 'email' => 'not-an-email' ), array( 'email' => array( 'email' ) ) ) );
		$this->assertSame( array(), Validator::validate( array( 'phone' => '+66 (0) 81-234-5601' ), array( 'phone' => array( 'phone' ) ) ) );
		$this->assertNotSame( array(), Validator::validate( array( 'phone' => 'abc' ), array( 'phone' => array( 'phone' ) ) ) );
		$this->assertNotSame( array(), Validator::validate( array( 'phone' => '12345' ), array( 'phone' => array( 'phone' ) ) ) );
	}

	public function test_enum_and_numbers(): void {
		$rules = array( 'status' => array( 'in:new,won' ), 'price' => array( 'numeric', 'min:1' ), 'beds' => array( 'integer', 'min:0', 'max:5' ) );
		$errors = Validator::validate( array( 'status' => 'lost', 'price' => 0, 'beds' => 2.5 ), $rules );
		$this->assertArrayHasKey( 'status', $errors );
		$this->assertArrayHasKey( 'price', $errors );
		$this->assertArrayHasKey( 'beds', $errors );
		$this->assertSame( array(), Validator::validate( array( 'status' => 'won', 'price' => 10, 'beds' => 3 ), $rules ) );
	}

	public function test_dates_and_past(): void {
		$errors = Validator::validate(
			array( 'date' => '2026-02-31', 'wrong' => '01/02/2026', 'past' => '2020-01-01' ),
			array(
				'date'  => array( 'date' ),
				'wrong' => array( 'date' ),
				'past'  => array( 'date_not_past:2026-11-24' ),
			)
		);
		$this->assertArrayHasKey( 'date', $errors );
		$this->assertArrayHasKey( 'wrong', $errors );
		$this->assertArrayHasKey( 'past', $errors );
	}

	public function test_boolean_representation(): void {
		$this->assertTrue( Validator::to_bool( '1' ) );
		$this->assertTrue( Validator::to_bool( 'on' ) );
		$this->assertTrue( Validator::to_bool( true ) );
		$this->assertFalse( Validator::to_bool( '0' ) );
		$this->assertFalse( Validator::to_bool( null ) );
		$this->assertSame( array(), Validator::validate( array( 'consent' => '1' ), array( 'consent' => array( 'boolean' ) ) ) );
	}

	public function test_assert_throws_with_field_errors(): void {
		try {
			Validator::assert( array( 'email' => 'x' ), array( 'email' => array( 'required', 'email' ) ) );
			$this->fail( 'Expected Validation_Exception' );
		} catch ( Validation_Exception $e ) {
			$this->assertSame( 422, $e->get_status() );
			$this->assertArrayHasKey( 'email', $e->get_errors() );
		}
	}

	public function test_optional_empty_values_skip_validation(): void {
		$this->assertSame( array(), Validator::validate( array( 'seo' => '' ), array( 'seo' => array( 'url' ) ) ) );
	}
}
