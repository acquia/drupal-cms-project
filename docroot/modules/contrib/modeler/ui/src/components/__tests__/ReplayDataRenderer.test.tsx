/**
 * Tests for ReplayDataRenderer component
 */

import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { ReplayDataRenderer, StepDataContainer } from '../ReplayDataRenderer';

// Mock react-icons
jest.mock('react-icons/fi', () => ({
  FiChevronDown: () => <span data-testid="fi-chevron-down" />,
  FiChevronRight: () => <span data-testid="fi-chevron-right" />,
}));

describe('ReplayDataRenderer', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('primitive values', () => {
    it('should render null value', () => {
      render(<ReplayDataRenderer data={null} />);
      expect(screen.getByText('null')).toBeInTheDocument();
    });

    it('should render undefined value', () => {
      render(<ReplayDataRenderer data={undefined} />);
      expect(screen.getByText('undefined')).toBeInTheDocument();
    });

    it('should render boolean true', () => {
      render(<ReplayDataRenderer data={true} />);
      expect(screen.getByText('true')).toBeInTheDocument();
    });

    it('should render boolean false', () => {
      render(<ReplayDataRenderer data={false} />);
      expect(screen.getByText('false')).toBeInTheDocument();
    });

    it('should render number', () => {
      render(<ReplayDataRenderer data={42} />);
      expect(screen.getByText('42')).toBeInTheDocument();
    });

    it('should render string without quotes', () => {
      render(<ReplayDataRenderer data="hello" />);
      expect(screen.getByText('hello')).toBeInTheDocument();
    });

    it('should display long strings without truncation', () => {
      const longString = 'a'.repeat(150);
      render(<ReplayDataRenderer data={longString} />);
      expect(screen.getByText('a'.repeat(150))).toBeInTheDocument();
    });
  });

  describe('token data structure', () => {
    it('should render token data with label', () => {
      const tokenData = { label: 'Test Token' };
      render(<ReplayDataRenderer data={tokenData} />);
      expect(screen.getByText('Test Token')).toBeInTheDocument();
    });

    it('should render token data with label and value', () => {
      const tokenData = { label: 'My Value', value: 'test value' };
      render(<ReplayDataRenderer data={tokenData} />);
      expect(screen.getByText('My Value')).toBeInTheDocument();
      expect(screen.getByText('test value')).toBeInTheDocument();
    });

  });

  describe('collapsible sections', () => {
    it('should render collapsed array initially', () => {
      const data = [1, 2, 3];
      render(<ReplayDataRenderer data={data} />);
      expect(screen.getByText(/Array \(3\)/)).toBeInTheDocument();
      expect(screen.queryByText('[0]:')).not.toBeInTheDocument();
    });

    it('should expand array on click', () => {
      const data = [1, 2, 3];
      render(<ReplayDataRenderer data={data} />);
      
      fireEvent.click(screen.getByText(/Array \(3\)/));
      
      expect(screen.getByText('[0]:')).toBeInTheDocument();
      expect(screen.getByText('[1]:')).toBeInTheDocument();
      expect(screen.getByText('[2]:')).toBeInTheDocument();
    });

    it('should render collapsed object initially', () => {
      const data = { a: 1, b: 2 };
      render(<ReplayDataRenderer data={data} />);
      expect(screen.getByText(/Object \(2\)/)).toBeInTheDocument();
      expect(screen.queryByText('a:')).not.toBeInTheDocument();
    });

    it('should expand object on click', () => {
      const data = { a: 1, b: 2 };
      render(<ReplayDataRenderer data={data} />);
      
      fireEvent.click(screen.getByText(/Object \(2\)/));
      
      expect(screen.getByText('a:')).toBeInTheDocument();
      expect(screen.getByText('b:')).toBeInTheDocument();
    });

    it('should collapse expanded section on second click', () => {
      const data = [1, 2, 3];
      render(<ReplayDataRenderer data={data} />);
      
      // Expand
      fireEvent.click(screen.getByText(/Array \(3\)/));
      expect(screen.getByText('[0]:')).toBeInTheDocument();
      
      // Collapse
      fireEvent.click(screen.getByText(/Array \(3\)/));
      expect(screen.queryByText('[0]:')).not.toBeInTheDocument();
    });
  });

  describe('nested token data', () => {
    it('should render token data with nested data property', () => {
      const tokenData = {
        label: 'Parent',
        data: {
          child1: { label: 'Child 1', value: 'value1' },
          child2: { label: 'Child 2', value: 'value2' },
        }
      };
      render(<ReplayDataRenderer data={tokenData} />);
      
      // Parent should be visible
      expect(screen.getByText('Parent')).toBeInTheDocument();
      expect(screen.getByText('(2)')).toBeInTheDocument(); // Item count
      
      // Children should be hidden initially
      expect(screen.queryByText('Child 1')).not.toBeInTheDocument();
    });

    it('should expand nested token data on click', () => {
      const tokenData = {
        label: 'Parent',
        data: {
          child1: { label: 'Child 1', value: 'value1' },
        }
      };
      render(<ReplayDataRenderer data={tokenData} />);
      
      fireEvent.click(screen.getByText('Parent'));
      
      expect(screen.getByText('Child 1')).toBeInTheDocument();
      expect(screen.getByText('value1')).toBeInTheDocument();
    });
  });

  describe('empty collections', () => {
    it('should render empty array as []', () => {
      render(<ReplayDataRenderer data={[]} />);
      expect(screen.getByText('[]')).toBeInTheDocument();
    });

    it('should render empty object as {}', () => {
      render(<ReplayDataRenderer data={{}} />);
      expect(screen.getByText('{}')).toBeInTheDocument();
    });
  });

  describe('large collections', () => {
    it('should display all items in large arrays without truncation', () => {
      const data = Array.from({ length: 15 }, (_, i) => i);
      render(<ReplayDataRenderer data={data} />);
      
      fireEvent.click(screen.getByText(/Array \(15\)/));
      
      // All 15 items should be visible, no truncation message
      expect(screen.getByText('[14]:')).toBeInTheDocument();
      expect(screen.queryByText(/\.\.\.and .* more/)).toBeNull();
    });

    it('should display all entries in large objects without truncation', () => {
      const data: Record<string, number> = {};
      for (let i = 0; i < 15; i++) {
        data[`key${i}`] = i;
      }
      render(<ReplayDataRenderer data={data} />);
      
      fireEvent.click(screen.getByText(/Object \(15\)/));
      
      // All 15 keys should be visible, no truncation message
      expect(screen.getByText('key14:')).toBeInTheDocument();
      expect(screen.queryByText(/\.\.\.and .* more/)).toBeNull();
    });
  });
});

