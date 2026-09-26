/**
 * YamlEditor - Structured YAML editing widget
 *
 * Renders a form-based UI for editing YAML data according to a schema.
 * The schema is provided inline via the `yaml_schema` field property
 * (auto-discovered from Drupal config schema) and defines the structure
 * of the data: field types, nesting, lists, mappings, etc.
 *
 * Supported schema types:
 *   - string:  text input (with optional `options` for enum/select)
 *   - number:  numeric input (with optional min/max/step)
 *   - boolean: checkbox toggle
 *   - list:           ordered array with add/remove controls
 *   - mapping:        named key/value group rendered as a fieldset
 *   - keyed_mapping:  mapping with user-defined keys and structured values
 *
 * Data is stored as a YAML string but the UI provides granular,
 * field-level editing so users never have to write raw YAML.
 */

import React, { useState, useCallback, useEffect, useMemo, useRef } from 'react';
import yaml from 'js-yaml';
import { FiPlus, FiTrash2, FiChevronDown, FiChevronRight, FiAlertTriangle, FiAlignLeft } from 'react-icons/fi';
import { t } from '../utils/translation';

// ---------------------------------------------------------------------------
// Schema types
// ---------------------------------------------------------------------------

export interface YamlSchemaBase {
  type: 'string' | 'number' | 'boolean' | 'scalar' | 'list' | 'mapping' | 'keyed_mapping';
  label?: string;
  description?: string;
  required?: boolean;
  default?: unknown;
}

export interface YamlSchemaString extends YamlSchemaBase {
  type: 'string';
  /** When present the field renders as a <select> with these choices. */
  options?: Record<string, string>;
  placeholder?: string;
}

export interface YamlSchemaNumber extends YamlSchemaBase {
  type: 'number';
  min?: number;
  max?: number;
  step?: number;
  placeholder?: string;
}

export interface YamlSchemaBoolean extends YamlSchemaBase {
  type: 'boolean';
}

/** Accepts any scalar value: string, number, or boolean.  Renders as a text
 *  input and preserves the original YAML type on round-trip. */
export interface YamlSchemaScalar extends YamlSchemaBase {
  type: 'scalar';
  placeholder?: string;
}

export interface YamlSchemaList extends YamlSchemaBase {
  type: 'list';
  /** Schema that describes each element in the list. */
  items: YamlSchema;
}

export interface YamlSchemaMapping extends YamlSchemaBase {
  type: 'mapping';
  /** Named properties of the mapping. */
  properties: Record<string, YamlSchema>;
  /**
   * When set, this field acts as a discriminator for conditional properties.
   * The value of this field determines which additional properties are shown.
   * Must reference a key in `properties` that has `options` defined.
   */
  discriminator?: string;
  /**
   * Conditional properties that are shown based on the discriminator value.
   * Keys are discriminator values, values are property schemas to show when
   * that discriminator value is selected.
   */
  conditionalProperties?: Record<string, Record<string, YamlSchema>>;
}

export interface YamlSchemaKeyedMapping extends YamlSchemaBase {
  type: 'keyed_mapping';
  /** Schema that describes the value of each keyed entry. */
  items: YamlSchema;
}

export type YamlSchema =
  | YamlSchemaString
  | YamlSchemaNumber
  | YamlSchemaBoolean
  | YamlSchemaScalar
  | YamlSchemaList
  | YamlSchemaMapping
  | YamlSchemaKeyedMapping;

// ---------------------------------------------------------------------------
// Props
// ---------------------------------------------------------------------------

