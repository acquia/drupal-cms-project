const mockRender = jest.fn();
const mockCreateRoot = jest.fn(() => ({
  render: mockRender,
  unmount: jest.fn(),
}));

jest.mock('react-dom/client', () => ({
  createRoot: mockCreateRoot,
}));

jest.mock('../App', () => ({
  __esModule: true,
  default: jest.fn(() => null),
}));

jest.mock('../styles/modeler.css', () => ({}));
jest.mock('reactflow/dist/style.css', () => ({}));

jest.mock('../plugins/pluginRegistry', () => ({
  registerPanel: jest.fn(),
  unregisterPanel: jest.fn(),
  registerWidget: jest.fn(),
  unregisterWidget: jest.fn(),
  onReady: jest.fn(),
}));

describe('Drupal modeler entry point', () => {
  beforeEach(() => {
    jest.resetModules();
    jest.clearAllMocks();
    document.body.innerHTML = '';
    delete window.WorkflowModeler;
  });

  it('reattaches all Drupal behaviors when loaded after the HTMX attach pass', () => {
    const dependencyAttach = jest.fn();
    const settings = { modeler: { modelId: 'test-model' } };

    global.drupalSettings = settings;
    global.Drupal = {
      behaviors: {
        modelerDependency: {
          attach: dependencyAttach,
        },
      },
      attachBehaviors: jest.fn((context, behaviorSettings) => {
        Object.values(global.Drupal.behaviors).forEach((behavior) => {
          behavior.attach?.(context, behaviorSettings);
        });
      }),
    };

    document.body.innerHTML = '<div id="workflow-modeler-react-root"></div>';

    jest.isolateModules(() => {
      require('../index');
    });

    expect(global.Drupal.attachBehaviors).toHaveBeenCalledWith(
      document,
      settings,
    );
    expect(dependencyAttach).toHaveBeenCalledWith(document, settings);
    expect(mockCreateRoot).toHaveBeenCalledWith(
      document.querySelector('#workflow-modeler-react-root'),
    );
  });
});
