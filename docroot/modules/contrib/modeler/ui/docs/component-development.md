# Component Development

Creating, modifying, and maintaining React components following established patterns.

## Component Architecture

### File Structure
```typescript
// ✅ CORRECT: Component file organization
src/components/
├── MyComponent.tsx              # Main component
├── MyComponent.stories.tsx       # Storybook stories
└── __tests__/
    └── MyComponent.test.tsx    # Unit tests
```

### Component Template
```typescript
// ✅ CORRECT: Complete component structure
import React from 'react';
import { useGraphStore } from '../store/useGraphStore';

interface Props {
  title: string;
  onAction?: () => void;
  disabled?: boolean;
}

const MyComponent: React.FC<Props> = ({ title, onAction, disabled = false }) => {
  // Store selectors
  const isDarkMode = useUISettingsStore(state => state.darkMode);
  
  // Local state (if needed)
  const [isOpen, setIsOpen] = React.useState(false);
  
  // Event handlers
  const handleClick = React.useCallback(() => {
    if (!disabled && onAction) {
      onAction();
    }
  }, [disabled, onAction]);

  return (
    <div className="my-component">
      <h3>{title}</h3>
      <button onClick={handleClick} disabled={disabled}>
        Action
      </button>
    </div>
  );
};

export default MyComponent;
```

## Storybook Integration

### Story Template
```typescript
// MyComponent.stories.tsx
import type { Meta, StoryObj } from '@storybook/react';
import MyComponent from './MyComponent';

const meta: Meta<typeof MyComponent> = {
  title: 'Components/MyComponent',
  component: MyComponent,
  parameters: {
    layout: 'centered',
  },
};

export default meta;

type Story = StoryObj<typeof MyComponent>;

export const Default: Story = {
  args: {
    title: 'My Component',
    onAction: () => console.log('Action clicked'),
  },
};

export const Disabled: Story = {
  args: {
    title: 'Disabled Component',
    disabled: true,
  },
};

export const WithDarkMode: Story = {
  args: {
    title: 'Dark Mode Component',
  },
  parameters: {
    backgrounds: { default: 'dark' },
  },
};
```

### Decorator Usage
```typescript
// For components needing store context
import { withStore } from '../../.storybook/decorators';

const meta: Meta<typeof MyComponent> = {
  title: 'Components/MyComponent',
  component: MyComponent,
  decorators: [withStore],
};

// For ReactFlow components
import { withReactFlow } from '../../.storybook/decorators';

const meta: Meta<typeof FlowCanvas> = {
  title: 'Components/FlowCanvas',
  component: FlowCanvas,
  decorators: [withReactFlow, withStore],
};
```

## Testing Patterns

### Unit Test Template
```typescript
// MyComponent.test.tsx
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';
import MyComponent from '../MyComponent';

describe('MyComponent', () => {
  it('renders title correctly', () => {
    render(<MyComponent title="Test Title" />);
    
    expect(screen.getByText('Test Title')).toBeInTheDocument();
  });

  it('calls onAction when button clicked', () => {
    const mockOnAction = jest.fn();
    
    render(
      <MyComponent 
        title="Test" 
        onAction={mockOnAction} 
      />
    );
    
    fireEvent.click(screen.getByRole('button', { name: 'Action' }));
    expect(mockOnAction).toHaveBeenCalledTimes(1);
  });

  it('applies disabled state correctly', () => {
    render(
      <MyComponent 
        title="Test" 
        disabled={true} 
      />
    );
    
    const button = screen.getByRole('button', { name: 'Action' });
    expect(button).toBeDisabled();
  });

  it('responds to store changes', () => {
    // Mock store state
    const mockStore = createMockStore({ darkMode: true });
    
    render(
      <StoreProvider store={mockStore}>
        <MyComponent title="Test" />
      </StoreProvider>
    );
    
    // Test dark mode behavior
    expect(screen.getByTestId('dark-mode-element')).toBeInTheDocument();
  });
});
```

### Test Utilities
```typescript
// Test setup helpers
const createMockStore = (initialState: Partial<StoreState>) => {
  return create<StoreState>((set, get) => ({
    ...defaultStoreState,
    ...initialState,
  }));
};

const renderWithStore = (component: React.ReactElement, store: StoreState) => {
  return render(
    <StoreProvider store={store}>
      {component}
    </StoreProvider>
  );
};
```

## Component Patterns

### State Management Integration
```typescript
// ✅ CORRECT: Store integration pattern
const ConnectedComponent: React.FC = () => {
  // Individual selectors - no destructuring
  const nodes = useGraphStore(state => state.nodes);
  const selectedNode = useSelectionStore(state => state.selectedNode);
  const setNodes = useGraphStore(state => state.setNodes);
  
  const handleAddNode = useCallback((node: Node) => {
    setNodes(prevNodes => [...prevNodes, node]);
  }, [setNodes]);

  return (
    <div>
      <button onClick={() => handleAddNode(mockNode)}>
        Add Node
      </button>
      <div>Nodes: {nodes.length}</div>
      {selectedNode && <div>Selected: {selectedNode.id}</div>}
    </div>
  );
};

// ❌ WRONG: Store destructuring
const ConnectedComponent: React.FC = () => {
  const { nodes, selectedNode, setNodes } = useGraphStore(); // Causes re-renders
};
```

