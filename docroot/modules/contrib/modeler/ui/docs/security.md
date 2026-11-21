# Security Implementation

Secure coding practices to prevent XSS attacks and protect user data.

## XSS Prevention

### HTML Sanitization
```typescript
// ✅ CORRECT: Always sanitize HTML content
import { sanitizeHtml, sanitizeTokenHtml } from '../utils/sanitize';

// Server-provided content
const sanitizedMarkup = sanitizeHtml(field.markup);
<div dangerouslySetInnerHTML={{ __html: sanitizedMarkup }} />

// Token field content
const sanitizedToken = sanitizeTokenHtml(pastedHtml);
setContent(sanitizedToken);

// ❌ WRONG: Never trust user input
<div dangerouslySetInnerHTML={{ __html: userInput }} />
```

### Safe DOM Methods
```typescript
// ✅ CORRECT: Use safe DOM methods
element.textContent = userInput; // Escapes HTML
element.setAttribute('data-value', userInput); // Escapes attributes

// ❌ WRONG: Dangerous innerHTML without sanitization
element.innerHTML = userInput; // Executes scripts
```

### Token Drag-and-Drop Security
```typescript
// ✅ CORRECT: Validate token data structure
const handleDrop = useCallback((e: React.DragEvent) => {
  const tokenData = e.dataTransfer.getData('application/token');
  const parsed = JSON.parse(tokenData);

  // Validate structure
  const label = typeof parsed.label === 'string' ? parsed.label : '';
  let token = typeof parsed.token === 'string' ? parsed.token : '';

  if (!label || !token) {
    console.warn('Invalid token data');
    return;
  }

  // Ensure proper format
  if (!token.startsWith('[')) {
    token = `[${token}]`;
  }

  // Create element safely
  const tokenElement = document.createElement('span');
  tokenElement.textContent = label; // Safe textContent
  tokenElement.setAttribute('data-token', token); // Safe attribute
}, []);

// ❌ WRONG: Trust drag data without validation
const parsed = JSON.parse(tokenData);
const tokenElement = document.createElement('span');
tokenElement.innerHTML = parsed.label; // Dangerous
```

## API Response Validation

### CSRF Token Validation
```typescript
// ✅ CORRECT: Use validated CSRF tokens
import { fetchValidatedCsrfToken, validateCsrfToken } from '../utils/validation';

const saveModel = async (modelData: string) => {
  const csrfToken = await fetchValidatedCsrfToken(); // Fetches + validates
  
  const response = await fetch('/modeler-api/save', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify({ data: modelData })
  });
  
  return response;
};

// ❌ WRONG: Unvalidated token usage
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
```

### Replay Data Validation
```typescript
// ✅ CORRECT: Validate replay entries
import { validateReplayEntries } from '../utils/validation';

const loadReplayData = async (modelId: string, componentId: string) => {
  const response = await fetch('/modeler-api/replay', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ modelId, componentId })
  });
  
  const rawData = await response.json();
  const validatedEntries = validateReplayEntries(rawData); // Drops invalid with warnings
  
  return validatedEntries;
};

// ❌ WRONG: Trust API response structure
const entries = await response.json(); // Could be malformed
setReplayData(entries.history); // Might crash app
```

### Configuration Form Validation
```typescript
// ✅ CORRECT: Validate JSON response and handle errors
const loadConfigurationForm = async (componentId: string) => {
  const response = await fetch(`/modeler-api/config-form/${componentId}`, { method: 'POST', ... });
  const rawData = await response.json();
  
  // Returns { form: Record<string, unknown>[] | null, error: string | null }
  const { form, error } = validateConfigurationResponse(rawData);
  if (error) {
    showDrupalMessage(error, 'error');
  }
  setConfigurationForm(form);
};

// ❌ WRONG: Direct usage without validation
const configData = await response.json();
setFormFields(configData.form); // Might have wrong structure, error ignored
```

## Clipboard Security

### Encrypted Clipboard Storage
```typescript
// ✅ CORRECT: Use encrypted clipboard utilities
import { getFromClipboard, copyToClipboard } from '../utils/clipboardUtils';

const copySelected = async () => {
  const selectedData = {
    nodes: selectedNodes,
    edges: selectedEdges,
    timestamp: Date.now()
  };
  
  await copyToClipboard(selectedData); // Encrypts automatically
};

const paste = async () => {
  const clipboardData = await getFromClipboard(); // Decrypts automatically
  
  if (clipboardData) {
    addNodes(clipboardData.nodes);
    addEdges(clipboardData.edges);
  }
};

// ❌ WRONG: Plain text localStorage
localStorage.setItem('clipboard', JSON.stringify(data)); // Exposed data
const data = JSON.parse(localStorage.getItem('clipboard')); // Unvalidated
```

## Input Validation

### Paste Event Handling
```typescript
// ✅ CORRECT: Safe paste handling
const handlePaste = useCallback((e: React.ClipboardEvent) => {
  e.preventDefault();

  const plainText = e.clipboardData.getData('text/plain');
  const htmlText = e.clipboardData.getData('text/html');

  let contentToInsert: string;
  
  if (htmlText && htmlText.includes('config-token')) {
    // Preserve tokens but sanitize everything else
    contentToInsert = sanitizeTokenHtml(htmlText);
  } else {
    // Plain text - escape all HTML
    contentToInsert = escapeHtml(plainText);
  }
  
  insertContent(contentToInsert);
}, []);

// ❌ WRONG: Direct paste without sanitization
const handlePaste = (e: React.ClipboardEvent) => {
  const htmlText = e.clipboardData.getData('text/html');
  document.execCommand('insertHTML', false, htmlText); // Dangerous
};
```

