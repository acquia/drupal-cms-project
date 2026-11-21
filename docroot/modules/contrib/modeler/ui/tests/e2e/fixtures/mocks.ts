import { Page, Route } from '@playwright/test';

/**
 * Mock data for workflow modeler API responses.
 * These simulate Drupal backend responses for isolated E2E testing.
 */

/**
 * Mock components in the format sent by the backend (drupalSettings.modeler.components).
 * Components carry `componentType` (integer) — the frontend resolves the string
 * `type` at load time via the typeMap.
 *
 * Each type needs >= 15 items so the search field is visible in
 * quick-add popups (see THRESHOLDS.SEARCH_VISIBILITY_MIN_COMPONENTS).
 */

// Generate filler components for a given componentType
function fillers(prefix: string, componentType: number, count: number) {
  const typeName = { 1: 'start', 4: 'element', 5: 'link', 6: 'gateway' }[componentType] ?? 'element';
  return Array.from({ length: count }, (_, i) => ({
    plugin: `${prefix}:filler_${i}`,
    label: `${typeName} Filler ${i}`,
    componentType,
    provider: 'eca_test',
    description: `Filler ${typeName} component ${i}`,
  }));
}

export const mockComponents = [
  // Events (3 real + 12 fillers = 15)
  { plugin: 'content_entity:insert', label: 'Content Entity Insert', componentType: 1, provider: 'eca_content', description: 'Triggered when a content entity is inserted' },
  { plugin: 'cron', label: 'Cron Run', componentType: 1, provider: 'eca_base', description: 'Triggered on cron execution' },
  { plugin: 'user:login', label: 'User Login', componentType: 1, provider: 'eca_user', description: 'Triggered when a user logs in' },
  ...fillers('event', 1, 12),
  // Actions (4 real + 11 fillers = 15)
  { plugin: 'entity:save', label: 'Save Entity', componentType: 4, provider: 'eca_content', description: 'Saves the current entity' },
  { plugin: 'message:set', label: 'Set Message', componentType: 4, provider: 'eca_base', description: 'Displays a message to the user' },
  { plugin: 'email:send', label: 'Send Email', componentType: 4, provider: 'eca_base', description: 'Sends an email' },
  { plugin: 'entity:delete', label: 'Delete Entity', componentType: 4, provider: 'eca_content', description: 'Deletes an entity' },
  ...fillers('action', 4, 11),
  // Conditions (2 real + 13 fillers = 15)
  { plugin: 'entity:is_new', label: 'Entity is New', componentType: 5, provider: 'eca_content', description: 'Checks if entity is new' },
  { plugin: 'user:has_role', label: 'User Has Role', componentType: 5, provider: 'eca_user', description: 'Checks if user has a specific role' },
  ...fillers('condition', 5, 13),
  // Gateways
  { plugin: 'gateway', label: 'Gateway', componentType: 6, provider: 'modeler', description: 'Gateway for conditional branching' }
];

export const mockModel = {
  id: 'test-model-1',
  label: 'Test Workflow',
  version: '1.0.0',
  status: true,
  documentation: 'A test workflow for E2E testing',
  nodes: [
    {
      id: 'event_1',
      type: 'start',
      position: { x: 100, y: 200 },
      data: {
        label: 'On Entity Insert',
        pluginId: 'content_entity:insert',
        configuration: {}
      }
    },
    {
      id: 'action_1',
      type: 'element',
      position: { x: 400, y: 200 },
      data: {
        label: 'Save Entity',
        pluginId: 'entity:save',
        configuration: {}
      }
    },
    {
      id: 'action_2',
      type: 'element',
      position: { x: 700, y: 200 },
      data: {
        label: 'Send Email',
        pluginId: 'email:send',
        configuration: {}
      }
    }
  ],
  edges: [
    {
      id: 'edge_1',
      source: 'event_1',
      target: 'action_1',
      condition: 'entity:is_new',
      conditionId: 'eca_entity_is_new_10j5tps',
      conditionLabel: 'Entity is New',
      conditionConfiguration: { negate: false, entity: '' },
    },
    {
      id: 'edge_2',
      source: 'action_1',
      target: 'action_2',
    }
  ]
};

export const mockEmptyModel = {
  id: 'new-model',
  label: 'New Workflow',
  version: '1.0.0',
  status: false,
  documentation: '',
  nodes: [],
  edges: []
};

/**
 * Mock model exercising condition REUSE (issue #3589093).
 *
 * Two condition edges share the SAME non-empty conditionId AND the same
 * target (action_1).  When the owner opts into reuse via
 * `model_constraints.*.successors.allowConditionReuse`, the load-time
 * translation in modelUtils.ts collapses them into ONE shared condition node
 * with TWO inbound edges and a single outbound edge to action_1.  On save it
 * demotes back to the two original backend condition edges (same conditionId),
 * losslessly.
 *
 * Backend format is unchanged — conditions are EDGE PROPERTIES here; promotion
 * turns them into a node on load.
 */
export const mockReuseModel = {
  id: 'test-model-1',
  label: 'Reuse Workflow',
  version: '1.0.0',
  status: true,
  documentation: 'A workflow that reuses one condition across two events',
  nodes: [
    {
      id: 'event_1',
      type: 'start',
      position: { x: 100, y: 100 },
      label: 'On Entity Insert',
      plugin: 'content_entity:insert',
      configuration: {},
    },
    {
      id: 'event_2',
      type: 'start',
      position: { x: 400, y: 100 },
      label: 'On Cron',
      plugin: 'cron',
      configuration: {},
    },
    {
      id: 'action_1',
      type: 'element',
      position: { x: 250, y: 400 },
      label: 'Save Entity',
      plugin: 'entity:save',
      configuration: {},
    },
  ],
  edges: [
    {
      id: 'edge_r1',
      source: 'event_1',
      target: 'action_1',
      condition: 'entity:is_new',
      conditionId: 'eca_shared_condition_1',
      conditionLabel: 'Entity is New',
      conditionConfiguration: { negate: false, entity: '' },
    },
    {
      id: 'edge_r2',
      source: 'event_2',
      target: 'action_1',
      condition: 'entity:is_new',
      conditionId: 'eca_shared_condition_1',
      conditionLabel: 'Entity is New',
      conditionConfiguration: { negate: false, entity: '' },
    },
  ],
};

