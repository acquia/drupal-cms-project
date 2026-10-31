# Language management

This page covers how this module interacts with the language list: protecting
the locked language from deletion and handling the case where the locked
language no longer exists.

## Language deletion protection

When a lock language is set, deleting that language would leave all active
configuration referencing a langcode that no longer exists. To prevent this,
`hook_entity_access` returns `AccessResult::forbidden()` for delete operations
on the locked language. This removes the delete link from the language admin UI
automatically — no separate confirmation step is needed.

The locked language is also annotated with `(Configuration language)` in the
language overview table to make it clear to administrators why it cannot be
removed. See [Site default language selector](#site-default-language-selector)
for the other annotations in that table.

Both of these only apply when the Language module is installed — it is the
module that provides the `configurable_language` entity type and the language
overview page. Without it the site has a single language that cannot be deleted
in the first place, so there is nothing to protect.

## Site default language selector

Core lets administrators pick the site default language with a radio button in
every row of the language overview table. This module removes those radio
buttons and adds a select list in a **Default language** fieldset below the
table instead. The select list submits the same form value as core's radio
buttons (`site_default_language`), so core's validation and submit handlers
work unchanged, and so does the follow-site-default submit handler.

The fieldset gives the module room to explain why the site default language
cannot be changed when that is the case. See
[Canvas integration](canvas-integration.md) for the one situation where the
select list is disabled.

Because the radio buttons no longer show which language is the site default,
the table marks it in the name column instead:

| Language role | Annotation |
|---|---|
| Site default language | `(Site default language)` |
| Configuration language | `(Configuration language)` |
| Both | `(Site default and configuration language)` |

## Stale lock protection

Despite the deletion protection above, it is still possible for the locked
language to disappear — for example via drush, config sync, or direct database
manipulation. `getLockedLangcode()` guards against this by validating the
stored langcode against the list of currently installed languages before
returning it. If the stored value no longer corresponds to a known language, the
method returns `NULL` and the lock is silently treated as disabled. This
prevents any config from being rewritten to a nonexistent language. So an
accidental invalid lock cannot have wide reaching bad consequences this
way.

The stale state persists in settings until an administrator explicitly changes
or clears the lock on the settings form.

## Related pages

- [UI forms](ui-forms.md) — form-based config entity creation and the langcode selector
- [Lock switch lifecycle](lock-switch-lifecycle.md) — what happens to config when the lock language changes
