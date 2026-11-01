# Findings: menu modules for translated menus

A preliminary study for generating menu modules in Translator Feedback. Everything here
was found on a copy of the User Guide (guide.joomla.org) of 1 November 2026: 10 content
languages, a main menu of 685 items in each, the English and German menu modules made
by hand.

## How the User Guide's menu works

- **A split menu.** One `mod_menu` on `position-1` shows level 1 of the main menu
  (`startLevel` 1, `endLevel` 1, class suffix ` topmenu`). Five `mod_menu`s on
  `position-8` show level 2 and below (`startLevel` 2, `endLevel` 0, `showAllChildren`
  1), one for each level 1 item: Explanations, Tutorials, How-to Guides, Admin Pages
  References, Contributors.
- **A side menu on its own shows the wrong branch.** A menu module starting at level 2
  shows the children of whatever level 1 item the current page is under. So each side
  module is assigned to the pages of *its* branch only (`#__modules_menu`), which is
  also what lets it carry a title of its own ("Tutorials"). Two of the English modules
  also set `base` (How-to Guides: 590, Contributors: 113), which pins the branch; the
  German copies leave `base` empty and rely on the assignment alone. A translated module
  must not copy a `base` id: it names an English menu item.
- **Language.** With the language filter on, a module with a language is shown only on
  pages of that language; a module with *All* (`*`) on all languages' pages, as far as
  its assignment allows. The English *Main Menu* had *All* and was on all pages, so it
  showed on every translated page too, above that language's own top menu. The English
  side modules had *All* or *English*; it made no difference because they are assigned
  to English pages only. Every module of a translated menu should have its language,
  and so should the source's.
- **Module titles.** The side menus show their title (`showtitle` 1), so it is short,
  in the language, and without a language code. The top menu and the Contributors menu
  show none. The German *Mainmenu de-DE* has `showtitle` 1, but its chrome (`style` "0",
  none) does not print titles; a generated top menu is safer with `showtitle` 0, like the
  English one.

## What a module is in the database

Saving a module in the backend writes:

1. A row in `#__modules`: `title`, `position`, `ordering`, `published`, `module`,
   `access`, `showtitle`, `params` (JSON; Joomla's Registry writes it with plain
   `json_encode()`, so `/` and non-ASCII are escaped), `client_id` 0, `language`,
   `publish_up`/`publish_down`, `note`, `content` NULL, `checked_out` NULL.
2. Its pages in `#__modules_menu` (`moduleid`, `menuid`): one row `0` for all pages,
   positive ids for "only on these", negative ids for "on all but these", no row for
   "on no page".
3. An asset in `#__assets`: `name` `com_modules.module.<id>`, `title` the module's
   title, `rules` `{}` (everything inherited), `parent_id` the `com_modules` asset,
   `level` 2. `#__modules.asset_id` points at it.

`#__assets` is a nested set. A new asset goes in as the last child of `com_modules`:
take that asset's `rgt` R, add 2n to every `rgt >= R` and every `lft > R`, and give the n
new assets `lft` R, R+2, ... and `rgt` one more. On the User Guide that moves the
assets after `com_modules` (about 50) and the root, in two statements.

No other table is involved: modules have no workflow, no versions, no fields, no tags,
no UCM rows.

Found along the way:

- **Orphan assets.** Assets `com_modules.module.110`, `.116`, `.117` and `.128` belong to
  no module: those modules were deleted without their asset. Harmless, but a sign that
  something deletes modules without going through the module table. Possibly related to
  the trouble saving modules on the site.
- `AUTO_INCREMENT` of `#__modules` and `#__assets` moves on even when a transaction is
  rolled back (InnoDB never gives ids back). Harmless.
- `ordering` is per position (and client), from 1. Joomla does not keep it contiguous;
  the User Guide's was.

## How the German modules were made from the English ones

Compared through `#__associations` (context `com_menus.item`):

- Every side module's pages are exactly the translations of the English module's pages.
  The German Explanations module has two more: *Suchergebnisse* (Search results) and
  *Abmelden* (Logout), level 1 items outside the branch.
- *Mainmenu de-DE* is assigned to 634 chosen pages instead of all pages. It misses 54
  items of its menu, but they are all headings and one alias.
- The English modules seem to miss pages too: *Help pages* misses all 50 level 2 and 3
  group items under Admin Pages References, *Tutorials* misses *Content Management*,
  *Site Building* and *Site Maintenance*, *Explanations* misses *Schema.org*. But those
  are **headings** and one **alias**: they have no page of their own (an alias shows the
  page of its target), so there is nothing to show a module on. Comparing a module's
  pages with its branch has to leave out the menu item types `heading`, `separator`,
  `url` and `alias`.

## Choices made here

- **Copy the German module, not the English one.** The German ones are what Herman made
  for a translated language: language set, `base` emptied, the menu of the language.
- **A side module is on its whole branch**, the pages of the branch counted as above,
  plus the translations of the template's pages outside its branch. On the User Guide
  that gives exactly the translations of the German module's pages, for all 40.
- **The top menu is on all pages**, with its language. The language filter then shows
  the right one on every page, and new pages need nothing. (The German one, assigned to
  chosen pages, would miss every page added after it was made.)
- **One module per language and kind.** An existing module is recognised by its menu
  (`mainmenu-xx-xx`), its start level, and for a side menu the branch most of its pages
  are in. So modules made by hand are kept, and a second run makes nothing.
