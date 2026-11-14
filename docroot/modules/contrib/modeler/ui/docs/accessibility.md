# Accessibility Implementation

WCAG AA compliant interfaces with full keyboard navigation and screen reader support.

## Accessibility Standards

### WCAG AA Requirements
- **Text Contrast**: 4.5:1 for normal text (18px+), 3:1 for large text
- **Non-Text Contrast**: 3:1 for icons, borders, UI elements
- **Keyboard Navigation**: All functionality accessible via keyboard
- **Screen Reader Support**: Proper ARIA labels and announcements
- **Focus Management**: Visible focus indicators and logical tab order

### Color Contrast Compliance
```css
/* Use semantic color variables with proper contrast ratios */
:root {
  /* Text colors - 4.5:1 minimum on white */
  --text-primary: #374151;      /* gray-700 */
  --text-secondary: #4b5563;    /* gray-600 */
  --text-muted: #6b7280;        /* gray-500 - only for large text */
  
  /* UI element colors - 3:1 minimum on white */
  --ui-border: #8b8b8b;         /* 3.4:1 */
  --ui-icon: #6b7280;           /* 4.0:1 */
  --ui-focus: #2563eb;           /* 4.58:1 */
  
  /* Danger colors - higher contrast for accessibility */
  --danger-text: #991b1b;       /* On danger background */
  --danger-bg: #dc2626;          /* 4.63:1 with white text */
  --danger-hover: #b91c1c;       /* 3.7:1 with white text */
}
```

## Keyboard Navigation

### Comprehensive Keyboard Support
```typescript
// Full keyboard navigation system
const useKeyboardNavigation = () => {
  const [focusedIndex, setFocusedIndex] = useState(0);
  const [focusedElement, setFocusedElement] = useState<string | null>(null);
  
  const handleKeyDown = useCallback((e: KeyboardEvent) => {
    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        navigateDown();
        break;
        
      case 'ArrowUp':
        e.preventDefault();
        navigateUp();
        break;
        
      case 'ArrowRight':
        e.preventDefault();
        navigateRight();
        break;
        
      case 'ArrowLeft':
        e.preventDefault();
        navigateLeft();
        break;
        
      case 'Home':
        e.preventDefault();
        navigateToFirst();
        break;
        
      case 'End':
        e.preventDefault();
        navigateToLast();
        break;
        
      case 'Enter':
      case ' ':
        e.preventDefault();
        activateFocusedElement();
        break;
        
      case 'Escape':
        e.preventDefault();
        exitCurrentContext();
        break;
    }
  }, []);

  const navigateDown = () => {
    setFocusedIndex(prev => {
      const next = findNextFocusableElement(prev);
      focusElement(next.elementId);
      return next.index;
    });
  };

  return {
    focusedIndex,
    focusedElement,
    handleKeyDown,
    focusedRect: getFocusedElementRect()
  };
};
```

### Focus Management
```typescript
// Robust focus trapping and restoration
const useFocusTrap = (containerRef: RefObject<HTMLElement>, options: FocusTrapOptions) => {
  const previousFocusRef = useRef<HTMLElement | null>(null);
  const [isActive, setIsActive] = useState(false);

  const activateTrap = useCallback(() => {
    const container = containerRef.current;
    if (!container) return;

    // Save current focus
    previousFocusRef.current = document.activeElement as HTMLElement;
    
    // Find first focusable element
    const focusableElements = container.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    
    if (focusableElements.length > 0) {
      (focusableElements[0] as HTMLElement).focus();
      setIsActive(true);
    }
  }, []);

  const deactivateTrap = useCallback(() => {
    setIsActive(false);
    
    // Restore previous focus
    if (previousFocusRef.current) {
      previousFocusRef.current.focus();
    }
  }, []);

  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && isActive) {
        e.preventDefault();
        deactivateTrap();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isActive, deactivateTrap]);

  // Handle clicks outside
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (isActive && containerRef.current && !containerRef.current.contains(e.target as Node)) {
        deactivateTrap();
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isActive, deactivateTrap]);

  return { activateTrap, deactivateTrap, isActive };
};
```

## Screen Reader Support

