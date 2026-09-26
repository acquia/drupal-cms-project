/**
 * Simple test server for E2E testing.
 *
 * This creates a minimal server that serves the modeler application
 * with mocked Drupal integration for isolated E2E testing.
 *
 * Usage:
 *   npx ts-node e2e/test-server.ts
 *
 * The server will start on http://localhost:3000
 */

import { createServer, IncomingMessage, ServerResponse } from 'http';
import { readFileSync, existsSync } from 'fs';
import { join, extname, dirname } from 'path';
import { fileURLToPath } from 'url';
import { metadataForm, newModelMetadataForm } from '../../src/components/__fixtures__/metadataForm';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

const PORT = process.env.E2E_PORT || 3000;
const DIST_DIR = join(__dirname, '../../../dist');
const E2E_DIR = __dirname;

// MIME types
const mimeTypes: Record<string, string> = {
  '.html': 'text/html',
  '.js': 'application/javascript',
  '.css': 'text/css',
  '.json': 'application/json',
  '.png': 'image/png',
  '.svg': 'image/svg+xml',
};

// Generate the test HTML page
function generateTestPage(modelId: string): string {
  // Check if this is a "new model" scenario based on modelId
  const isNewModel = modelId === 'new-model' || modelId.startsWith('new-');
  
  // For new models, use empty nodes/edges
  const modelData = isNewModel ? {
    id: modelId,
    version: '1.0.0',
    metadata: {
      label: 'New Workflow',
      description: ''
    },
    nodes: [],
    edges: []
  } : {
    id: modelId,
    version: '1.0.0',
    metadata: {
      label: 'Test Workflow',
      description: 'Test workflow for E2E testing'
    },
    nodes: [
      {
        id: 'event_1',
        type: 'start',
        position: { x: 100, y: 200 },
        label: 'On Entity Insert',
        plugin: 'content_entity:insert',
        configuration: {}
      },
      {
        id: 'action_1',
        type: 'element',
        position: { x: 400, y: 200 },
        label: 'Save Entity',
        plugin: 'entity:save',
        configuration: {}
      },
      {
        id: 'action_2',
        type: 'element',
        position: { x: 700, y: 200 },
        label: 'Send Email',
        plugin: 'email:send',
        configuration: {}
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
  
  return `
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Workflow Modeler - E2E Test</title>
  <link rel="stylesheet" href="/dist/modeler.bundle.css">
  <style>
    html, body {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
      overflow: hidden;
    }
    #workflow-modeler-react-root {
      width: 100%;
      height: 100%;
    }
  </style>
</head>
<body>
  <div id="workflow-modeler-react-root"></div>

  <!-- Mock Drupal globals -->
  <script>
    // Mock components for the modeler - using backend format with componentType (integer).
    // The frontend resolves the string 'type' at load time via the typeMap.
    // Each type needs >= 15 items so the search field is visible in quick-add popups.
    var mockComponents = [
      // Events (3 real + 12 fillers)
      { plugin: 'content_entity:insert', label: 'Content Entity Insert', componentType: 1, provider: 'eca_content' },
      { plugin: 'cron', label: 'Cron Run', componentType: 1, provider: 'eca_base' },
      { plugin: 'user:login', label: 'User Login', componentType: 1, provider: 'eca_user' },
    ].concat(Array.from({ length: 12 }, function(_, i) {
      return { plugin: 'event:filler_' + i, label: 'start Filler ' + i, componentType: 1, provider: 'eca_test' };
    })).concat([
      // Actions (4 real + 11 fillers)
      { plugin: 'entity:save', label: 'Save Entity', componentType: 4, provider: 'eca_content' },
      { plugin: 'message:set', label: 'Set Message', componentType: 4, provider: 'eca_base' },
      { plugin: 'email:send', label: 'Send Email', componentType: 4, provider: 'eca_base' },
      { plugin: 'entity:delete', label: 'Delete Entity', componentType: 4, provider: 'eca_content' },
    ]).concat(Array.from({ length: 11 }, function(_, i) {
      return { plugin: 'action:filler_' + i, label: 'element Filler ' + i, componentType: 4, provider: 'eca_test' };
    })).concat([
      // Conditions (2 real + 13 fillers)
      { plugin: 'entity:is_new', label: 'Entity is New', componentType: 5, provider: 'eca_content' },
      { plugin: 'user:has_role', label: 'User Has Role', componentType: 5, provider: 'eca_user' },
    ]).concat(Array.from({ length: 13 }, function(_, i) {
      return { plugin: 'condition:filler_' + i, label: 'link Filler ' + i, componentType: 5, provider: 'eca_test' };
    })).concat([
      // Gateways
      { plugin: 'gateway', label: 'Gateway', componentType: 6, provider: 'modeler', description: 'Gateway for conditional branching' }
    ]);

    // Mock model data with nodes and edges
    var mockModelData = JSON.stringify(${JSON.stringify(modelData)});

    window.drupalSettings = {
      path: {
        baseUrl: '/',
        pathPrefix: ''
      },
      modeler: {
        components: mockComponents,
        typeMap: { 1: 'start', 2: 'subprocess', 3: 'swimlane', 4: 'element', 5: 'link', 6: 'gateway', 7: 'annotation' },
        modelData: mockModelData,
        // The metadata dialog renders the form the backend converts from the
        // model owner's Drupal form; without it there is nothing to render.
        metadataForm: ${JSON.stringify(isNewModel ? newModelMetadataForm : metadataForm)}
      },
      modeler_api: {
        isNew: ${isNewModel},
        token_url: '/modeler-api/tokens',
        save_url: '/modeler-api/model',
        config_url: '/modeler-api/config',
        replay_url: '/modeler-api/replay',
        component_labels: {
          start: 'Event',
          element: 'Action',
          link: 'Condition'
        },
        component_labels_plural: {
          start: 'Events',
          element: 'Actions',
          link: 'Conditions',
          gateway: 'Gateways'
        },
        permissions: {
          'create template': true,
          'edit metadata': true,
          'edit template': true,
          'replay': true,
          'switch context': true,
          'test': true
        }
      }
    };

    window.Drupal = {
      behaviors: {},
      attachBehaviors: function(context, settings) {
        var self = this;
        Object.keys(this.behaviors).forEach(function(key) {
          if (typeof self.behaviors[key].attach === 'function') {
            self.behaviors[key].attach(context, settings);
          }
        });
      },
      t: function(str, args) {
        if (args) {
          Object.keys(args).forEach(function(key) {
            str = str.replace(key, args[key]);
          });
        }
        return str;
      },
      checkPlain: function(str) { return str; },
      /**
       * Mock Drupal.ajax — performs a real fetch() to the test server's
       * save endpoint so that E2E save tests can intercept the request.
       */
      ajax: function(settings) {
        var ajaxObject = {
          options: settings,
          success: null,
          error: null,
          execute: function() {
            var self = this;
            var url = settings.url;
            var data = settings.submit || '';

            // Call beforeSend to set up headers
            var headers = { 'Content-Type': 'application/json;charset=UTF-8' };
            if (settings.beforeSend) {
              var mockXhr = {
                _headers: headers,
                setRequestHeader: function(key, value) {
                  this._headers[key] = value;
                }
              };
              settings.beforeSend(mockXhr);
              headers = mockXhr._headers;
            }

            fetch(url, {
              method: 'POST',
              headers: headers,
              body: data
            })
            .then(function(response) { return response.json(); })
            .then(function(responseData) {
              // Wrap in Drupal AJAX command format
              var commands = [{ command: 'settings', settings: {}, merge: true }];
              if (self.success) {
                self.success(commands, 'success');
              }
            })
            .catch(function(err) {
              if (self.error) {
                self.error({ status: 500 }, settings.url, err.message);
              }
            });
          }
        };
        return ajaxObject;
      }
    };

    // Expose jQuery-like interface with a working $.get() for CSRF token fetches
    window.jQuery = window.jQuery || function(selector) {
      return {
        once: function() { return this; },
        each: function() { return this; }
      };
    };
    window.jQuery.get = function(url) {
      var callbacks = { _done: null, _fail: null };
      var chainable = {
        done: function(cb) { callbacks._done = cb; return chainable; },
        fail: function(cb) { callbacks._fail = cb; return chainable; }
      };
      fetch(url)
        .then(function(response) { return response.text(); })
        .then(function(text) {
          if (callbacks._done) callbacks._done(text);
        })
        .catch(function(err) {
          if (callbacks._fail) callbacks._fail({ status: 0 }, 'error', err.message);
        });
      return chainable;
    };
    window.$ = window.jQuery;
  </script>

  <!-- Application bundle (React is bundled in) -->
  <script src="/dist/modeler.bundle.js"></script>

  <!-- Initialize the app -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      Drupal.attachBehaviors(document, drupalSettings);
    });
  </script>
</body>
</html>
`;
}

// Mock data for API endpoints — flat component array using internal type keys
const mockApiComponents = [
  { id: 'content_entity:insert', label: 'Content Entity Insert', type: 'start', description: 'Triggered when a content entity is inserted', fields: [] },
  { id: 'cron', label: 'Cron Run', type: 'start', description: 'Triggered on cron execution', fields: [] },
  { id: 'entity:save', label: 'Save Entity', type: 'element', description: 'Saves the current entity', fields: [] },
  { id: 'message:set', label: 'Set Message', type: 'element', description: 'Displays a message to the user', fields: [] },
  { id: 'entity:is_new', label: 'Entity is New', type: 'link', description: 'Checks if entity is new', fields: [] },
  { id: 'gateway:exclusive', label: 'Exclusive Gateway', type: 'gateway', description: 'Exclusive (XOR) gateway', fields: [] },
];

const mockModel = {
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

// Helper to send JSON response
function sendJson(res: ServerResponse, data: unknown, status = 200) {
  res.writeHead(status, {
    'Content-Type': 'application/json',
    'Access-Control-Allow-Origin': '*'
  });
  res.end(JSON.stringify(data));
}

// Request handler
function handleRequest(req: IncomingMessage, res: ServerResponse) {
  const url = req.url || '/';
  console.log(`${req.method} ${url}`);

  // Handle CORS preflight
  if (req.method === 'OPTIONS') {
    res.writeHead(204, {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type'
    });
    res.end();
    return;
  }

  // API: Components endpoint
  if (url.startsWith('/modeler-api/components')) {
    sendJson(res, mockApiComponents);
    return;
  }

  // API: Model endpoint (GET)
  if (url.startsWith('/modeler-api/model/') && req.method === 'GET') {
    sendJson(res, mockModel);
    return;
  }

  // API: Model save endpoint (POST/PUT)
  if (url === '/modeler-api/model' && (req.method === 'POST' || req.method === 'PUT')) {
    let body = '';
    req.on('data', chunk => body += chunk);
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        sendJson(res, { ...data, saved: true });
      } catch {
        sendJson(res, { error: 'Invalid JSON' }, 400);
      }
    });
    return;
  }

  // API: Tokens endpoint
  if (url.startsWith('/modeler-api/tokens')) {
    sendJson(res, [
      { token: '[node:title]', label: 'Node Title' },
      { token: '[current-user:name]', label: 'Current User Name' }
    ]);
    return;
  }

  // API: Config endpoint
  if (url.startsWith('/modeler-api/config/')) {
    const pluginId = url.split('/').pop()?.split('?')[0] || 'unknown';
    sendJson(res, {
      pluginId,
      form: '<div class="form-wrapper"><input type="text" name="field1" /></div>',
      values: {}
    });
    return;
  }

  // API: Test endpoint (POST) — initiate test or poll for results
  if (url.startsWith('/modeler-api/test') && req.method === 'POST') {
    let body = '';
    req.on('data', chunk => body += chunk);
    req.on('end', () => {
      try {
        const data = JSON.parse(body);
        if (data.jobId && data.cancelled) {
          // Cancellation notification — acknowledge it
          sendJson(res, { status: 'cancelled' });
        } else if (data.jobId) {
          // Poll request — return replay data immediately
          sendJson(res, [
            { id: 'event_1', type: 'event', data: { label: 'On Entity Insert' } },
            { id: 'action_1', type: 'action', successorId: 'action_1', data: { label: 'Save Entity', entity: { title: 'Test Result', type: 'node' } } },
          ]);
        } else {
          // Initial request — return job ID
          sendJson(res, { jobId: 'test-job-123' });
        }
      } catch {
        sendJson(res, { error: 'Invalid JSON' }, 400);
      }
    });
    return;
  }

  // API: Replay data endpoint (POST) — returns ReplayEntry[]
  if (url.startsWith('/modeler-api/replay') && req.method === 'POST') {
    let body = '';
    req.on('data', chunk => body += chunk);
    req.on('end', () => {
      sendJson(res, [
        {
          model_id: 'test-model-1',
          component_id: 'event_1',
          history: [
            { id: 'event_1', type: 'started', data: { label: 'On Entity Insert' } },
            { id: 'event_1', type: 'add successor', successorId: 'action_1', conditionId: 'eca_entity_is_new_10j5tps', data: {} },
            { id: 'action_1', type: 'execute', data: { label: 'Save Entity', entity: { title: 'Test Article', type: 'node' } } },
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
            { id: 'event_1', type: 'started', data: { label: 'On Entity Insert' } },
          ],
          timestamp: '2026-02-08T14:15:30Z',
          user: { name: 'editor', uid: 5 },
          ip: '10.0.0.42',
          url: '/admin/content',
        },
      ]);
    });
    return;
  }

  // Serve static files from dist
  if (url.startsWith('/dist/')) {
    const filePath = join(DIST_DIR, url.replace('/dist/', ''));
    if (existsSync(filePath)) {
      const ext = extname(filePath);
      const contentType = mimeTypes[ext] || 'application/octet-stream';
      const content = readFileSync(filePath);
      res.writeHead(200, { 'Content-Type': contentType });
      res.end(content);
      return;
    }
  }

  // Modeler page routes
  if (url.startsWith('/modeler/')) {
    const modelId = url.split('/')[2] || 'test-model-1';
    const html = generateTestPage(modelId);
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(html);
    return;
  }

  // Root redirect to test model
  if (url === '/') {
    res.writeHead(302, { Location: '/modeler/test-model-1' });
    res.end();
    return;
  }

  // 404 for everything else
  res.writeHead(404, { 'Content-Type': 'text/plain' });
  res.end('Not Found');
}

// Start server
const server = createServer(handleRequest);

server.listen(PORT, () => {
  console.log(`E2E Test Server running at http://localhost:${PORT}`);
  console.log('Press Ctrl+C to stop');
});

// Handle shutdown
process.on('SIGINT', () => {
  console.log('\nShutting down...');
  server.close();
  process.exit(0);
});
