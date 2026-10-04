/**
 * Tests for edgeTypeUtils
 */

import { getEdgeType } from '../edgeTypeUtils';

describe('edgeTypeUtils', () => {
  describe('getEdgeType', () => {
    it('should return "default" for null data', () => {
      expect(getEdgeType(null)).toBe('default');
    });

    it('should return "default" for undefined data', () => {
      expect(getEdgeType(undefined)).toBe('default');
    });

    it('should return "default" for empty data', () => {
      expect(getEdgeType({})).toBe('default');
    });

    it('should return "condition" when data has condition', () => {
      expect(getEdgeType({ condition: 'some-plugin' })).toBe('condition');
    });

    it('should return "condition" when data has conditionLabel', () => {
      expect(getEdgeType({ conditionLabel: 'My Condition' })).toBe('condition');
    });

    it('should return "condition" when data has conditionConfiguration', () => {
      expect(getEdgeType({ conditionConfiguration: { key: 'value' } })).toBe('condition');
    });

    it('should return "default" when data has only annotation (no condition)', () => {
      // Annotations belong to conditions, not edges. An edge with only an annotation is 'default'.
      expect(getEdgeType({ annotation: 'Some note' })).toBe('default');
    });

    it('should return "condition" when both condition and annotation exist', () => {
      expect(getEdgeType({ condition: 'plugin', annotation: 'note' })).toBe('condition');
    });

    it('should return "default" when all condition fields are null/empty', () => {
      expect(getEdgeType({ condition: null, conditionLabel: null, conditionConfiguration: null, annotation: null })).toBe('default');
    });

    it('should return "default" when condition is empty string', () => {
      expect(getEdgeType({ condition: '' })).toBe('default');
    });

    it('should return "default" when conditionLabel is empty string', () => {
      expect(getEdgeType({ conditionLabel: '' })).toBe('default');
    });

    it('should return "default" when conditionConfiguration is an empty object', () => {
      expect(getEdgeType({ conditionConfiguration: {} })).toBe('default');
    });

    it('should return "default" when conditionConfiguration is null', () => {
      expect(getEdgeType({ conditionConfiguration: null })).toBe('default');
    });

    it('should return "default" when all condition fields are empty strings/objects', () => {
      // Regression: backend sends condition: "", conditionLabel: "", conditionConfiguration: {}
      // for edges without conditions. All of these must be treated as "no condition".
      expect(getEdgeType({ condition: '', conditionLabel: '', conditionConfiguration: {} })).toBe('default');
    });

    it('should return "default" when all condition fields are null', () => {
      expect(getEdgeType({ condition: null, conditionLabel: null, conditionConfiguration: null })).toBe('default');
    });
  });
});
