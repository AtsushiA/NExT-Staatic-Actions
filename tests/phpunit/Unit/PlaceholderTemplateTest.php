<?php

declare(strict_types=1);

namespace NExT\StaaticActions\Tests\Unit;

use NExT\StaaticActions\Support\PlaceholderTemplate;
use PHPUnit\Framework\TestCase;

final class PlaceholderTemplateTest extends TestCase {

	public function test_replaces_known_placeholders(): void {
		$result = PlaceholderTemplate::render(
			'[{{status}}] {{publication_id}}',
			array(
				'status'         => 'finished',
				'publication_id' => 'abc-123',
			)
		);

		self::assertSame( '[finished] abc-123', $result );
	}

	public function test_leaves_unknown_placeholders_untouched(): void {
		$result = PlaceholderTemplate::render(
			'{{status}} {{unknown}}',
			array(
				'status' => 'finished',
			)
		);

		self::assertSame( 'finished {{unknown}}', $result );
	}

	public function test_skips_array_values(): void {
		$result = PlaceholderTemplate::render(
			'{{status}} {{meta}}',
			array(
				'status' => 'finished',
				'meta'   => array( 'nested' => 'value' ),
			)
		);

		self::assertSame( 'finished {{meta}}', $result );
	}

	public function test_handles_empty_context(): void {
		$result = PlaceholderTemplate::render( 'static text', array() );

		self::assertSame( 'static text', $result );
	}
}