/**
 * Model constraints enabling condition reuse (grouping on load).
 *
 * `allowConditionReuse` is read defensively from any successor constraint; a
 * single opt-in turns grouping on globally (see isConditionReuseEnabled in
 * utils/modelUtils.ts).
 */
export const mockReuseConstraints = {
  start: { successors: { allowConditionReuse: true } },
};

/**
 * Mock model with a genuinely DISCONNECTED action node (`action_orphan`).
 *
 * `event_1 -> action_1` is a normal connected flow, but `action_orphan` has NO
 * edges to or from any node — it has no structural owning event and no review
 * session. It exists to cover the true "no owning event whatsoever" case that
 * `action_1` (structurally owned by `event_1`) no longer represents in
 * replay.spec.ts. Served only for that single test via the `orphanNode`
 * setupMocks flag, mirroring the `reuseModel` wiring, so the shared `mockModel`
 * (and its exact node/edge count assertions in modeler.spec.ts) stays
 * untouched.
 *
 * Shape matches the inline `mockModelData` (flat `label`/`plugin`/`configuration`
 * per node), exactly like `mockReuseModel`.
 */
export const mockOrphanModel = {
  id: 'test-model-1',
  label: 'Orphan Node Workflow',
  version: '1.0.0',
  status: true,
  documentation: 'A workflow with a disconnected action node that has no owning event',
  nodes: [
    {
      id: 'event_1',
      type: 'start',
      position: { x: 100, y: 100 },
      label: 'On Entity Insert',
      plugin: 'content_entity:insert',
      configuration: {},
    },
    {
      id: 'action_1',
      type: 'element',
      position: { x: 400, y: 100 },
      label: 'Save Entity',
      plugin: 'entity:save',
      configuration: {},
    },
    {
      id: 'action_orphan',
      type: 'element',
      position: { x: 400, y: 400 },
      label: 'Disconnected Action',
      plugin: 'email:send',
      configuration: {},
    },
  ],
  edges: [
    {
      id: 'edge_o1',
      source: 'event_1',
      target: 'action_1',
    },
  ],
};

/**
 * Model constraints paired with `mockOrphanModel` so the Review affordance is
 * available in the panel (matching the default e2e permission/capability set).
 * No `allowConditionReuse` — the orphan model has no conditions to reuse.
 */
export const mockOrphanConstraints = {
  start: { successors: {} },
};

export const mockTokens = [
  { token: '[node:title]', label: 'Node Title', description: 'The title of the node' },
  { token: '[node:nid]', label: 'Node ID', description: 'The node ID' },
  { token: '[current-user:name]', label: 'Current User Name', description: 'Name of current user' },
  { token: '[current-user:mail]', label: 'Current User Email', description: 'Email of current user' },
  { token: '[site:name]', label: 'Site Name', description: 'The name of the site' }
];

/**
 * Node IDs from `mockEcaLib0007Model` that the screenshot spec selects.
 *
 * The raw IDs are BPMN-derived gibberish fragments (e.g. "07ksdyx", "182dlgt")
 * that trip cspell.  This fixture file is excluded from cspell scanning, so the
 * literals live here ONLY; the screenshot spec imports these semantic names
 * instead of hardcoding the raw IDs (keeping the spec — which IS scanned —
 * free of gibberish).
 */
export const SCREENSHOT_NODE_IDS = {
  /** "User Login" start event (user:login). */
  userLoginEvent: 'Event_0erz1e4',
  /** "Inform admins by email" action (action_send_email_action) — richest
   *  property panel / token fields. */
  emailAction: 'Activity_07ksdyx',
  /** "Redirect to content overview" action (action_goto_action). */
  contentRedirectAction: 'Activity_0l4w3fc',
  /** Promoted condition node for the User Register -> "Display link to Mailhog"
   *  edge ("admin?" condition). */
  adminConditionNode: 'cond__Flow_182dlgt',
} as const;

/**
 * Screenshot-only model mirroring the real "ECA Feature Demo" recipe
 * (eca_lib_0007) for the mkdocs documentation screenshots.  Used exclusively
 * by tests/e2e/screenshots.spec.ts via the `ecaLib0007` setupMocks option; the
 * rest of the e2e suite continues to use the untouched `mockModel`.
 *
 * Shape matches the inline `mockModelData` (flat `label`/`plugin`/`configuration`
 * per node; conditions are EDGE properties promoted to nodes on load).
 *
 * LAYOUT: every node is given the SAME default position {x:100, y:100}
 * (LAYOUT.DEFAULT_POSITION_X/Y in src/constants/dimensions.ts).  modelUtils.ts
 * (parseModelData, ~line 275) runs the modeler's own auto-layout on load only
 * when ALL nodes share that default position; using distinct BPMN-derived
 * coordinates would SKIP auto-layout and render a cramped/overlapping diagram.
 * Identical defaults intentionally trigger the clean on-load auto-layout that
 * the real UI produces.
 */