### ARIA Implementation
```typescript
// Comprehensive ARIA labeling
const AccessibleButton: React.FC<{
  children: React.ReactNode;
  label: string;
  description?: string;
  pressed?: boolean;
  onClick: () => void;
}> = ({ children, label, description, pressed = false, onClick }) => {
  return (
    <button
      onClick={onClick}
      aria-label={label}
      aria-describedby={description ? `${label}-desc` : undefined}
      aria-pressed={pressed}
      className="accessible-button"
    >
      {children}
      {description && (
        <div id={`${label}-desc`} className="sr-only">
          {description}
        </div>
      )}
    </button>
  );
};

// Accessible form controls
const AccessibleCombobox: React.FC<{
  options: { value: string; label: string }[];
  value: string;
  onChange: (value: string) => void;
  label: string;
}> = ({ options, value, onChange, label }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(-1);

  const handleKeyDown = (e: React.KeyboardEvent) => {
    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setHighlightedIndex(prev => Math.min(prev + 1, options.length - 1));
        break;
      case 'ArrowUp':
        e.preventDefault();
        setHighlightedIndex(prev => Math.max(prev - 1, 0));
        break;
      case 'Enter':
      case ' ':
        e.preventDefault();
        if (highlightedIndex >= 0) {
          onChange(options[highlightedIndex].value);
        }
        setIsOpen(false);
        break;
      case 'Escape':
        e.preventDefault();
        setIsOpen(false);
        break;
    }
  };

  return (
    <div className="combobox">
      <label>{label}</label>
      <div
        role="combobox"
        aria-expanded={isOpen}
        aria-haspopup="listbox"
        aria-activedescendant={highlightedIndex >= 0 ? `option-${highlightedIndex}` : undefined}
        onClick={() => setIsOpen(!isOpen)}
        onKeyDown={handleKeyDown}
        tabIndex={0}
      >
        {value}
      </div>
      {isOpen && (
        <ul role="listbox" className="combobox-list">
          {options.map((option, index) => (
            <li
              key={option.value}
              id={`option-${index}`}
              role="option"
              aria-selected={option.value === value}
              className={index === highlightedIndex ? 'highlighted' : ''}
            >
              {option.label}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
};
```

### Dynamic Content Announcements
```typescript
// Screen reader announcements for dynamic content
const useScreenReaderAnnouncements = () => {
  const [announcement, setAnnouncement] = useState('');
  const [priority, setPriority] = useState<'polite' | 'assertive'>('polite');

  const announce = useCallback((message: string, level: 'polite' | 'assertive' = 'polite') => {
    setAnnouncement(message);
    setPriority(level);
  }, []);

  // Clear announcement after it's read
  useEffect(() => {
    if (!announcement) return;

    const timer = setTimeout(() => {
      setAnnouncement('');
      setPriority('polite');
    }, 1000); // Adjust based on message length

    return () => clearTimeout(timer);
  }, [announcement]);

  return (
    <div
      aria-live={priority}
      aria-atomic="true"
      className="sr-only"
    >
      {announcement}
    </div>
  );
};
```

## Messages Container Accessibility

The floating messages container uses ARIA live region attributes to ensure screen readers announce new messages:

```html
<div
  role="log"
  aria-label="Workflow messages"
  aria-live="polite"
  aria-relevant="additions removals"
  class="workflow-messages-container visible"
>
  <!-- Drupal .messages-list is moved here -->
</div>
```

