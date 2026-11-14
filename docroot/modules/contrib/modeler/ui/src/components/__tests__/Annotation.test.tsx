import React from 'react';
import { render, fireEvent } from '@testing-library/react';
import Annotation from '../Annotation';

describe('Annotation', () => {
  const mockAnnotation = 'Test annotation text';
  const mockOnToggle = jest.fn();

  beforeEach(() => {
    mockOnToggle.mockClear();
  });

  test('renders annotation icon', () => {
    const { container } = render(
      <Annotation
        annotation={mockAnnotation}
        isVisible={false}
        onToggle={mockOnToggle}
      />
    );

    const icon = container.querySelector('.annotation-icon');
    expect(icon).not.toBeNull();
  });

  test('renders annotation text only when visible', () => {
    const { container } = render(
      <Annotation
        annotation={mockAnnotation}
        isVisible={true}
        onToggle={mockOnToggle}
      />
    );

    const text = container.querySelector('.annotation-label');
    expect(text).not.toBeNull();
    expect(text?.textContent).toBe(mockAnnotation);
  });

  test('does not render annotation text when not visible', () => {
    const { container } = render(
      <Annotation
        annotation={mockAnnotation}
        isVisible={false}
        onToggle={mockOnToggle}
      />
    );

    const text = container.querySelector('.annotation-label');
    expect(text).toBeNull();
  });

  test('applies active class when visible', () => {
    const { container } = render(
      <Annotation
        annotation={mockAnnotation}
        isVisible={true}
        onToggle={mockOnToggle}
      />
    );

    const icon = container.querySelector('.annotation-icon');
    expect(icon?.className).toContain('active');
  });

  test('does not apply active class when not visible', () => {
    const { container } = render(
      <Annotation
        annotation={mockAnnotation}
        isVisible={false}
        onToggle={mockOnToggle}
      />
    );

    const icon = container.querySelector('.annotation-icon');
    expect(icon?.className).not.toContain('active');
  });

  test('applies offset class when offset is true', () => {
    const { container } = render(
      <Annotation
        annotation={mockAnnotation}
        isVisible={true}
        onToggle={mockOnToggle}
        offset={true}
      />
    );

    const annotationDiv = container.querySelector('.annotation-label');
    expect(annotationDiv?.className).toContain('annotation-label-offset');
  });

  test('does not apply offset class when offset is false', () => {
    const { container } = render(
      <Annotation
        annotation={mockAnnotation}
        isVisible={true}
        onToggle={mockOnToggle}
        offset={false}
      />
    );

    const annotationDiv = container.querySelector('.annotation-label');
    expect(annotationDiv?.className).not.toContain('annotation-label-offset');
  });

  test('does not apply offset class by default', () => {
    const { container } = render(
      <Annotation
        annotation={mockAnnotation}
        isVisible={true}
        onToggle={mockOnToggle}
      />
    );

    const annotationDiv = container.querySelector('.annotation-label');
    expect(annotationDiv?.className).not.toContain('annotation-label-offset');
  });

  test('calls onToggle when icon is clicked', () => {
    const { container } = render(
      <Annotation
        annotation={mockAnnotation}
        isVisible={false}
        onToggle={mockOnToggle}
      />
    );

    const icon = container.querySelector('.annotation-icon');
    if (icon) {
      fireEvent.click(icon);
    }

    expect(mockOnToggle).toHaveBeenCalledTimes(1);
  });

  test('stops event propagation', () => {
    const parentClickHandler = jest.fn();
    const { container } = render(
      <div onClick={parentClickHandler}>
        <Annotation
          annotation={mockAnnotation}
          isVisible={false}
          onToggle={mockOnToggle}
        />
      </div>
    );

    const icon = container.querySelector('.annotation-icon');
    if (icon) {
      fireEvent.click(icon);
    }

    expect(mockOnToggle).toHaveBeenCalledTimes(1);
    expect(parentClickHandler).not.toHaveBeenCalled();
  });

  test('works without onToggle callback', () => {
    const { container } = render(
      <Annotation
        annotation={mockAnnotation}
        isVisible={false}
      />
    );

    const icon = container.querySelector('.annotation-icon');

    expect(() => {
      if (icon) fireEvent.click(icon);
    }).not.toThrow();
  });

  test('displays annotation as title attribute', () => {
    const { container } = render(
      <Annotation
        annotation={mockAnnotation}
        isVisible={false}
        onToggle={mockOnToggle}
      />
    );

    const icon = container.querySelector('.annotation-icon');
    expect(icon?.getAttribute('title')).toBe(mockAnnotation);
  });

  test('uses default values for optional props', () => {
    const { container } = render(
      <Annotation annotation={mockAnnotation} />
    );

    const icon = container.querySelector('.annotation-icon');
    const text = container.querySelector('.annotation-label');

    expect(icon).not.toBeNull();
    expect(text).toBeNull(); // Not visible by default
    expect(icon?.className).not.toContain('active'); // Not active by default
  });
});
