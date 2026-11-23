import {
  buildBreadcrumb,
  buildStepNodes,
  buildTokenCategories,
  markPredictedDeep,
  computePickerPlacement,
  formatTokenValue,
  pickerMinHeight,
  PICKER_ROW_HEIGHT,
  PICKER_MIN_LIST_ROWS,
  PICKER_CHROME_HEIGHT,
  PICKER_WAITING_MIN,
} from '../tokenPickerData';

const labels = { step: 'Step data tokens', global: 'Global tokens', template: 'Template tokens' };

describe('buildTokenCategories (Feature J)', () => {
  it('includes the step category for a non-template model with a resolvable owning event even with ZERO step nodes', () => {
    const categories = buildTokenCategories({
      stepData: null,
      isTemplate: false,
      canResolveStepData: true,
      labels,
    });
    const step = categories.find((c) => c.id === 'step');
    expect(step).toBeTruthy();
    expect(step?.count).toBe(0);
    expect(step?.nodes).toEqual([]);
  });

  it('excludes the step category when canResolveStepData is false and there is no cached step data', () => {
    const categories = buildTokenCategories({
      stepData: null,
      isTemplate: false,
      canResolveStepData: false,
      labels,
    });
    expect(categories.find((c) => c.id === 'step')).toBeUndefined();
  });

  it('excludes the step category for a template model even when canResolveStepData is true', () => {
    const categories = buildTokenCategories({
      stepData: { foo: { label: 'Foo', token: '[foo]' } },
      isTemplate: true,
      canResolveStepData: true,
      labels,
    });
    expect(categories.find((c) => c.id === 'step')).toBeUndefined();
  });

  it('still includes the step category for cached step data without canResolveStepData (legacy path), non-template only', () => {
    const categories = buildTokenCategories({
      stepData: { foo: { label: 'Foo', token: '[foo]' } },
      isTemplate: false,
      labels,
    });
    const step = categories.find((c) => c.id === 'step');
    expect(step).toBeTruthy();
    expect(step?.count).toBe(1);
  });
});

describe('buildStepNodes (predicted flag — issue #3577207)', () => {
  const stepData = {
    user: { label: 'User', token: '[user:name]', value: 'admin' },
    bare: 'value',
  };

  it('leaves confirmed tokens unchanged (no predicted key) by default', () => {
    const nodes = buildStepNodes(stepData);
    expect(nodes).toEqual([
      { label: 'User', token: '[user:name]', value: 'admin' },
      { label: 'bare', value: 'value' },
    ]);
    nodes.forEach((n) => expect('predicted' in n).toBe(false));
  });

  it('leaves confirmed tokens unchanged when predicted is explicitly false', () => {
    const nodes = buildStepNodes(stepData, false);
    nodes.forEach((n) => expect(n.predicted).toBeUndefined());
  });

  it('stamps predicted: true on EVERY node when predicted is true', () => {
    const nodes = buildStepNodes(stepData, true);
    expect(nodes).toHaveLength(2);
    nodes.forEach((n) => expect(n.predicted).toBe(true));
    // The underlying token data is otherwise preserved.
    expect(nodes[0]).toMatchObject({ label: 'User', token: '[user:name]', value: 'admin' });
  });

  it('returns [] for null/undefined regardless of the predicted flag', () => {
    expect(buildStepNodes(null, true)).toEqual([]);
    expect(buildStepNodes(undefined, true)).toEqual([]);
  });

  describe('deep-stamping nested descendants (issue #3577207)', () => {
    const nestedStepData = {
      entity: {
        label: 'Entity',
        data: {
          title: { label: 'Title', token: '[entity:title]', value: 'Hello' },
          author: {
            label: 'Author',
            data: {
              name: { label: 'Name', token: '[entity:author:name]', value: 'admin' },
            },
          },
        },
      },
    };

    it('stamps predicted: true on the node AND every nested descendant when predicted is true', () => {
      const [entity] = buildStepNodes(nestedStepData, true);
      expect(entity.predicted).toBe(true);
      const title = entity.data!.title;
      const author = entity.data!.author;
      expect(title.predicted).toBe(true);
      expect(author.predicted).toBe(true);
      // Recurse one level deeper.
      expect(author.data!.name.predicted).toBe(true);
    });

    it('leaves NO predicted key anywhere (top level or nested) when predicted is false', () => {
      const [entity] = buildStepNodes(nestedStepData);
      expect('predicted' in entity).toBe(false);
      expect('predicted' in entity.data!.title).toBe(false);
      expect('predicted' in entity.data!.author).toBe(false);
      expect('predicted' in entity.data!.author.data!.name).toBe(false);
    });

    it('does NOT mutate the input step data when deep-stamping (pure)', () => {
      const original = JSON.parse(JSON.stringify(nestedStepData));
      buildStepNodes(nestedStepData, true);
      expect(nestedStepData).toEqual(original);
    });
  });
});

