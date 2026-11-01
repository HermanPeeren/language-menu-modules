<?php

/**
 * @package     LanguageMenuModules
 *
 * @copyright   Copyright (C) 2026 Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Plugin\Task\LanguageMenuModules\Plan;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The menu modules every language of a site should have.
 */
final class Blueprint
{
	/**
	 * @param   string       $sourceLanguage  The language of the source menu.
	 * @param   string       $sourceMenu      The menutype of the source main menu.
	 * @param   string       $sourceMainTitle The title of the source language's top menu module.
	 * @param   list<Kind>   $kinds           In the order their groups of modules get.
	 */
	public function __construct(
		public readonly string $sourceLanguage,
		public readonly string $sourceMenu,
		public readonly string $sourceMainTitle,
		public readonly array $kinds
	) {
	}

	/**
	 * The main menu of a language: mainmenu for the source, mainmenu-de-de and so on for
	 * the translations, as Translator Feedback names them.
	 */
	public function menuOf(string $language): string
	{
		return $language === $this->sourceLanguage ? $this->sourceMenu : $this->sourceMenu . '-' . strtolower($language);
	}

	/**
	 * The User Guide on guide.joomla.org, as of 1 November 2026. The source modules are
	 * the English ones, the templates the German ones Herman made by hand.
	 *
	 * The titles of the side menus are shown above them, so they are short and carry no
	 * language. The Contributors menu shows no title.
	 */
	public static function userGuide(): self
	{
		return new self('en-GB', 'mainmenu', 'Mainmenu', [
			new Kind('main', 1, 141, true, [], Kind::TITLE_MAINMENU),
			new Kind('explanations', 112, 139, false, [
				'en-GB' => 'Explanations',
				'de-DE' => 'Erklärungen',
				'es-ES' => 'Explicaciones',
				'fa-IR' => 'توضیحات',
				'fr-FR' => 'Explications',
				'it-IT' => 'Spiegazioni',
				'nl-NL' => 'Toelichtingen',
				'pt-BR' => 'Explicações',
				'pt-PT' => 'Explicações',
				'ru-RU' => 'Пояснения',
			], Kind::TITLE_TOP_ITEM_LAST_PART),
			new Kind('tutorials', 137, 142, false, [
				'en-GB' => 'Tutorials',
				'de-DE' => 'Tutorials',
				'es-ES' => 'Tutoriales',
				'fa-IR' => 'آموزش‌ها',
				'fr-FR' => 'Tutoriels',
				'it-IT' => 'Tutorial',
				'nl-NL' => 'Zelfstudies',
				'pt-BR' => 'Tutoriais',
				'pt-PT' => 'Tutoriais',
				'ru-RU' => 'Руководства',
			], Kind::TITLE_TOP_ITEM),
			new Kind('howto', 132, 143, false, [
				'en-GB' => 'How-to Guides',
				'de-DE' => 'Anleitungen',
				'es-ES' => 'Guías prácticas',
				'fa-IR' => 'راهنماهای عملی',
				'fr-FR' => 'Guides pratiques',
				'it-IT' => 'Guide pratiche',
				'nl-NL' => 'How-to-handleidingen',
				'pt-BR' => 'Guias práticos',
				'pt-PT' => 'Guias práticos',
				'ru-RU' => 'Практические руководства',
			], Kind::TITLE_TOP_ITEM),
			new Kind('adminpages', 133, 144, false, [
				'en-GB' => 'Help pages',
				'de-DE' => 'Hilfeseiten',
				'es-ES' => 'Páginas de ayuda',
				'fa-IR' => 'صفحات راهنما',
				'fr-FR' => "Pages d'aide",
				'it-IT' => 'Pagine di aiuto',
				'nl-NL' => "Helppagina's",
				'pt-BR' => 'Páginas de ajuda',
				'pt-PT' => 'Páginas de ajuda',
				'ru-RU' => 'Страницы справки',
			], Kind::TITLE_TOP_ITEM),
			new Kind('contributors', 111, 145, false, [], Kind::TITLE_TOP_ITEM),
		]);
	}
}
