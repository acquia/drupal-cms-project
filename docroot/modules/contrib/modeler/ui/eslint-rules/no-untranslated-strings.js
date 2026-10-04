/**
 * ESLint rule to enforce translation of user-facing strings
 * 
 * This rule ensures that string literals in JSX and certain function calls
 * are wrapped with the t() translation function for i18n support.
 * 
 * @example
 * // Bad
 * <button>Save</button>
 * <div title="Click here">...</div>
 * 
 * // Good
 * <button>{t('Save')}</button>
 * <div title={t('Click here')}>...</div>
 */

/** @type {import('eslint').Rule.RuleModule} */
const rule = {
  meta: {
    type: 'suggestion',
    docs: {
      description: 'Enforce translation of user-facing strings using t()',
      category: 'Best Practices',
      recommended: true,
    },
    messages: {
      untranslatedString: 'User-facing string "{{text}}" should be wrapped with t() for translation',
      untranslatedJsxText: 'JSX text "{{text}}" should be wrapped with t() for translation',
      untranslatedAttribute: 'Attribute "{{attr}}" with value "{{text}}" should use t() for translation',
    },
    schema: [
      {
        type: 'object',
        properties: {
          ignoreAttributes: {
            type: 'array',
            items: { type: 'string' },
            description: 'List of JSX attributes to ignore',
          },
          ignoreFunctions: {
            type: 'array',
            items: { type: 'string' },
            description: 'List of function names whose arguments should be ignored',
          },
          ignorePatterns: {
            type: 'array',
            items: { type: 'string' },
            description: 'Regex patterns for strings to ignore',
          },
        },
        additionalProperties: false,
      },
    ],
  },

  create(context) {
    const options = context.options[0] || {};
    
    // Attributes that should be translated (user-facing)
    const translatableAttributes = new Set([
      'title',
      'alt',
      'placeholder',
      'aria-label',
      'aria-description',
      'aria-placeholder',
      'aria-valuetext',
    ]);
    
    // Attributes to always ignore (technical, not user-facing)
    const ignoredAttributes = new Set([
      'className',
      'class',
      'id',
      'name',
      'type',
      'value',
      'href',
      'src',
      'data-testid',
      'data-id',
      'key',
      'ref',
      'style',
      'role',
      'tabIndex',
      'htmlFor',
      'autoComplete',
      'autoFocus',
      'disabled',
      'readOnly',
      'required',
      'checked',
      'selected',
      'multiple',
      'accept',
      'pattern',
      'min',
      'max',
      'step',
      'rows',
      'cols',
      'size',
      'maxLength',
      'minLength',
      'width',
      'height',
      'viewBox',
      'xmlns',
      'd',
      'fill',
      'stroke',
      'strokeWidth',
      'transform',
      'cx',
      'cy',
      'r',
      'x',
      'y',
      'x1',
      'x2',
      'y1',
      'y2',
      'points',
      'preserveAspectRatio',
      ...(options.ignoreAttributes || []),
    ]);
    
    // Functions whose arguments should not be checked
    const ignoredFunctions = new Set([
      'console.log',
      'console.warn',
      'console.error',
      'console.info',
      'console.debug',
      'require',
      'import',
      't', // The translation function itself
      'Drupal.t',
      'getElementById',
      'getElementsByClassName',
      'querySelector',
      'querySelectorAll',
      'getAttribute',
      'setAttribute',
      'hasAttribute',
      'removeAttribute',
      'classList.add',
      'classList.remove',
      'classList.toggle',
      'classList.contains',
      'addEventListener',
      'removeEventListener',
      'dispatchEvent',
      'matchMedia', // CSS media queries (technical, not user-facing)
      'JSON.parse',
      'JSON.stringify',
      'Object.keys',
      'Object.values',
      'Object.entries',
      'Array.from',
      'Array.isArray',
      'String',
      'Number',
      'Boolean',
      'parseInt',
      'parseFloat',
      'encodeURIComponent',
      'decodeURIComponent',
      'encodeURI',
      'decodeURI',
      'btoa',
      'atob',
      'fetch',
      'localStorage.getItem',
      'localStorage.setItem',
      'localStorage.removeItem',
      'sessionStorage.getItem',
      'sessionStorage.setItem',
      'sessionStorage.removeItem',
      'RegExp',
      'Date',
      'Error',
      'TypeError',
      'RangeError',
      'SyntaxError',
      'setTimeout',
      'setInterval',
      'clearTimeout',
      'clearInterval',
      'requestAnimationFrame',
      'cancelAnimationFrame',
      // String matching methods - used for comparing against error messages, API values, etc.
      'includes',
      'startsWith',
      'endsWith',
      'indexOf',
      'lastIndexOf',
      'match',
      'search',
      'replace',
      'replaceAll',
      'split',
      ...(options.ignoreFunctions || []),
    ]);
    
    // Patterns to ignore (technical strings, not user-facing)
    const defaultIgnorePatterns = [
      // Single characters or very short strings (3 chars or less)
      /^.{0,3}$/,
      // CSS-like values (borders, shadows, animations, etc.)
      /^-?\d+(\.\d+)?(px|em|rem|%|vh|vw|deg|rad|s|ms)?$/,
      /^\d+px\s+solid\s+/i, // border values
      /^\d+\s+\d+\s+\d+/, // box-shadow numeric values
      /rgba?\s*\(/i, // rgba/rgb values
      /^[\d.]+\s*(px|em|rem|%|s|ms)\s/i, // CSS values with units
      /^\d+(\.\d+)?x$/, // multipliers like 0.5x, 2x
      /\s+infinite$/i, // CSS animation keywords
      /\s+solid\s+/i, // CSS border style
      // Color values
      /^#[0-9a-fA-F]{3,8}$/,
      /^(rgb|rgba|hsl|hsla)\(/,
      // URLs and paths
      /^(https?:\/\/|\/|\.\/|\.\.\/)/,
      /\.(js|jsx|ts|tsx|css|scss|json|html|svg|png|jpg|jpeg|gif|webp|ico|woff|woff2|ttf|eot)$/i,
      // Technical identifiers (camelCase, snake_case, kebab-case, SCREAMING_CASE)
      /^[a-z][a-zA-Z0-9]*$/, // camelCase
      /^[a-z][a-z0-9]*(_[a-z0-9]+)+$/, // snake_case
      /^[a-z][a-z0-9]*(-[a-z0-9]+)+$/, // kebab-case
      /^[a-z][a-z0-9]*((-|--)[a-z0-9]+)+$/, // BEM class modifiers (block__element--modifier, kebab segments)
      /^[A-Z][A-Z0-9]*(_[A-Z0-9]+)*$/, // SCREAMING_SNAKE_CASE
      // Keyboard key names (used in event handlers, not shown to users)
      /^(Escape|Enter|Tab|Backspace|Delete|ArrowUp|ArrowDown|ArrowLeft|ArrowRight|Home|End|PageUp|PageDown|Insert|Shift|Control|Alt|Meta|CapsLock|NumLock|ScrollLock|Pause|F\d+)$/,
      // Data attributes and DOM-related
      /^data-/,
      /^aria-/,
      // Event names
      /^on[A-Z]/,
      // Common technical strings
      /^(true|false|null|undefined|NaN|Infinity)$/,
      // MIME types
      /^(text|application|image|audio|video)\//,
      // UUIDs
      /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i,
      // Numbers only (including decimals)
      /^-?\d+(\.\d+)?$/,
      // Empty or whitespace only
      /^\s*$/,
      // Template literal placeholders
      /^[@%!:]\w+$/,
      // JSON-like structures
      /^\{.*\}$|^\[.*\]$/,
      // Email pattern (but allow actual error messages about email)
      /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/,
      // Version strings
      /^\d+\.\d+(\.\d+)?(-[a-zA-Z0-9]+)?$/,
      // Ellipsis patterns (often used as placeholders, not user-facing)
      /^\.{2,}$/,
      /^\.\.\.[a-z]+$/i, // like "...and"
      // Technical display strings (Array, Object with counts)
      /^(Array|Object)\s*\(/,
      // CSS margin/padding shorthand (0 auto, 10px 20px, etc.)
      /^\d*\s*auto$/i,
      /^auto\s*\d*$/i,
      // CSS transition/animation values
      /^\w+\s+[\d.]+s/i, // opacity 0.2s, transform 0.3s
    ];
    
    const userIgnorePatterns = (options.ignorePatterns || []).map(p => new RegExp(p));
    const ignorePatterns = [...defaultIgnorePatterns, ...userIgnorePatterns];
    
    /**
     * Check if a string should be ignored
     */
    function shouldIgnoreString(str) {
      if (typeof str !== 'string') return true;
      
      const trimmed = str.trim();
      
      // Ignore empty strings
      if (!trimmed) return true;
      
      // Check against ignore patterns
      return ignorePatterns.some(pattern => pattern.test(trimmed));
    }
    
    /**
     * Check if we're inside a t() call
     */
    function isInsideTranslationCall(node) {
      let current = node.parent;
      while (current) {
        if (current.type === 'CallExpression') {
          const callee = current.callee;
          // Check for t() or Drupal.t()
          if (callee.type === 'Identifier' && callee.name === 't') {
            return true;
          }
          if (
            callee.type === 'MemberExpression' &&
            callee.object.type === 'Identifier' &&
            callee.object.name === 'Drupal' &&
            callee.property.type === 'Identifier' &&
            callee.property.name === 't'
          ) {
            return true;
          }
        }
        current = current.parent;
      }
      return false;
    }
    
    /**
     * Check if we're inside an ignored function call
     */
    function isInsideIgnoredFunction(node) {
      let current = node.parent;
      while (current) {
        // Handle both CallExpression (function()) and NewExpression (new Constructor())
        if (current.type === 'CallExpression' || current.type === 'NewExpression') {
          const callee = current.callee;
          let funcName = '';
          let methodName = '';
          
          if (callee.type === 'Identifier') {
            funcName = callee.name;
          } else if (callee.type === 'MemberExpression') {
            // Get the method name (e.g., 'includes' from message.includes)
            if (callee.property.type === 'Identifier') {
              methodName = callee.property.name;
            }
            // Build the full function name (e.g., console.log)
            const parts = [];
            let obj = callee;
            while (obj.type === 'MemberExpression') {
              if (obj.property.type === 'Identifier') {
                parts.unshift(obj.property.name);
              }
              obj = obj.object;
            }
            if (obj.type === 'Identifier') {
              parts.unshift(obj.name);
            }
            funcName = parts.join('.');
          }
          
          // Check both full function name and just the method name
          if (ignoredFunctions.has(funcName) || ignoredFunctions.has(methodName)) {
            return true;
          }
        }
        current = current.parent;
      }
      return false;
    }
    
    /**
     * Check if we're in a test file
     */
    function isTestFile() {
      const filename = context.getFilename();
      return /\.(test|spec)\.(js|jsx|ts|tsx)$/.test(filename) ||
             /__tests__\//.test(filename) ||
             /\.stories\.(js|jsx|ts|tsx)$/.test(filename);
    }
    
    /**
     * Check if we're inside a jest/test function
     */
    function isInsideTestFunction(node) {
      let current = node.parent;
      while (current) {
        if (current.type === 'CallExpression') {
          const callee = current.callee;
          if (callee.type === 'Identifier') {
            const testFunctions = ['describe', 'it', 'test', 'beforeEach', 'afterEach', 'beforeAll', 'afterAll', 'expect'];
            if (testFunctions.includes(callee.name)) {
              return true;
            }
          }
        }
        current = current.parent;
      }
      return false;
    }
    
    /**
     * Check if we're in an object key position
     */
    function isObjectKey(node) {
      return node.parent.type === 'Property' && node.parent.key === node;
    }
    
    /**
     * Check if we're in a switch case
     */
    function isSwitchCase(node) {
      return node.parent.type === 'SwitchCase' && node.parent.test === node;
    }
    
    /**
     * Check if we're in a type annotation or interface
     */
    function isTypeAnnotation(node) {
      let current = node.parent;
      while (current) {
        if (
          current.type === 'TSTypeAnnotation' ||
          current.type === 'TSInterfaceDeclaration' ||
          current.type === 'TSTypeAliasDeclaration' ||
          current.type === 'TSLiteralType'
        ) {
          return true;
        }
        current = current.parent;
      }
      return false;
    }
    
    /**
     * Check if we're in an import/export statement
     */
    function isImportExport(node) {
      let current = node.parent;
      while (current) {
        if (
          current.type === 'ImportDeclaration' ||
          current.type === 'ExportNamedDeclaration' ||
          current.type === 'ExportDefaultDeclaration'
        ) {
          return true;
        }
        current = current.parent;
      }
      return false;
    }
    
    /**
     * Check if we're in a comparison expression (===, ==, !==, !=)
     * These are typically used for comparing against API values or technical identifiers
     */
    function isComparisonOperand(node) {
      if (node.parent.type === 'BinaryExpression') {
        const operators = ['===', '==', '!==', '!='];
        return operators.includes(node.parent.operator);
      }
      return false;
    }
    
    /**
     * Check if we're in a logical expression with comparison
     * e.g., component.type === 'link' || component.type === 'start'
     */
    function isInLogicalComparison(node) {
      let current = node.parent;
      while (current) {
        if (current.type === 'LogicalExpression') {
          // Check if either side is a binary comparison
          const checkBinary = (expr) => {
            if (expr.type === 'BinaryExpression') {
              const operators = ['===', '==', '!==', '!='];
              return operators.includes(expr.operator);
            }
            return false;
          };
          if (checkBinary(current.left) || checkBinary(current.right)) {
            return true;
          }
        }
        current = current.parent;
      }
      return false;
    }

    return {
      // Check JSX text content
      JSXText(node) {
        // Skip test files
        if (isTestFile()) return;
        
        const text = node.value.trim();
        
        // Skip empty or whitespace-only text
        if (!text) return;
        
        // Skip if it matches ignore patterns
        if (shouldIgnoreString(text)) return;
        
        context.report({
          node,
          messageId: 'untranslatedJsxText',
          data: { text: text.substring(0, 50) + (text.length > 50 ? '...' : '') },
        });
      },
      
      // Check JSX attributes with string values
      JSXAttribute(node) {
        // Skip test files
        if (isTestFile()) return;
        
        const attrName = node.name.name;
        
        // Skip non-translatable attributes
        if (ignoredAttributes.has(attrName)) return;
        
        // Only check attributes that should be translated
        if (!translatableAttributes.has(attrName)) return;
        
        // Check if value is a string literal
        if (node.value && node.value.type === 'Literal' && typeof node.value.value === 'string') {
          const text = node.value.value;
          
          if (!shouldIgnoreString(text)) {
            context.report({
              node: node.value,
              messageId: 'untranslatedAttribute',
              data: { 
                attr: attrName, 
                text: text.substring(0, 50) + (text.length > 50 ? '...' : '') 
              },
            });
          }
        }
      },
      
      // Check string literals in specific contexts
      Literal(node) {
        // Skip test files
        if (isTestFile()) return;
        
        // Only check string literals
        if (typeof node.value !== 'string') return;
        
        const text = node.value;
        
        // Skip if matches ignore patterns
        if (shouldIgnoreString(text)) return;
        
        // Skip if inside t() call
        if (isInsideTranslationCall(node)) return;
        
        // Skip if inside ignored function
        if (isInsideIgnoredFunction(node)) return;
        
        // Skip if inside test function
        if (isInsideTestFunction(node)) return;
        
        // Skip object keys
        if (isObjectKey(node)) return;
        
        // Skip switch cases
        if (isSwitchCase(node)) return;
        
        // Skip type annotations
        if (isTypeAnnotation(node)) return;
        
        // Skip imports/exports
        if (isImportExport(node)) return;
        
        // Skip comparison operands (comparing against API values, categories, etc.)
        if (isComparisonOperand(node)) return;
        
        // Skip strings in logical comparisons
        if (isInLogicalComparison(node)) return;
        
        // Skip JSX attributes (handled separately)
        if (node.parent.type === 'JSXAttribute') return;
        
        // Skip JSX expression containers that are attribute values
        if (
          node.parent.type === 'JSXExpressionContainer' &&
          node.parent.parent.type === 'JSXAttribute'
        ) {
          const attrName = node.parent.parent.name.name;
          if (ignoredAttributes.has(attrName) || !translatableAttributes.has(attrName)) {
            return;
          }
        }
        
        // Skip variable declarations with technical names
        if (
          node.parent.type === 'VariableDeclarator' &&
          node.parent.id.type === 'Identifier'
        ) {
          // Allow const MY_CONSTANT = 'value' patterns
          return;
        }
        
        // Skip template literal quasi (the static parts)
        if (node.parent.type === 'TemplateLiteral') return;
        
        // Report user-facing strings that should be translated
        // Only report strings that look like user-facing text (contain spaces or are common UI words)
        const looksLikeUserFacingText = 
          text.includes(' ') || // Has spaces (likely a phrase)
          /^[A-Z][a-z]+$/.test(text); // Capitalized word (like "Save", "Cancel")
        
        if (looksLikeUserFacingText) {
          context.report({
            node,
            messageId: 'untranslatedString',
            data: { text: text.substring(0, 50) + (text.length > 50 ? '...' : '') },
          });
        }
      },
    };
  },
};

export default rule;
