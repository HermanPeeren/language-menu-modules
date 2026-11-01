# Language Menu Modules (temporary)

A Joomla 6.1+ task plugin that gives every content language of the User Guide
(guide.joomla.org) its menu modules, written straight into the database, so the site
can be shown in all its languages.

The User Guide's menu is a *split menu*: one module on top shows level 1 of the main
menu, five modules on the side show level 2 and below of one branch each. Every language
needs those six modules for its own main menu (`mainmenu-de-de`, `mainmenu-fr-fr`, ...).
Saving modules in the backend does not work well on the site at the moment, so this
task makes them in the tables, the way the backend would. The same will come to
Translator Feedback later; see [docs/findings.md](docs/findings.md), which keeps
everything learned on the way.

## What it does

The plugin adds one task type to **System > Scheduled Tasks**, **Language menu modules:
create**. For every published content language with a main menu `mainmenu-xx-xx`, it
makes the modules that language does not have yet, as copies of the German ones Herman
made by hand:

| Kind | Title | Position | Shows | On the pages |
|---|---|---|---|---|
| Top menu | `Mainmenu xx-XX` (title not shown) | position-1 | level 1 | all pages; the language filter shows only the module of the page's language |
| Explanations | e.g. *Explications* | position-8 | level 2 on | the branch *User Guide / Explanations*, plus *Search results* and *Logout* |
| Tutorials | e.g. *Tutoriels* | position-8 | level 2 on | the branch *Tutorials* |
| How-to Guides | e.g. *Guides pratiques* | position-8 | level 2 on | the branch *How-to Guides* |
| Admin Pages References | e.g. *Pages d'aide* | position-8 | level 2 on | the branch *Admin Pages References* |
| Contributors | the level 1 title (not shown) | position-8 | level 2 on | the branch *Contributors* |

Each new module has the German module's settings, with the language's own menu and
language. The top menu always has the class suffix ` topmenu`. A side menu is assigned
to every page of its branch (headings, separators, URLs and aliases have no page of their
own and are left out) and, like the German one, to the pages it has outside it.

Further, with the options on (the default):

- **Order**: in each position, the menu modules are put together kind by kind (all top
  menus; all Explanations, all Tutorials, ...), and within a kind in the order of their
  language codes: de-DE, en-GB, es-ES, fa-IR, ... The other modules of the position
  keep their places.
- **Give the English modules their language**: the English menu modules that have
  language *All* get *English*, and the English top menu module is titled *Mainmenu*.
  Without this the English top menu, which is on all pages, also shows on every page of
  the other languages, above their own.
- **Set the home page of every language**: a language without a home page gets the
  translation of the English home page (*User Guide / Explanations*), so it shows in the
  language switcher.
- **Complete the pages of existing modules**: a menu module that already exists and is
  assigned to chosen pages also gets the pages of its branch it is missing. On the site
  as of 1 November 2026 there are none; this is for menu items added later.

A module that exists is never changed beyond that. A language that has a module of a
kind already gets no second one, so running the task again changes nothing.

Everything is written in one database transaction. Before it commits, the task checks
what it wrote: `#__assets` is still a whole nested set, every new module has its own
asset, and planning again finds nothing left to do. If any of that fails, nothing is
written. It then clears the site's cache of modules, menus and languages.

On a copy of the User Guide of 1 November 2026 (10 languages, of which en-GB and de-DE
had their modules) the run makes 48 modules (6 for each of 8 languages), gives 4 English
modules their language, reorders 13 modules and sets 8 home pages, in about 4 seconds.

## Using it

1. Take a backup of the site (Akeeba Backup), or at least of `#__modules`,
   `#__modules_menu`, `#__assets` and `#__menu`.
2. Put the content languages in the order of their codes (*System > Content
   Languages*), if that is the order the language switcher should have.
3. Install the package: `composer build` makes `build/plg_task_languagemenumodules-<version>.zip`,
   or take it from the [releases](https://github.com/HermanPeeren/language-menu-modules/releases).
4. Create a task **Language menu modules: create**. Keep **Dry run** on and run it. The
   task log lists every module it would make, with title, position, ordering and
   number of pages, and everything else it would change.
5. Switch **Dry run** off and run it again.
6. Look at the site in a few languages, and uninstall the plugin.

`php tools/verify-modules.php <path-to-site>` checks the outcome on its own, read-only:
every language has its six modules, in its language, on the pages of its branch, in the
right order, with a home page, and `#__assets` is whole.

## Requirements

Joomla 6.1 or later (the plugin is loaded lazily, which Joomla 6.0 cannot do), PHP 8.3,
MySQL or MariaDB with InnoDB tables. The menus need their Multilingual Associations, as
Translator Feedback makes them. The module ids of the English and German modules are in
`Blueprint::userGuide()`: this plugin is for the User Guide only.

## Development

See [docs/development.md](docs/development.md).

## Licence

GNU General Public License version 3 or later; see [LICENSE](LICENSE).