export const mockEcaLib0007Model = {
  id: 'eca_lib_0007',
  label: 'ECA Feature Demo',
  version: 'v2',
  status: true,
  documentation:
    'This model demonstrates a number of smart features around user accounts: '
    + 'informing admins by email on registration, role-based redirects on login, '
    + 'and assigning an internal role based on the user email domain.',
  nodes: [
    // ── Start events ──────────────────────────────────────────────────────
    { id: 'Event_0erz1e4', type: 'start', position: { x: 100, y: 100 }, label: 'User Login', plugin: 'user:login', configuration: {} },
    { id: 'Event_00dfxlw', type: 'start', position: { x: 100, y: 100 }, label: 'User Register', plugin: 'content_entity:insert', configuration: { type: 'user user' } },
    { id: 'Event_04tl9lk', type: 'start', position: { x: 100, y: 100 }, label: 'Update User', plugin: 'content_entity:update', configuration: { type: 'user user' } },
    // ── Action tasks ──────────────────────────────────────────────────────
    { id: 'Activity_0l4w3fc', type: 'element', position: { x: 100, y: 100 }, label: 'Redirect to content overview', plugin: 'action_goto_action', configuration: { replace_tokens: false, url: '/admin/content' } },
    { id: 'Activity_182vndw', type: 'element', position: { x: 100, y: 100 }, label: 'Redirect to admin overview', plugin: 'action_goto_action', configuration: { replace_tokens: false, url: '/admin' } },
    { id: 'Activity_1tfgvxt', type: 'element', position: { x: 100, y: 100 }, label: 'Redirect to user profile', plugin: 'action_goto_action', configuration: { replace_tokens: false, url: '/user' } },
    {
      id: 'Activity_07ksdyx',
      type: 'element',
      position: { x: 100, y: 100 },
      label: 'Inform admins by email',
      plugin: 'action_send_email_action',
      configuration: {
        recipient: '[admin:mail]',
        subject: '[site:name] New user registered: [newuser:name]',
        message: 'Please review here: [newuser:url]',
        replace_tokens: true,
      },
    },
    { id: 'Activity_1w9sk6r', type: 'element', position: { x: 100, y: 100 }, label: 'Load all admin users', plugin: 'eca_views_query', configuration: { token_name: 'admins', view_id: 'user_admin_people', display_id: 'attachment_1', arguments: 'administrator' } },
    { id: 'Activity_0atqgae', type: 'element', position: { x: 100, y: 100 }, label: 'Pop an admin from the list', plugin: 'eca_list_remove', configuration: { value: '', token_name: 'admin', method: 'first', index: '', list_token: 'admins' } },
    { id: 'Activity_0tlx3ln', type: 'element', position: { x: 100, y: 100 }, label: 'Save new user as token', plugin: 'eca_token_set_value', configuration: { token_name: 'newuser', token_value: '[entity]', use_yaml: false } },
    { id: 'Activity_0xd3fam', type: 'element', position: { x: 100, y: 100 }, label: 'Switch user', plugin: 'eca_switch_account', configuration: { user_id: '1' } },
    { id: 'Activity_0nr4ng5', type: 'element', position: { x: 100, y: 100 }, label: 'Display link to Mailhog', plugin: 'eca_warning_message', configuration: { message: 'Check emails in <a href="https://mailhog-[site:url-brief]" target="_blank">Mailhog</a>', replace_tokens: true } },
    { id: 'Activity_19q8z5c', type: 'element', position: { x: 100, y: 100 }, label: 'Add internal role', plugin: 'user_add_role_action', configuration: { replace_tokens: false, rid: 'internal', object: 'actuser' } },
    { id: 'Activity_18vsxl7', type: 'element', position: { x: 100, y: 100 }, label: 'Remove internal role', plugin: 'user_remove_role_action', configuration: { replace_tokens: false, rid: 'internal', object: 'actuser' } },
    { id: 'Activity_1gige0f', type: 'element', position: { x: 100, y: 100 }, label: 'Switch user', plugin: 'eca_switch_account', configuration: { user_id: '1' } },
    { id: 'Activity_1vtj47i', type: 'element', position: { x: 100, y: 100 }, label: 'Message', plugin: 'action_message_action', configuration: { replace_tokens: false, message: 'You have been added to the internal group. Welcome!' } },
    { id: 'Activity_0qzx0pp', type: 'element', position: { x: 100, y: 100 }, label: 'Warning', plugin: 'eca_warning_message', configuration: { replace_tokens: false, message: 'You are no longer part of the internal group.' } },
    { id: 'Activity_0bk309u', type: 'element', position: { x: 100, y: 100 }, label: 'Save user as token', plugin: 'eca_token_set_value', configuration: { token_name: 'actuser', token_value: '[entity]', use_yaml: false } },
    // ── Gateways ──────────────────────────────────────────────────────────
    { id: 'Gateway_0hd8858', type: 'gateway', position: { x: 100, y: 100 }, label: '', plugin: 'gateway', configuration: {} },
    { id: 'Gateway_1rthid4', type: 'gateway', position: { x: 100, y: 100 }, label: '', plugin: 'gateway', configuration: {} },
    { id: 'Gateway_14hq8dd', type: 'gateway', position: { x: 100, y: 100 }, label: '', plugin: 'gateway', configuration: {} },
    { id: 'Gateway_1lz4l89', type: 'gateway', position: { x: 100, y: 100 }, label: '', plugin: 'gateway', configuration: {} },
    { id: 'Gateway_1o87unm', type: 'gateway', position: { x: 100, y: 100 }, label: '', plugin: 'gateway', configuration: {} },
  ],
  edges: [
    // Conditional edges carry the condition as EDGE properties (promoted to a
    // condition node on load, exactly like mockModel.edge_1).
    {
      id: 'Flow_1o433l9', source: 'Event_0erz1e4', target: 'Gateway_0hd8858',
      condition: 'eca_scalar', conditionId: 'eca_scalar_flow_1o433l9', conditionLabel: 'not PW reset?',
      conditionConfiguration: { case: false, left: '[current-page:url:path]', right: '/user/reset', operator: 'beginswith', type: 'value', negate: true },
    },
    {
      id: 'Flow_182dlgt', source: 'Event_00dfxlw', target: 'Activity_0nr4ng5',
      condition: 'eca_current_user_role', conditionId: 'eca_current_user_role_flow_182dlgt', conditionLabel: 'admin?',
      conditionConfiguration: { negate: false, role: 'administrator' },
    },
    { id: 'Flow_0rvptvj', source: 'Event_00dfxlw', target: 'Activity_0tlx3ln' },
    { id: 'Flow_1uajnym', source: 'Event_04tl9lk', target: 'Activity_0bk309u' },
    {
      id: 'Flow_1hqinah', source: 'Gateway_0hd8858', target: 'Activity_0l4w3fc',
      condition: 'eca_current_user_role', conditionId: 'eca_current_user_role_flow_1hqinah', conditionLabel: 'Content editor?',
      conditionConfiguration: { negate: false, role: 'content_editor' },
    },
    {
      id: 'Flow_0047zve', source: 'Gateway_0hd8858', target: 'Activity_182vndw',
      condition: 'eca_current_user_role', conditionId: 'eca_current_user_role_flow_0047zve', conditionLabel: 'Admin?',
      conditionConfiguration: { negate: false, role: 'administrator' },
    },
    {
      id: 'Flow_0ijt8mj', source: 'Gateway_0hd8858', target: 'Gateway_14hq8dd',
      condition: 'eca_current_user_role', conditionId: 'eca_current_user_role_flow_0ijt8mj', conditionLabel: 'not Admin?',
      conditionConfiguration: { role: 'administrator', negate: true },
    },
    {
      id: 'Flow_0a1zeo8', source: 'Gateway_1rthid4', target: 'Activity_0atqgae',
      condition: 'eca_count', conditionId: 'eca_count_flow_0a1zeo8', conditionLabel: '> 0',
      conditionConfiguration: { negate: false, case: false, left: 'admins', right: '0', operator: 'greaterthan', type: 'numeric' },
    },
    {
      id: 'Flow_1j2h2dk', source: 'Gateway_14hq8dd', target: 'Activity_1tfgvxt',
      condition: 'eca_current_user_role', conditionId: 'eca_current_user_role_flow_1j2h2dk', conditionLabel: 'not Content Editor?',
      conditionConfiguration: { role: 'content_editor', negate: true },
    },
    { id: 'Flow_0xdxd7h', source: 'Gateway_14hq8dd', target: 'Activity_0bk309u' },
    {
      id: 'Flow_10zxcgn', source: 'Gateway_1lz4l89', target: 'Activity_19q8z5c',
      condition: 'eca_user_role', conditionId: 'eca_user_role_flow_10zxcgn', conditionLabel: 'not internal?',
      conditionConfiguration: { account: '[actuser]', role: 'internal', negate: true },
    },
    {
      id: 'Flow_0c7hrjx', source: 'Gateway_1o87unm', target: 'Activity_18vsxl7',
      condition: 'eca_user_role', conditionId: 'eca_user_role_flow_0c7hrjx', conditionLabel: 'internal?',
      conditionConfiguration: { negate: false, account: '[actuser]', role: 'internal' },
    },
    { id: 'Flow_1jf1yqf', source: 'Activity_07ksdyx', target: 'Gateway_1rthid4' },
    { id: 'Flow_03ipjgn', source: 'Activity_1w9sk6r', target: 'Gateway_1rthid4' },
    { id: 'Flow_0upys2i', source: 'Activity_0atqgae', target: 'Activity_07ksdyx' },
    { id: 'Flow_0psevjr', source: 'Activity_0tlx3ln', target: 'Activity_0xd3fam' },
    { id: 'Flow_1nwt9q0', source: 'Activity_0xd3fam', target: 'Activity_1w9sk6r' },
    { id: 'Flow_1h0rlsm', source: 'Activity_19q8z5c', target: 'Activity_1vtj47i' },
    { id: 'Flow_1j4xfpo', source: 'Activity_18vsxl7', target: 'Activity_0qzx0pp' },
    {
      id: 'Flow_1vczt3y', source: 'Activity_1gige0f', target: 'Gateway_1lz4l89',
      condition: 'eca_scalar', conditionId: 'eca_scalar_flow_1vczt3y', conditionLabel: 'from example.com?',
      conditionConfiguration: { negate: false, case: false, left: '[actuser:mail]', right: '@example.com', operator: 'contains', type: 'value' },
    },
    {
      id: 'Flow_0xavi4t', source: 'Activity_1gige0f', target: 'Gateway_1o87unm',
      condition: 'eca_scalar', conditionId: 'eca_scalar_flow_0xavi4t', conditionLabel: 'not from example.com?',
      conditionConfiguration: { case: false, left: '[actuser:mail]', right: '@example.com', operator: 'contains', type: 'value', negate: true },
    },
    { id: 'Flow_1oh4w3t', source: 'Activity_0bk309u', target: 'Activity_1gige0f' },
  ],
};

