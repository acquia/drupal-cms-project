import { encodeMetadata, decodeMetadata } from '../metadataCodec';
import type { MetadataFormData } from '../metadataCodec';

describe('metadataCodec', () => {
  const configActions = [
    { config: 'system.site', actions: { simple_config_update: { slogan: 'Hi' } } },
  ];

  const stored: MetadataFormData = {
    label: 'Test Model',
    version: '2.0.0',
    executable: true,
    template: false,
    storage: 'separate',
    documentation: 'Some docs',
    tags: ['one', 'two'],
    changelog: 'Initial',
    summary: 'A one-line description.',
    recipes: ['core/recipes/article_tags', 'core/recipes/page_content_type'],
    export_config: ['system.site'],
    modules: ['node', 'user'],
    config_actions: configActions,
  };

  describe('encodeMetadata', () => {
    it('spells lists the way the form shows them', () => {
      const values = encodeMetadata(stored, false, 'test_model');

      expect(values.tags).toBe('one, two');
      expect(values.recipes).toBe('core/recipes/article_tags\ncore/recipes/page_content_type');
      expect(values.export_config).toBe('system.site');
      expect(values.modules).toBe('node\nuser');
      expect(values.config_actions).toContain('config: system.site');
    });

    it('passes strings and booleans through unchanged', () => {
      const values = encodeMetadata(stored, false, 'test_model');

      expect(values.label).toBe('Test Model');
      expect(values.executable).toBe(true);
      expect(values.template).toBe(false);
      expect(values.storage).toBe('separate');
    });

    it('carries the machine name of an existing model', () => {
      expect(encodeMetadata(stored, false, 'test_model').model_id).toBe('test_model');
    });

    it('leaves the machine name empty for a new model', () => {
      expect(encodeMetadata(stored, true, 'ignored_id').model_id).toBe('');
    });

    it('shows an empty config actions list as an empty editor', () => {
      expect(encodeMetadata({ config_actions: [] }, false).config_actions).toBe('');
    });

    it('omits keys the metadata does not carry', () => {
      const values = encodeMetadata({ label: 'Only a label' }, false, 'only_a_label');

      expect(Object.keys(values).sort()).toEqual(['label', 'model_id']);
    });
  });

  describe('decodeMetadata', () => {
    it('round-trips every stored value', () => {
      expect(decodeMetadata(encodeMetadata(stored, false, 'test_model'), stored)).toEqual(stored);
    });

    it('trims entries and drops blank ones', () => {
      const decoded = decodeMetadata({
        tags: '  one  ,  two  , , ',
        recipes: 'core/recipes/article_tags\n\n  core/recipes/page_content_type  \n',
        modules: '\n node \n\n',
      });

      expect(decoded.tags).toEqual(['one', 'two']);
      expect(decoded.recipes).toEqual(['core/recipes/article_tags', 'core/recipes/page_content_type']);
      expect(decoded.modules).toEqual(['node']);
    });

    it('turns a cleared list into an empty array', () => {
      const decoded = decodeMetadata({ tags: '', recipes: '', export_config: '', modules: '' });

      expect(decoded.tags).toEqual([]);
      expect(decoded.recipes).toEqual([]);
      expect(decoded.export_config).toEqual([]);
      expect(decoded.modules).toEqual([]);
    });

    it('turns a cleared config actions editor into an empty list', () => {
      expect(decodeMetadata({ config_actions: '' }, stored).config_actions).toEqual([]);
    });

    it('keeps the previous config actions while the YAML does not parse', () => {
      expect(decodeMetadata({ config_actions: '- config: [unclosed' }, stored).config_actions)
        .toEqual(configActions);
    });

    it('keeps the previous config actions when the YAML is not a list', () => {
      expect(decodeMetadata({ config_actions: 'config: system.site' }, stored).config_actions)
        .toEqual(configActions);
    });

    it('drops the machine name, which travels as the payload id', () => {
      expect(decodeMetadata({ model_id: 'test_model', label: 'Test' })).toEqual({ label: 'Test' });
    });

    it('omits keys the form did not carry', () => {
      const decoded = decodeMetadata({ label: 'Only a label' }, stored);

      expect(Object.keys(decoded)).toEqual(['label']);
      expect('summary' in decoded).toBe(false);
    });
  });
});
