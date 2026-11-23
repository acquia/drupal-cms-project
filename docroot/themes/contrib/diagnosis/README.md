# Diagnosis

A modern, component-based Drupal 11 theme built for the Canvas visual site builder. Diagnosis provides a scalable design system with 30+ Single Directory Components (SDCs), Tailwind CSS 4 integration, and a focus on accessibility and performance.

## Features

- **Canvas Ready**: Fully integrated with Drupal's Canvas visual site builder for intuitive page building
- **30+ SDC Components**: Comprehensive library of reusable, configurable components
- **Tailwind CSS 4**: Modern utility-first CSS framework with custom design tokens
- **CVA (Class Variant Authority)**: Type-safe variant styling for consistent component behavior
- **Accessibility First**: WCAG 2.1 Level AA compliant components
- **PHP 8.3+ Ready**: Modern PHP with typed hooks and strict typing
- **Flexible Theming**: Light/dark mode support with customizable color schemes
- **Performance Optimized**: Minimal CSS bundle with Tailwind's optimization

## Requirements

- Drupal: 11.3 or higher
- PHP: 8.3 or higher
- Node.js: 20.x or higher (for development)
- Required Drupal modules:
  - CVA (Class Variant Authority)
- Recommended modules:
  - Canvas (for visual site building)

## Installation

### Using Composer (Recommended)

```bash
composer require drupal/diagnosis
```

### Manual Installation

1. Download the theme from Drupal.org
2. Extract to `/themes/custom/diagnosis`
3. Install the required CVA module: `composer require drupal/cva`
4. Enable the theme in the Drupal admin interface

## Getting Started

### Enable the Theme

1. Navigate to **Appearance** in your Drupal admin
2. Click **Install and set as default** next to Diagnosis
3. Configure theme settings at **Appearance > Settings > Diagnosis**

### Configure Color Scheme

The theme supports light and dark color schemes:

1. Go to **Appearance > Settings > Diagnosis**
2. Select your preferred color scheme (Light or Dark)
3. Save the configuration

### Using with Canvas

Diagnosis is optimized for use with Drupal's Canvas visual site builder:

1. Install the Canvas module: `composer require drupal/canvas`
2. Enable Canvas in **Extend**
3. Create or edit a page
4. Use Canvas to visually compose pages using Diagnosis components

## Component Library

Diagnosis includes 30+ professionally designed components organized into categories:

### Base Components

- **Heading**: Configurable headings with multiple sizes and styling options
- **Text**: Rich text content with formatting
- **Button**: Multiple button variants (primary, secondary, inverted) with icons
- **Anchor**: Styled links with variants
- **Icon**: Font Awesome icon integration
- **Image**: Responsive images with lazy loading
- **Badge**: Label and category badges

### Layout Components

- **Section**: Grid-based page section with spacing and background options
- **Group**: Flexbox container with alignment, gap, and background options
- **Navbar**: Responsive navigation with mobile menu
- **Footer**: Multi-column footer with social links
- **Menu Container**: Flexible menu wrapper for navigation

### Content Components

- **Card**: Versatile card component with multiple orientations
- **Card Icon**: Icon-based feature cards
- **Card Logo**: Logo showcase cards
- **Card Pricing**: Pricing table cards
- **Card Testimonial**: Customer testimonial cards
- **Blockquote**: Styled quotations

### Hero Components

- **Hero Billboard**: Full-width hero with background image
- **Hero Blog**: Blog-style hero with metadata
- **Hero Side by Side**: Split-screen hero layout
- **Hero CTA**: Call-to-action with heading, text, and button slot

### Interactive Components

- **Accordion**: Expandable content sections
- **Accordion Container**: Wrapper for accordion groups
- **Tabs**: Tabbed content interface
- **Tab**: Individual tab panels
- **Carousel**: Image and content carousels
- **Carousel Slide**: Individual carousel items

### Form Components

- **Address**: Formatted address display
- **Email**: Email link component
- **Phone**: Phone number link component
- **Plain Text**: Simple text input display
- **Date**: Formatted date display

### Other Components

- **Stats Section**: Statistics display with multiple columns
- **CTA**: Call-to-action sections

## Development

### Prerequisites

```bash
npm install
```

### Development Workflow

Watch for CSS changes during development:

```bash
npm run dev
```

Build production CSS:

```bash
npm run build
```