/**
 * Components covering every pluginId used by `mockEcaLib0007Model` so node
 * labels resolve.  Reuses the three real events already in `mockComponents`
 * where they overlap, but is a self-contained list served when `ecaLib0007`
 * is enabled.  No >= 15-per-type filler requirement here (this model is not
 * used for quick-add search screenshots).
 */
export const mockEcaLib0007Components = [
  // Events (componentType 1)
  { plugin: 'user:login', label: 'User Login', componentType: 1, provider: 'eca_user', description: 'Triggered when a user logs in' },
  { plugin: 'content_entity:insert', label: 'Content Entity Insert', componentType: 1, provider: 'eca_content', description: 'Triggered when a content entity is inserted' },
  { plugin: 'content_entity:update', label: 'Content Entity Update', componentType: 1, provider: 'eca_content', description: 'Triggered when a content entity is updated' },
  // Actions (componentType 4)
  { plugin: 'action_goto_action', label: 'Redirect to URL', componentType: 4, provider: 'eca_base', description: 'Redirects the user to a given URL' },
  { plugin: 'action_send_email_action', label: 'Send email', componentType: 4, provider: 'eca_base', description: 'Sends an email' },
  { plugin: 'eca_views_query', label: 'Views query', componentType: 4, provider: 'eca_views', description: 'Runs a Views query and stores the result' },
  { plugin: 'eca_list_remove', label: 'Remove item from list', componentType: 4, provider: 'eca_base', description: 'Removes an item from a list token' },
  { plugin: 'eca_token_set_value', label: 'Set token value', componentType: 4, provider: 'eca_base', description: 'Stores a value in a token' },
  { plugin: 'eca_switch_account', label: 'Switch account', componentType: 4, provider: 'eca_user', description: 'Switches the current user account' },
  { plugin: 'eca_warning_message', label: 'Display warning message', componentType: 4, provider: 'eca_base', description: 'Displays a warning message' },
  { plugin: 'user_add_role_action', label: 'Add user role', componentType: 4, provider: 'eca_user', description: 'Adds a role to a user' },
  { plugin: 'user_remove_role_action', label: 'Remove user role', componentType: 4, provider: 'eca_user', description: 'Removes a role from a user' },
  { plugin: 'action_message_action', label: 'Display message', componentType: 4, provider: 'eca_base', description: 'Displays a status message' },
  // Conditions (componentType 5)
  { plugin: 'eca_current_user_role', label: 'Current user role', componentType: 5, provider: 'eca_user', description: 'Checks the role of the current user' },
  { plugin: 'eca_count', label: 'Count comparison', componentType: 5, provider: 'eca_base', description: 'Compares a count against a value' },
  { plugin: 'eca_scalar', label: 'Scalar value', componentType: 5, provider: 'eca_base', description: 'Compares a scalar value' },
  { plugin: 'eca_user_role', label: 'User role', componentType: 5, provider: 'eca_user', description: 'Checks whether a user has a role' },
  // Gateway (componentType 6)
  { plugin: 'gateway', label: 'Gateway', componentType: 6, provider: 'modeler', description: 'Gateway for conditional branching' },
];

