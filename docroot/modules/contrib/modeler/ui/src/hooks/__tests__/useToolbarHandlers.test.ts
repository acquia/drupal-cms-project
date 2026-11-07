/**
 * Tests for useToolbarHandlers hook
 */

import { isAtMinZoom, isAtMaxZoom } from '../useToolbarHandlers';

describe('useToolbarHandlers', () => {
  describe('isAtMinZoom', () => {
    it('should return true when zoom is exactly at minimum', () => {
      expect(isAtMinZoom(0.1)).toBe(true);
    });

    it('should return true when zoom is below minimum', () => {
      expect(isAtMinZoom(0.05)).toBe(true);
    });

    it('should return true when zoom is within tolerance of minimum', () => {
      expect(isAtMinZoom(0.11)).toBe(true);
    });

    it('should return false when zoom is above minimum + tolerance', () => {
      expect(isAtMinZoom(0.2)).toBe(false);
    });

    it('should return false for normal zoom level', () => {
      expect(isAtMinZoom(1)).toBe(false);
    });
  });

  describe('isAtMaxZoom', () => {
    it('should return true when zoom is exactly at maximum', () => {
      expect(isAtMaxZoom(4)).toBe(true);
    });

    it('should return true when zoom is above maximum', () => {
      expect(isAtMaxZoom(5)).toBe(true);
    });

    it('should return true when zoom is within tolerance of maximum', () => {
      expect(isAtMaxZoom(3.99)).toBe(true);
    });

    it('should return false when zoom is below maximum - tolerance', () => {
      expect(isAtMaxZoom(3.9)).toBe(false);
    });

    it('should return false for normal zoom level', () => {
      expect(isAtMaxZoom(1)).toBe(false);
    });
  });
});
