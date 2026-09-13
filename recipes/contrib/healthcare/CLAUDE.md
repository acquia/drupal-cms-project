# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is an **open source Drupal Site Template** called "Healthcare", designed for medical clinics and hospital networks. Built on Drupal CMS 2.x, it provides a complete starter site with pre-configured content types, default content, and a medical-focused theme.

**Key architectural components:**
- **Type**: `Site` recipe (monolith recipe exported from a complete Drupal installation)
- **Export method**: Generated using `drush site:export` from drupal_cms_helper module
- **Theme**: "Diagnosis" theme with CVA (Component Variant API) support
- **Page builder**: Canvas module for component-based page building
- **Default content**: Pre-built pages, nodes, media, and taxonomies exported as configuration
- **Target audience**: Medical clinics, hospital networks, healthcare systems, and mission-driven health organizations

**What Healthcare provides:**
- Pre-configured content types for healthcare (Person, Location, Event, Post, Internal Resource)
- Accessibility-focused design (WCAG compliance built-in)
- SEO and schema.org markup for healthcare services
- Privacy and security modules (CAPTCHA, Honeypot, Klaro consent)
- Patient-focused page layouts and components
- Office hours, locations, and provider directories
- Event management for appointments and health programs

## Architecture

### Monolith Recipe System
This project uses a **monolith architecture** where the entire site is developed as a full Drupal installation and then exported as a recipe:

1. **Development**: Work in a full Drupal CMS installation (not in this repo)
2. **Export**: Use `drush site:export` to generate the recipe files
3. **Distribution**: The exported recipe.yml and /config are committed to this repository
4. **Installation**: Applied to new Drupal CMS sites via `drush site:install`

The `recipe.yml` is **auto-generated** and contains:
- **type**: `Site` (monolith recipe type)
- **install**: Complete list of all enabled modules (120+ modules)
- **config.actions**: Configuration overrides using `setProperties` and `simpleConfigUpdate`

The `composer.json` declares direct dependencies on all required modules (not recipes).

### Configuration Structure
Located in `config/` directory (770+ YAML files exported via drush site:export):
- **Content types**: node.type.*.yml (person, location, event, post, internal_resource)
- **Fields**: field.field.*, field.storage.* (for all entity types)
- **Canvas components**: canvas.component.*, canvas.content_template.*, canvas.folder.*
- **Views, menus, blocks**: Standard Drupal config entities
- **Default content**: Default content entities exported as configuration
- **Theme/system**: system.*, theme.*, etc.

All configuration is exported from a working Drupal site using `drush site:export`. Do not manually edit these files unless you understand the implications.