export interface YamlEditorProps {
  /** Current YAML string value. */
  value: string;
  /** Called with the updated YAML string whenever the data changes. */
  onChange: (yamlString: string) => void;
  /**
   * The schema that defines the data structure.
   * When omitted the editor operates in schema-less mode: only raw YAML
   * editing is available (no structured "Editor" tab).
   */
  schema?: YamlSchema;
  /** When true all inputs are read-only. */
  disabled?: boolean;
  /**
   * When true (and no schema is provided), validate YAML syntax while the
   * user types and display parse errors inline.  Has no effect when a schema
   * is provided (schema mode always validates).
   */
  validate?: boolean;
  /**
   * Content format for the schema-less editor.  Defaults to `'yaml'` (the
   * existing behavior: js-yaml parsing and the `Invalid YAML syntax` message).
   * When set to `'json'`, the schema-less editor validates with `JSON.parse`,
   * shows an `Invalid JSON:` error with the raw parser message, and exposes a
   * one-click "Format" action that pretty-prints valid JSON.  The value still
   * round-trips as the raw string the user typed.  Has no effect in schema
   * mode (schema mode is always YAML).
   */
  format?: 'yaml' | 'json';
  /**
   * DOM id for the raw textarea in schema-less mode, so a surrounding
   * `<label htmlFor=...>` can name it. Ignored in schema mode, which renders a
   * tree of individually labeled inputs rather than one control.
   */
  id?: string;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Parse a YAML string into a JS value, returning `undefined` on failure. */
function parseYaml(yamlString: string): unknown {
  // Guard against non-string input (e.g. objects passed by mistake).
  if (typeof yamlString !== 'string') {
    return typeof yamlString === 'object' && yamlString !== null
      ? yamlString
      : undefined;
  }
  if (!yamlString.trim()) return undefined;
  try {
    return yaml.load(yamlString);
  } catch {
    return undefined;
  }
}

/** Serialize a JS value to a YAML string. */
function toYaml(value: unknown): string {
  if (value === undefined || value === null) return '';
  try {
    const result = yaml.dump(value, {
      indent: 2,
      lineWidth: -1,
      noRefs: true,
      sortKeys: false,
    });
    // yaml.dump always appends a trailing newline — trim it for cleaner storage.
    return result.replace(/\n$/, '');
  } catch {
    return '';
  }
}

/** Create a default/empty value matching a schema node. */
function createDefaultValue(schema: YamlSchema): unknown {
  if (schema.default !== undefined) return schema.default;
  switch (schema.type) {
    case 'string':
    case 'scalar':
      return '';
    case 'number':
      return 0;
    case 'boolean':
      return false;
    case 'list':
      return [];
    case 'mapping': {
      const obj: Record<string, unknown> = {};
      for (const [key, propSchema] of Object.entries(schema.properties)) {
        obj[key] = createDefaultValue(propSchema);
      }
      return obj;
    }
    case 'keyed_mapping':
      return {};
  }
}

/** Create a new empty item for a list based on its items schema. */
function createListItem(itemSchema: YamlSchema): unknown {
  return createDefaultValue(itemSchema);
}

// ---------------------------------------------------------------------------
// YAML textarea keyboard helpers
// ---------------------------------------------------------------------------

/** Number of spaces used for one indent level. */
const INDENT = '  '; // 2 spaces — matches js-yaml dump default.

/**
 * Return the line of `text` that contains position `pos`,
 * together with its start offset within `text`.
 */
function lineAt(text: string, pos: number): { line: string; start: number } {
  const start = text.lastIndexOf('\n', pos - 1) + 1;
  let end = text.indexOf('\n', pos);
  if (end === -1) end = text.length;
  return { line: text.slice(start, end), start };
}

/** Return the leading whitespace of a line. */
function leadingWhitespace(line: string): string {
  const match = line.match(/^(\s*)/);
  return match ? match[1] : '';
}

/**
 * Try to use `document.execCommand('insertText')` for undo-stack integration.
 * Returns `true` if the command was available and executed, `false` otherwise
 * (e.g. in JSDOM / tests).
 */
function tryExecInsertText(text: string): boolean {
  try {
    if (typeof document !== 'undefined' && typeof document.execCommand === 'function') {
      return document.execCommand('insertText', false, text);
    }
  } catch {
    // execCommand may throw in some environments; treat as unavailable.
  }
  return false;
}

/**
 * Build a `keydown` handler for a YAML `<textarea>` that provides:
 *
 *  - **Tab** – insert 2 spaces (or indent every selected line)
 *  - **Shift+Tab** – remove up to 2 leading spaces from selected lines
 *  - **Enter** – auto-indent, auto-deepen after `:`, continue `- ` bullets
 *  - **Enter on empty bullet** – remove the empty `- ` instead of continuing
 *
 * The handler calls `onNewValue(text)` whenever it modifies the textarea
 * content so the React component can sync state.
 */
export function handleYamlKeyDown(
  e: React.KeyboardEvent<HTMLTextAreaElement>,
  onNewValue: (text: string) => void,
): void {
  const ta = e.currentTarget;
  const { selectionStart, selectionEnd, value } = ta;

  // ---- Tab / Shift-Tab ------------------------------------------------
  if (e.key === 'Tab') {
    e.preventDefault();

    // Find the range of lines covered by the selection.
    const firstLineStart = value.lastIndexOf('\n', selectionStart - 1) + 1;
    let lastLineEnd = value.indexOf('\n', selectionEnd);
    if (lastLineEnd === -1) lastLineEnd = value.length;

    const selectedBlock = value.slice(firstLineStart, lastLineEnd);
    const lines = selectedBlock.split('\n');

    if (e.shiftKey) {
      // ----- outdent -----
      let removedBeforeCursor = 0;
      let removedBeforeEnd = 0;
      const newLines = lines.map((line, i) => {
        let removed = 0;
        let result = line;
        // Remove up to 2 leading spaces.
        for (let s = 0; s < INDENT.length && result.startsWith(' '); s++) {
          result = result.slice(1);
          removed++;
        }
        // Track how much was removed for cursor adjustment.
        if (i === 0) removedBeforeCursor = removed;
        removedBeforeEnd += removed;
        return result;
      });

      const newBlock = newLines.join('\n');
      const newValue = value.slice(0, firstLineStart) + newBlock + value.slice(lastLineEnd);
      // Apply the edit via execCommand for undo-ability, falling back to
      // direct assignment when execCommand is not available.
      ta.setSelectionRange(firstLineStart, lastLineEnd);
      if (!tryExecInsertText(newBlock)) {
        ta.value = newValue;
      }
      onNewValue(ta.value);
      ta.setSelectionRange(
        Math.max(firstLineStart, selectionStart - removedBeforeCursor),
        Math.max(firstLineStart, selectionEnd - removedBeforeEnd),
      );
    } else {
      // ----- indent -----
      if (selectionStart === selectionEnd) {
        // No selection — insert spaces at cursor.
        if (!tryExecInsertText(INDENT)) {
          ta.value = value.slice(0, selectionStart) + INDENT + value.slice(selectionEnd);
        }
        onNewValue(ta.value);
        const newPos = selectionStart + INDENT.length;
        ta.setSelectionRange(newPos, newPos);
      } else {
        // Selection spans lines — indent each line.
        const newBlock = lines.map((l) => INDENT + l).join('\n');
        const newValue = value.slice(0, firstLineStart) + newBlock + value.slice(lastLineEnd);
        ta.setSelectionRange(firstLineStart, lastLineEnd);
        if (!tryExecInsertText(newBlock)) {
          ta.value = newValue;
        }
        onNewValue(ta.value);
        ta.setSelectionRange(
          selectionStart + INDENT.length,
          selectionEnd + lines.length * INDENT.length,
        );
      }
    }
    return;
  }

  // ---- Enter ----------------------------------------------------------
  if (e.key === 'Enter') {
    const { line, start } = lineAt(value, selectionStart);
    const indent = leadingWhitespace(line);
    const trimmed = line.trimStart();

    // Empty list bullet (e.g. just "  - "): remove the bullet instead of
    // continuing.  This lets users press Enter twice to "escape" a list.
    if (/^-\s*$/.test(trimmed)) {
      e.preventDefault();
      // Remove the entire current line (including preceding newline if present).
      const deleteFrom = start > 0 ? start - 1 : start;
      const deleteTo = start + line.length;
      ta.setSelectionRange(deleteFrom, deleteTo);
      if (!tryExecInsertText('')) {
        ta.value = value.slice(0, deleteFrom) + value.slice(deleteTo);
      }
      onNewValue(ta.value);
      const newPos = deleteFrom;
      ta.setSelectionRange(newPos, newPos);
      return;
    }

    // Determine what to insert after the newline.
    let extra = indent; // at least preserve indentation

    if (/:\s*$/.test(trimmed) || trimmed === ':') {
      // Current line ends with ":" → deepen indent (mapping value follows).
      // This takes priority over list-bullet continuation so that
      // "- key:" deepens rather than repeating the bullet.
      extra = indent + INDENT;
    } else if (/^-\s/.test(trimmed)) {
      // Current line is a list item → continue with "- "
      extra = indent + '- ';
    }

    e.preventDefault();
    const insertion = '\n' + extra;
    if (!tryExecInsertText(insertion)) {
      ta.value = value.slice(0, selectionStart) + insertion + value.slice(selectionEnd);
    }
    onNewValue(ta.value);
    const newPos = selectionStart + insertion.length;
    ta.setSelectionRange(newPos, newPos);
  }
}

// ---------------------------------------------------------------------------
// Schema validation
// ---------------------------------------------------------------------------

export interface ValidationError {
  /** Dot-separated path to the offending value (e.g. "host" or "endpoints[0].path"). */
  path: string;
  /** Human-readable description of the problem. */
  message: string;
}

/**
 * Recursively validate a parsed value against a YamlSchema.
 *
 * Returns an array of validation errors.  An empty array means the data is
 * valid.  The function is intentionally lenient: it reports structural
 * mismatches (wrong type, unexpected keys, missing required fields) but does
 * not reject `undefined`/`null` for optional fields so that partially-filled
 * YAML is tolerated while the user is still typing.
 */
export function validateAgainstSchema(
  value: unknown,
  schema: YamlSchema,
  path = '',
): ValidationError[] {
  const errors: ValidationError[] = [];

  // Null / undefined — only flag if the field is required.
  if (value === undefined || value === null) {
    if (schema.required) {
      errors.push({ path: path || schema.label || 'root', message: t('Required field is missing.') });
    }
    return errors;
  }

  switch (schema.type) {
    // ----- scalars -----
    case 'scalar': {
      // Accepts string, number, or boolean — anything that YAML can represent
      // as a scalar value.  Only reject objects/arrays.
      if (typeof value === 'object') {
        errors.push({
          path: path || schema.label || 'value',
          message: t('Expected a scalar value (string, number, or boolean), got @type.', { '@type': Array.isArray(value) ? 'array' : 'object' }),
        });
      }
      if (schema.required && (value === '' || value === null || value === undefined)) {
        errors.push({
          path: path || schema.label || 'value',
          message: t('Required field must not be empty.'),
        });
      }
      break;
    }

    case 'string': {
      if (typeof value !== 'string') {
        errors.push({
          path: path || schema.label || 'value',
          message: t('Expected a string, got @type.', { '@type': typeof value }),
        });
      } else {
        const strSchema = schema as YamlSchemaString;
        if (strSchema.options && value !== '' && !(value in strSchema.options)) {
          const allowed = Object.keys(strSchema.options).join(', ');
          errors.push({
            path: path || schema.label || 'value',
            message: t('Value "@value" is not one of the allowed options (@allowed).', {
              '@value': value,
              '@allowed': allowed,
            }),
          });
        }
        if (schema.required && value === '') {
          errors.push({
            path: path || schema.label || 'value',
            message: t('Required field must not be empty.'),
          });
        }
      }
      break;
    }

    case 'number': {
      if (typeof value !== 'number') {
        errors.push({
          path: path || schema.label || 'value',
          message: t('Expected a number, got @type.', { '@type': typeof value }),
        });
      } else {
        const numSchema = schema as YamlSchemaNumber;
        if (numSchema.min !== undefined && value < numSchema.min) {
          errors.push({
            path: path || schema.label || 'value',
            message: t('Value @value is below the minimum of @min.', {
              '@value': String(value),
              '@min': String(numSchema.min),
            }),
          });
        }
        if (numSchema.max !== undefined && value > numSchema.max) {
          errors.push({
            path: path || schema.label || 'value',
            message: t('Value @value exceeds the maximum of @max.', {
              '@value': String(value),
              '@max': String(numSchema.max),
            }),
          });
        }
      }
      break;
    }

    case 'boolean': {
      if (typeof value !== 'boolean') {
        errors.push({
          path: path || schema.label || 'value',
          message: t('Expected a boolean (true/false), got @type.', { '@type': typeof value }),
        });
      }
      break;
    }

    // ----- collections -----
    case 'list': {
      if (!Array.isArray(value)) {
        errors.push({
          path: path || schema.label || 'value',
          message: t('Expected a list (array), got @type.', {
            '@type': typeof value === 'object' ? 'object' : typeof value,
          }),
        });
      } else {
        const listSchema = schema as YamlSchemaList;
        for (let i = 0; i < value.length; i++) {
          const itemPath = path ? `${path}[${i}]` : `[${i}]`;
          errors.push(...validateAgainstSchema(value[i], listSchema.items, itemPath));
        }
      }
      break;
    }

    case 'mapping': {
      if (typeof value !== 'object' || Array.isArray(value)) {
        errors.push({
          path: path || schema.label || 'value',
          message: t('Expected a mapping (object), got @type.', {
            '@type': Array.isArray(value) ? 'array' : typeof value,
          }),
        });
      } else {
        const mappingSchema = schema as YamlSchemaMapping;
        const obj = value as Record<string, unknown>;

        // Build effective properties: base + conditional based on discriminator.
        let effectiveProperties = { ...mappingSchema.properties };
        if (mappingSchema.discriminator && mappingSchema.conditionalProperties) {
          const discriminatorValue = String(obj[mappingSchema.discriminator] ?? '');
          const conditionalProps = mappingSchema.conditionalProperties[discriminatorValue];
          if (conditionalProps) {
            effectiveProperties = { ...effectiveProperties, ...conditionalProps };
          }
        }

        // Check each declared property.
        for (const [propKey, propSchema] of Object.entries(effectiveProperties)) {
          const propPath = path ? `${path}.${propKey}` : propKey;
          errors.push(...validateAgainstSchema(obj[propKey], propSchema, propPath));
        }
        // Flag unexpected keys.
        for (const key of Object.keys(obj)) {
          if (!(key in effectiveProperties)) {
            const keyPath = path ? `${path}.${key}` : key;
            errors.push({
              path: keyPath,
              message: t('Unexpected property "@key".', { '@key': key }),
            });
          }
        }
      }
      break;
    }

    case 'keyed_mapping': {
      if (typeof value !== 'object' || Array.isArray(value)) {
        errors.push({
          path: path || schema.label || 'value',
          message: t('Expected a keyed mapping (object), got @type.', {
            '@type': Array.isArray(value) ? 'array' : typeof value,
          }),
        });
      } else {
        const kmSchema = schema as YamlSchemaKeyedMapping;
        const obj = value as Record<string, unknown>;
        for (const [key, entryValue] of Object.entries(obj)) {
          const entryPath = path ? `${path}.${key}` : key;
          errors.push(...validateAgainstSchema(entryValue, kmSchema.items, entryPath));
        }
      }
      break;
    }
  }

  return errors;
}

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

interface FieldEditorProps {
  schema: YamlSchema;
  value: unknown;
  onChange: (value: unknown) => void;
  disabled: boolean;
  /** Depth level for visual nesting. */
  depth: number;
  /** Optional key label to show (used for mapping property names). */
  fieldKey?: string;
}

/** Renders a single scalar field (string, number, boolean, scalar). */
const ScalarEditor: React.FC<FieldEditorProps> = ({ schema, value, onChange, disabled, fieldKey }) => {
  const accessibleName = schema.label || fieldKey || '';
  switch (schema.type) {
    case 'scalar': {
      // Text input that preserves the YAML-native type: if the user types a
      // number or boolean literal the value is stored as that type, otherwise
      // as a string.  This mirrors how YAML itself parses unquoted scalars.
      const scalarSchema = schema as YamlSchemaScalar;
      return (
        <input
          type="text"
          className="form-control yaml-editor-input"
          value={value === undefined || value === null ? '' : String(value)}
          placeholder={scalarSchema.placeholder || ''}
          onChange={(e) => {
            const raw = e.target.value;
            // Attempt to preserve the YAML-native type.
            if (raw === 'true') { onChange(true); return; }
            if (raw === 'false') { onChange(false); return; }
            if (raw !== '' && !isNaN(Number(raw)) && raw.trim() === raw) { onChange(Number(raw)); return; }
            onChange(raw);
          }}
          disabled={disabled}
          aria-label={accessibleName}
        />
      );
    }
    case 'string': {
      const strSchema = schema as YamlSchemaString;
      if (strSchema.options) {
        return (
          <select
            className="form-control yaml-editor-select"
            value={String(value ?? '')}
            onChange={(e) => onChange(e.target.value)}
            disabled={disabled}
            aria-label={accessibleName}
          >
            <option value="">{t('- Select -')}</option>
            {Object.entries(strSchema.options).map(([k, label]) => (
              <option key={k} value={k}>{label}</option>
            ))}
          </select>
        );
      }
      return (
        <input
          type="text"
          className="form-control yaml-editor-input"
          value={String(value ?? '')}
          placeholder={strSchema.placeholder || ''}
          onChange={(e) => onChange(e.target.value)}
          disabled={disabled}
          aria-label={accessibleName}
        />
      );
    }
    case 'number': {
      const numSchema = schema as YamlSchemaNumber;
      return (
        <input
          type="number"
          className="form-control yaml-editor-input"
          value={value === undefined || value === null || value === '' ? '' : Number(value)}
          placeholder={numSchema.placeholder || ''}
          min={numSchema.min}
          max={numSchema.max}
          step={numSchema.step}
          onChange={(e) => {
            const raw = e.target.value;
            onChange(raw === '' ? '' : Number(raw));
          }}
          disabled={disabled}
          aria-label={accessibleName}
        />
      );
    }
    case 'boolean':
      return (
        <label className="checkbox-wrapper yaml-editor-checkbox">
          <input
            type="checkbox"
            checked={!!value}
            onChange={(e) => onChange(e.target.checked)}
            disabled={disabled}
          />
          <span className="checkbox-label">
            {schema.label || ''}
          </span>
        </label>
      );
    default:
      return null;
  }
};

/** Renders a list editor with add/remove/reorder controls. */
const ListEditor: React.FC<FieldEditorProps> = ({ schema, value, onChange, disabled, depth }) => {
  const listSchema = schema as YamlSchemaList;
  const items = useMemo(() => (Array.isArray(value) ? value : []), [value]);
  const [collapsedItems, setCollapsedItems] = useState<Record<number, boolean>>({});

  const handleItemChange = useCallback((index: number, newItemValue: unknown) => {
    const newItems = [...items];
    newItems[index] = newItemValue;
    onChange(newItems);
  }, [items, onChange]);

  const handleAdd = useCallback(() => {
    onChange([...items, createListItem(listSchema.items)]);
  }, [items, onChange, listSchema.items]);

  const handleRemove = useCallback((index: number) => {
    const newItems = items.filter((_: unknown, i: number) => i !== index);
    // Clean up collapsed state
    setCollapsedItems((prev) => {
      const next: Record<number, boolean> = {};
      for (const [k, v] of Object.entries(prev)) {
        const ki = Number(k);
        if (ki < index) next[ki] = v;
        else if (ki > index) next[ki - 1] = v;
      }
      return next;
    });
    onChange(newItems);
  }, [items, onChange]);

  const toggleCollapse = useCallback((index: number) => {
    setCollapsedItems((prev) => ({ ...prev, [index]: !prev[index] }));
  }, []);

  const isComplex = listSchema.items.type === 'mapping' || listSchema.items.type === 'list';

  return (
    <div className="yaml-editor-list">
      {items.length === 0 && (
        <div className="yaml-editor-empty">{t('No items yet.')}</div>
      )}
      {items.map((item: unknown, index: number) => (
        <div key={index} className={`yaml-editor-list-item ${isComplex ? 'yaml-editor-list-item-complex' : ''}`}>
          <div className="yaml-editor-list-item-header">
            {isComplex && (
              <button
                type="button"
                className="yaml-editor-collapse-btn"
                onClick={() => toggleCollapse(index)}
                aria-label={collapsedItems[index] ? t('Expand item @index', { '@index': String(index + 1) }) : t('Collapse item @index', { '@index': String(index + 1) })}
                aria-expanded={!collapsedItems[index]}
              >
                {collapsedItems[index] ? <FiChevronRight /> : <FiChevronDown />}
              </button>
            )}
            <span className="yaml-editor-list-item-label">
              {listSchema.items.label
                ? t('@label @index', { '@label': listSchema.items.label, '@index': String(index + 1) })
                : t('Item @index', { '@index': String(index + 1) })}
            </span>
            {!disabled && (
              <button
                type="button"
                className="yaml-editor-remove-btn"
                onClick={() => handleRemove(index)}
                aria-label={t('Remove item @index', { '@index': String(index + 1) })}
                title={t('Remove')}
              >
                <FiTrash2 />
              </button>
            )}
          </div>
          {(!isComplex || !collapsedItems[index]) && (
            <div className="yaml-editor-list-item-body">
              <FieldEditor
                schema={listSchema.items}
                value={item}
                onChange={(v) => handleItemChange(index, v)}
                disabled={disabled}
                depth={depth + 1}
              />
            </div>
          )}
        </div>
      ))}
      {!disabled && (
        <button
          type="button"
          className="yaml-editor-add-btn"
          onClick={handleAdd}
        >
          <FiPlus />
          <span>{t('Add @item', {
            '@item': listSchema.items.label || t('item'),
          })}</span>
        </button>
      )}
    </div>
  );
};

/** Renders a mapping editor — a group of named fields. */
const MappingEditor: React.FC<FieldEditorProps> = ({ schema, value, onChange, disabled, depth }) => {
  const mappingSchema = schema as YamlSchemaMapping;
  const data = useMemo(
    () => (typeof value === 'object' && value !== null && !Array.isArray(value))
      ? value as Record<string, unknown>
      : {},
    [value],
  );

  // Get the discriminator value if this mapping uses conditional properties.
  const discriminatorKey = mappingSchema.discriminator;
  const discriminatorValue = discriminatorKey ? String(data[discriminatorKey] ?? '') : '';

  // Compute effective properties: base properties + conditional properties
  // based on the current discriminator value.
  const effectiveProperties = useMemo(() => {
    const baseProps = { ...mappingSchema.properties };

    // If there's a discriminator and conditional properties, merge them.
    if (discriminatorKey && mappingSchema.conditionalProperties && discriminatorValue) {
      const conditionalProps = mappingSchema.conditionalProperties[discriminatorValue];
      if (conditionalProps) {
        return { ...baseProps, ...conditionalProps };
      }
    }

    return baseProps;
  }, [mappingSchema.properties, mappingSchema.conditionalProperties, discriminatorKey, discriminatorValue]);

  // Track the previous discriminator value to clean up obsolete properties.
  const prevDiscriminatorRef = useRef(discriminatorValue);

  const handlePropertyChange = useCallback((propKey: string, propValue: unknown) => {
    // If the discriminator field is changing, clean up properties that are
    // no longer applicable to the new discriminator value.
    if (discriminatorKey && propKey === discriminatorKey && mappingSchema.conditionalProperties) {
      const newDiscriminatorValue = String(propValue ?? '');
      const oldDiscriminatorValue = prevDiscriminatorRef.current;

      if (newDiscriminatorValue !== oldDiscriminatorValue) {
        prevDiscriminatorRef.current = newDiscriminatorValue;

        // Build the set of valid property keys for the new discriminator value.
        const validKeys = new Set(Object.keys(mappingSchema.properties));
        const newConditionalProps = mappingSchema.conditionalProperties[newDiscriminatorValue];
        if (newConditionalProps) {
          Object.keys(newConditionalProps).forEach((k) => validKeys.add(k));
        }

        // Create a new data object, filtering out properties that are no longer valid.
        const newData: Record<string, unknown> = {};
        for (const [key, val] of Object.entries(data)) {
          if (validKeys.has(key)) {
            newData[key] = val;
          }
        }
        newData[propKey] = propValue;
        onChange(newData);
        return;
      }
    }

    onChange({ ...data, [propKey]: propValue });
  }, [data, onChange, discriminatorKey, mappingSchema.properties, mappingSchema.conditionalProperties]);

  return (
    <div className="yaml-editor-mapping">
      {Object.entries(effectiveProperties).map(([propKey, propSchema]) => {
        const isNested = propSchema.type === 'mapping' || propSchema.type === 'list';
        return (
          <div key={propKey} className={`yaml-editor-field ${isNested ? 'yaml-editor-field-nested' : ''}`}>
            {propSchema.type !== 'boolean' && (
              <label className="yaml-editor-field-label">
                {propSchema.label || propKey}
                {propSchema.required && <span className="required">*</span>}
              </label>
            )}
            <FieldEditor
              schema={propSchema}
              value={data[propKey]}
              onChange={(v) => handlePropertyChange(propKey, v)}
              disabled={disabled}
              depth={depth + 1}
              fieldKey={propKey}
            />
            {propSchema.description && (
              <div className="yaml-editor-field-description">{propSchema.description}</div>
            )}
          </div>
        );
      })}
    </div>
  );
};

/**
 * Renders a keyed mapping editor — a dynamic list of entries where each
 * entry has a user-editable key name and a structured value.
 *
 * Data is stored as a plain object: { key1: { ...props }, key2: { ...props } }.
 */
const KeyedMappingEditor: React.FC<FieldEditorProps> = ({ schema, value, onChange, disabled, depth }) => {
  const kmSchema = schema as YamlSchemaKeyedMapping;
  const data = useMemo(
    () => (typeof value === 'object' && value !== null && !Array.isArray(value))
      ? value as Record<string, unknown>
      : {},
    [value],
  );
  const entries = useMemo(() => Object.entries(data), [data]);
  // Collapse existing entries by default; newly added items start expanded.
  const [collapsedItems, setCollapsedItems] = useState<Record<number, boolean>>(() => {
    const initial: Record<number, boolean> = {};
    const keys = Object.keys(
      (typeof value === 'object' && value !== null && !Array.isArray(value))
        ? value as Record<string, unknown>
        : {},
    );
    for (let i = 0; i < keys.length; i++) {
      initial[i] = true;
    }
    return initial;
  });

  const handleKeyChange = useCallback((oldKey: string, newKey: string, index: number) => {
    // Rebuild the object preserving insertion order but replacing the key.
    const newData: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(data)) {
      if (k === oldKey) {
        newData[newKey] = v;
      }
      else {
        // If the new key collides with an existing key, skip the existing one
        // (the renamed entry takes precedence at its original position).
        if (k !== newKey) {
          newData[k] = v;
        }
      }
    }
    // Also update the collapsed state to keep it stable across the rename.
    setCollapsedItems((prev) => ({ ...prev }));
    void index; // index kept for potential future reorder support.
    onChange(newData);
  }, [data, onChange]);

