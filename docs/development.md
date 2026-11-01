# Developing the language menu modules plugin

## Layout

`src/` is the root of the installable package, laid out as it will be on a site:

```
src/
  languagemenumodules.xml                    the manifest; <files folder="plugins/task/languagemenumodules">
  script.php                                 install script: Joomla 6.1 / PHP 8.3 checks, enables the plugin
  plugins/task/languagemenumodules/
    services/provider.php                    the composition root
    forms/create.xml                         the task's parameters
    language/en-GB/                          plg_task_languagemenumodules(.sys).ini
    src/
      Extension/LanguageMenuModules.php      the task plugin: parameters in, exit code out, cache cleaning
      Run/Creator.php                        one run: read, plan, log, write, check before commit
      Site/                                  MenuItem, Module, Snapshot: the site as read, no database
      Plan/Blueprint.php, Kind.php           which modules every language gets; the User Guide's
      Plan/Planner.php                       the snapshot in, the Plan out: new modules, pages, orderings, homes
      Plan/Plan.php, NewModule.php           what is written
      Check/NestedSet.php                    is #__assets a whole nested set
      Store/                                 the tables behind ModuleStore
tests/
  Unit/                                      PHPUnit, no Joomla, no database
  Support/                                   a small three-language User Guide, an in-memory store
build/build.php                              zips src/ into build/plg_task_languagemenumodules-<version>.zip
tools/                                       install-local.php, verify-modules.php, check-pages.php, phpstan-bootstrap.php
```

The namespace is `Yepr\Plugin\Task\LanguageMenuModules`.

## How the plan is made

A `Kind` is one of the six modules every language gets, defined by two modules on the
site: the English one (the source) and the German one (the template). From the source
module the planner learns the branch: the level 1 item of `mainmenu` most of its pages
are under. From the template it learns the settings to copy and the pages it has
outside its branch (*Search results* and *Logout* for Explanations).

For each language and kind, an existing module is looked for: a menu module showing the
language's main menu, of the top menu kind when it starts at level 1, else of the side
menu whose branch most of its pages are in. When there is none, a `NewModule` is planned:
the template's row with the language, the menu, a title, and its pages. The pages of a
branch are those of the translation of the source's level 1 item, found through
`#__associations`.

`#__modules_menu` holds a module's pages: 0 for all pages, a negative id for "all but
this one", a positive id for "only on this one".

## The development site

`/joomla` holds Joomla 6.2.0, git-ignored. It was installed into the database of a
copy of the User Guide and then pointed at the User Guide's tables (`dbprefix`
`xlhje_`), so the task runs on the real modules and menus:

```bash
curl -sSL -o joomla.tar.gz https://github.com/joomla/joomla-cms/releases/download/6.2.0/Joomla_6.2.0-Stable-Full_Package.tar.gz
mkdir -p joomla && tar -xzf joomla.tar.gz -C joomla && rm joomla.tar.gz
php joomla/installation/joomla.php install -n --site-name="Language menu modules dev" --admin-user=Admin --admin-username=admin --admin-password=<a local test password> --admin-email=admin@example.invalid --db-type=mysqli --db-host=127.0.0.1:3307 --db-user=root --db-pass= --db-name=guide-menumodules --db-prefix=jlm_
rm -rf joomla/installation
# import the dump of the User Guide into guide-menumodules, then in joomla/configuration.php:
#   public $dbprefix = 'xlhje_';
composer install-local
```

Keep a copy of the tables the task writes, to start again from:

```bash
mariadb-dump -h127.0.0.1 -P3307 -uroot guide-menumodules xlhje_modules xlhje_modules_menu xlhje_assets xlhje_menu xlhje_languages > xdiv/pristine-tables.sql
mariadb -h127.0.0.1 -P3307 -uroot guide-menumodules < xdiv/pristine-tables.sql
```

Create a task (in *System > Scheduled Tasks*, or with a row in `xlhje_scheduler_tasks` of
type `languagemenumodules.create`, params
`{"dry_run":1,"complete_pages":1,"fix_source":1,"set_homes":1}`), run it and check:

```bash
php joomla/cli/joomla.php scheduler:run --id=<id>
php tools/verify-modules.php
```

The task log is `joomla/administrator/logs/joomla_scheduler.php`.

### The pages themselves

`tools/check-pages.php` fetches, for every language, the home page and pages of each
branch (level 1, 2 and the deepest), and checks which modules Joomla selects there. The
User Guide's template is not in the dump, so the development site needs two stand-ins,
both inside the git-ignored `/joomla`:

- `joomla/templates/joomla/index.php`, a template that writes the modules Joomla selects
  for position-1 and position-8 (`ModuleHelper::getModules()`), and the rendered
  language switcher, as JSON in an HTML comment `<!--LMM{...}LMM-->`;
- a folder `joomla/language/xx-XX` for every content language, a copy of `en-GB` with
  its own `<tag>` in `langmetadata.xml` (and `<rtl>1</rtl>` for fa-IR). Without the
  language installed in the files, Joomla cannot serve its pages.

The site must be served by the local web server (WampServer:
`http://localhost/language-menu-modules/joomla/`). Run it after the task:

```bash
php tools/check-pages.php
```

## Commands

| Command | What it does |
|---|---|
| `composer test` | PHPUnit |
| `composer analyse` | PHPStan, level 8, against the Joomla in `/joomla` |
| `composer cs` | phpcs: PSR-12, with Joomla's tabs in `src/` |
| `composer cs-fix` | php-cs-fixer on tests, build and tools |
| `composer build` | the package zip in `build/` |
| `composer install-local` | build, then install into `/joomla` through Joomla's CLI |
| `composer verify` | `tools/verify-modules.php` on `/joomla` |

## Releasing

Bump `<version>` in `src/languagemenumodules.xml`, commit, and push a tag `v<version>`.
The release workflow checks that tag and manifest agree, runs the checks, builds the
package and attaches it to a GitHub release.
