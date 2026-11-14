# TypeScript Implementation

Type-safe code with zero TypeScript errors and proper type definitions.

## TypeScript Configuration

### Basic Setup
```typescript
// tsconfig.json - Already configured for this project
{
  "compilerOptions": {
    "target": "ES2020",
    "lib": ["DOM", "DOM.Iterable", "ES6"],
    "allowJs": true,
    "skipLibCheck": true,
    "esModuleInterop": true,
    "allowSyntheticDefaultImports": true,
    "strict": true,
    "forceConsistentCasingInFileNames": true,
    "module": "ESNext",
    "moduleResolution": "node",
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "jsx": "react-jsx",
    "declaration": false,
    "declarationMap": false,
    "sourceMap": true,
    "outDir": "./dist",
    "baseUrl": "./src"
  },
  "include": ["src/**/*"],
  "exclude": ["node_modules", "__tests__"]
}
```

## Type System Patterns

### Interface Definitions
```typescript
// ✅ CORRECT: Proper interface definitions
interface StoreState {
  nodes: Node[];
  edges: Edge[];
  selectedNode: Node | null;
  setNodes: (nodes: Node[] | ((nodes: Node[]) => Node[])) => void;
  updateNode: (nodeId: string, updates: Partial<Node>) => void;
}

interface Node {
  id: string;
  type: 'start' | 'element' | 'gateway' | 'subprocess';
  position: { x: number; y: number };
  data: {
    label?: string;
    plugin?: string;
  };
}

// ❌ WRONG: Using any types
interface BadStoreState {
  nodes: any[];  // Should be Node[]
  data: any;     // Should be properly typed
}
```

### Type Guards
```typescript
// ✅ CORRECT: Type guards for runtime validation
function isValidNode(node: unknown): node is Node {
  return typeof node === 'object' &&
         node !== null &&
         'id' in node &&
         'type' in node &&
         typeof (node as any).id === 'string';
}

function processNode(node: unknown) {
  if (isValidNode(node)) {
    // TypeScript knows this is Node here
    console.log(node.id);
  }
}

// ❌ WRONG: Type assertions without guards
function processNode(node: unknown) {
  console.log((node as Node).id);  // Unsafe
}
```

## Component Typing

### React Component Props
```typescript
// ✅ CORRECT: Proper component typing
interface Props {
  title: string;
  onAction?: () => void;
  disabled?: boolean;
}

const MyComponent: React.FC<Props> = ({ title, onAction, disabled = false }) => {
  return <button onClick={onAction} disabled={disabled}>{title}</button>;
};

// Alternative with explicit typing
const MyComponent = ({ title, onAction, disabled = false }: Props) => {
  return <button onClick={onAction} disabled={disabled}>{title}</button>;
};

// ❌ WRONG: Untyped props
const MyComponent = ({ title, onAction, disabled }) => {
  return <button onClick={onAction} disabled={disabled}>{title}</button>;
};
```

### Event Handler Typing
```typescript
// ✅ CORRECT: Properly typed event handlers
interface EventHandlers {
  onNodesChange: (changes: NodeChange[]) => void;
  onEdgesChange: (changes: EdgeChange[]) => void;
  onConnect: (connection: Connection) => void;
}

const FlowCanvas: React.FC<EventHandlers> = ({
  onNodesChange,
  onEdgesChange,
  onConnect
}) => {
  // Implementation
};

// ❌ WRONG: Untyped handlers
const FlowCanvas = ({ onNodesChange, onEdgesChange, onConnect }) => {
  // Types are unknown
};
```

## Utility Function Typing

### Generic Functions
```typescript
// ✅ CORRECT: Proper generic typing
function filterByType<T extends { type: string }>(
  items: T[],
  type: string
): T[] {
  return items.filter(item => item.type === type);
}

// Usage with type inference
const actionNodes = filterByType(nodes, 'action'); // TypeScript infers return type

// ❌ WRONG: Using any
function filterByType(items: any[], type: string): any[] {
  return items.filter(item => item.type === type);
}
```

