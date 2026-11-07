---
title: Installation
---

# Installation

Local is a Drupal Site Template. Apply it to Drupal CMS at installation time.

## Before you start

You'll need Drupal CMS installed and running locally. Follow the [Drupal CMS installation guide](https://project.pages.drupalcode.org/drupal_cms/get-started/install/) to get up to speed with pre-requesites.

## Install the Local Site Template

Require the recipe with Composer:

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

## What gets installed

- The **Consistent** front-end theme and **Gin** admin theme
- Three content types: Service landing page, Service page, and Utility page
- Demo service sections with example service pages
- Canvas pages complete with components for Home, About, and What's On
- ECA workflows for service page automation and content management
- Search API
- Klaro cookie consent, Easy Email, and content moderation
