# Healthcare

Healthcare is an open source Drupal Site Template designed for medical clinics
and hospital networks. Built on Drupal CMS 2.x, it provides a complete starter
site with pre-configured content types, default content, and a medical-focused
theme. This recipe bundles configuration, content structure, and the Diagnosis
theme into a reusable site starter that can be installed into any Drupal CMS
site.

The template is freely available and designed to give healthcare organizations
a strong, accessible, and scalable digital foundation.

## Built for Healthcare Organizations

Modern healthcare websites must do more than look professional. They must build
trust, support patients, meet accessibility standards, and adapt to evolving
regulations and expectations.

Kanopi Studios partners with clinics, regional hospitals, healthcare systems,
and mission-driven organizations to create digital experiences that:

- Build patient confidence and community trust
- Support accessibility and compliance standards
- Improve findability of services and care providers
- Scale with organizational growth
- Strengthen brand credibility


## Table of contents

- Requirements
- Recommended modules
- Installation
- Configuration
- Maintainers


## Requirements

This recipe requires and installs the following contributed modules:

- [Address](https://www.drupal.org/project/address)
- [Automatic Updates](https://www.drupal.org/project/automatic_updates)
- [Autosave Form](https://www.drupal.org/project/autosave_form)
- [Better Exposed Filters](https://www.drupal.org/project/better_exposed_filters)
- [BPMN.io](https://www.drupal.org/project/bpmn_io)
- [Canvas](https://www.drupal.org/project/canvas)
- [CAPTCHA](https://www.drupal.org/project/captcha)
- [Checklist API](https://www.drupal.org/project/checklistapi)
- [Coffee](https://www.drupal.org/project/coffee)
- [Crop API](https://www.drupal.org/project/crop)
- [Custom Field](https://www.drupal.org/project/custom_field)
- [CVA](https://www.drupal.org/project/cva) (Component Variant API)
- [Dashboard](https://www.drupal.org/project/dashboard)
- [Drupal CMS Helper](https://www.drupal.org/project/drupal_cms_helper)
- [Drupical](https://www.drupal.org/project/drupical)
- [Easy Breadcrumb](https://www.drupal.org/project/easy_breadcrumb)
- [Easy Email](https://www.drupal.org/project/easy_email)
- [ECA](https://www.drupal.org/project/eca) (Event-Condition-Action)
- [Field Group](https://www.drupal.org/project/field_group)
- [Focal Point](https://www.drupal.org/project/focal_point)
- [Friendly Captcha](https://www.drupal.org/project/friendlycaptcha)
- [Geofield](https://www.drupal.org/project/geofield)
- [Gin Login](https://www.drupal.org/project/gin_login)
- [Gin Toolbar](https://www.drupal.org/project/gin_toolbar)
- [Honeypot](https://www.drupal.org/project/honeypot)
- [jQuery UI](https://www.drupal.org/project/jquery_ui)
- [Klaro](https://www.drupal.org/project/klaro)
- [Linkit](https://www.drupal.org/project/linkit)
- [Login Email or Username](https://www.drupal.org/project/login_emailusername)
- [Mail System](https://www.drupal.org/project/mailsystem)
- [Media File Delete](https://www.drupal.org/project/media_file_delete)
- [Media Library Bulk Upload](https://www.drupal.org/project/media_library_bulk_upload)
- [Media Library Edit](https://www.drupal.org/project/media_library_edit)
- [Menu Link Attributes](https://www.drupal.org/project/menu_link_attributes)
- [Metatag](https://www.drupal.org/project/metatag)
- [Modeler API](https://www.drupal.org/project/modeler_api)
- [Navigation Extra Tools](https://www.drupal.org/project/navigation_extra_tools)
- [Office Hours](https://www.drupal.org/project/office_hours)
- [Pathauto](https://www.drupal.org/project/pathauto)
- [Project Browser](https://www.drupal.org/project/project_browser)
- [Redirect](https://www.drupal.org/project/redirect)
- [SAM](https://www.drupal.org/project/sam)
- [Scheduler](https://www.drupal.org/project/scheduler)
- [Schema.org Metatag](https://www.drupal.org/project/schema_metatag)
- [SEO Checklist](https://www.drupal.org/project/seo_checklist)
- [Simple XML Sitemap](https://www.drupal.org/project/simple_sitemap)
- [Smart Date](https://www.drupal.org/project/smart_date)
- [SVG Image](https://www.drupal.org/project/svg_image)
- [Symfony Mailer Lite](https://www.drupal.org/project/symfony_mailer_lite)
- [Tagify](https://www.drupal.org/project/tagify)
- [Token](https://www.drupal.org/project/token)
- [Token OR](https://www.drupal.org/project/token_or)
- [Trash](https://www.drupal.org/project/trash)
- [UI Icons](https://www.drupal.org/project/ui_icons)
- [View Password](https://www.drupal.org/project/view_password)
- [Yoast SEO](https://www.drupal.org/project/yoast_seo)

And the following contributed themes:

- [Canvas Stark](https://www.drupal.org/project/canvas_stark)
- [Diagnosis](https://www.drupal.org/project/diagnosis)
- [Easy Email Theme](https://www.drupal.org/project/easy_email_theme)
- [Gin](https://www.drupal.org/project/gin)


## Recommended projects

This recipe works well with other Drupal CMS recipes such as:

- [Accessibility Tools](https://www.drupal.org/project/drupal_cms_accessibility_tools)
- [AI Assistant](https://www.drupal.org/project/drupal_cms_ai)
- [Google Analytics](https://www.drupal.org/project/drupal_cms_google_analytics)
- [Mailchimp](https://www.drupal.org/project/mailchimp_signup_forms)


## Installation

This recipe should be installed into an existing Drupal CMS site.

1. Install DDEV

   The steps for installing DDEV depend on your computer's operating system.
   [Follow the instructions in the DDEV documentation to install it](https://ddev.readthedocs.io/en/stable/)

1. Create a new Drupal CMS site or use an existing one:

   ```
   ddev composer create-project drupal/cms:^2 my-healthcare-site
   cd my-healthcare-site
   ```

1. Add the Healthcare recipe to your site:

   ```
   ddev composer require drupal/healthcare
   ```

1. Install the recipe using Drush:

   ```
   ddev drush site:install --yes recipes/healthcare
   ```

For further information about installing recipes, see the
[Drupal CMS documentation](https://www.drupal.org/project/cms).


## Configuration

The Healthcare recipe is pre-configured and ready to use after installation:

1. The site includes default content types for medical organizations:
   - Person (staff profiles)
   - Location (clinic/hospital locations)
   - Event (appointments, health events)
   - Post (news and articles)
   - Internal resource (staff resources)

1. Pre-built Canvas pages demonstrate the Diagnosis theme and page builder.

1. The Diagnosis theme is enabled by default.

1. Navigation menus and site structure are configured automatically.

Access the site at your local URL after installation to explore the default
content and begin customizing for your healthcare organization.


## Development

This is a **monolith recipe** exported from a complete Drupal installation. You
should not edit the recipe files directly. To contribute or make changes:

1. **Set up a development Drupal site**:

   ```
   mkdir healthcare-dev && cd healthcare-dev
   ddev config --project-type=drupal11 --docroot=web
   ddev composer create-project drupal/cms
   ddev composer require drupal/healthcare
   ddev drush site:install --yes ../recipes/healthcare
   ```

1. Create or start working on an issue in the [Healthcare project](https://www.drupal.org/project/issues/healthcare?categories=All)
   - Create a fork if one hasn't been created.
   - Click *Show commands*
   - Copy and paste the *Add & fetch this issue fork’s repository* commands

   ```
   git remote add healthcare-[issue-number] git@git.drupal.org:issue/healthcare-[issue-number].git
   git fetch healthcare-[issue-number]
   ```

   - Checkout the branch

   ```
   git checkout -b '[issue-number]update-documentation-after' --track healthcare-[issue-number]/'[issue-number]-update-documentation-after'
   ```

1. **Make your changes** in the development site through the Drupal UI:
   - Configure content types, fields, and views
   - Create or modify Canvas pages and components
   - Install additional modules
   - Configure themes and settings
   - Add default content

1. **Export the updated recipe**:

   ```
   drush site:export ../../healthcare
   ```

   This will regenerate:
   - `recipe.yml` with updated module list and config actions
   - `config/` directory with all configuration (770+ files)
   - `content/` directory with default content

1. **Commit and contribute** your changes to the Healthcare repository.

   ```
   git add ...
   git commit -m "feat: #[issue-number] Description of changes"
   ```

   Use conventional commit types (feat, fix, docs, refactor, etc.) with the issue number.

   - Copy the *Push your current local branch from your Git clone* command

   ```
   git push --set-upstream healthcare-3578112 HEAD
   ```

   - Set the issue to Needs Review.

For more detailed development information, see the CLAUDE.md file in this
repository.


## Maintainers

Current maintainers:
 * [thejimbirch](https://www.drupal.org/u/thejimbirch)
 * [kerrymick](https://www.drupal.org/u/kerrymick)
 * [nkarhoff](https://www.drupal.org/u/nkarhoff)
 * [banoodle](https://www.drupal.org/u/banoodle)

This project has been sponsored by:
 * [Kanopi studios](https://www.drupal.org/kanopi-studios)
