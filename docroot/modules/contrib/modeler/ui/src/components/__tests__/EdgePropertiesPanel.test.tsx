import React from 'react';
import { render, screen } from '@testing-library/react';
import EdgePropertiesPanel from '../EdgePropertiesPanel';

describe('EdgePropertiesPanel', () => {
  const mockOnEdgeUpdate = jest.fn();

  const createMockField = (value: string = '') => ({
    value,
    setValue: jest.fn(),
    onChange: jest.fn(),
    onBlur: jest.fn(),
    flush: jest.fn(),
  });

  const plainEdge = {
    id: 'edge-1',
    source: 'node-1',
    target: 'node-2',
    data: {
      annotation: 'A note about this edge',
    },
  };

  const defaultProps = {
    edge: plainEdge as any,
    onEdgeUpdate: mockOnEdgeUpdate,
    isLocked: false,
    edgeAnnotationField: createMockField('A note about this edge'),
  };

  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('annotation-only editor', () => {
    it('should render annotation textarea directly', () => {
      render(<EdgePropertiesPanel {...defaultProps} />);
      const annotation = screen.getByLabelText('Annotation');
      expect(annotation).toBeTruthy();
      expect(annotation.tagName).toBe('TEXTAREA');
    });

    it('should show annotation value', () => {
      render(<EdgePropertiesPanel {...defaultProps} />);
      const annotation = screen.getByLabelText('Annotation');
      expect(annotation).toHaveValue('A note about this edge');
    });

    it('should disable annotation when locked', () => {
      render(<EdgePropertiesPanel {...defaultProps} isLocked={true} />);
      const annotation = screen.getByLabelText('Annotation');
      expect(annotation).toBeDisabled();
    });

    it('should not render any condition label input', () => {
      render(<EdgePropertiesPanel {...defaultProps} />);
      expect(screen.queryByLabelText('Condition Label')).toBeNull();
    });

    it('should not render a remove-condition button', () => {
      render(<EdgePropertiesPanel {...defaultProps} />);
      expect(screen.queryByTitle('Remove condition')).toBeNull();
    });

    it('should not render a configuration form', () => {
      render(<EdgePropertiesPanel {...defaultProps} />);
      expect(screen.queryByTestId('configuration-form')).toBeNull();
    });
  });

});