### Content Structure
Located in `content/` directory, organized by entity type:
- **canvas_page/**: Canvas-built landing pages (UUID-based YAML files with component trees)
- **node/**: Content nodes (person, location, event, post, internal_resource types)
- **media/**: Media entities referenced by pages and nodes
- **file/**: File entities (images, documents)
- **taxonomy_term/**: Taxonomy terms
- **menu_link_content/**: Menu links for main/footer navigation
- **crop/**: Image crop definitions

Each content file uses the `_meta` format with UUIDs and dependency tracking for proper import ordering.

### Healthcare-Specific Features
The recipe includes content types designed for medical organizations:
- **Person**: Staff profiles, physicians, and care team members
- **Location**: Clinic and hospital locations with address and office hours
- **Event**: Appointments, health events, and community programs
- **Post**: News, articles, and health information
- **Internal Resource**: Staff resources and internal documentation

Pre-built Canvas pages demonstrate:
- Patient-focused homepage design
- Service and location finders
- Staff directory and profiles
- Accessible, WCAG-compliant layouts

### Theme and Styling
- **Default theme**: "diagnosis" (configured in recipe.yml via system.theme)
- **Admin theme**: Gin for modern content editing experience
- **CVA module**: Required for Diagnosis theme component variants
- **Canvas integration**: Theme works with Canvas page builder for layout management

### Key Module: drupal_cms_helper
The `drupal_cms_helper` module (version 2.x) is critical to this architecture:
- **Provides**: `drush site:export` command for generating monolith recipes
- **Exports**: All modules, configuration, and content from a Drupal site
- **Generates**: recipe.yml with install list and config.actions
- **Required**: Must be installed in the development site to export updates

## Development Workflow

### Making Changes to the Recipe
This is a **monolith recipe** - you cannot make changes directly in this repository. Instead:

1. **Set up a development Drupal site** (using DDEV):
   ```bash
   # Create a new directory and configure DDEV
   mkdir healthcare-dev && cd healthcare-dev
   ddev config --project-type=drupal11 --docroot=web

   # Create Drupal CMS site and install Healthcare recipe
   ddev composer create-project drupal/cms
   ddev composer require drupal/healthcare
   ddev drush site:install --yes ../recipes/healthcare
   ```

2. **Create or start working on an issue** in the [Healthcare project](https://www.drupal.org/project/issues/healthcare):
   - Create a fork if one hasn't been created
   - Click *Show commands* in the issue queue
   - Copy and paste the *Add & fetch this issue fork's repository* commands:

   ```bash
   git remote add healthcare-[issue-number] git@git.drupal.org:issue/healthcare-[issue-number].git
   git fetch healthcare-[issue-number]
   ```

   - Checkout the branch:

   ```bash
   git checkout -b '[issue-number]-your-branch-name' --track healthcare-[issue-number]/'[issue-number]-your-branch-name'
   ```

3. **Make your changes** in the development site:
   - Configure content types, fields, views through the Drupal UI
   - Create/modify Canvas pages and components
   - Install additional modules
   - Configure themes and settings
   - Add default content

4. **Export the updated recipe**:
   ```bash
   # Export the entire site as a recipe (from within the DDEV container or use ddev drush)
   ddev drush site:export ../../healthcare
   ```
   This will regenerate:
   - `recipe.yml` with updated module list and config actions
   - `config/` directory with all configuration (770+ files)
   - `content/` directory with default content

5. **Commit and push changes** to the Healthcare issue fork:
   ```bash
   cd ../../healthcare
   git add .

   # Use conventional commits with issue number: "type: #[number] Description"
   git commit -m "feat: #[issue-number] Description of changes"

   # Copy the *Push your current local branch* command from the issue page
   git push --set-upstream healthcare-[issue-number] HEAD
   ```

   Use conventional commit types: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, etc.

### Testing
Run functional tests (requires Drupal core test infrastructure):
```bash
# Run basic site template test
vendor/bin/phpunit -c core tests/src/Functional/SiteTemplateTest.php

# Run performance test (requires PERFORMANCE_TEST env var in CI)
PERFORMANCE_TEST=1 vendor/bin/phpunit -c core tests/src/FunctionalJavascript/PerformanceTest.php
```

Tests verify:
- Recipe applies successfully
- All default content pages are accessible
- Required Canvas components are enabled
- Performance benchmarks for anonymous and authenticated users

### Installing the Recipe
This recipe should be installed into a Drupal CMS site (not standalone).

Using DDEV (recommended):
```bash
# Install DDEV first (see https://ddev.readthedocs.io/)

# Create new Drupal CMS site
ddev composer create-project drupal/cms:^2 my-healthcare-site
cd my-healthcare-site

# Add and apply the Healthcare recipe
ddev composer require drupal/healthcare
ddev drush site:install --yes recipes/healthcare

# Start the site
ddev start
```

Without DDEV:
```bash
# Create new Drupal CMS site
composer create-project drupal/cms:^2 my-healthcare-site
cd my-healthcare-site

# Add and apply the Healthcare recipe
composer require drupal/healthcare
./vendor/bin/drush site:install --yes recipes/healthcare
```

### CI/CD
- **GitLab CI**: Uses `.gitlab-ci.yml` with Drupal.org's standard GitLab templates
- **Tugboat**: Preview environments configured in `.tugboat/config.yml`
  - Automatically sets up Drupal CMS + Healthcare recipe
  - Cancels admin account for security in public previews
  - Only demonstrates front-end theme and content

### Contributing to Drupal.org
This project is hosted on Drupal.org and follows Drupal contribution workflows:

**Issue-based development:**
- All work should be tied to an issue in the [Healthcare issue queue](https://www.drupal.org/project/issues/healthcare)
- Create an issue fork for each issue (drupal.org provides git commands)
- Branch naming: `[issue-number]-brief-description` (e.g., `3578112-update-documentation`)
- Commit messages: Use conventional commits with issue number (e.g., `feat: #3578112 Update documentation`)

**Git workflow:**
- Use issue forks (not personal forks) - drupal.org creates these automatically
- Remote naming: `healthcare-[issue-number]` (e.g., `healthcare-3578112`)
- Push to issue fork remote, not origin
- Follow drupal.org's git commands provided in each issue

**Review process:**
- Patches and merge requests are reviewed by maintainers
- Automated testing runs on GitLab CI and Tugboat
- Address feedback in additional commits to the same branch

## Working with Content and Configuration

### Adding/Modifying Default Content
**IMPORTANT**: Do not edit content or configuration files directly. Use the development workflow:

1. Set up a development Drupal site with the Healthcare recipe installed
2. Make changes through the Drupal UI:
   - Create/edit nodes, Canvas pages, media
   - Configure content types, fields, views
   - Modify Canvas components and templates
   - Adjust theme settings
3. Export the updated recipe using `drush site:export /path/to/healthcare`
4. Commit the exported changes

### Understanding the Content Structure
Content files in `content/` use the `_meta` format:
- `uuid`: Entity UUID (must be unique)
- `entity_type`: Entity type (node, canvas_page, media, etc.)
- `bundle`: Bundle/type (person, location, event, etc.)
- `depends`: UUID dependencies (user, media, node references)

Canvas pages (`content/canvas_page/`) are component trees with:
- Component references by component ID
- Configuration props for each component instance
- Layout regions and slots
- Media and entity references by UUID

### Understanding Configuration
The `config/` directory contains 770+ YAML files exported from the development site. These files are **auto-generated** by `drush site:export` and should not be manually edited unless you have a specific reason.

The `recipe.yml` file's `config.actions` section contains overrides applied during installation:
- `setProperties`: Set specific properties on config entities
- `simpleConfigUpdate`: Merge values into existing config
- `?` prefix: Optional actions that won't fail if entity doesn't exist

## Important Notes

### Monolith Recipe Architecture
This is a **monolith recipe** (type: Site), which differs from traditional recipes:

**Monolith Recipe (Healthcare):**
- Exports the entire site as a single package
- Generated from a full Drupal installation using `drush site:export`
- Contains all modules, configuration, and content inline
- recipe.yml lists 120+ modules in the `install` section
- All configuration is in the `config/` directory (770+ files)
- Edit by developing in a full Drupal site, then re-exporting

**Traditional Recipe (for comparison):**
- Extends other recipes using the `recipes` section
- Uses `config.import` for targeted configuration changes
- Requires fewer dependencies (only what's new)
- Can be edited directly in the recipe repository

**Why monolith for Healthcare:**
- Provides a complete, opinionated starter site
- Easier to maintain a consistent healthcare-focused experience
- All features are tested and integrated together
- Simpler installation - one command installs everything

### Diagnosis Theme
The Healthcare recipe uses the Diagnosis theme:
- A medical-focused theme built for healthcare organizations
- Requires CVA module for component variants
- Set as the default theme via `system.theme.default: diagnosis`
- Gin is configured as the admin theme for a modern editing experience

### Canvas Component Management
- Recipe disables many default Canvas components (navigation blocks, admin blocks)
- Uses wildcards for bulk operations: `canvas.component.block.project_browser_block.*`
- Optional disabling with `?` prefix allows graceful handling of missing components

### Performance and Testing
Performance tests verify baseline metrics for:
- Anonymous user page loads (minimal queries, optimized caching)
- Authenticated editor experience (reasonable query counts)
- Asset sizes (CSS/JS with tolerance for theme updates)
- Default content accessibility (all pages must be accessible)
- Canvas component functionality

Tests are run via PHPUnit and include both functional and performance benchmarks.

### Recommended Additional Projects
The Healthcare recipe works well with other Drupal CMS recipes:
- **Accessibility Tools**: Enhanced accessibility features and testing
- **AI Assistant**: AI-powered content suggestions and editing
- **Google Analytics**: Web analytics integration
- **Mailchimp**: Email marketing and newsletter management

These can be installed after the Healthcare recipe is applied to extend functionality.
