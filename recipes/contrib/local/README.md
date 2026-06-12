# Local — Drupal CMS Site Template

A site template for local community organisations built on [Drupal CMS](https://www.drupal.org/project/cms). 

## Team

Local was created by [Annertech](https://www.annertech.com/) with contributions from:

- [Tony Barker](https://www.drupal.org/u/tonypaulbarker)
- [Luke Brennan](https://www.drupal.org/u/lukejosephbrennan)
- [Fatima Mahmood](https://www.drupal.org/u/fatimamahmood)
- [Stella Power](https://www.drupal.org/u/stella)
- [Mike King](https://www.drupal.org/u/emkay)

## Documentation

[Local Site Template Documentation](https://project.pages.drupalcode.org/local/)

## What's included

- **Content types** — Service page and Service landing page, with Pathauto clean URLs
- **ECA automation** — Service landing pages stay in sync automatically when service pages are published or updated
- **Canvas pages** — Home, About, and What's on, laid out with Canvas components
- **Demo content** — Five service sections (Waste, Local Tax, Housing, Parks, Libraries) with 25+ service pages
- **Consistent theme** — A clean, accessible theme built on the GOV.UK Design System colour palette

## Quick start

### 1. Create the Drupal CMS project

```
mkdir my-project
cd my-project
ddev config --project-type=drupal11 --docroot=web
ddev composer create-project drupal/cms
```

### 2. Get the recipe and theme

```shell
ddev composer require drupal/local
```

---

## Usage

Start the DDEV environment:

```bash
ddev start
ddev launch
```