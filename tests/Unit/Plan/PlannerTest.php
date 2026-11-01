<?php

declare(strict_types=1);

namespace Yepr\Plugin\Task\LanguageMenuModules\Tests\Unit\Plan;

use PHPUnit\Framework\TestCase;
use Yepr\Plugin\Task\LanguageMenuModules\Plan\Blueprint;
use Yepr\Plugin\Task\LanguageMenuModules\Plan\Kind;
use Yepr\Plugin\Task\LanguageMenuModules\Plan\NewModule;
use Yepr\Plugin\Task\LanguageMenuModules\Plan\Plan;
use Yepr\Plugin\Task\LanguageMenuModules\Plan\Planner;
use Yepr\Plugin\Task\LanguageMenuModules\Tests\Support\SiteFixture;

final class PlannerTest extends TestCase
{
    private function plan(SiteFixture $site, bool $completePages = true, bool $fixSource = true, bool $setHomes = true): Plan
    {
        return (new Planner(SiteFixture::blueprint()))->plan($site->snapshot(), $completePages, $fixSource, $setHomes);
    }

    private function newModule(Plan $plan, string $ref): NewModule
    {
        foreach ($plan->newModules as $module) {
            if ($module->ref() === $ref) {
                return $module;
            }
        }

        self::fail("No new module {$ref}.");
    }

    /**
     * @return array<string, mixed>
     */
    private static function params(NewModule $module): array
    {
        return (array) json_decode((string) $module->row['params'], true);
    }

    public function testOnlyTheLanguageWithoutModulesGetsThemOneOfEachKind(): void
    {
        $plan = $this->plan(new SiteFixture());

        self::assertSame(
            ['main/fr-FR', 'explanations/fr-FR', 'tutorials/fr-FR', 'contributors/fr-FR'],
            array_map(static fn (NewModule $m): string => $m->ref(), $plan->newModules)
        );
    }

    public function testANewModuleIsACopyOfTheTemplateWithItsOwnMenuAndLanguage(): void
    {
        $module = $this->newModule($this->plan(new SiteFixture()), 'tutorials/fr-FR');

        self::assertSame('fr-FR', $module->row['language']);
        self::assertSame('mainmenu-fr-fr', self::params($module)['menutype']);
        self::assertSame(2, self::params($module)['startLevel']);
        self::assertSame('position-8', $module->row['position']);
        self::assertSame(1, $module->row['showtitle']);
        self::assertArrayNotHasKey('id', $module->row);
        self::assertArrayNotHasKey('asset_id', $module->row);
    }

    public function testTheTopMenuIsOnAllPagesWithTheTopmenuClassAndNoTitleShown(): void
    {
        $module = $this->newModule($this->plan(new SiteFixture()), 'main/fr-FR');

        self::assertSame('Mainmenu fr-FR', $module->row['title']);
        self::assertSame([0], $module->pages);
        self::assertSame(' topmenu', self::params($module)['class_sfx']);
        self::assertSame(1, self::params($module)['startLevel']);
        self::assertSame('position-1', $module->row['position']);
        // The German template shows its title; the English top menu does not.
        self::assertSame(0, $module->row['showtitle']);
    }

    public function testTheTopmenuClassIsAddedWhenTheTemplateLacksIt(): void
    {
        $site = new SiteFixture();
        $site->module(11, 'Mainmenu de-DE', 'position-1', 3, 'de-DE', 1, 'mainmenu-de-de', 1, ['class_sfx' => ''], [0]);

        self::assertSame(' topmenu', self::params($this->newModule($this->plan($site), 'main/fr-FR'))['class_sfx']);
    }

    public function testASideMenuIsOnItsWholeBranchAndOnWhatTheTemplateHasOutsideIt(): void
    {
        $module = $this->newModule($this->plan(new SiteFixture()), 'explanations/fr-FR');

        // Guide, Guide 1, Guide 2, Guide 2a, and Search results as the German one has.
        self::assertSame([301, 302, 303, 304, 309], $module->pages);
    }

    public function testHeadingsAreNotPagesAModuleIsAssignedTo(): void
    {
        self::assertSame([305, 306], $this->newModule($this->plan(new SiteFixture()), 'tutorials/fr-FR')->pages);
    }

    public function testTitlesAreGivenOrTakenFromTheLevelOneItem(): void
    {
        $plan = $this->plan(new SiteFixture());

        self::assertSame('Explications', $this->newModule($plan, 'explanations/fr-FR')->title());
        self::assertSame('Tutorials fr', $this->newModule($plan, 'tutorials/fr-FR')->title());
        self::assertSame('Contributors fr', $this->newModule($plan, 'contributors/fr-FR')->title());
    }

    public function testTheLastPartOfTheLevelOneTitleWhenNoTitleIsGiven(): void
    {
        $site = new SiteFixture();
        $bp   = SiteFixture::blueprint();
        $kind = $bp->kinds[1];
        $bp   = new Blueprint($bp->sourceLanguage, $bp->sourceMenu, $bp->sourceMainTitle, [
            $bp->kinds[0],
            new Kind($kind->key, $kind->sourceModuleId, $kind->templateModuleId, false, [], $kind->fallbackTitle),
            $bp->kinds[2],
            $bp->kinds[3],
        ]);

        $plan = (new Planner($bp))->plan($site->snapshot());

        self::assertSame('Explanations fr', $this->newModule($plan, 'explanations/fr-FR')->title());
    }