### API Response Typing
```typescript
// ✅ CORRECT: Type-safe API handling
interface ApiResponse<T> {
  data: T;
  status: 'success' | 'error';
  message?: string;
}

async function fetchModelData(modelId: string): Promise<ApiResponse<ModelData>> {
  const response = await fetch(`/api/models/${modelId}`);
  const data = await response.json() as ApiResponse<ModelData>;
  return data;
}

// ❌ WRONG: Untyped API responses
async function fetchModelData(modelId: string) {
  const response = await fetch(`/api/models/${modelId}`);
  return await response.json(); // Type is unknown
}
```

## Shared Types

### Settings Types (types/settings.ts)
```typescript
// ✅ CORRECT: Centralized shared types
interface ModelerSettings {
  isNew?: boolean;
  stayInContextOnClose?: boolean;
  replayData?: unknown[];
  modelId?: string;
  components?: unknown[];
  modelData?: string;
  selectComponentId?: string;
  selectContextId?: string;
  favorite_components?: Record<number, string[]>;
  contexts?: ModelerContext[];
}

// Context types for filtering components by workflow context
type ContextComponentType = 'start' | 'subprocess' | 'swimlane' | 'element' | 'link' | 'gateway' | 'annotation';

interface ContextDependency {
  type: ContextComponentType;
  id: string;
}

interface ContextComponentEntry {
  plugins: string[];
}

type ModelerDependencies = Partial<
  Record<ContextComponentType, Record<string, ContextDependency[]>>
>;

interface ModelerContext {
  id: string;
  topic: string;
  model_owner: string;
  components: Partial<Record<ContextComponentType, ContextComponentEntry>>;
}

interface ModelerApiSettings {
  token_url?: string;
  save_url?: string;
  config_url?: string;
  replay_url?: string;
  collection_url?: string;
  metadata?: Metadata;
}

// Usage across components
const MyComponent = ({ settings }: { settings: ModelerSettings }) => {
  // Type-safe access to settings
  const isNewModel = settings.isNew ?? false;
};
```

### Validation Types
```typescript
// ✅ CORRECT: Type-safe validation utilities
interface ValidReplayEntry {
  model_id: string;
  event_id: string;
  history: unknown[];
  timestamp: string;
  user: string | Record<string, unknown>;
  ip: string;
  url: string;
}

function isValidReplayEntry(entry: unknown): entry is ValidReplayEntry {
  return typeof entry === 'object' &&
         entry !== null &&
         'model_id' in entry &&
         'event_id' in entry &&
         typeof (entry as any).model_id === 'string';
}
```

## Advanced TypeScript Patterns

### Discriminated Unions
```typescript
// ✅ CORRECT: Type-safe state handling
type LoadingState = { status: 'loading' };
type SuccessState<T> = { status: 'success'; data: T };
type ErrorState = { status: 'error'; error: string };

type AsyncState<T> = LoadingState | SuccessState<T> | ErrorState;

function renderState<T>(state: AsyncState<T>): ReactNode {
  switch (state.status) {
    case 'loading':
      return <div>Loading...</div>;
    case 'success':
      return <div>{state.data}</div>;
    case 'error':
      return <div>Error: {state.error}</div>;
  }
}
```

### Utility Types
```typescript
// ✅ CORRECT: Useful utility types
type DeepPartial<T> = {
  [P in keyof T]?: T[P] extends object ? DeepPartial<T[P]> : T[P];
};

type RequiredFields<T, K extends keyof T> = T & Required<Pick<T, K>>;

// Example usage
interface Config {
  id?: string;
  name: string;
  settings?: {
    theme?: string;
  };
}

const ensureRequired: RequiredFields<Config, 'id'> = {
  name: 'test',
  id: 'required',
  // settings optional as original
};
```

## Type-Safe State Management