  const handleValueChange = useCallback((key: string, newValue: unknown) => {
    onChange({ ...data, [key]: newValue });
  }, [data, onChange]);

  const handleAdd = useCallback(() => {
    // Generate a unique default key name based on the item label (e.g.
    // "Argument" → "argument_1", "argument_2") or fall back to "item_1".
    const base = (kmSchema.items.label || 'item').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    let counter = Object.keys(data).length + 1;
    let newKey = `${base}_${counter}`;
    while (newKey in data) {
      counter++;
      newKey = `${base}_${counter}`;
    }
    onChange({ ...data, [newKey]: createDefaultValue(kmSchema.items) });
  }, [data, onChange, kmSchema.items]);

  const handleRemove = useCallback((keyToRemove: string, index: number) => {
    const newData: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(data)) {
      if (k !== keyToRemove) {
        newData[k] = v;
      }
    }
    // Clean up collapsed state.
    setCollapsedItems((prev) => {
      const next: Record<number, boolean> = {};
      for (const [k, v] of Object.entries(prev)) {
        const ki = Number(k);
        if (ki < index) next[ki] = v;
        else if (ki > index) next[ki - 1] = v;
      }
      return next;
    });
    onChange(newData);
  }, [data, onChange]);

  const toggleCollapse = useCallback((index: number) => {
    setCollapsedItems((prev) => ({ ...prev, [index]: !prev[index] }));
  }, []);

  const isComplex = kmSchema.items.type === 'mapping' || kmSchema.items.type === 'list' || kmSchema.items.type === 'keyed_mapping';

  return (
    <div className="yaml-editor-list">
      {entries.length === 0 && (
        <div className="yaml-editor-empty">{t('No items yet.')}</div>
      )}
      {entries.map(([entryKey, entryValue], index) => (
        <div key={index} className={`yaml-editor-list-item ${isComplex ? 'yaml-editor-list-item-complex' : ''}`}>
          <div
            className="yaml-editor-list-item-header"
            role={isComplex ? 'button' : undefined}
            tabIndex={isComplex ? 0 : undefined}
            onClick={() => { if (isComplex) toggleCollapse(index); }}
            onKeyDown={(e) => { if (isComplex && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); toggleCollapse(index); } }}
            aria-expanded={isComplex ? !collapsedItems[index] : undefined}
            aria-label={isComplex
              ? (collapsedItems[index] ? t('Expand @name', { '@name': entryKey || String(index + 1) }) : t('Collapse @name', { '@name': entryKey || String(index + 1) }))
              : undefined}
            style={isComplex ? { cursor: 'pointer' } : undefined}
          >
            {isComplex && (
              <span className="yaml-editor-collapse-indicator">
                {collapsedItems[index] ? <FiChevronRight /> : <FiChevronDown />}
              </span>
            )}
            <span className="yaml-editor-list-item-label">
              {entryKey || t('Item @index', { '@index': String(index + 1) })}
            </span>
            {!disabled && (
              <button
                type="button"
                className="yaml-editor-remove-btn"
                onClick={(e) => { e.stopPropagation(); handleRemove(entryKey, index); }}
                aria-label={t('Remove item @index', { '@index': String(index + 1) })}
                title={t('Remove')}
              >
                <FiTrash2 />
              </button>
            )}
          </div>
          {(!isComplex || !collapsedItems[index]) && (
            <div className="yaml-editor-list-item-body">
              <div className="yaml-editor-field">
                <label className="yaml-editor-field-label">
                  {t('Key')}
                  <span className="required">*</span>
                </label>
                <input
                  type="text"
                  className="form-control yaml-editor-input"
                  value={entryKey}
                  onChange={(e) => handleKeyChange(entryKey, e.target.value, index)}
                  disabled={disabled}
                  aria-label={t('Key for item @index', { '@index': String(index + 1) })}
                />
              </div>
              <FieldEditor
                schema={kmSchema.items}
                value={entryValue}
                onChange={(v) => handleValueChange(entryKey, v)}
                disabled={disabled}
                depth={depth + 1}
              />
            </div>
          )}
        </div>
      ))}
      {!disabled && (
        <button
          type="button"
          className="yaml-editor-add-btn"
          onClick={handleAdd}
        >
          <FiPlus />
          <span>{t('Add @item', {
            '@item': kmSchema.items.label || t('item'),
          })}</span>
        </button>
      )}
    </div>
  );
};

