# Easy Email Theme
Easy Email Theme is an email-only theme to be used for sending
HTML-formatted emails. It's designed to be used in conjunction
with the [Easy Email module](https://www.drupal.org/project/easy_email).

## Footer
HTML emails can end with a footer that shows the site name (linked to the
front page) and the site slogan, both from the Basic site settings. It is off
by default. Turn it on in `easy_email_theme.settings`:

```
drush config:set easy_email_theme.settings \
  footer.enabled true --input-format=yaml
```

`footer.site_name` and `footer.slogan` (both `true` unless set) choose which
parts it shows, for example:

```
drush config:set easy_email_theme.settings \
  footer.slogan false --input-format=yaml
```