### Zustand Store Typing
```typescript
// ✅ CORRECT: Type-safe domain-specific Zustand stores
// Each store has its own interface and create() call
interface GraphState {
  nodes: Node[];
  edges: Edge[];
  setNodes: (nodes: Node[] | ((nodes: Node[]) => Node[])) => void;
  setEdges: (edges: Edge[] | ((edges: Edge[]) => Edge[])) => void;
}

const useGraphStore = create<GraphState>((set, get) => ({
  nodes: [],
  edges: [],
  setNodes: (nodes) => set({ nodes: typeof nodes === 'function' ? nodes(get().nodes) : nodes }),
  setEdges: (edges) => set({ edges: typeof edges === 'function' ? edges(get().edges) : edges }),
}));

interface SelectionState {
  selectedNode: Node | null;
  setSelectedNode: (node: Node | null) => void;
}

const useSelectionStore = create<SelectionState>((set) => ({
  selectedNode: null,
  setSelectedNode: (selectedNode) => set({ selectedNode }),
}));
```

### Selector Functions
```typescript
// ✅ CORRECT: Type-safe selectors from domain stores
const useNodes = () => useGraphStore(state => state.nodes);
const useSelectedNode = () => useSelectionStore(state => state.selectedNode);
```

## Common TypeScript Errors

### Import/Export Issues
```typescript
// ✅ CORRECT: Import directly from the domain store file
import { Node, Edge } from 'reactflow';
import { useGraphStore } from '../store/useGraphStore';

// ❌ WRONG: Missing imports or incorrect paths
import { useGraphStore } from '../store';  // No barrel file
import Node from 'reactflow';              // Default import incorrect
```

### Type Assertion Issues
```typescript
// ✅ CORRECT: Safe type assertions
const element = document.getElementById('my-id') as HTMLElement | null;

// ❌ WRONG: Unsafe assertions
const element = document.getElementById('my-id') as HTMLElement;  // Might be null
```

### Promise Typing
```typescript
// ✅ CORRECT: Proper async function typing
const fetchData = async (url: string): Promise<{ data: unknown }> => {
  const response = await fetch(url);
  return response.json();
};

// ❌ WRONG: Untyped async function
const fetchData = async (url: string) => {
  const response = await fetch(url);
  return response.json();  // Type is any
};
```

## React Flow Integration

### React Flow Types
```typescript
// ✅ CORRECT: Proper React Flow typing
import { Node, Edge, NodeChange, EdgeChange, Connection } from 'reactflow';

const FlowCanvas: React.FC<{
  nodes: Node[];
  edges: Edge[];
  onNodesChange: (changes: NodeChange[]) => void;
  onEdgesChange: (changes: EdgeChange[]) => void;
  onConnect: (connection: Connection) => void;
}> = ({ nodes, edges, onNodesChange, onEdgesChange, onConnect }) => {
  return (
    <ReactFlow
      nodes={nodes}
      edges={edges}
      onNodesChange={onNodesChange}
      onEdgesChange={onEdgesChange}
      onConnect={onConnect}
    />
  );
};
```

### Custom Node Types
```typescript
// ✅ CORRECT: Extending React Flow types
interface CustomNodeData {
  label: string;
  plugin: string;
  annotation?: string;
}

type CustomNode = Node<CustomNodeData>;

const MyNode: React.FC<{ data: CustomNodeData; selected: boolean }> = ({ data, selected }) => {
  return <div>{data.label}</div>;
};
```

## Development Workflow

### Type Checking Commands
```bash
# TypeScript checking
npm run type-check

# Development build with type checking
npm run dev  # Includes type checking

# Watch mode (if configured)
npx tsc --watch
```

### IDE Integration
```typescript
// VSCode settings for optimal TypeScript experience
{
  "typescript.preferences.importModuleSpecifier": "relative",
  "typescript.suggest.autoImports": true,
  "typescript.updateImportsOnFileMove.enabled": "always",
  "typescript.preferences.includePackageJsonAutoImports": "on"
}
```

## Type Safety Checklist

### Before Committing Code
- [ ] All components have proper interface definitions
- [ ] No `any` types except for truly unknown data
- [ ] Type guards used for runtime validation
- [ ] Async functions properly typed with Promise<T>
- [ ] Event handlers have correct parameter types
- [ ] Store operations use proper selector patterns

### Code Review Points
- [ ] Interface definitions are complete and accurate
- [ ] Generic types are used appropriately
- [ ] Type assertions are safe and necessary
- [ ] Optional properties handled correctly
- [ ] Union types use discriminated unions when helpful