/** Dispatches to the right sub-editor based on schema type. */
const FieldEditor: React.FC<FieldEditorProps> = (props) => {
  const { schema } = props;
  switch (schema.type) {
    case 'string':
    case 'number':
    case 'boolean':
    case 'scalar':
      return <ScalarEditor {...props} />;
    case 'list':
      return <ListEditor {...props} />;
    case 'mapping':
      return <MappingEditor {...props} />;
    case 'keyed_mapping':
      return <KeyedMappingEditor {...props} />;
    default:
      return null;
  }
};

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

const YamlEditor: React.FC<YamlEditorProps> = React.memo(({ value, onChange, schema, disabled = false, validate = false, format = 'yaml', id }) => {
  // -----------------------------------------------------------------------
  // Schema-less mode: plain YAML (or JSON) textarea with optional syntax
  // validation.  No structured editor, no schema validation — just raw text.
  // -----------------------------------------------------------------------
  if (!schema) {
    return (
      <SchemalessYamlEditor
        value={value}
        onChange={onChange}
        disabled={disabled}
        validate={validate}
        format={format}
        id={id}
      />
    );
  }

  // -----------------------------------------------------------------------
  // Schema mode: structured editor + raw YAML with schema validation.
  // -----------------------------------------------------------------------
  return (
    <SchemaYamlEditor
      value={value}
      onChange={onChange}
      schema={schema}
      disabled={disabled}
    />
  );
});

