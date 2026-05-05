**ECA is the no-code solution that empowers you to orchestrate your Drupal
site.**

ECA is a powerful, versatile, and user-friendly rules engine for Drupal. It
processes event-condition-action models: when a Drupal event fires, ECA
evaluates conditions and executes actions accordingly. Combined with a visual
modeler, ECA lets you build sophisticated automation workflows without writing
code.

### How it works

ECA listens to Drupal events and validates them against your models. Like
Drupal's core action system, ECA leverages existing Drupal components -- events,
actions, and its own plugin manager for conditions. All three component types
are plugins that can be extended by other modules. Models are stored as
configuration, so they can be imported, exported, and deployed through Drupal's
standard configuration management or Drush.

<div class="note-version">

#### ECA Guide

For comprehensive documentation, tutorials, and downloadable example models,
visit the [ECA Guide](https://ecaguide.org).

</div>

### Visual modelers

ECA Core is a processing engine that runs in the background. To create and
edit models, you use a visual modeler -- a separate module that provides the
graphical interface.

ECA integrates with modelers through the
[Modeler API](https://www.drupal.org/project/modeler_api), a framework that
fully decouples what is being modeled from how it is modeled. This means any
modeler that implements the Modeler API works with ECA, and you can switch
between modelers at any time without losing your models.

#### Recommended: Workflow Modeler

The [Workflow Modeler](https://www.drupal.org/project/modeler) is the
recommended modeler for ECA. Built on React Flow, it provides a modern
drag-and-drop interface integrated into Drupal's admin UI with features
including:

- Drag-and-drop visual editing on an infinite canvas
- Context-aware quick-add for building workflows efficiently
- Execution replay to step through past runs visually
- Live testing to trigger and observe workflows directly in the modeler
- Undo/redo, search, copy/paste, and keyboard shortcuts
- Dark mode, multiple view modes, and WCAG AA accessibility
- Export to Recipe, Archive, JSON, or SVG
- A standalone viewer for embedding read-only diagrams on any web page

See the [Workflow Modeler documentation](https://project.pages.drupalcode.org/modeler/)
for full details.

#### Alternative modelers

- [BPMN.iO](https://www.drupal.org/project/bpmn_io): BPMN 2.0 editor
  using the bpmn.js library
- [ECA Classic Modeler](https://www.drupal.org/project/eca_cm): Form-based
  modelling tool using Drupal's Form API

Modelers are installed separately. On production sites, ECA models run without
any modeler enabled.

### Features

- Plugin managers for events and conditions, with integration of all core and
  contrib actions
- Extensible interfaces, abstract base classes, and traits for building custom
  plugins
- Context stack support (optional with
  [Context Stack](https://www.drupal.org/project/context_stack))
- Caching, loops, logging, states, and token support
- Recursion prevention and TypedData support
- Event tagging for characterization and filtering
- Configuration-based storage with full import/export support

### Included sub-modules

ECA ships with sub-modules that add events, conditions, and actions for
specific Drupal subsystems. Enable only what you need:

- **ECA Access:** Entity and field access control
- **ECA Base:** Foundational events, conditions, and actions
- **ECA Cache:** Cache read, write, and invalidation
- **ECA Config:** Configuration events
- **ECA Content:** Content entity events, conditions, and actions
- **ECA Endpoint:** Custom routes and request/response handling
- **ECA File:** File system operations
- **ECA Form:** Form API events, conditions, and actions
- **ECA Language:** Language and translation events and actions
- **ECA Log:** Log message events and actions
- **ECA Menu:** Menu system integration
- **ECA Migrate:** Migration events
- **ECA Misc:** Miscellaneous core and kernel events and conditions
- **ECA Project Browser:** Project Browser integration
- **ECA Queue:** Queued operation events, conditions, and actions
- **ECA Render:** Render API events and actions for blocks, views, and Twig
- **ECA User:** User events, conditions, and actions
- **ECA Views:** Views query execution and export
- **ECA Workflow:** Content entity workflow actions

Additionally: **ECA UI** provides the admin interface for managing models, and
**ECA Development** adds Drush commands for developers.

### Installation

```bash
composer require drupal/eca drupal/modeler
drush en eca eca_ui modeler
```

Enable any ECA sub-modules you need for your site. For a detailed walkthrough,
see the [Install section in the ECA Guide](https://ecaguide.org/eca/install).

### Quick start

The fastest way to get a fully working ECA setup is the
[ECA Starterkit](https://www.drupal.org/project/eca_starterkit) recipe. It
installs the recommended set of ECA sub-modules, the Workflow Modeler, and a
demo model that customizes the user registration form -- so you can open the
modeler right away and see a real workflow in action.

```bash
composer require drupal/eca_starterkit
drush recipe ../recipes/eca_starterkit
```

After applying the recipe, navigate to **Administration > Configuration >
Workflow > ECA** to explore the demo model, or visit `/admin/people/create`
to try the in-place form customization it provides.

### Extending ECA

A growing ecosystem of contrib modules integrates with ECA by providing
additional event, condition, and action plugins. The
[ECA Guide](https://ecaguide.org) maintains a current list of available
integrations.

If you want to add ECA support to your own module, the **ECA Development**
sub-module (`eca_development`) includes Drush code generators that scaffold
the boilerplate for you:

- `drush generate plugin:eca:action` -- generates an action plugin
- `drush generate plugin:eca:condition` -- generates a condition plugin
- `drush generate plugin:eca:events` -- generates a complete event
  integration (event class, ECA event plugin, deriver, and event constants)

Each generator walks you through an interactive interview and produces
properly attributed plugin classes ready to implement.

### Requirements

- Drupal 11.3 or later (including Drupal 12)
- PHP 8.3 or later
- [Modeler API](https://www.drupal.org/project/modeler_api) (installed
  automatically as a dependency)

### Documentation

- [ECA Guide](https://ecaguide.org) -- comprehensive user and developer
  documentation
- [Modeler API documentation](https://project.pages.drupalcode.org/modeler_api/)
  -- architecture and plugin development
- [Workflow Modeler documentation](https://project.pages.drupalcode.org/modeler/)
  -- modeler features and UI development

### Join the team

Contributors are welcome in many areas: development, testing, code review,
support, documentation, translations, and spreading the word.

Get in touch through the [issue queue](https://www.drupal.org/project/issues/eca),
the maintainers' drupal.org profiles, or the
[Drupal Slack #ECA channel](https://drupal.slack.com/archives/C0287U62CSG).

#### Credits

ECA Logo by [Nico Grienauer](https://www.drupal.org/u/grienauer)