/**
 * Global tokens shown in the "[" token picker for the eca_lib_0007 screenshots.
 *
 * Shape is the `drupalSettings.modeler_api.global_tokens` record consumed by
 * the picker (via useLazyTokens → buildTokenCategories → transformGlobalToken):
 * keyed by group, each a GlobalToken with `name`, `raw token`, `token`, and
 * nested `children` that become drill-in leaf (usable) tokens.  Wired into the
 * HTML modeler_api block by the `ecaLib0007` patch so the picker shows real,
 * on-topic entries (the e2e test server otherwise ships NO global tokens, so
 * the picker would only show the empty category list).  This is a
 * screenshot-only path — it does NOT touch the shared mockReplayEntries.
 */
export const mockEcaLib0007GlobalTokens = {
  site: {
    name: 'Site information',
    'raw token': '[site]',
    token: 'site',
    children: {
      name: { name: 'Site name', description: 'The name of the site', 'raw token': '[site:name]', token: 'name', value: 'ECA Feature Demo' },
      mail: { name: 'Site email', description: 'The email address of the site', 'raw token': '[site:mail]', token: 'mail', value: 'admin@example.com' },
    },
  },
  admin: {
    name: 'Administrator',
    'raw token': '[admin]',
    token: 'admin',
    children: {
      mail: { name: 'Admin email', description: 'Email address of an administrator', 'raw token': '[admin:mail]', token: 'mail', value: 'admin@example.com' },
    },
  },
  newuser: {
    name: 'New user',
    'raw token': '[newuser]',
    token: 'newuser',
    children: {
      name: { name: 'New user name', description: 'Name of the newly registered user', 'raw token': '[newuser:name]', token: 'name', value: 'jane.doe' },
      url: { name: 'New user URL', description: 'Canonical URL of the new user account', 'raw token': '[newuser:url]', token: 'url', value: '/user/42' },
    },
  },
  actuser: {
    name: 'Acting user',
    'raw token': '[actuser]',
    token: 'actuser',
    children: {
      mail: { name: 'Acting user email', description: 'Email address of the acting user', 'raw token': '[actuser:mail]', token: 'mail', value: 'jane.doe@example.com' },
    },
  },
  entity: { name: 'Entity', description: 'The entity from the triggering event', 'raw token': '[entity]', token: 'entity' },
};

/**
 * Per-plugin configuration FORMS for the eca_lib_0007 screenshots.  Served by
 * the mock config route (gated behind `ecaLib0007`) as a `FormField[]` ARRAY —
 * the shape ConfigurationForm expects — instead of the legacy HTML string.
 * Fields with token_support (or textfield/email/url/textarea types) render as
 * token-enabled ContentEditableFields, so typing "[" opens the picker.
 *
 * FormField shape: { key, type, title?, placeholder?, required?, token_support? }.
 */
export const mockEcaLib0007ConfigForms: Record<string, Array<Record<string, unknown>>> = {
  action_send_email_action: [
    { key: 'recipient', type: 'email', title: 'Recipient', placeholder: 'Enter email address', required: true, token_support: true },
    { key: 'subject', type: 'textfield', title: 'Subject', placeholder: 'Enter subject', token_support: true },
    { key: 'message', type: 'textarea', title: 'Message', placeholder: 'Enter message', token_support: true },
    { key: 'replace_tokens', type: 'checkbox', title: 'Replace tokens in field values' },
  ],
  action_goto_action: [
    { key: 'url', type: 'textfield', title: 'URL', placeholder: 'Enter URL', token_support: true },
    { key: 'replace_tokens', type: 'checkbox', title: 'Replace tokens in field values' },
  ],
  action_message_action: [
    { key: 'message', type: 'textarea', title: 'Message', placeholder: 'Enter message', token_support: true },
    { key: 'replace_tokens', type: 'checkbox', title: 'Replace tokens in field values' },
  ],
  eca_token_set_value: [
    { key: 'token_name', type: 'textfield', title: 'Token name', placeholder: 'Enter token name' },
    { key: 'token_value', type: 'textfield', title: 'Token value', placeholder: 'Enter value', token_support: true },
  ],
};