- **Order kind by kind, then by language code**, matching the language switcher once the
  content languages are ordered by code. Only one module of a kind is shown on a page, so
  the order within a kind is for the administrator, not the visitor.
- **Titles** of the side menus are given per language; where none is given, the level 1
  item's title (for Explanations its last part, after " / "). The Contributors title is
  never shown, so it is the level 1 title.

  | | Explanations | Tutorials | How-to Guides | Admin Pages References | Contributors |
  |---|---|---|---|---|---|
  | en-GB | Explanations | Tutorials | How-to Guides | Help pages | Contributors menu |
  | de-DE | Erklärungen | Tutorials | Anleitungen | Hilfeseiten | Mitwirkende |
  | es-ES | Explicaciones | Tutoriales | Guías prácticas | Páginas de ayuda | Colaboradores |
  | fa-IR | توضیحات | آموزش‌ها | راهنماهای عملی | صفحات راهنما | مشارکت کنندگان |
  | fr-FR | Explications | Tutoriels | Guides pratiques | Pages d'aide | Contributeurs |
  | it-IT | Spiegazioni | Tutorial | Guide pratiche | Pagine di aiuto | Collaboratori |
  | nl-NL | Toelichtingen | Zelfstudies | How-to-handleidingen | Helppagina's | Bijdragers |
  | pt-BR | Explicações | Tutoriais | Guias práticos | Páginas de ajuda | Colaboradores |
  | pt-PT | Explicações | Tutoriais | Guias práticos | Páginas de ajuda | Colaboradores |
  | ru-RU | Пояснения | Руководства | Практические руководства | Страницы справки | Авторы |

  These are mine, not a translator's: worth a look by the translation teams.

## The language switcher

`mod_languages` lists a content language only when it is published, its site language
is installed, and it has a **home page**: a published menu item with `home` 1 and that
language. Before the run only English and German had one. The task makes the
translation of the English home page (*User Guide / Explanations*, id 118) the home page
of each language. There is one home page per language and client: setting one means
unsetting any other of that language, as Joomla's menu table does. The order in the
switcher is the order of the content languages (`#__languages.ordering`).

## Other modules that need the same care

Not changed by this task, but they have per-page assignments that do not cover all
languages:

- **Search** (`mod_finder`, 115) is on all pages except the Contributors pages, but only
  the English and German ones are excluded: in the other languages it shows on the
  Contributors pages. Its `set_itemid` is 189, *Installing an extension*, an English
  article: search results are shown under an English menu item, also from a translated
  page.
- **RO Single Sign On Login** (`mod_sso`, 125) is on the Contributors pages of en-GB (5 of
  9), de-DE, es-ES, fa-IR, fr-FR, it-IT and nl-NL, one page of pt-PT, and none of pt-BR
  and ru-RU.

## For Translator Feedback

What a generator of menu modules needs, learned here:

- **The modules of the source language are the definition.** For each menu module of
  the source menu, the translated menu gets a copy with the menu, language and title of
  the translation. Better still, let the user point at one translated language's
  modules as the template, as here: they carry the decisions (no `base`, extra pages,
  the title shown or not) a mechanical copy of the source cannot make.
- **Page assignments must follow the menu, and keep following it.** A side menu module
  is useful only when assigned to exactly its branch. Each time a translated menu item
  is added (which Translator Feedback does), it must be added to the pages of the module
  that shows its branch in that language, or that module does not show on it. A task
  like this one, with *Complete the pages of existing modules*, repairs it afterwards;
  doing it when the menu item is made is better. Map assignments through
  `#__associations`, and skip headings, separators, URLs and aliases.
- **Ids in params must be translated or dropped.** `base` (and in other modules things
  like `set_itemid`) name menu items of the source language.
- **Recognise what exists.** A module of a translated menu is known by its menu, start
  level and the branch of its pages; nothing in the row says "this was generated". A
  generator could write a `note` to recognise its own modules later.
- **Write it in one transaction and check before committing.** Module row, its pages,
  its asset and the nested-set shift all together. The checks that proved useful: the
  asset tree is whole (one walk from the root reproduces every `lft`, `rgt` and
  `level`), every new module has its own asset under `com_modules`, and planning again
  finds nothing to do.
- **Home pages.** A generated translated menu should get its home page right away,
  otherwise the language does not show in the switcher.
- **Ordering.** Put the modules of one kind together, in the order of the content
  languages; it keeps the module manager readable with many languages.

## How this was tested

On a copy of the User Guide's database, with the task run through Joomla's CLI
scheduler:

- 34 unit tests on a small three-language site (planner, run, nested set);
- `tools/verify-modules.php`, which shares no code with the plugin: before the run it
  reports the 8 languages without modules and home page and the English modules with
  *All*; after it, all 10 languages are complete and `#__assets` is whole;
- the pages of every new side module compared, through the associations, with those of
  the German module of its kind: 40 of 40 identical;
- a forced failure of the check before the commit: all 48 modules, 48 assets and 5056
  page rows were there inside the transaction, and none after the rollback;
- a second run: "Nothing to do";
- `tools/check-pages.php`: 140 real pages in 10 languages, each showing its own top menu
  and only the side menu of its branch, with all 10 languages in the language switcher.