YamlEditor.displayName = 'YamlEditor';

/**
 * Validate a JSON string. Returns the raw parser message on failure, or `null`
 * when the value is empty or valid. The raw message is rendered as a plain
 * React text node (never passed through t()'s @-placeholder, which HTML-escapes
 * quotes and would surface as literal &#39;/&quot; entities).
 */
function validateJson(value: string): string | null {
  if (!value || !value.trim()) {
    return null;
  }
  try {
    JSON.parse(value);
    return null;
  } catch (e) {
    return (e as Error).message;
  }
}

/**
 * Schema-less editor: a raw YAML (or JSON) textarea with optional parse
 * validation. Used when `use_yaml` is checked but no Drupal config schema
 * exists, or for `format === 'json'` fields (e.g. an `ai_json_schema` element).
 *
 * - `format === 'yaml'` (default): validates with js-yaml and shows the
 *   `Invalid YAML syntax` message. No Format button.
 * - `format === 'json'`: validates with `JSON.parse`, shows `Invalid JSON:`
 *   followed by the raw parser message, and exposes a one-click "Format"
 *   action that pretty-prints valid JSON. The value round-trips as the raw
 *   string the user typed.
 */
const SchemalessYamlEditor: React.FC<{
  value: string;
  onChange: (yamlString: string) => void;
  disabled: boolean;
  validate: boolean;
  format?: 'yaml' | 'json';
  id?: string;
}> = ({ value, onChange, disabled, validate, format = 'yaml', id }) => {
  const isJson = format === 'json';

  if (isJson) {
    return (
      <JsonSchemalessEditor
        value={value}
        onChange={onChange}
        disabled={disabled}
        validate={validate}
        id={id}
      />
    );
  }

  return (
    <YamlSchemalessEditor
      value={value}
      onChange={onChange}
      disabled={disabled}
      validate={validate}
      id={id}
    />
  );
};