/** Pre-filled values for plugins where showing populated fields matters. */
export const mockEcaLib0007ConfigValues: Record<string, Record<string, unknown>> = {
  action_send_email_action: {
    recipient: '[admin:mail]',
    subject: '[site:name] New user registered: [newuser:name]',
    message: 'Please review here: [newuser:url]',
    replace_tokens: true,
  },
};

/** Fallback form for any other plugin when ecaLib0007: one token-enabled field. */
export const mockEcaLib0007ConfigFallback: Array<Record<string, unknown>> = [
  { key: 'value', type: 'textfield', title: 'Value', token_support: true },
];

/**
 * Mock replay entries in the ReplayEntry[] format expected by useReplayLoader.
 * Each entry represents a single workflow execution with its history of steps.
 */
export const mockReplayEntries = [
  {
    model_id: 'test-model-1',
    component_id: 'event_1',
    history: [
      {
        id: 'event_1',
        type: 'started',
        data: { label: 'On Entity Insert' },
      },
      {
        id: 'event_1',
        type: 'add successor',
        successorId: 'action_1',
        conditionId: 'eca_entity_is_new_10j5tps',
        data: {},
      },
      {
        id: 'action_1',
        type: 'execute',
        data: { label: 'Save Entity', entity: { title: 'Test Article', type: 'node' } },
      },
    ],
    timestamp: '2026-02-09T10:30:00Z',
    user: { name: 'admin', uid: 1 },
    ip: '192.168.1.100',
    url: '/node/42/edit',
  },
  {
    model_id: 'test-model-1',
    component_id: 'event_1',
    history: [
      {
        id: 'event_1',
        type: 'event',
        data: { label: 'On Entity Insert' },
      },
    ],
    timestamp: '2026-02-08T14:15:30Z',
    user: { name: 'editor', uid: 5 },
    ip: '10.0.0.42',
    url: '/admin/content',
  },
  {
    model_id: 'test-model-1',
    component_id: 'event_1',
    history: [
      {
        id: 'event_1',
        type: 'event',
        data: { label: 'On Entity Insert' },
      },
      {
        id: 'action_1',
        type: 'action',
        successorId: 'action_1',
        data: { label: 'Save Entity' },
      },
    ],
    timestamp: '2026-02-07T09:00:00Z',
    user: 'anonymous',
    ip: '203.0.113.50',
    url: '/contact',
  },
];

/**
 * Mock test endpoint responses.
 * The test flow is: POST with {modelId, componentId} → get {jobId}
 * then poll with {jobId} → get {status: "waiting"} or replay data array.
 */
export const mockTestJobId = 'test-job-abc-123';

export const mockTestReplayData = [
  {
    id: 'event_1',
    type: 'event',
    data: { label: 'On Entity Insert' },
  },
  {
    id: 'action_1',
    type: 'action',
    successorId: 'action_1',
    data: { label: 'Save Entity', entity: { title: 'Test Result', type: 'node' } },
  },
];

/**
 * Mock contexts for testing the context switcher dropdown.
 */
export const mockContexts = [
  {
    id: 'ctx_content',
    topic: 'Content Management',
    model_owner: 'node',
    components: {
      start: { plugins: ['content_entity:insert'] },
      element: { plugins: ['entity:save', 'message:set'] },
    },
  },
  {
    id: 'ctx_user',
    topic: 'User Management',
    model_owner: 'user',
    components: {
      start: { plugins: ['user:login'] },
      element: { plugins: ['email:send'] },
    },
  },
];

/**
 * Options for setupMocks function.
 */
export interface SetupMocksOptions {
  /** Whether the model is new (triggers metadata modal) */
  isNew?: boolean;
  /** Whether to include test_url in modeler_api settings (default: false) */
  withTestUrl?: boolean;
  /** Number of poll requests to return "waiting" before returning data (default: 1) */
  testPollWaitCount?: number;
  /** If set, the test endpoint returns this error on initial request */
  testInitError?: string;
  /** If set, the test endpoint returns this warning on initial request */
  testInitWarning?: string;
  /** If set, the test poll endpoint returns this error */
  testPollError?: string;
  /**
   * Override individual permission values in `drupalSettings.modeler_api.permissions`.
   * Missing keys keep their default `true` from the test server HTML.
   */
  permissions?: Partial<Record<
    'create template' | 'edit metadata' | 'edit template' | 'replay' | 'switch context' | 'test',
    boolean
  >>;
  /** Whether to inject mock contexts into `drupalSettings.modeler_api.contexts` */
  withContexts?: boolean;
  /** Whether to set the model metadata as template (`metadata.template: true`) */
  withTemplate?: boolean;
  /** Whether to set the model as read-only (`modeler_api.readOnly: true`) */
  readOnly?: boolean;
  /**
   * Owner-provided model constraints injected into
   * `drupalSettings.modeler_api.model_constraints` (issue #3589093).  Used to
   * drive successor cardinality and the `allowConditionReuse` opt-in.  When
   * omitted, no `model_constraints` key is added (today's default behavior).
   */
  modelConstraints?: Record<string, unknown>;
  /**
   * When `true`, serve the condition-REUSE mock model (mockReuseModel) instead
   * of the default mock model.  Two condition edges share the same conditionId
   * and target so that, with `allowConditionReuse` on, they collapse into a
   * single shared condition node on load.  Typically combined with
   * `modelConstraints: mockReuseConstraints`.
   */
  reuseModel?: boolean;
  /**
   * When `true`, serve the orphan-node mock model (mockOrphanModel) instead of
   * the default mock model.  It contains a genuinely disconnected action node
   * (`action_orphan`) with no edges, used to assert the Review button is
   * DISABLED for a node with NO owning event.  Mirrors the `reuseModel` wiring
   * (HTML modelData patch + model route override); keeps the shared `mockModel`
   * untouched so its exact node/edge count assertions do not break.
   */
  orphanNode?: boolean;
  /**
   * When `true`, serve the "ECA Feature Demo" recipe mock (mockEcaLib0007Model)
   * plus its matching components and tokens instead of the default mock model.
   * Used only by the documentation screenshot spec so the screenshots depict
   * the real recipe.  Mirrors the `reuseModel` wiring (HTML modelData patch +
   * model route override).
   */
  ecaLib0007?: boolean;
}

