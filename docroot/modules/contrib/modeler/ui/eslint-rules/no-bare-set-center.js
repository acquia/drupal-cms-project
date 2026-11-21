/**
 * ESLint rule: no-bare-set-center
 *
 * Prevents calling ReactFlow's `setCenter()` without an explicit `zoom`
 * property in the options argument.  When `zoom` is omitted, ReactFlow
 * defaults to maxZoom, which causes jarring zoom jumps that break the
 * user's viewport context.
 *
 * @example
 * // Bad - zoom defaults to maxZoom, causing an invasive viewport jump
 * setCenter(x, y, { duration: 800 });
 * setCenter(x, y);
 *
 * // Good - preserves the user's current zoom level
 * setCenter(x, y, { zoom: currentZoom, duration: 800 });
 * setCenter(x, y, { zoom: getZoom(), duration: 800 });
 */

/** @type {import('eslint').Rule.RuleModule} */
const rule = {
  meta: {
    type: 'problem',
    docs: {
      description:
        'Require an explicit `zoom` option when calling setCenter() to prevent unintended zoom changes',
      category: 'Best Practices',
      recommended: true,
    },
    messages: {
      missingZoom:
        'setCenter() called without a `zoom` option. ' +
        'ReactFlow defaults to maxZoom when zoom is omitted, causing invasive viewport jumps. ' +
        'Pass `zoom: getZoom()` (or another explicit value) in the options object.',
    },
    schema: [],
  },

  create(context) {
    return {
      CallExpression(node) {
        // Match any call whose callee ends with `setCenter`.
        // This covers `setCenter(...)`, `rf.setCenter(...)`, etc.
        const callee = node.callee;
        let name = null;

        if (callee.type === 'Identifier') {
          name = callee.name;
        } else if (
          callee.type === 'MemberExpression' &&
          callee.property.type === 'Identifier'
        ) {
          name = callee.property.name;
        }

        if (name !== 'setCenter') return;

        // setCenter(x, y) — no options at all
        if (node.arguments.length < 3) {
          context.report({ node, messageId: 'missingZoom' });
          return;
        }

        const optionsArg = node.arguments[2];

        // setCenter(x, y, someVariable) — can't statically check a variable,
        // so we only flag object literals that are clearly missing `zoom`.
        if (optionsArg.type !== 'ObjectExpression') return;

        const hasZoom = optionsArg.properties.some(
          (prop) =>
            prop.type === 'Property' &&
            ((prop.key.type === 'Identifier' && prop.key.name === 'zoom') ||
             (prop.key.type === 'Literal' && prop.key.value === 'zoom')),
        );

        if (!hasZoom) {
          context.report({ node, messageId: 'missingZoom' });
        }
      },
    };
  },
};

export default rule;
