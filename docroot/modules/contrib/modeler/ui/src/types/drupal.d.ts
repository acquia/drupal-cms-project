/**
 * TypeScript declarations for Drupal JavaScript API
 */

interface DrupalTranslationOptions {
  context?: string;
  [key: string]: string | number | undefined;
}

/**
 * Drupal Message messenger interface
 */
interface DrupalMessageMessenger {
  add(message: string, options?: { type?: 'status' | 'warning' | 'error' }): void;
  clear(): void;
}

/**
 * Drupal Message constructor
 */
interface DrupalMessageConstructor {
  new(): DrupalMessageMessenger;
}

interface DrupalInterface {
  /**
   * Translates a string using Drupal's translation system.
   * @param str - The string to translate
   * @param args - Optional placeholder replacements (e.g., { '@name': 'value' })
   * @param options - Optional options including context
   */
  t(str: string, args?: Record<string, string | number>, options?: DrupalTranslationOptions): string;

  /**
   * Formats a plural string.
   */
  formatPlural(count: number, singular: string, plural: string, args?: Record<string, string | number>, options?: DrupalTranslationOptions): string;

  /**
   * Drupal behaviors system
   */
  behaviors: Record<string, {
    attach?: (context: HTMLElement | Document, settings: any) => void;
    detach?: (context: HTMLElement | Document, settings: any, trigger: string) => void;
  }>;

  /**
   * Theme functions
   */
  theme: Record<string, (...args: any[]) => string>;

  /**
   * URL handling
   */
  url: (path: string) => string;

  /**
   * Check if a string needs escaping
   */
  checkPlain: (str: string) => string;

  /**
   * Message system for displaying status messages
   */
  Message?: DrupalMessageConstructor;
}

declare global {
  interface Window {
    Drupal: DrupalInterface;
    /** Global plugin API exposed by the Workflow Modeler bundle. */
    WorkflowModeler?: import('./pluginApi').WorkflowModelerGlobal;
  }
  const Drupal: DrupalInterface;
}

export {};
