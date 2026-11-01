<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LanguageMenuModules\Tests\Unit\Run;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\LanguageMenuModules\Plan\Planner;
use Yepr\Plugin\Task\LanguageMenuModules\Run\Creator;
use Yepr\Plugin\Task\LanguageMenuModules\Tests\Support\MemoryModuleStore;
use Yepr\Plugin\Task\LanguageMenuModules\Tests\Support\SiteFixture;

final class CreatorTest extends TestCase
{
    /**
     * @var list<array{0: string, 1: string}>
     */
    private array $log = [];

    private int $cleaned = 0;

    private function creator(MemoryModuleStore $store): Creator
    {
        return new Creator(
            $store,
            new Planner(SiteFixture::blueprint()),
            function (string $message, string $priority): void {
                $this->log[] = [$priority, $message];
            },
            function (): void {
                $this->cleaned++;
            }
        );
    }

    private function logged(): string
    {
        return implode("\n", array_map(static fn (array $entry): string => $entry[1], $this->log));
    }

    public function testADryRunWritesNothing(): void
    {
        $store = new MemoryModuleStore(new SiteFixture());

        $this->creator($store)->run(true, true, true, true);

        self::assertSame(0, $store->writes);
        self::assertSame(0, $this->cleaned);
        self::assertStringContainsString('New module main/fr-FR: "Mainmenu fr-FR", position-1, ordering 4, on all pages.', $this->logged());
        self::assertStringContainsString('Dry run: nothing was written.', $this->logged());
    }

    public function testARunWritesOnceAndASecondRunFindsNothingToDo(): void
    {
        $store = new MemoryModuleStore(new SiteFixture());

        $this->creator($store)->run(false, true, true, true);

        self::assertSame(1, $store->writes);
        self::assertSame(1, $this->cleaned);
        self::assertStringContainsString('Written: 4 new modules', $this->logged());

        $this->log = [];
        $this->creator($store)->run(false, true, true, true);

        self::assertSame(1, $store->writes);
        self::assertStringContainsString('Nothing to do', $this->logged());
    }

    public function testWithAnOptionOffTheCheckPlansWithTheSameOptions(): void
    {
        $store = new MemoryModuleStore(new SiteFixture());

        // The check plans again after writing: with the same options it finds nothing,
        // although with the options on there would be pages to add.
        $this->creator($store)->run(false, false, false, false);

        self::assertSame(1, $store->writes);
    }
}
