# AGENTS.md - ECA module

Module-specific instructions for agents working inside
`web/modules/contrib/eca/` (the ECA contrib module repository).

## Always run SCOPED PHPUnit tests for MR work

ECA's PHPUnit suite is very large and takes far too long to run in full. When
working on a merge request in this module, **always run scoped PHPUnit tests**
- target the specific test class(es) or method(s) relevant to the change.
**Never run the entire ECA suite** as part of MR work.

This is a policy, not a how-to: the supported mechanism for scoping a run
(passing `--filter=...` to the `l3d ahoy test phpunitmodule eca` wrapper) is
documented in the `l3d` skill. Use that mechanism - do not invent your own
phpunit invocation.

## Provide a recipe for manual MR reviews

Most bug fixes and new features in ECA are best verified by a reviewer manually
exercising the change on a running site, in addition to any automated tests.
To make that reproducible, provide a **Drupal recipe** that puts a fresh site
into exactly the state needed to reproduce or verify the change.

### When to provide one

Provide a recipe when the change is best proven by interacting with a running
site, for example:

- A new or changed Event, Condition, or Action that needs an ECA model to
  demonstrate it.
- A bug fix whose reproduction requires specific configuration or content.
- Any change where "do X, then observe Y" is the clearest proof it works.

Skip it when the change is fully covered by automated tests and needs no manual
interaction - for example an internal refactor, a docs-only change, or a fix
already proven by a new PHPUnit test.

### How to build the recipe

Produce a self-contained recipe directory using the standard Drupal recipe
layout:

- `recipe.yml` - name, description, `install` (the modules to enable, e.g.
  `eca_base`, `eca_content`, plus any contrib dependencies the scenario needs),
  and `config`.
- `composer.json` - `"type": "drupal-recipe"` with the module dependencies.
- `config/` - the configuration to import: content types, fields, and the ECA
  model(s) (`eca.eca.*.yml`), etc.
- content to load - only when the scenario needs sample data.
- `README.md` - a one-paragraph description plus the commands to apply it.

The recipe must apply cleanly on a **fresh Drupal site** and leave it in the
exact state required to reproduce or verify the change.

Do not assume the install profile provides anything beyond its own
configuration. As of Drupal 11.4 the Standard profile no longer creates the
`article` or `page` content types; separate core recipes do. A long-lived
development site usually still has those bundles from earlier work, which hides
the problem locally and breaks the recipe for the reviewer. If the scenario
needs them, have the recipe provision them itself:

    recipes:
      - core/recipes/article_content_type
      - core/recipes/page_content_type

A `recipes:` value containing `/` is resolved relative to the Drupal root,
while a bare name is resolved relative to the including recipe's own directory
- so core recipes need the root-relative form.

Be aware that including another recipe also applies its config actions, which
can rewrite existing configuration on a site that is not fresh. Core's
`article_content_type`, for example, normalizes
`core.entity_form_display.node.article.default`. Mention that in the recipe's
`README.md` and tell the reviewer to use a throwaway site.

### Verify the recipe in both code states

When the recipe demonstrates a fix, verify that it **applies** both with and
without the change, not only that the runtime behavior differs. A recipe that
installs solely on patched code blocks the very comparison the reviewer is
trying to make, and that failure is the first thing they run into.

To capture before and after when the recipe genuinely needs the change in order
to install, apply it with the change in place and capture the "after" output,
then revert only the patched hunk, rebuild caches and capture "before", then
restore. The configuration is already in the database, so the reverted run
really does exercise unpatched code.

Avoid syntax in model configuration that older ECA releases cannot load. A bare
`": "` in message text is scanned for tokens by `DependencyCalculation`, and
before the #3590349 guard it reached `hasDefinition()` with the whole string
and threw "Bundle entity types are not supported directly." Prefer `" -> "` as
a separator so the recipe also applies on earlier releases.

### Where the recipe goes

**Attach the recipe to the merge request - do NOT commit it to the module
repository.** Package the recipe directory as an archive (e.g. `.zip` or
`.tar.gz`) and attach it to the MR description or an MR comment. These review
recipes are throwaway verification aids tied to a single MR; they are not part
of the module's shipped code and must not enter the repository history.

The GitLab uploads API cannot be reached from the command line: `glab` cannot
send file upload requests, so `POST /projects/<id>/uploads` fails. Build the
archive, report where it is, and let a human attach it through the web
interface. Do not base64 the archive into the MR description or a comment -
that works, but it is unpleasant to review.

### Instructions for the reviewer

Every MR that ships a review recipe must include a short, copy-pasteable
"Manual testing" block in the MR description so the reviewer can use it without
guesswork. Keep the steps agnostic to any particular local development
environment - describe the Drupal-level actions, not a specific tooling
wrapper. For example:

    1. Start from a fresh Drupal site that meets the module's core and PHP
       requirements.
    2. Download and unpack the attached recipe into a directory the site can
       reach (e.g. `recipes/<name>`).
    3. Apply it:
       `drush recipe recipes/<name>` (Drush 13+), or
       `cd web && php core/scripts/drupal recipe ../recipes/<name>`
    4. Rebuild caches: `drush cr`
    5. <the specific steps to observe the fixed or new behavior, and the
       expected result>

Do not reference a specific local development environment (such as any Docker
or virtualization wrapper) in these instructions - the reviewer may use any
setup, so the steps must work regardless of how the site is hosted.

Make sure those instructions also cover:

- The **permissions** the reviewer's account needs. A missing permission can
  make an ECA chain stop part way through, which looks like a broken feature
  rather than a missing grant.
- Both the page source and what the reviewer actually sees on screen, whenever
  the change concerns escaping. Escaped markup shows literal angle brackets to
  a human and `&lt;em&gt;` only in the HTML source, so quoting entities as
  "what you will see" is wrong.
- Which rows of an expected-output table are meant to change, and which are
  no-regression controls.