Format code:

```bash
npm run format
```

Check code formatting:

```bash
npm run format:check
```

### CSS Architecture

The theme uses Tailwind CSS 4 with a custom configuration:

- **Source**: `src/main.css` - Main Tailwind entry point
- **Output**: `build/main.min.css` - Compiled, minified CSS
- **Component Styles**: Individual `.tailwind.css` files in component directories
- **Custom Tokens**: Design system tokens defined in `src/main.css`

### Creating Custom Components

1. Create a new directory in `components/`
2. Add `component-name.component.yml` with schema
3. Add `component-name.twig` template
4. Optionally add `component-name.js` and `component-name.css`
5. Follow the CVA patterns documented in `AGENTS.md`

## Coding Standards

Diagnosis follows strict coding conventions for maintainability:

- **CVA Required**: All conditional styling must use CVA, not inline conditionals
- **Context Isolation**: Component includes must use `with_context: false` or `with only`
- **No Split Tags**: HTML opening/closing tags cannot be split across conditionals
- **Explicit Tags**: No dynamic tag names - use explicit HTML elements
- **Proper Spacing**: Attributes must have proper spacing in templates

See `AGENTS.md` for complete coding standards and conventions.

## Theme Settings

### Available Settings

- **Color Scheme**: Choose between light and dark mode
- **Logo**: Upload custom logo or use site name
- **Favicon**: Custom favicon support

### Customizing Logos

The theme includes two logo files in the theme directory for light and dark modes:

- `logo-light.svg` - Logo for light mode
- `logo-dark.svg` - Logo for dark mode (inverted colors)

#### Replacing Theme Logos

To replace the default logos:

1. Create SVG files for both light and dark modes
2. Replace `logo-light.svg` and `logo-dark.svg` in the theme directory
3. Clear Drupal's cache

**Performance Best Practices:**

For optimal performance and to minimize layout shift (CLS):

- **Use SVG format** with proper `viewBox` attributes
- **Recommended aspect ratio**: 4:1 (width to height) for horizontal logos
  - Example: 400×100px, 800×200px, 331×78px
- **Recommended dimensions**: Keep width under 800px and height under 200px
- **File size**: Optimize SVGs to keep file size under 20KB
- **viewBox attribute**: Ensure your SVG includes a proper viewBox for aspect ratio preservation
  ```xml
  <svg viewBox="0 0 400 100" xmlns="http://www.w3.org/2000/svg">
    <!-- your logo content -->
  </svg>
  ```

Following these guidelines helps the browser reserve the correct amount of space for your logo before it loads, preventing content from shifting during page load.

### Menus

The theme supports four menu locations:

- **Main Menu**: Primary navigation (displayed in navbar)
- **Banner Menu**: Utility menu (displayed above navbar on desktop)
- **Footer Menu**: Footer navigation links
- **Social Menu**: Social media links in footer

Configure menus at **Structure > Menus**.

## Browser Support

- Chrome/Edge: Latest 2 versions
- Firefox: Latest 2 versions
- Safari: Latest 2 versions
- Mobile Safari: iOS 14+
- Chrome Android: Latest version

## Accessibility

Diagnosis is designed with accessibility as a priority:

- WCAG 2.1 Level AA compliant components
- Semantic HTML5 markup
- Proper ARIA labels and roles
- Keyboard navigation support
- Screen reader tested
- Color contrast verified
- Focus indicators on all interactive elements

## Performance

- Minimal CSS bundle (~50KB gzipped)
- Optimized component loading
- Lazy loading for images
- No jQuery dependency
- Modern CSS with minimal JavaScript

## Support

- **Issue Queue**: https://www.drupal.org/project/issues/diagnosis
- **Source Code**: https://git.drupalcode.org/project/diagnosis
- **Documentation**: See `CLAUDE.md` and `AGENTS.md` in the theme directory

## Credits

Diagnosis is a fork of the Mercury theme, adapted and enhanced for broader use cases with expanded component library and improved Canvas integration.

### Contributors

- [thejimbirch](https://www.drupal.org/u/thejimbirch)
- [kerrymick](https://www.drupal.org/u/kerrymick)
- [banoodle](https://www.drupal.org/u/banoodle)
- [nkarhoff](https://www.drupal.org/u/nkarhoff)

## License

GNU General Public License v2.0 or later

See LICENSE.txt for full license text.