describe('markPredictedDeep (issue #3577207)', () => {
  it('returns a deep-cloned node with predicted on the node and all descendants', () => {
    const node = {
      label: 'Entity',
      data: { title: { label: 'Title', token: '[entity:title]' } },
    };
    const result = markPredictedDeep(node);
    expect(result.predicted).toBe(true);
    expect(result.data!.title.predicted).toBe(true);
    // Non-mutating: distinct objects, input untouched.
    expect(result).not.toBe(node);
    expect(result.data).not.toBe(node.data);
    expect('predicted' in node).toBe(false);
    expect('predicted' in node.data.title).toBe(false);
  });

  it('handles leaf nodes without a data map', () => {
    const node = { label: 'Title', token: '[entity:title]', value: 'Hello' };
    const result = markPredictedDeep(node);
    expect(result).toEqual({ label: 'Title', token: '[entity:title]', value: 'Hello', predicted: true });
    expect(result.data).toBeUndefined();
  });
});

describe('buildTokenCategories (predicted step data — issue #3577207)', () => {
  it('stamps predicted on step nodes when stepPredicted is true', () => {
    const categories = buildTokenCategories({
      stepData: { foo: { label: 'Foo', token: '[foo]' } },
      isTemplate: false,
      stepPredicted: true,
      labels,
    });
    const step = categories.find((c) => c.id === 'step');
    expect(step?.nodes.every((n) => n.predicted === true)).toBe(true);
  });

  it('leaves step nodes unflagged when stepPredicted is omitted (confirmed)', () => {
    const categories = buildTokenCategories({
      stepData: { foo: { label: 'Foo', token: '[foo]' } },
      isTemplate: false,
      labels,
    });
    const step = categories.find((c) => c.id === 'step');
    expect(step?.nodes.every((n) => n.predicted === undefined)).toBe(true);
  });
});

