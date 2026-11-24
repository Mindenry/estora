<?php

use Estora\Core\Support\Formatter;
use PHPUnit\Framework\TestCase;

final class FormatterTest extends TestCase {

	public function test_price_formatting(): void {
		$this->assertSame( '฿12,500,000', Formatter::price( 12500000 ) );
		$this->assertSame( '฿32,000', Formatter::price( 32000 ) );
		$this->assertSame( '฿1,234.50', Formatter::price( 1234.5 ) );
		$this->assertSame( '฿12.5M', Formatter::price( 12500000, 'THB', true ) );
		$this->assertSame( '฿85K', Formatter::price( 85000, 'THB', true ) );
	}

	public function test_area_formatting(): void {
		$this->assertSame( '240 m²', Formatter::area( 240 ) );
		$this->assertSame( '66.5 m²', Formatter::area( 66.5 ) );
		$this->assertSame( '—', Formatter::area( 0 ) );
	}

	public function test_reference_and_initials(): void {
		$this->assertSame( 'EST-1042', Formatter::reference( 42 ) );
		$this->assertSame( 'AP', Formatter::initials( 'Anan Prasert' ) );
		$this->assertSame( '?', Formatter::initials( '   ' ) );
	}

	public function test_phone_normalisation(): void {
		$this->assertSame( '+66812345601', Formatter::normalize_phone( '+66 (0) 81-234-5601' ) );
		$this->assertSame( '0812345601', Formatter::normalize_phone( '081 234 5601' ) );
		$this->assertSame( '0851234567', Formatter::normalize_phone( '085-123-4567' ) );
	}

	public function test_relative_time(): void {
		$now = strtotime( '2026-11-24 12:00:00' );
		$this->assertSame( 'just now', Formatter::time_ago( '2026-11-24 11:59:40', $now ) );
		$this->assertSame( '5 min ago', Formatter::time_ago( '2026-11-24 11:55:00', $now ) );
		$this->assertSame( '2 hours ago', Formatter::time_ago( '2026-11-24 10:00:00', $now ) );
		$this->assertSame( '1 day ago', Formatter::time_ago( '2026-11-23 11:00:00', $now ) );
	}
}
