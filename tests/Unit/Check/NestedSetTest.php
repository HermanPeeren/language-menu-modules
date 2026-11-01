<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LanguageMenuModules\Tests\Unit\Check;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\LanguageMenuModules\Check\NestedSet;

final class NestedSetTest extends TestCase
{
    /**
     * root 1 (0, 7) > 2 (1, 4) > 3 (2, 3); 4 (5, 6) under the root.
     */
    private const TREE = [
        [1, 0, 0, 7, 0],
        [2, 1, 1, 4, 1],
        [3, 2, 2, 3, 2],
        [4, 1, 5, 6, 1],
    ];

    /**
     * @param   list<array{0: int, 1: int, 2: int, 3: int, 4: int}>  $nodes  [id, parent_id, lft, rgt, level]
     *
     * @return  list<array{id: int, parent_id: int, lft: int, rgt: int, level: int}>
     */
    private static function rows(array $nodes): array
    {
        return array_map(
            static fn (array $n): array => ['id' => $n[0], 'parent_id' => $n[1], 'lft' => $n[2], 'rgt' => $n[3], 'level' => $n[4]],
            $nodes
        );
    }

    public function testAWholeTreeHasNoProblems(): void
    {
        self::assertSame([], NestedSet::problems(self::rows(self::TREE)));
    }

    public function testAGapIsFound(): void
    {
        $nodes    = self::TREE;
        $nodes[0] = [1, 0, 0, 8, 0];
        $nodes[3] = [4, 1, 6, 7, 1];

        self::assertSame(
            ['node 4 has lft 6, should be 5', 'node 4 has rgt 7, should be 6', 'node 1 has rgt 8, should be 7'],
            NestedSet::problems(self::rows($nodes))
        );
    }

    public function testAWrongLevelIsFound(): void
    {
        $nodes    = self::TREE;
        $nodes[2] = [3, 2, 2, 3, 1];

        self::assertSame(['node 3 has level 1, should be 2'], NestedSet::problems(self::rows($nodes)));
    }

    public function testANodeOutsideTheTreeIsFound(): void
    {
        $nodes   = self::TREE;
        $nodes[] = [5, 99, 8, 9, 1];

        self::assertSame(['1 rows have parent 99, which does not exist'], NestedSet::problems(self::rows($nodes)));
    }

    public function testTwoRootsAreFound(): void
    {
        $nodes    = self::TREE;
        $nodes[3] = [4, 0, 5, 6, 1];

        self::assertSame(['2 roots instead of one'], NestedSet::problems(self::rows($nodes)));
    }
}