### Error Boundary Pattern
```typescript
// ✅ CORRECT: Component error boundary
interface ErrorBoundaryState {
  hasError: boolean;
  error?: Error;
}

class ComponentErrorBoundary extends React.Component<
  { children: React.ReactNode },
  ErrorBoundaryState
> {
  constructor(props: { children: React.ReactNode }) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError(error: Error): ErrorBoundaryState {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    console.error('Component error:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div data-testid="error-fallback">
          <h2>Something went wrong.</h2>
          <button onClick={() => this.setState({ hasError: false })}>
            Try Again
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}

// Usage
<ErrorBoundary>
  <MyComponent />
</ErrorBoundary>
```

## Styling Patterns

### CSS Classes Strategy
```typescript
// ✅ CORRECT: CSS custom properties
const styles = {
  component: 'my-component',
  button: 'my-component__button',
  title: 'my-component__title',
  disabled: 'my-component--disabled',
};

// Usage
return (
  <div className={styles.component}>
    <h3 className={styles.title}>{title}</h3>
    <button 
      className={`${styles.button} ${disabled ? styles.disabled : ''}`}
      onClick={handleClick}
    >
      Action
    </button>
  </div>
);
```

### Conditional Styling
```typescript
// ✅ CORRECT: Conditional classes
const buttonClass = [
  styles.button,
  disabled && styles.disabled,
  isActive && styles.active,
].filter(Boolean).join(' ');

// ✅ CORRECT: CSS-in-JS with custom properties
const dynamicStyle: React.CSSProperties = {
  '--local-color': primaryColor,
  width: `${width}px`,
};
```

## Props Design

### Interface Best Practices
```typescript
// ✅ CORRECT: Clear prop interfaces
interface ComponentProps {
  // Required props
  children: React.ReactNode;
  title: string;
  
  // Optional props with defaults
  variant?: 'primary' | 'secondary';
  size?: 'small' | 'medium' | 'large';
  
  // Event handlers
  onSubmit?: (data: FormData) => void;
  onCancel?: () => void;
  
  // Advanced props
  disabled?: boolean;
  className?: string;
  style?: React.CSSProperties;
}

const MyComponent: React.FC<ComponentProps> = ({
  children,
  title,
  variant = 'primary',
  size = 'medium',
  onSubmit,
  onCancel,
  disabled = false,
  className,
  style
}) => {
  // Implementation
};
```

### Props Validation
```typescript
// ✅ CORRECT: Runtime prop validation (if needed)
const MyComponent: React.FC<ComponentProps> = (props) => {
  if (process.env.NODE_ENV === 'development') {
    if (props.size && !['small', 'medium', 'large'].includes(props.size)) {
      console.warn(`Invalid size: ${props.size}`);
    }
  }
};
```

## Advanced Patterns

### Compound Components
```typescript
// Parent component
interface CardProps {
  children?: React.ReactNode;
  title?: string;
  className?: string;
}

const Card: React.FC<CardProps> = ({ children, title, className }) => (
  <div className={`card ${className || ''}`}>
    {title && <div className="card__header">{title}</div>}
    <div className="card__content">{children}</div>
  </div>
);

// Child components
const CardHeader: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <div className="card__header">{children}</div>
);

const CardContent: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <div className="card__content">{children}</div>
);

// Usage
<Card title="Card Title">
  <CardContent>Content here</CardContent>
</Card>
```

### Render Props Pattern
```typescript
interface RenderProps<T> {
  data: T[];
  renderItem: (item: T, index: number) => React.ReactNode;
  emptyState?: React.ReactNode;
}

const List = <T,>({ data, renderItem, emptyState }: RenderProps<T>) => (
  <div className="list">
    {data.length === 0 ? (
      emptyState || <div className="list__empty">No items</div>
    ) : (
      data.map((item, index) => (
        <div key={index} className="list__item">
          {renderItem(item, index)}
        </div>
      ))
    )}
  </div>
);

// Usage
<List
  data={items}
  renderItem={(item, index) => (
    <div>{item.name} (index: {index})</div>
  )}
  emptyState={<div>No items found</div>}
/>
```

## Component Lifecycle

### useEffect Patterns
```typescript
// ✅ CORRECT: Effect with cleanup
const MyComponent: React.FC<{ apiEndpoint: string }> = ({ apiEndpoint }) => {
  const [data, setData] = React.useState(null);

  React.useEffect(() => {
    let cancelled = false;

    const fetchData = async () => {
      try {
        const result = await fetch(apiEndpoint);
        if (!cancelled) {
          setData(result);
        }
      } catch (error) {
        if (!cancelled) {
          console.error('Fetch failed:', error);
        }
      }
    };

    fetchData();

    return () => {
      cancelled = true;
    };
  }, [apiEndpoint]);

  return <div>{/* render data */}</div>;
};
```

## Component Guidelines

### Performance Considerations
- Use `React.memo()` for components that re-render frequently with same props
- Use `useCallback()` for event handlers passed to child components
- Use `useMemo()` for expensive calculations
- Avoid creating new objects/arrays in render

### Accessibility Requirements
- Use semantic HTML elements
- Include proper ARIA attributes
- Ensure keyboard navigation
- Test with screen readers
- Follow WCAG AA guidelines for color contrast

### Test Coverage
- Write tests for all prop combinations
- Test error states and edge cases
- Mock store state for state-dependent behavior
- Test accessibility with `@testing-library/jest-dom`
- Include visual regression tests when appropriate

