import React from 'react';
import { render, screen } from '@testing-library/react';
import NodePropertiesPanel from '../NodePropertiesPanel';

// Mock ConfigurationForm - capture onChange for testing
let capturedConfigFormOnChange: ((config: Record<string, any>) => void) | null = null;
jest.mock('../ConfigurationForm', () => {
  return function MockConfigurationForm(props: any) {
    capturedConfigFormOnChange = props.onChange;
    return <div data-testid="configuration-form" data-disabled={props.disabled} />;
  };
});

// Mock react-icons (none currently used, but keep mock to prevent import errors)
jest.mock('react-icons/fi', () => ({}));

describe('NodePropertiesPanel', () => {
  const mockOnConfigurationChange = jest.fn();
  const mockOnNodeUpdate = jest.fn();

  const createMockField = (value: string = '') => ({
    value,
    setValue: jest.fn(),
    onChange: jest.fn(),
    onBlur: jest.fn(),
    flush: jest.fn(),
  });

  const defaultNode = {
    id: 'node-1',
    type: 'element',
    position: { x: 0, y: 0 },
    data: {
      label: 'Test Node',
      plugin: 'test.action.test',
      description: 'A test node',
      annotation: 'Some note',
      configuration: { field1: 'value1' },
    },
  };

  const defaultProps = {
    node: defaultNode as any,
    configurationForm: null,
    onConfigurationChange: mockOnConfigurationChange,
    onNodeUpdate: mockOnNodeUpdate,
    isLocked: false,
    nodeLabelField: createMockField('Test Node'),
    nodeAnnotationField: createMockField('Some note'),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('label input', () => {
    it('should render label input with node label', () => {
      render(<NodePropertiesPanel {...defaultProps} />);
      const input = screen.getByLabelText('Label');
      expect(input).toBeTruthy();
      expect((input as HTMLInputElement).value).toBe('Test Node');
    });

    it('should disable label input when locked', () => {
      render(<NodePropertiesPanel {...defaultProps} isLocked={true} />);
      const input = screen.getByLabelText('Label');
      expect(input).toBeDisabled();
    });

  });

  describe('description', () => {
    it('should render node description', () => {
      render(<NodePropertiesPanel {...defaultProps} />);
      expect(screen.getByText('A test node')).toBeTruthy();
    });

    it('should not render description when not present', () => {
      const nodeWithoutDesc = { ...defaultNode, data: { ...defaultNode.data, description: undefined } };
      render(<NodePropertiesPanel {...defaultProps} node={nodeWithoutDesc as any} />);
      expect(screen.queryByText('A test node')).toBeNull();
    });
  });

  describe('configuration form', () => {
    it('should render configuration form when provided', () => {
      render(<NodePropertiesPanel {...defaultProps} configurationForm={{ fields: [] }} />);
      expect(screen.getByTestId('configuration-form')).toBeTruthy();
    });

    it('should not render configuration form when null', () => {
      render(<NodePropertiesPanel {...defaultProps} configurationForm={null} />);
      expect(screen.queryByTestId('configuration-form')).toBeNull();
    });

    it('should call onConfigurationChange when form changes', () => {
      capturedConfigFormOnChange = null;
      render(<NodePropertiesPanel {...defaultProps} configurationForm={{ fields: [] }} />);

      // Trigger the captured onChange
      capturedConfigFormOnChange!({ field1: 'newValue' });

      expect(mockOnConfigurationChange).toHaveBeenCalledWith('node-1', { field1: 'newValue' });
    });

    it('should not call onConfigurationChange when callback is not provided', () => {
      capturedConfigFormOnChange = null;
      render(
        <NodePropertiesPanel
          {...defaultProps}
          onConfigurationChange={undefined}
          configurationForm={{ fields: [] }}
        />
      );

      // Should not throw when callback is not provided
      expect(() => capturedConfigFormOnChange?.({ field1: 'newValue' })).not.toThrow();
      expect(mockOnConfigurationChange).not.toHaveBeenCalled();
    });

  });

  describe('annotation field', () => {
    it('should render annotation textarea', () => {
      render(<NodePropertiesPanel {...defaultProps} />);
      const annotation = screen.getByLabelText('Annotation');
      expect(annotation).toBeTruthy();
      expect(annotation.tagName).toBe('TEXTAREA');
    });

    it('should show annotation value', () => {
      render(<NodePropertiesPanel {...defaultProps} />);
      const annotation = screen.getByLabelText('Annotation');
      expect(annotation).toHaveValue('Some note');
    });

    it('should disable annotation when locked', () => {
      render(<NodePropertiesPanel {...defaultProps} isLocked={true} />);
      const annotation = screen.getByLabelText('Annotation');
      expect(annotation).toBeDisabled();
    });
  });


});