/**
 * Default schema-less editor: a single raw YAML textarea with optional
 * js-yaml parse validation.  This preserves the original `format === 'yaml'`
 * behavior unchanged.
 */
const YamlSchemalessEditor: React.FC<{
  value: string;
  onChange: (yamlString: string) => void;
  disabled: boolean;
  validate: boolean;
  id?: string;
}> = ({ value, onChange, disabled, validate, id }) => {
  const computeError = useCallback((raw: string): string | null => {
    if (!validate || !raw || !raw.trim()) {
      return null;
    }
    return parseYaml(raw) === undefined ? t('Invalid YAML syntax') : null;
  }, [validate]);

  const [parseError, setParseError] = useState<string | null>(() => computeError(value));

  // Re-validate when the validate flag or value changes externally.
  const prevValidateRef = useRef(validate);
  const prevValueRef = useRef(value);
  useEffect(() => {
    const flagChanged = validate !== prevValidateRef.current;
    const valueChanged = value !== prevValueRef.current;
    prevValidateRef.current = validate;
    prevValueRef.current = value;

    if (!validate) {
      setParseError(null);
      return;
    }
    if (flagChanged || valueChanged) {
      setParseError(computeError(value));
    }
  }, [validate, value, computeError]);

  const updateValue = useCallback((raw: string) => {
    onChange(raw);
    if (validate) {
      setParseError(computeError(raw));
    }
  }, [onChange, validate, computeError]);

  const handleChange = useCallback((e: React.ChangeEvent<HTMLTextAreaElement>) => {
    updateValue(e.target.value);
  }, [updateValue]);

  const handleKeyDown = useCallback((e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    handleYamlKeyDown(e, updateValue);
  }, [updateValue]);

  return (
    <div className="yaml-editor yaml-editor-schemaless">
      {parseError && (
        <div className="yaml-editor-error" role="alert">
          <FiAlertTriangle />
          <span>{parseError}</span>
        </div>
      )}
      <textarea
        id={id}
        className="form-control yaml-editor-raw"
        value={value || ''}
        onChange={handleChange}
        onKeyDown={handleKeyDown}
        disabled={disabled}
        rows={8}
        spellCheck={false}
      />
    </div>
  );
};