### URL Validation
```typescript
// ✅ CORRECT: Validate URLs
import { sanitizeUrl } from '../utils/sanitize';

const handleLink = (url: string) => {
  const safeUrl = sanitizeUrl(url); // Blocks javascript:, data:, vbscript:
  
  if (safeUrl) {
    window.open(safeUrl, '_blank');
  }
};

// ❌ WRONG: Use URLs directly
window.open(userInputUrl, '_blank'); // Potential XSS
```

## Content Security Policy

### Safe Iframe Handling
```typescript
// ✅ CORRECT: Safe iframe for documentation
<iframe
  src={documentationUrl}
  sandbox="allow-scripts allow-same-origin" // Restricts capabilities
  referrerPolicy="no-referrer-when-downgrade"
  loading="lazy"
/>

// ❌ WRONG: Unrestricted iframe
<iframe src={userProvidedUrl} /> // Could load malicious content
```

### Dynamic Script Loading
```typescript
// ✅ CORRECT: Validate script sources
const loadExternalScript = async (scriptUrl: string) => {
  if (!scriptUrl.startsWith('https://trusted-domain.com/')) {
    throw new Error('Untrusted script source');
  }
  
  const script = document.createElement('script');
  script.src = scriptUrl;
  script.crossOrigin = 'anonymous';
  document.head.appendChild(script);
};

// ❌ WRONG: Load any script
const script = document.createElement('script');
script.src = userInput; // Loads any URL
document.head.appendChild(script);
```

## Error Handling Security

### Safe Error Display
```typescript
// ✅ CORRECT: Sanitize error messages
const displayError = (error: Error) => {
  const sanitizedMessage = escapeHtml(error.message);
  setErrorMessage(sanitizedMessage);
};

// ❌ WRONG: Direct error message display
setErrorMessage(error.message); // Could contain HTML/JS
```

### Logging Security
```typescript
// ✅ CORRECT: Sanitize logged data
const logUserAction = (action: string, data: unknown) => {
  const sanitizedData = JSON.stringify(data)
    .replace(/</g, '\\u003c')
    .replace(/>/g, '\\u003e');
  
  console.log(`User action: ${action}`, sanitizedData);
};

// ❌ WRONG: Log raw data
console.log('User data:', userData); // Exposes sensitive data
```

## Token Security Patterns

### Token Format Validation
```typescript
// ✅ CORRECT: Validate token syntax
const validateTokenFormat = (token: string): boolean => {
  // [token:path:here] format
  return /^\[[a-zA-Z][a-zA-Z0-9_:-]*\]$/.test(token);
};

const processToken = (token: string) => {
  if (!validateTokenFormat(token)) {
    console.warn('Invalid token format:', token);
    return null;
  }
  
  return extractTokenData(token);
};
```

### Token Conversion Security
```typescript
// ✅ CORRECT: Secure bidirectional conversion
import { tokensToHtml, htmlToTokens } from '../utils/tokenUtils';

// Storage → Display
const displayTokens = (storageText: string) => {
  const html = tokensToHtml(storageText); // Creates safe spans
  setContent(html);
};

// Display → Storage  
const saveTokens = (displayHtml: string) => {
  const storageText = htmlToTokens(displayHtml); // Extracts token strings
  saveToStorage(storageText);
};
```

## Security Utilities

### Available Functions
```typescript
import { 
  sanitizeHtml,           // General HTML sanitization
  sanitizeTokenHtml,       // Token-specific sanitization
  escapeHtml,             // Plain text escaping
  sanitizeUrl,            // URL validation
  validateCsrfToken,      // CSRF token validation
  validateReplayEntries,  // Replay data validation
  validateConfigurationResponse, // Form data validation
  fetchValidatedCsrfToken, // Fetch + validate CSRF
} from '../utils/sanitize';
```

### Validation Utilities
```typescript
import {
  validateCsrfToken,
  validateReplayEntries,
  validateConfigurationResponse,
  validateModelDataShape,
  validateDocumentationResponse
} from '../utils/validation';
```

## Security Checklist

### Before Implementing Features
- [ ] All user input sanitized with `sanitizeHtml()` or `sanitizeTokenHtml()`
- [ ] External API responses validated with appropriate validators
- [ ] Clipboard data encrypted using clipboard utilities
- [ ] URLs validated with `sanitizeUrl()`
- [ ] Error messages sanitized with `escapeHtml()`
- [ ] Iframes sandboxed with appropriate restrictions
- [ ] Token format validated before processing

### Before Deploying Changes
- [ ] No direct `innerHTML` usage without sanitization
- [ ] No `eval()` or similar dynamic code execution
- [ ] All `dangerouslySetInnerHTML` uses sanitized content
- [ ] Clipboard operations use encrypted storage
- [ ] API calls include CSRF tokens
- [ ] Error handling doesn't expose sensitive information

## Common Security Vulnerabilities

### XSS Injection Points
- Configuration form fields
- Token drag-and-drop
- Paste operations
- Documentation popups
- Error message display

### Data Exposure Risks
- Unencrypted localStorage
- Detailed error messages
- Console logging of sensitive data
- Unvalidated API responses

### Injection Vectors
- JavaScript URLs in links
- Data URLs in images
- CSS injection via styles
- Script tag injection

