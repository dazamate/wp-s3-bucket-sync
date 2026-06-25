<?php

namespace Dazamate\S3ImageSync\Tests\Service;

use PHPUnit\Framework\TestCase;
use Dazamate\S3ImageSync\Service\ServerRequirements;

class ServerRequirementsTest extends TestCase {
    public function testChecksReturnsAllRequirements(): void {
        $checks = ServerRequirements::checks();

        $this->assertCount(4, $checks);

        foreach ($checks as $check) {
            $this->assertArrayHasKey('name', $check);
            $this->assertArrayHasKey('met', $check);
            $this->assertArrayHasKey('required', $check);
            $this->assertArrayHasKey('detail', $check);
            $this->assertIsBool($check['met']);
        }
    }

    public function testPhpCheckReflectsRuntime(): void {
        $php = ServerRequirements::checks()[0];

        $this->assertTrue($php['required']);
        $this->assertSame(
            version_compare(PHP_VERSION, ServerRequirements::MIN_PHP, '>='),
            $php['met']
        );
    }

    public function testHasAnyDriverMatchesLoadedExtensions(): void {
        $expected = extension_loaded('gd')
            || extension_loaded('imagick')
            || extension_loaded('vips');

        $this->assertSame($expected, ServerRequirements::has_any_driver());
    }
}