/**
 * JSON schema-less editor.
 *
 * The canonical stored value (the `value` prop and everything emitted through
 * `onChange`) is ALWAYS a JSON string — the field's `Json` config-schema
 * constraint requires it.  This component NEVER emits YAML.
 *
 * It offers two tabs:
 *   - **JSON** (default): edit the JSON string directly.  Validated with
 *     `JSON.parse`; the raw parser message is shown after `Invalid JSON:`.
 *   - **YAML**: a convenience view that shows the same data as YAML.  Edits
 *     are validated with `parseYaml`; when valid they are converted back to
 *     JSON and emitted via `onChange(JSON.stringify(parsed, null, 2))`.  When
 *     invalid, nothing is emitted so the last valid JSON is preserved.
 *
 * Switching tabs is blocked while the current tab's content is invalid so the
 * user never loses their in-progress text.
 */
const JsonSchemalessEditor: React.FC<{
  value: string;
  onChange: (jsonString: string) => void;
  disabled: boolean;
  validate: boolean;
  id?: string;
}> = ({ value, onChange, disabled, validate, id }) => {
  const [activeTab, setActiveTab] = useState<'json' | 'yaml'>('json');

  // Local YAML draft for the YAML tab.  The JSON tab always renders `value`
  // directly (the canonical JSON string).
  const [yamlText, setYamlText] = useState<string>('');

  // Inline parse errors per tab.  JSON errors carry the raw parser message;
  // YAML errors carry the fixed translated message.
  const [jsonError, setJsonError] = useState<string | null>(() =>
    validate ? validateJson(value) : null,
  );
  const [yamlError, setYamlError] = useState<string | null>(null);

  // Derive a YAML string from a canonical JSON string.  Returns '' for empty
  // or unparsable JSON (invalid JSON is flagged separately on the JSON tab).
  const jsonToYaml = useCallback((json: string): string => {
    if (!json || !json.trim()) {
      return '';
    }
    try {
      return toYaml(JSON.parse(json));
    } catch {
      return '';
    }
  }, []);

  // Re-derive displayed text for the active tab when the external `value`
  // changes (e.g. undo/redo or selecting another node).
  const prevValueRef = useRef(value);
  useEffect(() => {
    if (value === prevValueRef.current) {
      return;
    }
    prevValueRef.current = value;
    setJsonError(validate ? validateJson(value) : null);
    if (activeTab === 'yaml') {
      setYamlText(jsonToYaml(value));
      setYamlError(null);
    }
  }, [value, validate, activeTab, jsonToYaml]);

  // ---- JSON tab editing ------------------------------------------------
  const updateJson = useCallback((raw: string) => {
    prevValueRef.current = raw;
    onChange(raw);
    if (validate) {
      setJsonError(validateJson(raw));
    }
  }, [onChange, validate]);

  const handleJsonChange = useCallback((e: React.ChangeEvent<HTMLTextAreaElement>) => {
    updateJson(e.target.value);
  }, [updateJson]);

  const handleJsonKeyDown = useCallback((e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    handleYamlKeyDown(e, updateJson);
  }, [updateJson]);

  // ---- YAML tab editing ------------------------------------------------
  const updateYaml = useCallback((raw: string) => {
    setYamlText(raw);
    if (!raw || !raw.trim()) {
      // Empty YAML maps to empty JSON.
      setYamlError(null);
      prevValueRef.current = '';
      onChange('');
      return;
    }
    const parsed = parseYaml(raw);
    if (parsed === undefined) {
      // Invalid YAML: keep the draft, flag it, and do NOT emit so the last
      // valid JSON value is preserved.
      setYamlError(t('Invalid YAML syntax'));
      return;
    }
    setYamlError(null);
    const json = JSON.stringify(parsed, null, 2);
    prevValueRef.current = json;
    onChange(json);
  }, [onChange]);

  const handleYamlChange = useCallback((e: React.ChangeEvent<HTMLTextAreaElement>) => {
    updateYaml(e.target.value);
  }, [updateYaml]);

  const handleYamlKey = useCallback((e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    handleYamlKeyDown(e, updateYaml);
  }, [updateYaml]);

  // ---- Tab switching (block on invalid content) ------------------------
  const switchToJson = useCallback(() => {
    if (activeTab === 'json') {
      return;
    }
    // Block the switch while the YAML draft is invalid.
    if (yamlError) {
      return;
    }
    setActiveTab('json');
  }, [activeTab, yamlError]);

  const switchToYaml = useCallback(() => {
    if (activeTab === 'yaml') {
      return;
    }
    // Block the switch while the current JSON is invalid.
    const err = validateJson(value);
    if (err) {
      setJsonError(err);
      return;
    }
    setYamlText(jsonToYaml(value));
    setYamlError(null);
    setActiveTab('yaml');
  }, [activeTab, value, jsonToYaml]);

  // ---- Format action (acts on the active tab) --------------------------
  const handleFormat = useCallback(() => {
    if (activeTab === 'json') {
      if (!value || !value.trim()) {
        return;
      }
      try {
        updateJson(JSON.stringify(JSON.parse(value), null, 2));
      } catch {
        // Invalid JSON — leave untouched; the inline error already flags it.
      }
      return;
    }
    // YAML tab: re-dump to normalize indentation, leaving invalid YAML alone.
    if (!yamlText || !yamlText.trim()) {
      return;
    }
    const parsed = parseYaml(yamlText);
    if (parsed === undefined) {
      return;
    }
    updateYaml(toYaml(parsed));
  }, [activeTab, value, yamlText, updateJson, updateYaml]);

  const isYamlTab = activeTab === 'yaml';

  return (
    <div className="yaml-editor yaml-editor-schemaless">
      <div className="yaml-editor-toolbar">
        <button
          type="button"
          className={`yaml-editor-mode-btn ${!isYamlTab ? 'yaml-editor-mode-btn-active' : ''}`}
          onClick={switchToJson}
          disabled={disabled}
          aria-pressed={!isYamlTab}
        >
          {t('JSON')}
        </button>
        <button
          type="button"
          className={`yaml-editor-mode-btn ${isYamlTab ? 'yaml-editor-mode-btn-active' : ''}`}
          onClick={switchToYaml}
          disabled={disabled}
          aria-pressed={isYamlTab}
        >
          {t('YAML')}
        </button>
        <button
          type="button"
          className="yaml-editor-mode-btn yaml-editor-format-btn"
          onClick={handleFormat}
          disabled={disabled}
          title={isYamlTab ? t('Format the YAML with indentation') : t('Format the JSON with indentation')}
        >
          <FiAlignLeft />
          <span>{t('Format')}</span>
        </button>
      </div>

      {!isYamlTab && jsonError && (
        <div className="yaml-editor-error" role="alert">
          <FiAlertTriangle />
          <span>{t('Invalid JSON:')} {jsonError}</span>
        </div>
      )}

      {isYamlTab && yamlError && (
        <div className="yaml-editor-error" role="alert">
          <FiAlertTriangle />
          <span>{yamlError}</span>
        </div>
      )}

      {isYamlTab ? (
        <textarea
          id={id}
          className="form-control yaml-editor-raw"
          value={yamlText}
          onChange={handleYamlChange}
          onKeyDown={handleYamlKey}
          disabled={disabled}
          rows={8}
          spellCheck={false}
        />
      ) : (
        <textarea
          id={id}
          className="form-control yaml-editor-raw"
          value={value || ''}
          onChange={handleJsonChange}
          onKeyDown={handleJsonKeyDown}
          disabled={disabled}
          rows={8}
          spellCheck={false}
        />
      )}
    </div>
  );
};

