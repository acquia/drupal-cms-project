# Accessibility

The Modeler is built with accessibility as a core requirement. It meets
**WCAG AA** standards and provides comprehensive support for keyboard
navigation, screen readers, and user preference settings.

## Keyboard navigation

All modeler functionality is accessible via keyboard. See
[Keyboard Shortcuts](features/keyboard-shortcuts.md) for the full reference.

Key capabilities:

- **Tab navigation**: Move between interactive elements using Tab and
  Shift+Tab.
- **Arrow keys**: Navigate within panels, dropdown lists, and step lists.
- **Enter/Space**: Activate buttons and select items.
- **Escape**: Close dialogs, popups, and the search bar.
- **Alt+Shift+R** (**Option+Shift+R** on macOS): Toggle the right-hand panel
  between property mode and review mode without reaching for the mouse.

## Focus management

### Visible focus indicators

All interactive elements show clear focus indicators when navigated to via
keyboard:

- A **2px blue outline** with offset for visibility.
- **High-contrast focus** styles for better visibility on various backgrounds.

### Focus trapping

Modal dialogs and popups trap focus within their boundaries:

- **Tab** cycles through focusable elements inside the dialog.
- **Escape** closes the dialog and restores focus to the element that opened
  it.
- Clicking outside a dialog also closes it.

### Skip navigation

The modeler respects skip-navigation patterns, allowing keyboard users to
bypass repetitive elements and jump to main content areas.

## Screen reader support

### ARIA labels

All interactive elements have proper ARIA labeling:

- **Buttons**: `aria-label` attributes describe the action (e.g., "Zoom in",
  "Save model", "Delete selected items").
- **Dialogs**: `role="dialog"` with `aria-modal="true"`.
- **Live regions**: Dynamic content changes (like messages and replay step
  updates) are announced via `aria-live` regions.
- **Comboboxes**: Search dropdowns use `role="combobox"` with proper
  `aria-expanded` and `aria-activedescendant` attributes.

### Dynamic announcements

When content changes dynamically (e.g., a new message appears, replay steps
update), screen readers announce the change through ARIA live regions:

- **Polite**: Non-urgent updates are announced at the next natural pause.
- **Assertive**: Important updates (like errors) are announced immediately.

### Messages

The floating messages container uses:

- `role="log"` for meaningful sequential content.
- `aria-live="polite"` for non-intrusive announcements.
- `aria-relevant="additions removals"` to announce both new and cleared
  messages.

## Color and contrast

### Contrast ratios

All text and UI elements meet WCAG AA contrast requirements:

| Element | Minimum ratio | Standard |
|---------|---------------|----------|
| Normal text (below 18px) | 4.5:1 | WCAG AA |
| Large text (18px and above) | 3:1 | WCAG AA |
| Non-text elements (icons, borders) | 3:1 | WCAG AA |

### High contrast mode

When the operating system is set to **prefer high contrast**, the modeler
switches to higher-contrast colors automatically.

### Dark mode

Both light and dark themes are designed to meet contrast requirements. See
[Dark Mode](features/dark-mode.md).

## Motion and animation

### Reduced motion

When the operating system is set to **prefer reduced motion**:

- All animations are minimized (near-zero duration).
- Transition effects are disabled.
- The search highlight pulsing animation is suppressed.

This respects the user's preference and avoids triggering motion sensitivity
issues.

## Touch accessibility

On touch devices:

- All interactive elements have a minimum touch target of **44x44 pixels**.
- Padding is increased for comfortable touch interaction.

## Destructive actions

Destructive actions (like deleting elements) follow safety patterns:

- **Danger styling**: Red color with sufficient contrast signals a destructive
  action.
- **Confirmation dialogs**: Multi-element deletions always require explicit
  confirmation through an alert dialog.
- **Disabled states**: Actions that cannot be performed (e.g., editing in
  read-only mode) are visually muted and non-interactive.

## Edge order controls

The edge order badges ("Flow 1", "Flow 2", etc.) are fully accessible:

- **Keyboard operable**: Badges are focusable with `tabIndex` and can be
  activated with Enter or Space to open the reorder dropdown.
- **ARIA attributes**: Badges use `aria-label` (e.g., "Flow 1 of 3, click
  to change"), `aria-expanded`, and `aria-haspopup="listbox"`.
- **Dropdown**: The reorder dropdown uses `role="listbox"` with
  `role="option"` items and `aria-selected` to indicate the current position.

## Scrollable regions

Scrollable content areas (like the inline step data that expands under the
selected step in Review flow mode) are keyboard-accessible with:

- `tabIndex="0"` for keyboard focus.
- `role="region"` for screen reader context.
- `aria-label` for descriptive identification.

## Testing

The modeler's accessibility is verified through:

- **Automated axe-core testing**: Every Storybook story is tested for
  accessibility violations in both light and dark mode.
- **Keyboard navigation testing**: All workflows are tested for keyboard-only
  operation.
- **Screen reader testing**: Verified with VoiceOver and NVDA.
- **Contrast verification**: All color combinations are checked against WCAG
  AA requirements.