describe('computePickerPlacement (Caveat 3: viewport-aware sizing)', () => {
  const base = { viewportHeight: 800, margin: 8, desired: 320, min: 120 };

  it('anchors BELOW when there is ample room below', () => {
    // Caret near the top → lots of space below.
    const r = computePickerPlacement({ ...base, anchorTop: 100, anchorBottom: 116 });
    expect(r.placement).toBe('below');
    expect(r.top).toBe(116); // anchorBottom
    expect(r.maxHeight).toBe(320); // full desired fits
  });

  it('flips ABOVE when below is tight but above has more room', () => {
    // Caret near the bottom → little space below, lots above.
    const r = computePickerPlacement({ ...base, anchorTop: 740, anchorBottom: 760 });
    // spaceBelow = 800-760-8 = 32; spaceAbove = 740-8 = 732 → flip above.
    expect(r.placement).toBe('above');
    // Capped to spaceAbove (732) but never above desired (320).
    expect(r.maxHeight).toBe(320);
    // bottom sits just above the caret top: top = anchorTop - maxHeight.
    expect(r.top).toBe(740 - 320);
  });

  it('caps maxHeight to the available space when neither side fits the desired height', () => {
    // Small viewport, caret mid-screen: below has more room than above → below,
    // but capped to that space (less than desired).
    const r = computePickerPlacement({ viewportHeight: 300, margin: 8, desired: 320, min: 120, anchorTop: 120, anchorBottom: 140 });
    // spaceBelow = 300-140-8 = 152; spaceAbove = 120-8 = 112 → below (152 >= 112).
    expect(r.placement).toBe('below');
    expect(r.maxHeight).toBe(152); // capped to available, < desired
    expect(r.top).toBe(140);
  });

  it('CLAMPS to the available space when it is smaller than `min` (never overflows)', () => {
    // Extremely tight below and above (both 88px) with a min of 120: the popup
    // must clamp to the available 88px (the list scrolls), NOT force 120 and
    // overflow the viewport.
    const r = computePickerPlacement({ viewportHeight: 200, margin: 8, desired: 320, min: 120, anchorTop: 96, anchorBottom: 104 });
    // spaceBelow = 200-104-8 = 88; spaceAbove = 96-8 = 88 → below (tie).
    expect(r.maxHeight).toBe(88); // clamped to available space, below `min`
  });

  it('raises to `min` (10-row target) when there is ample room, never exceeding space or desired', () => {
    // A high min (e.g. the 10-row list target 440) with lots of room: the popup
    // is at least `min`. When desired < min but room is ample, it is raised to
    // `min` (440); when desired exceeds min, it is capped to desired.
    const raisedToMin = computePickerPlacement({ viewportHeight: 800, margin: 8, desired: 320, min: 440, anchorTop: 100, anchorBottom: 116 });
    // spaceBelow = 800-116-8 = 676; desired 320 < min 440 → raised to 440 (< space).
    expect(raisedToMin.maxHeight).toBe(440);

    // Short content (desired < min) but ample room → raised up to `min` (440).
    const raised = computePickerPlacement({ viewportHeight: 800, margin: 8, desired: 100, min: 440, anchorTop: 100, anchorBottom: 116 });
    expect(raised.maxHeight).toBe(440); // floor applied; space (676) is larger

    // Tall content (desired > min) with ample room → capped to desired.
    const toDesired = computePickerPlacement({ viewportHeight: 1000, margin: 8, desired: 500, min: 440, anchorTop: 100, anchorBottom: 116 });
    // spaceBelow = 1000-116-8 = 876; desired 500 (>= min 440) fits within space.
    expect(toDesired.maxHeight).toBe(500);
  });

  it('with a high `min`, still clamps to available space in a tight viewport', () => {
    // 10-row target min=440 but only ~152px available below → clamp to 152.
    const r = computePickerPlacement({ viewportHeight: 300, margin: 8, desired: 320, min: 440, anchorTop: 120, anchorBottom: 140 });
    // spaceBelow = 300-140-8 = 152; spaceAbove = 112 → below.
    expect(r.maxHeight).toBe(152); // clamped to available, below the 440 target
  });

  it('prefers BELOW on a tie (spaceBelow === spaceAbove)', () => {
    const r = computePickerPlacement({ ...base, anchorTop: 404, anchorBottom: 404 });
    // spaceBelow = 800-404-8 = 388; spaceAbove = 404-8 = 396 → above (396>388)…
    // use a true tie instead:
    const tie = computePickerPlacement({ viewportHeight: 800, margin: 0, desired: 1000, min: 120, anchorTop: 400, anchorBottom: 400 });
    // spaceBelow = 400, spaceAbove = 400 → tie → below.
    expect(tie.placement).toBe('below');
    void r;
  });
});

describe('formatTokenValue (leaf value display safety)', () => {
  it('returns empty string for null and undefined', () => {
    expect(formatTokenValue(null)).toBe('');
    expect(formatTokenValue(undefined)).toBe('');
  });

  it('stringifies primitives via String()', () => {
    expect(formatTokenValue('admin')).toBe('admin');
    expect(formatTokenValue(42)).toBe('42');
    expect(formatTokenValue(0)).toBe('0');
    expect(formatTokenValue(false)).toBe('false');
    expect(formatTokenValue(true)).toBe('true');
  });

  it('preserves an empty string as empty (treated as "no value" by callers)', () => {
    expect(formatTokenValue('')).toBe('');
  });

  it('JSON-stringifies objects and arrays', () => {
    expect(formatTokenValue({ qty: 2, sku: 'A1' })).toBe('{"qty":2,"sku":"A1"}');
    expect(formatTokenValue([1, 'a', true])).toBe('[1,"a",true]');
  });

  it('falls back gracefully when JSON.stringify throws (circular reference)', () => {
    const circular: Record<string, unknown> = {};
    circular.self = circular;
    // Must not throw; returns the String() fallback (e.g. "[object Object]").
    const out = formatTokenValue(circular);
    expect(typeof out).toBe('string');
    expect(out).toBe('[object Object]');
  });
});

