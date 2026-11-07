# AGENTS.md

Drupal contrib module `drupal/token_or` (https://www.drupal.org/project/token_or).
Adds "OR" fallback syntax to tokens: `[node:field_a|node:field_b|"literal"]`
returns the first non-empty value. Supports Drupal ^9.2 || ^10 || ^11 (D8 dropped).
Branch `2.x` is the main branch.

## How it works

- `src/TokenOrServiceProvider.php` swaps the class of the `token` service to
  `src/Token.php`. This is a class swap, not a decorator, so any other module
  that also swaps or decorates `token` can conflict.
- `src/Token.php` overrides:
  - `replace()` / `replacePlain()`: fire `hook_tokens_pre_alter()` before
    calling the parent.
  - `scan()`: excludes `|` from token names, then also scans each OR'd
    sub-token so token info/validation UIs see them.
- `token_or.module` implements `hook_tokens_pre_alter()` and hands off to the
  `token_or.tokens_pre_alter` service (`src/TokenOrTokensPreAlter.php`), which
  resolves each sub-token in order and substitutes the first non-empty result.
  Quoted sub-tokens (`"..."`) are literal strings. If nothing matches, the OR
  token is removed only when `$options['clear']` is truthy.
- Submodule `modules/token_or_webform`: decorates `webform.token_manager` to
  force `clear` for OR tokens and strip `[current-user:...]` alternatives for
  anonymous users (mirrors Webform's own anonymous handling).

## Tests

- `tests/src/Kernel/TokenOrTest.php`: core behavior, run against both
  `replace` and `replacePlain`. Test tokens come from
  `tests/modules/token_or_test` (`[token_or:test]`, `[token_or:test2]`,
  `[token_or:empty]`, `[token_or:empty2]`).
- `modules/token_or_webform/tests/`: Kernel and Functional tests; the
  `*BrokenTest` classes cover behavior without the decorator.
- CI: drupal.org GitLab templates (`.gitlab-ci.yml`), which run PHPUnit,
  phpcs (Drupal/DrupalPractice), PHPStan, and cspell. Add new legitimate words
  to `.cspell-project-words.txt` (`_cspell_unrecognized_words.txt` is a CI
  artifact, not an input).
- Local: ddev-drupal-contrib. First run: `ddev start && ddev poser &&
  ddev symlink-project`. Then `ddev phpunit`, `ddev phpcs`, `ddev phpstan`.
  Switch core with `ddev core-version ^10` (no arg = default ^11); switch PHP
  via `php_version` in `.ddev/config.local.yaml` (uncommitted), then
  `ddev restart && ddev poser`.

## Issue queue

Use the `drupalorg` CLI (mglaman/drupalorg-cli), not web scraping. Before
issue/MR work, load its current instructions with
`drupalorg skill:get drupalorg-cli` (also: `drupalorg-work-on-issue`,
`drupalorg-issue-search`, `drupalorg-issue-summary-update`).

- Pass `--format=llm` to read commands.
- `project:issues` defaults to `--core=8.x` and returns nothing here; use
  `drupalorg project:issues token_or --core=2.x`.
- MRs are addressed by issue nid + MR iid: `drupalorg mr:diff 3591318 26`.

## Conventions

- Drupal coding standards (2-space indent, docblocks on everything).
- Commit messages: use the one drupal.org generates on the issue's
  "Credit & committing" section (`fix: #<nid> <issue title>` plus one
  `By: <user>` line per credited user). Ask the maintainer for it rather than
  writing credit lines by hand. Older history uses
  `Issue #<nid> by <user>, <user>: <title>`.
- Never add `Co-Authored-By:` or other AI attribution trailers: drupal.org's
  release notes list every trailer as a contributor.
- Changes to behavior need a Kernel test case; add a sub-case for both
  `replace` and `replacePlain` when touching the replacement path.
- Keep D9.2 compatibility on 2.x.

## Roadmap

- Drupal 12 support ships as a new 3.x branch, which also drops Drupal 9
  (`core_version_requirement: ^10 || ^11 || ^12`). Do not add D12 to 2.x.
  #3603790 (automated D12 fixes) targets 2.x-dev and should land on 3.x.
- After 3.x exists, 2.x gets bug and security fixes only.
- When a branch requires Drupal 11+, declare `scan(string $text)` to match
  core (typed since 11.0) and drop the NULL guard (see #3337496). Not possible
  while D9/D10 are supported: narrowing the parent's untyped param is fatal.