    public function testExistingModulesGetThePagesOfTheirBranchTheyMiss(): void
    {
        $plan = $this->plan(new SiteFixture());

        // Headings have no page, so the tutorial group is not missed.
        $add = $plan->addPages;
        ksort($add);

        self::assertSame([
            2  => [104],
            11 => [204, 206, 207, 208, 209],
            12 => [204],
        ], $add);
    }

    public function testAModuleOnAllPagesOrAllButSomeIsLeftAlone(): void
    {
        $site            = new SiteFixture();
        $site->pages[11] = [-209];

        self::assertArrayNotHasKey(11, $this->plan($site)->addPages);
        self::assertArrayNotHasKey(1, $this->plan($site)->addPages);
    }

    public function testTheSourceModulesGetTheSourceLanguageAndTheTopMenuItsTitle(): void
    {
        self::assertSame([
            1 => ['language' => 'en-GB', 'title' => 'Mainmenu'],
            4 => ['language' => 'en-GB'],
        ], $this->plan(new SiteFixture())->updates);
    }

    public function testTheOptionsSwitchTheirPartsOff(): void
    {
        $plan = $this->plan(new SiteFixture(), false, false, false);

        self::assertSame([], $plan->addPages);
        self::assertSame([], $plan->updates);
        self::assertSame([], $plan->homes);
        self::assertCount(4, $plan->newModules);
    }

    public function testTheModulesOfAKindAreTogetherInTheOrderOfTheLanguageCodes(): void
    {
        $plan = $this->plan(new SiteFixture());

        // position-1: Main Menu (2) stays first, then the top menus, then the switcher.
        self::assertSame(2, $plan->orderings[11]);
        self::assertSame(3, $plan->orderings[1]);
        self::assertSame(4, $plan->newOrderings['main/fr-FR']);
        self::assertSame(5, $plan->orderings[9]);

        // position-8: Search first, then de, en, fr of each kind.
        self::assertSame(2, $plan->orderings[12]);
        self::assertSame(3, $plan->orderings[2]);
        self::assertSame(4, $plan->newOrderings['explanations/fr-FR']);
        self::assertSame(5, $plan->orderings[13]);
        self::assertSame(6, $plan->orderings[3]);
        self::assertSame(7, $plan->newOrderings['tutorials/fr-FR']);
        self::assertSame(8, $plan->orderings[14]);
        self::assertSame(9, $plan->orderings[4]);
        self::assertSame(10, $plan->newOrderings['contributors/fr-FR']);
        self::assertArrayNotHasKey(8, $plan->orderings);
        self::assertArrayNotHasKey(10, $plan->orderings);
    }

    public function testALanguageWithoutAHomePageGetsTheTranslationOfTheSourceHome(): void
    {
        self::assertSame(['fr-FR' => 301], $this->plan(new SiteFixture())->homes);
    }

    public function testALanguageWithoutItsMenuIsSkippedWithAWarning(): void
    {
        $site              = new SiteFixture();
        $site->withoutMenu = ['fr-FR'];

        $plan = $this->plan($site);

        self::assertSame([], $plan->newModules);
        self::assertSame([], $plan->homes);
        self::assertStringContainsString('fr-FR: content language without a menu', implode("\n", $plan->warnings));
    }

    public function testAModuleMadeByHandIsRecognisedByItsPagesAndNotMadeAgain(): void
    {
        $site = new SiteFixture();
        $site->module(20, 'Tutoriels', 'position-8', 9, 'fr-FR', 1, 'mainmenu-fr-fr', 2, [], [305, 306]);

        $plan = $this->plan($site);

        self::assertNotContains('tutorials/fr-FR', array_map(static fn (NewModule $m): string => $m->ref(), $plan->newModules));
        self::assertCount(3, $plan->newModules);
    }

    public function testATrashedModuleDoesNotCount(): void
    {
        $site = new SiteFixture();
        $site->module(20, 'Tutoriels', 'position-8', 9, 'fr-FR', 1, 'mainmenu-fr-fr', 2, [], [305, 306], 'mod_menu', -2);

        self::assertCount(4, $this->plan($site)->newModules);
    }

    public function testTwoModulesOfOneKindInALanguageAreReported(): void
    {
        $site = new SiteFixture();
        $site->module(20, 'Tutorials', 'position-8', 9, 'de-DE', 1, 'mainmenu-de-de', 2, [], [205, 206]);

        self::assertStringContainsString('de-DE: 2 tutorials modules (13, 20)', implode("\n", $this->plan($site)->warnings));
    }

    public function testAMissingTemplateStopsThePlan(): void
    {
        $site = new SiteFixture();
        unset($site->modules[13]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('the template module of the tutorials menus, 13');

        $this->plan($site);
    }

    public function testTheUserGuideBlueprintNamesTheMenusAsTranslatorFeedbackDoes(): void
    {
        $bp = Blueprint::userGuide();

        self::assertSame('mainmenu', $bp->menuOf('en-GB'));
        self::assertSame('mainmenu-pt-br', $bp->menuOf('pt-BR'));
        self::assertCount(6, $bp->kinds);
    }
}