describe('StepDataContainer', () => {
  it('should render step data entries', () => {
    const stepData = {
      token1: { label: 'Token 1', value: 'value1' },
      token2: { label: 'Token 2', value: 'value2' },
    };
    render(<StepDataContainer stepData={stepData} />);
    
    expect(screen.getByText('Token 1')).toBeInTheDocument();
    expect(screen.getByText('Token 2')).toBeInTheDocument();
  });

  it('should add label from key when not present', () => {
    const stepData = {
      myKey: { value: 'test value' },
    };
    render(<StepDataContainer stepData={stepData} />);
    
    expect(screen.getByText('myKey')).toBeInTheDocument();
    expect(screen.getByText('test value')).toBeInTheDocument();
  });

  it('should handle primitive values', () => {
    const stepData = {
      primitiveValue: 42,
    };
    render(<StepDataContainer stepData={stepData} />);
    
    expect(screen.getByText('primitiveValue')).toBeInTheDocument();
    expect(screen.getByText('42')).toBeInTheDocument();
  });

  it('does NOT render a predicted badge for confirmed step data (default)', () => {
    render(<StepDataContainer stepData={{ token1: { label: 'Token 1', value: 'v' } }} />);
    expect(screen.queryByLabelText('Predicted token')).not.toBeInTheDocument();
  });

  it('renders a predicted badge + tooltip on each token when predicted is true (issue #3577207)', () => {
    render(
      <StepDataContainer
        predicted
        stepData={{
          token1: { label: 'Token 1', value: 'v1' },
          token2: { label: 'Token 2', value: 'v2' },
        }}
      />,
    );
    const badges = screen.getAllByLabelText('Predicted token');
    expect(badges).toHaveLength(2);
    badges.forEach((badge) => {
      expect(badge).toHaveTextContent('Predicted');
      expect(badge).toHaveAttribute(
        'title',
        'Predicted from the previous step; not yet confirmed by a test run.',
      );
    });
  });
});