/**
 * Sets up mock API routes for the modeler.
 * Call this in your test's beforeEach to enable mocked responses.
 */
export async function setupMocks(page: Page, options: SetupMocksOptions = {}) {
  const {
    isNew = false,
    withTestUrl = false,
    testPollWaitCount = 1,
    testInitError,
    testInitWarning,
    testPollError,
    permissions,
    withContexts = false,
    withTemplate = false,
    readOnly = false,
    modelConstraints,
    reuseModel = false,
    orphanNode = false,
    ecaLib0007 = false,
  } = options;

  // Determine whether we need to intercept and modify the HTML document.
  const needsHtmlPatch =
    withTestUrl || permissions || withContexts || withTemplate || readOnly
    || !!modelConstraints || reuseModel || orphanNode || ecaLib0007;

  if (needsHtmlPatch) {
    await page.route('**/modeler/**', async (route: Route) => {
      // Only intercept HTML page requests (not API/static requests)
      const request = route.request();
      if (request.resourceType() !== 'document') {
        await route.continue();
        return;
      }
      const response = await route.fetch();
      let html = await response.text();

      // Inject test_url into the modeler_api settings object
      if (withTestUrl) {
        html = html.replace(
          "replay_url: '/modeler-api/replay'",
          "replay_url: '/modeler-api/replay',\n        test_url: '/modeler-api/test'"
        );
      }

      // Override individual permissions
      if (permissions) {
        for (const [key, value] of Object.entries(permissions)) {
          // Replace e.g. "'edit metadata': true" with "'edit metadata': false"
          html = html.replace(
            new RegExp(`'${key}':\\s*true`),
            `'${key}': ${String(value)}`,
          );
          html = html.replace(
            new RegExp(`'${key}':\\s*false`),
            `'${key}': ${String(value)}`,
          );
        }
      }

      // Inject contexts array into modeler_api
      if (withContexts) {
        html = html.replace(
          "permissions: {",
          `contexts: ${JSON.stringify(mockContexts)},\n        permissions: {`,
        );
      }

      // Set metadata.template to true in the inline modelData JSON AND
      // inject it into modeler_api.metadata so Flow.tsx can read it.
      if (withTemplate) {
        html = html.replace(
          /"metadata":\s*\{/,
          '"metadata": {\n      "template": true,',
        );
        html = html.replace(
          'isNew:',
          'metadata: { template: true },\n        isNew:',
        );
      }

      // Inject readOnly: true into the modeler_api settings block
      if (readOnly) {
        html = html.replace(
          'isNew:',
          'readOnly: true,\n        isNew:',
        );
      }

      // Inject owner model_constraints into the modeler_api settings block
      // (issue #3589093).  Read defensively by the app; drives successor
      // cardinality and the allowConditionReuse opt-in.
      if (modelConstraints) {
        html = html.replace(
          'isNew:',
          `model_constraints: ${JSON.stringify(modelConstraints)},\n        isNew:`,
        );
      }

      // Swap the inline model JSON for the condition-REUSE model so the
      // shared-condition grouping path can be exercised.  The app reads the
      // model from drupalSettings.modeler.modelData (the inline
      // `var mockModelData = JSON.stringify(...)` assignment in test-server.ts).
      if (reuseModel) {
        const reuseModelJson = {
          id: mockReuseModel.id,
          version: mockReuseModel.version,
          metadata: { label: mockReuseModel.label, description: mockReuseModel.documentation },
          nodes: mockReuseModel.nodes,
          edges: mockReuseModel.edges,
        };
        html = html.replace(
          /var mockModelData = JSON\.stringify\([\s\S]*?\);/,
          `var mockModelData = JSON.stringify(${JSON.stringify(reuseModelJson)});`,
        );
      }

      // Swap the inline model JSON for the orphan-node model (a disconnected
      // action node with no owning event).  Same patch path as `reuseModel`
      // above (drupalSettings.modeler.modelData).
      if (orphanNode) {
        const orphanModelJson = {
          id: mockOrphanModel.id,
          version: mockOrphanModel.version,
          metadata: { label: mockOrphanModel.label, description: mockOrphanModel.documentation },
          nodes: mockOrphanModel.nodes,
          edges: mockOrphanModel.edges,
        };
        html = html.replace(
          /var mockModelData = JSON\.stringify\([\s\S]*?\);/,
          `var mockModelData = JSON.stringify(${JSON.stringify(orphanModelJson)});`,
        );
      }

      // Swap the inline model JSON for the eca_lib_0007 "ECA Feature Demo"
      // recipe model used by the documentation screenshots.  Same patch path
      // as `reuseModel` above (drupalSettings.modeler.modelData).
      if (ecaLib0007) {
        const ecaLib0007ModelJson = {
          id: mockEcaLib0007Model.id,
          version: mockEcaLib0007Model.version,
          metadata: { label: mockEcaLib0007Model.label, description: mockEcaLib0007Model.documentation },
          nodes: mockEcaLib0007Model.nodes,
          edges: mockEcaLib0007Model.edges,
        };
        html = html.replace(
          /var mockModelData = JSON\.stringify\([\s\S]*?\);/,
          `var mockModelData = JSON.stringify(${JSON.stringify(ecaLib0007ModelJson)});`,
        );

        // Inject inline global_tokens into the modeler_api block so the "["
        // token picker has real entries to show (useLazyTokens reads
        // drupalSettings.modeler_api.global_tokens directly when present; the
        // e2e server otherwise provides none).
        html = html.replace(
          "token_url: '/modeler-api/tokens',",
          `token_url: '/modeler-api/tokens',\n        global_tokens: ${JSON.stringify(mockEcaLib0007GlobalTokens)},`,
        );
      }

      await route.fulfill({
        status: response.status(),
        headers: response.headers(),
        body: html,
      });
    });
  }

  // Mock component library endpoint
  await page.route('**/modeler-api/components**', async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(ecaLib0007 ? mockEcaLib0007Components : mockComponents)
    });
  });

  // Mock model load endpoint
  await page.route('**/modeler-api/model/**', async (route: Route) => {
    const url = route.request().url();
    if (url.includes('new-model') || isNew) {
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          ...mockEmptyModel,
          settings: { modeler_api: { isNew: true } }
        })
      });
    } else {
      // The app loads its model from the inline drupalSettings.modeler.modelData
      // (patched above for ecaLib0007/orphanNode), exactly as the reuseModel
      // branch relies on.  This route is a secondary fallback and keeps the
      // original mockModel shape (data.pluginId) to avoid breaking any consumer.
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
          ...(orphanNode ? mockOrphanModel : mockModel),
          settings: { modeler_api: { isNew: false } }
        })
      });
    }
  });

  // Mock model save endpoint
  await page.route('**/modeler-api/model', async (route: Route) => {
    if (route.request().method() === 'POST' || route.request().method() === 'PUT') {
      const body = route.request().postDataJSON();
      await route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ ...body, saved: true })
      });
    } else {
      await route.continue();
    }
  });

  // Mock token endpoint — serves both CSRF token (plain text GET) and token browser (JSON)
  await page.route('**/modeler-api/tokens**', async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: 'text/plain',
      body: 'mock-csrf-token'
    });
  });

  // Mock configuration form endpoint.
  //
  // The real loader (src/hooks/useConfigurationLoader.ts) POSTs to the bare
  // `config_url` (= `/modeler-api/config`, NO trailing path segment) and sends
  // the pluginId in the JSON body as `plugin_id` — NOT in the URL.  The pattern
  // therefore uses `config**` (matches the bare path + any query), not
  // `config/**` (which would require a path segment after `config/` and never
  // match the real request).  Distinct from the other routes: model
  // (`/model/**`), components (`/components**`), tokens (`/tokens**`), replay
  // (`/replay**`).
  await page.route('**/modeler-api/config**', async (route: Route) => {
    // Only OUR screenshot model serves config forms here.  For every other
    // spec, fall through to the test server's own /modeler-api/config handler
    // (test-server.ts) so their behavior is byte-for-byte unchanged — the old
    // `config/**` pattern never matched, so this route was effectively dead for
    // them, and route.continue() preserves exactly that.
    if (!ecaLib0007) {
      await route.continue();
      return;
    }

    // Derive the pluginId from the POST BODY (`plugin_id`); fall back to the
    // last URL segment for safety if the body is unavailable.
    const body = route.request().postDataJSON();
    const urlFallback = route.request().url().split('/').pop()?.split('?')[0] || 'unknown';
    const pluginId = body?.plugin_id || urlFallback;

    // Serve a real FormField[] ARRAY (the shape ConfigurationForm /
    // validateConfigurationResponse expects — an HTML string is rejected) so
    // action nodes show token-enabled fields (textfield/email/url/textarea or
    // token_support) where typing "[" opens the picker.  Falls back to a single
    // token-enabled field for any plugin without a specific form.
    const form = mockEcaLib0007ConfigForms[pluginId] ?? mockEcaLib0007ConfigFallback;
    const values = mockEcaLib0007ConfigValues[pluginId] ?? {};
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ pluginId, form, values }),
    });
  });

  // Mock replay data endpoint — returns ReplayEntry[]
  await page.route('**/modeler-api/replay**', async (route: Route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(mockReplayEntries)
    });
  });

  // Mock test endpoint — handles both initial request and polling
  if (withTestUrl) {
    let pollCount = 0;

    await page.route('**/modeler-api/test**', async (route: Route) => {
      if (route.request().method() !== 'POST') {
        await route.continue();
        return;
      }

      const body = route.request().postDataJSON();

      if (body && body.jobId && body.cancelled) {
        // Cancellation notification — acknowledge it
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify({ status: 'cancelled' }),
        });
      } else if (body && body.jobId) {
        // This is a poll request
        pollCount++;

        if (testPollError) {
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ error: testPollError }),
          });
          return;
        }

        if (pollCount <= testPollWaitCount) {
          // Still waiting
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ status: 'waiting' }),
          });
        } else {
          // Return replay data
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify(mockTestReplayData),
          });
        }
      } else {
        // This is the initial test request
        if (testInitError) {
          await route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({ error: testInitError }),
          });
          return;
        }

        const response: Record<string, string> = { jobId: mockTestJobId };
        if (testInitWarning) {
          response.warning = testInitWarning;
        }

        pollCount = 0; // Reset poll counter for this test run
        await route.fulfill({
          status: 200,
          contentType: 'application/json',
          body: JSON.stringify(response),
        });
      }
    });
  }
}

/**
 * Creates a test page with the modeler application and mocked APIs.
 */
export async function createModelerPage(page: Page, modelId = 'test-model-1') {
  await setupMocks(page);

  // Navigate to the modeler page
  // In a real setup, this would be the Drupal page URL
  await page.goto(`/modeler/${modelId}`);

  // Wait for the React app to initialize
  await page.waitForSelector('[data-testid="flow-canvas"]', { timeout: 10000 });
}