describe('pickerMinHeight (view-dependent popup floor)', () => {
  const listFloor = PICKER_MIN_LIST_ROWS * PICKER_ROW_HEIGHT + PICKER_CHROME_HEIGHT;

  it('uses the new constants: 10 rows, 32px row, 120px chrome → 440px list floor', () => {
    expect(PICKER_MIN_LIST_ROWS).toBe(10);
    expect(PICKER_ROW_HEIGHT).toBe(32);
    expect(PICKER_CHROME_HEIGHT).toBe(120);
    expect(listFloor).toBe(440);
  });

  it('returns the compact floor when neither a list nor the waiting view is shown', () => {
    expect(pickerMinHeight({ showingTokenList: false, showingWaiting: false }, 120)).toBe(120);
    expect(pickerMinHeight({ showingTokenList: false, showingWaiting: false }, 80)).toBe(80);
  });

  it('returns chrome + ~10 rows when a token list IS shown (independent of compact floor)', () => {
    expect(pickerMinHeight({ showingTokenList: true, showingWaiting: false }, 120)).toBe(listFloor);
    expect(pickerMinHeight({ showingTokenList: true, showingWaiting: false }, 80)).toBe(listFloor);
  });

  it('returns the generous waiting floor when the LISTEN/WAITING view is shown', () => {
    expect(pickerMinHeight({ showingTokenList: false, showingWaiting: true }, 120)).toBe(PICKER_WAITING_MIN);
  });

  it('the waiting floor is at least as tall as the compact floor', () => {
    expect(PICKER_WAITING_MIN).toBeGreaterThanOrEqual(120);
    expect(pickerMinHeight({ showingTokenList: false, showingWaiting: true }, 120)).toBeGreaterThanOrEqual(120);
  });

  it('takes the LARGER floor when both list and waiting somehow apply (Math.max safety)', () => {
    const both = pickerMinHeight({ showingTokenList: true, showingWaiting: true }, 120);
    expect(both).toBe(Math.max(listFloor, PICKER_WAITING_MIN));
  });

  it('the list floor reserves room for at least 10 rows', () => {
    expect(pickerMinHeight({ showingTokenList: true, showingWaiting: false }, 120)).toBeGreaterThanOrEqual(
      PICKER_MIN_LIST_ROWS * PICKER_ROW_HEIGHT,
    );
  });
});

describe('buildBreadcrumb (middle/left truncation keeps the rightmost crumb)', () => {
  it('category only (no path): lead is the category, no trailing crumb', () => {
    const b = buildBreadcrumb('Global tokens', []);
    expect(b.lead).toBe('Global tokens');
    expect(b.tail).toBe('');
    expect(b.hasTrailing).toBe(false);
    expect(b.title).toBe('Global tokens');
  });

  it('one path segment: tail is that segment, lead is the category', () => {
    const b = buildBreadcrumb('Global tokens', ['Current user']);
    expect(b.lead).toBe('Global tokens');
    expect(b.tail).toBe('Current user');
    expect(b.hasTrailing).toBe(true);
    expect(b.title).toBe('Global tokens / Current user');
  });

  it('deep path: tail is the LAST crumb, lead is category + middle crumbs, title is full path', () => {
    const b = buildBreadcrumb('Step data tokens', ['User', 'Roles', 'Administrator']);
    expect(b.tail).toBe('Administrator');
    expect(b.lead).toBe('Step data tokens / User / Roles');
    expect(b.hasTrailing).toBe(true);
    expect(b.title).toBe('Step data tokens / User / Roles / Administrator');
  });
});