- **`role="log"`**: Indicates a live region where new information is added in meaningful order
- **`aria-live="polite"`**: Screen readers announce new messages at the next graceful opportunity (doesn't interrupt)
- **`aria-relevant="additions removals"`**: Announces both when messages are added and when they are cleared
- **`aria-label`**: Provides a descriptive name for the region

### Toolbar Message Controls
- Both the toggle button (`FiZap`) and clear button (`FiTrash2`) have `aria-label` attributes that update based on state
- Toggle label alternates between "Show messages" and "Hide messages"
- Clear button always labelled "Clear messages"

## Destructive Actions Accessibility

### Delete All Button
The "Delete All" button in `MultiSelectionPanel` follows these accessibility practices:
- **Danger styling**: Uses `--modeler-color-danger` CSS variable for the red outline/fill, which meets WCAG AA 3:1 non-text contrast requirements
- **Disabled state**: Button is disabled (and visually muted) when the model is in read-only mode, preventing accidental deletions
- **Confirmation dialog**: Triggers a `ConfirmDialog` with `role="alertdialog"` before any deletion occurs, requiring explicit user confirmation
- **Icon + text label**: Combines `FiTrash2` icon with "Delete All" text, so the action is clear without relying solely on color
- **Title attribute**: Provides `title="Delete all selected items"` for tooltip/screen reader context

### ConfirmDialog Danger Variant
When used for destructive confirmations (e.g., bulk delete), the `ConfirmDialog` can be configured with:
- **`primaryButtonVariant="danger"`**: Styles the primary action button with danger colors, visually signaling a destructive action
- **`secondaryButtonLabel={false}`**: Hides the secondary button to reduce choice overload (only "Delete" and "Cancel" remain)
- **`role="alertdialog"`**: Ensures screen readers treat the dialog as requiring immediate attention
- **Focus trapping**: `useFocusTrap` keeps keyboard focus within the dialog; Escape dismisses it and restores focus to the trigger element

## Visual Accessibility

### Focus Indicators
```css
/* Clear, visible focus indicators */
.focus-visible {
  outline: 2px solid var(--ui-focus);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}

/* High contrast focus for better visibility */
.focus-visible:focus {
  outline-color: var(--focus-color);
  background-color: var(--focus-bg);
}

/* Skip navigation links */
.skip-link {
  position: absolute;
  top: -40px;
  left: 6px;
  background: var(--ui-focus);
  color: var(--text-primary);
  padding: 8px;
  text-decoration: none;
  z-index: 1000;
}

.skip-link:focus {
  top: 6px;
}
```

### Responsive Design
```css
/* Ensure accessibility across screen sizes */
@media (prefers-reduced-motion: reduce) {
  /* Respect motion preferences */
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

@media (prefers-contrast: high) {
  :root {
    /* Higher contrast colors for users who prefer it */
    --text-primary: #000000;
    --text-secondary: #333333;
    --ui-border: #000000;
    --ui-focus: #ffffff;
    --focus-bg: #0000ff;
  }
}

/* Ensure sufficient touch targets */
@media (pointer: coarse) {
  button, .clickable {
    min-height: 44px;
    min-width: 44px;
    padding: 12px;
  }
}
```

## Scrollable Region Accessibility

Scrollable containers must be keyboard-accessible per the axe `scrollable-region-focusable` rule. Any element with `overflow-y: auto` (or `scroll`) that can produce a scrollbar must have:
- `tabIndex={0}` — makes the region focusable via keyboard
- `role="region"` — gives it a landmark role for screen readers
- `aria-label` — provides a descriptive name

This pattern is used on the inline step data region in `ReplayPanelContent.tsx`:
```tsx
<div
  id={`replay-step-data-${index}`}
  className="replay-step-data"
  role="region"
  aria-label={t('Step data')}
  tabIndex={0}
>
  {/* scrollable content */}
</div>
```

## Token Drop Zone Contrast

The `.token-drop-rejected` state on `ContentEditableField` uses `color: var(--modeler-color-text-secondary)` to ensure text contrast meets WCAG AA 4.5:1 on the muted background:
- **Light mode**: #4b5563 on #f3f4f6 = ~5.74:1 contrast
- **Dark mode**: #d1d5db on #374151 = ~7.33:1 contrast

The `--modeler-color-text-tertiary` variable (#6b7280 / gray-500) must NOT be used for normal-sized text on muted backgrounds — it only achieves ~4.39:1 contrast in light mode, which fails WCAG AA.

## Testing Accessibility

### Storybook A11y Testing (axe-playwright)

All Storybook stories are automatically tested for accessibility violations using axe-playwright in the test-runner. Tests run in **both light and dark mode** for every story.

**Configuration:** `.storybook/test-runner.ts` (at the `ui/` root)
- Injects axe-core on each page visit
- Disables `nested-interactive` rule (upstream ReactFlow issue with `role="button"` on node wrappers)
- Runs `checkA11y` with retry logic (3 attempts) to handle async rendering race conditions
- Toggles `.dark-mode` class on the `.modeler` element between light and dark audits
- Waits for ReactFlow viewport to stabilize before running audits

**Disabled rules:**
- `nested-interactive` — ReactFlow adds `role="button"` to node wrappers; our custom nodes contain buttons inside, causing false positives

**Running the tests:**
```bash
# Build storybook, start a server, and run a11y tests (all-in-one)
npm run test-storybook:ci
```

**Excluding stories from a11y tests:**
When a component is behind a feature flag, its stories can be excluded using `includeStories: []` in the CSF meta:
```typescript
const meta: Meta<typeof MyComponent> = {
  title: 'Components/MyComponent',
  component: MyComponent,
  includeStories: [], // Exclude all stories while feature flag is disabled
};
```

### Automated Testing Setup
```typescript
// axe-core integration for accessibility testing
const setupAccessibilityTesting = () => {
  // Configure axe with custom rules
  axe.configure({
    rules: {
      // Disable rules that don't apply to our application
      'color-contrast-aa': { enabled: false }, // We check manually
      'landmark-one-main': { enabled: false }, // Application-specific
    },
    tags: ['wcag2aa', 'wcag2aaa'], // WCAG 2.1 standards
  });
};

// Accessibility assertions for testing
const expectAccessible = async (container: HTMLElement) => {
  const results = await axe.run(container);
  
  expect(results.violations).toHaveLength(0);
  
  // Log any violations for debugging
  if (results.violations.length > 0) {
    console.error('Accessibility violations:', results.violations);
  }
};
```

### Manual Testing Checklist
```typescript
// Manual accessibility test utilities
const AccessibilityTestSuite = {
  keyboardNavigation: async () => {
    // Test all interactive elements with keyboard
    const interactiveElements = document.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]'
    );
    
    for (let i = 0; i < interactiveElements.length; i++) {
      const element = interactiveElements[i] as HTMLElement;
      
      // Tab to element
      for (let j = 0; j <= i; j++) {
        fireEvent.keyDown(document, { key: 'Tab' });
        await new Promise(resolve => setTimeout(resolve, 100));
      }
      
      // Check if element has focus
      expect(document.activeElement).toBe(element);
      
      // Test activation
      fireEvent.keyDown(document, { key: 'Enter' });
      await new Promise(resolve => setTimeout(resolve, 200));
    }
  },

  screenReaderSupport: async () => {
    // Test ARIA labels
    const buttons = document.querySelectorAll('button');
    buttons.forEach(button => {
      const hasAriaLabel = button.hasAttribute('aria-label') || 
                            button.hasAttribute('aria-labelledby');
      expect(hasAriaLabel).toBe(true);
    });
    
    // Test live regions
    const liveRegions = document.querySelectorAll('[aria-live]');
    expect(liveRegions.length).toBeGreaterThan(0);
  },

  colorContrast: async () => {
    // Test contrast ratios
    const textElements = document.querySelectorAll('p, span, div, button');
    const issues = [];
    
    textElements.forEach(element => {
      const styles = window.getComputedStyle(element);
      const color = styles.color;
      const backgroundColor = styles.backgroundColor;
      
      // Calculate contrast ratio
      const ratio = calculateContrastRatio(color, backgroundColor);
      
      if (ratio < 4.5 && parseFloat(styles.fontSize) < 18) {
        issues.push({
          element,
          ratio,
          color,
          backgroundColor
        });
      }
    });
    
    expect(issues).toHaveLength(0);
    return issues;
  }
};
```

## Accessibility Guidelines

### Implementation Checklist
- [ ] All interactive elements keyboard accessible
- [ ] Proper focus management and visual indicators
- [ ] ARIA labels and descriptions for all controls
- [ ] Screen reader announcements for dynamic content
- [ ] Sufficient color contrast (4.5:1 text, 3:1 non-text)
- [ ] Semantic HTML elements used appropriately
- [ ] Skip navigation links for bypassing blocks
- [ ] Respects user preferences (reduced motion, high contrast)
- [ ] Touch targets at least 44x44px on touch devices
- [ ] Form validation messages associated with inputs
- [ ] Progress indicators with accessible text alternatives

### Testing Requirements
- [ ] Automated axe-core testing in CI/CD pipeline
- [ ] Manual keyboard navigation testing
- [ ] Screen reader testing (VoiceOver, NVDA, JAWS)
- [ ] Color contrast verification
- [ ] Mobile accessibility testing
- [ ] High contrast mode testing
- [ ] Reduced motion preference testing

### Documentation Standards
- [ ] Accessibility features documented in user guides
- [ ] Keyboard shortcuts listed and explained
- [ ] Screen reader usage instructions provided
- [ ] Accessibility contact/support information available