/**
 * Schema-backed YAML editor with structured editor + raw YAML mode.
 */
const SchemaYamlEditor: React.FC<{
  value: string;
  onChange: (yamlString: string) => void;
  schema: YamlSchema;
  disabled: boolean;
}> = ({ value, onChange, schema, disabled }) => {
  // Parse the incoming YAML string into a JS structure.
  const [data, setData] = useState<unknown>(() => {
    const parsed = parseYaml(value);
    return parsed !== undefined ? parsed : createDefaultValue(schema);
  });
  const [parseError, setParseError] = useState<string | null>(null);
  const [validationErrors, setValidationErrors] = useState<ValidationError[]>([]);
  const [showRawYaml, setShowRawYaml] = useState(false);

  // When the external `value` changes (e.g. undo/redo), re-parse it.
  const prevValueRef = useRef(value);
  useEffect(() => {
    if (value !== prevValueRef.current) {
      prevValueRef.current = value;
      const parsed = parseYaml(value);
      if (parsed !== undefined) {
        setData(parsed);
        setParseError(null);
        if (showRawYaml) {
          setValidationErrors(validateAgainstSchema(parsed, schema));
        }
      } else if (value && value.trim()) {
        setParseError(t('The current value contains invalid YAML and cannot be edited structurally.'));
        setValidationErrors([]);
      }
    }
  }, [value, showRawYaml, schema]);

  const handleDataChange = useCallback((newData: unknown) => {
    setData(newData);
    setParseError(null);
    const yamlStr = toYaml(newData);
    prevValueRef.current = yamlStr;
    onChange(yamlStr);
  }, [onChange]);

  const updateRawValue = useCallback((raw: string) => {
    prevValueRef.current = raw;
    onChange(raw);
    const parsed = parseYaml(raw);
    if (parsed !== undefined) {
      setData(parsed);
      setParseError(null);
      setValidationErrors(validateAgainstSchema(parsed, schema));
    } else if (raw.trim()) {
      setParseError(t('Invalid YAML syntax'));
      setValidationErrors([]);
    } else {
      setData(createDefaultValue(schema));
      setParseError(null);
      setValidationErrors([]);
    }
  }, [onChange, schema]);

  const handleRawChange = useCallback((e: React.ChangeEvent<HTMLTextAreaElement>) => {
    updateRawValue(e.target.value);
  }, [updateRawValue]);

  const handleRawKeyDown = useCallback((e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    handleYamlKeyDown(e, updateRawValue);
  }, [updateRawValue]);

  return (
    <div className="yaml-editor">
      <div className="yaml-editor-toolbar">
        <button
          type="button"
          className={`yaml-editor-mode-btn ${!showRawYaml ? 'yaml-editor-mode-btn-active' : ''}`}
          onClick={() => { setShowRawYaml(false); setValidationErrors([]); }}
          aria-pressed={!showRawYaml}
        >
          {t('Editor')}
        </button>
        <button
          type="button"
          className={`yaml-editor-mode-btn ${showRawYaml ? 'yaml-editor-mode-btn-active' : ''}`}
          onClick={() => {
            setShowRawYaml(true);
            const parsed = parseYaml(value);
            if (parsed !== undefined) {
              setValidationErrors(validateAgainstSchema(parsed, schema));
            }
          }}
          aria-pressed={showRawYaml}
        >
          {t('YAML')}
        </button>
      </div>

      {parseError && (
        <div className="yaml-editor-error" role="alert">
          <FiAlertTriangle />
          <span>{parseError}</span>
        </div>
      )}

      {validationErrors.length > 0 && !parseError && (
        <div className="yaml-editor-warnings" role="status">
          <div className="yaml-editor-warnings-header">
            <FiAlertTriangle />
            <span>{t('Schema validation (@count):', { '@count': String(validationErrors.length) })}</span>
          </div>
          <ul className="yaml-editor-warnings-list">
            {validationErrors.map((err, i) => (
              <li key={i}>
                <code>{err.path}</code>: {err.message}
              </li>
            ))}
          </ul>
        </div>
      )}

      {showRawYaml ? (
        <textarea
          className="form-control yaml-editor-raw"
          value={value || ''}
          onChange={handleRawChange}
          onKeyDown={handleRawKeyDown}
          disabled={disabled}
          rows={8}
          spellCheck={false}
        />
      ) : (
        <div className="yaml-editor-body">
          <FieldEditor
            schema={schema}
            value={data}
            onChange={handleDataChange}
            disabled={disabled}
            depth={0}
          />
        </div>
      )}
    </div>
  );
};

export default YamlEditor;
