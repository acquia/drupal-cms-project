# Agents.md

## Module Overview

Geofield is a Drupal field module for geographic/location data. It stores geometry in WKT (Well-Known Text) format with computed metadata columns (lat, lon, bbox centroid, geohash), and supports points, lines, polygons, and multi-geometry types. The backing library is `itamair/geophp`.

## Build, Lint, and Test Commands

```bash
# Install dependencies
composer install

# Run all tests (requires Drupal test bootstrap)
phpunit tests/

# Run a single test class
phpunit tests/src/Kernel/GeofieldItemTest.php

# Static analysis (level 2 with baseline)
phpstan analyze

# JavaScript linting
eslint js/

# Spelling checks
cspell
```

The CI pipeline (`.gitlab-ci.yml`) tests against Drupal next/previous major and minor versions. Integration tests in `tests/src/Kernel/` require a bootstrapped Drupal environment.

## Architecture

### Storage Model

`GeofieldItem` (field plugin) stores a single geometry as 9 database columns: `value` (WKT), `geo_type`, `lat`, `lon`, `top`, `bottom`, `left`, `right`, `geohash`. Computed properties (centroid, bbox) are derived on save by the active backend plugin.

### Service Layer (`geofield.services.yml`)

| Service | Class | Purpose |
|---|---|---|
| `geofield.wkt_generator` | `WktGenerator` | Generates WKT geometry strings |
| `geofield.geophp` | `GeoPHPWrapper` | Typed wrapper around geoPHP |
| `plugin.manager.geofield_backend` | `GeofieldBackendManager` | Manages storage backends |
| `plugin.manager.geofield_proximity_source` | `GeofieldProximitySourceManager` | Manages proximity source plugins |

### Pluggable Backends (`src/Plugin/GeofieldBackend/`)

`GeofieldBackendManager` discovers `@GeofieldBackend` annotated plugins. Two implementations exist: `GeofieldBackendDefault` (MySQL) and `GeofieldBackendPostgis` (PostGIS). The backend is responsible for processing geometry on save.

### Field Widgets (`src/Plugin/Field/FieldWidget/`)

All widgets extend `GeofieldBaseWidget` (abstract), which injects `geofield.geophp`, `geofield.wkt_generator`, and the backend manager. Concrete widgets: `GeofieldLatLonWidget`, `GeofieldDmsWidget` (degrees/minutes/seconds), `GeofieldDefaultWidget` (raw WKT/GeoJSON), `GeofieldBoundsWidget`.

### Views Integration (`src/Plugin/views/`)

Distance and boundary queries use two shared traits:
- `GeofieldProximityHandlerTrait` — haversine formula logic, shared by filter/argument/sort/field handlers
- `GeofieldBoundaryHandlerTrait` — rectangular boundary logic

Proximity handlers depend on `GeofieldProximitySourceManager` to resolve the origin point. Source plugins (`src/Plugin/GeofieldProximitySource/`) include manual coordinates, context-based, client browser geolocation, and derived-from-another-filter sources. The manager builds AJAX-enabled form elements for selecting the source type.

### Additional Integrations

- **JSON:API** — `src/Plugin/jsonapi/FieldEnhancer/GeofieldGeoJsonEnhancer.php` outputs GeoJSON in API responses
- **Migration** — `src/Plugin/migrate/` handles Drupal 7→current field data transformation (WKT and LatLon source processors)
- **Feeds** — `src/Feeds/` for bulk geometry imports
- **Diff** — `src/Plugin/diff/` for field value diffing
- **Validation** — `src/Plugin/Validation/Constraint/` enforces allowed geometry types

### Form Elements (`src/Element/`)

Reusable Drupal Form API elements: `GeofieldLatLon`, `GeofieldBounds`, `GeofieldDms` — used by widgets but also available independently.

## Code Style

- Drupal coding standards; 2-space indentation, no tabs
- PHP strict typing with PHP 8 features
- PHPDoc required for all classes and public methods
- Plugin definitions use Drupal attributes (not annotations) for new plugins
