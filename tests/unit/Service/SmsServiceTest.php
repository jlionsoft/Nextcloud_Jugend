<?php
declare(strict_types=1);

namespace OCA\Jugend\Tests\Unit\Service;

use OCA\Jugend\Service\SmsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

class SmsServiceTest extends TestCase {
    private SmsService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->service = (new ReflectionClass(SmsService::class))->newInstanceWithoutConstructor();
    }

    public function testShortMessageStaysInOnePart(): void {
        $this->assertSame(['Hallo Welt'], $this->service->split('Hallo Welt'));
    }

    public function testMessageIsSplitAtConfiguredPayloadLimit(): void {
        $parts = $this->service->split(str_repeat('A', 151));

        $this->assertCount(2, $parts);
        $this->assertSame(str_repeat('A', 150), $parts[0]);
        $this->assertSame('A', $parts[1]);
        foreach ($parts as $part) {
            $this->assertLessThanOrEqual(150, $this->gsmSeptets($part));
        }
    }

    public function testExtendedCharactersCountAsTwoSeptets(): void {
        $parts = $this->service->split(str_repeat('A', 149) . '^BC');

        $this->assertSame([str_repeat('A', 149), '^BC'], $parts);
        foreach ($parts as $part) {
            $this->assertLessThanOrEqual(150, $this->gsmSeptets($part));
        }
    }

    public function testSplitPrefersWhitespaceBoundary(): void {
        $parts = $this->service->split(str_repeat('A', 100) . ' ' . str_repeat('B', 100));

        $this->assertSame([str_repeat('A', 100), str_repeat('B', 100)], $parts);
    }

    public function testEmptyMessageProducesNoParts(): void {
        $this->assertSame([], $this->service->split(''));
    }

    #[DataProvider('unsupportedCharactersData')]
    public function testRejectsUnsupportedCharacters(string $input): void {
        $this->expectException(RuntimeException::class);
        $this->service->split($input);
    }

    public static function unsupportedCharactersData(): array {
        return [
            'emoji' => ['Hallo 👋'],
            'CJK character' => ['漢字'],
            'invalid UTF-8' => ["\xFF"],
        ];
    }

    private function gsmSeptets(string $message): int {
        $extended = '^{}\\[~]|€';
        $characters = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY);
        $this->assertNotFalse($characters);

        $septets = 0;
        foreach ($characters as $character) {
            $septets += str_contains($extended, $character) ? 2 : 1;
        }
        return $septets;
    }
}
