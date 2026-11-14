/**
 * Dimension constants for the workflow modeler
 * All hardcoded dimensions, offsets, and numeric values should be defined here
 */

// ============ Panel Dimensions ============
export const PANEL_DIMENSIONS = {
  // Property Panel
  PROPERTY_PANEL: {
    DEFAULT_WIDTH: 320,
    MIN_WIDTH: 200,
    MAX_WIDTH: 600,
    COLLAPSED_WIDTH: 40,
  },
  
  // Replay Panel
  REPLAY_PANEL: {
    DEFAULT_WIDTH: 350,
    MIN_WIDTH: 300,
    MAX_WIDTH: 600,
    COLLAPSED_WIDTH: 40,
  },

  // Plugin Panel (external panels registered by other Drupal modules)
  PLUGIN_PANEL: {
    DEFAULT_WIDTH: 320,
    MIN_WIDTH: 200,
    MAX_WIDTH: 600,
    /** Smallest useful manually constrained floating panel height (px). */
    MIN_HEIGHT: 120,
    /**
     * Gap (px) kept between a floating panel and the edge of the modeler.
     * Doubles as the clamp margin, so a floating panel can never be dragged
     * fully out of reach, and as the bottom clearance in the inline
     * `max-height` that PluginPanelContainer applies to a floating panel.
     */
    FLOATING_MARGIN: 16,
    /** Keyboard nudge step (px) for the floating panel move handle. */
    FLOATING_NUDGE_STEP: 10,
    /** Keyboard nudge step (px) when Shift is held. */
    FLOATING_NUDGE_STEP_LARGE: 50,
    /** Keyboard resize step (px) for a floating panel's bottom handle. */
    FLOATING_RESIZE_STEP: 10,
    /** Keyboard resize step (px) when Shift is held. */
    FLOATING_RESIZE_STEP_LARGE: 50,
  },
} as const;

// ============ Node Dimensions ============
// Most node types share the same uniform card dimensions.
// Gateway nodes use a compact height matching condition cards.
export const NODE_DIMENSIONS = {
  /** Fixed card width for all node types */
  CARD_WIDTH: 180,
  /** Fixed card height for most node types */
  CARD_HEIGHT: 120,
  /** Compact height for gateway nodes (matches condition card height).
   *  Computed: 2px border + 26px header + 1px divider + 29px body + 2px border = 60px.
   *  The actual CSS uses height:auto; this constant is the fallback for layout math. */
  GATEWAY_HEIGHT: 60,
  /** @deprecated Use CARD_WIDTH */
  DEFAULT_WIDTH: 180,
  /** @deprecated Use CARD_HEIGHT */
  DEFAULT_HEIGHT: 120,
  START_NODE_WIDTH: 180,
  START_NODE_HEIGHT: 120,
  ELEMENT_NODE_WIDTH: 180,
  ELEMENT_NODE_HEIGHT: 120,
} as const;

// ============ Edge Styling ============
export const EDGE_STYLING = {
  ARROW_WIDTH: 12,
  ARROW_HEIGHT: 12,
  STROKE_WIDTH: 2,
  STROKE_WIDTH_HIGHLIGHTED: 3,
  STROKE_WIDTH_TRANSITION: 6,
  CONTROL_OFFSET: 40, // Offset for smoother curves
  BORDER_RADIUS: 20, // Rounded corners for step edges
  /**
   * Width (px) of the transparent edge-interaction hit path (issue #3585553
   * follow-on UX). React Flow's <BaseEdge> renders an invisible stroke of
   * `interactionWidth` for click/hover detection; DefaultEdge hand-rolls its
   * path, so it renders this hit path itself. 30px is a generous target that
   * makes edges easy to select when clicking NEAR (not exactly on) the curve,
   * while staying narrow enough not to overlap neighboring edges in dense
   * graphs (node spacing is 250px x / 84px y; parallel edges are offset by
   * CONTROL_OFFSET=40px, comfortably more than 30/2=15px of half-width either
   * side). It is the default when no `interactionWidth` prop is supplied.
   */
  INTERACTION_WIDTH: 30,
  /** Vertical offset for quick-add buttons above/below a condition card.
   *  Derived from: half card height (28) + half button (11) + gap (6) = 45. */
  CONDITION_BUTTON_OFFSET: 45,
  /** Horizontal and vertical offset to place edge-order badges northeast of a
   *  quick-add button.  The badge is shifted right by this amount and the
   *  badge's own -20px vertical lift puts it above the button center. */
  BADGE_NE_OFFSET: 20,
} as const;

// ============ Layout & Spacing ============
export const LAYOUT = {
  GRID_SIZE: 20,
  NODE_SPACING_X: 250,
  NODE_SPACING_Y: 84, // Compact gap between rows
  AUTO_LAYOUT_PADDING: 50,
  PASTE_OFFSET: 100, // Offset when pasting nodes
  DEFAULT_PASTE_OFFSET: 50,
  DEFAULT_POSITION_X: 100, // Default X position for new nodes
  DEFAULT_POSITION_Y: 100, // Default Y position for new nodes
  LAYOUT_START_X: 400, // Starting X position for layout algorithm
  LAYOUT_START_Y: 100, // Starting Y position for layout algorithm
} as const;

// ============ Viewport & Zoom ============
export const VIEWPORT = {
  DEFAULT_ZOOM: 1,
  MIN_ZOOM: 0.1,
  MAX_ZOOM: 4,
  /** Maximum zoom level for fitToNodes / fitView operations (prevents over-zooming). */
  FIT_MAX_ZOOM: 1.5,
  FIT_VIEW_PADDING: 0.1, // 10% padding
  AUTO_LAYOUT_PADDING: 0.2, // 20% padding
  TOP_ALIGN_OFFSET: 150, // Pixels from top for event nodes
  PAN_ANIMATION_DURATION: 800, // Milliseconds
} as const;

// ============ Animation & Timing ============
export const TIMING = {
  DEBOUNCE_DELAY: 300,
  SEARCH_DEBOUNCE: 200,
  COPY_FEEDBACK_DURATION: 2000,
  TOOLTIP_DELAY: 500,
  RESIZE_DEBOUNCE: 10,
  SYNC_DELAY: 100,
  REPLAY_SYNC_DELAY: 150,
  CLEANUP_DELAY: 200,
  NODE_SELECTION_DELAY: 100,
} as const;

// ============ Interaction Thresholds ============
export const THRESHOLDS = {
  MIN_DRAG_DISTANCE: 5,
  DOUBLE_CLICK_TIME: 300,
  LONG_PRESS_TIME: 500,
  SELECTION_TOLERANCE: 10,
  /** Minimum number of components required to show the search field in quick-add popups */
  SEARCH_VISIBILITY_MIN_COMPONENTS: 15,
} as const;

// ============ UI Elements ============
export const UI_DIMENSIONS = {
  ICON_SIZE: 16,
  ICON_SIZE_SMALL: 12,
  ICON_SIZE_LARGE: 24,
  BUTTON_HEIGHT: 32,
  INPUT_HEIGHT: 36,
  BORDER_RADIUS: 4,
  BORDER_RADIUS_LARGE: 8,
  PADDING_SMALL: 4,
  PADDING_MEDIUM: 8,
  PADDING_LARGE: 16,
  MARGIN_SMALL: 4,
  MARGIN_MEDIUM: 8,
  MARGIN_LARGE: 16,
} as const;

// ============ Error Recovery ============
export const ERROR_RECOVERY = {
  /** Maximum number of automatic retries before giving up */
  MAX_AUTO_RETRIES: 2,
  /** Delay in ms before first auto-retry (doubles each subsequent retry) */
  AUTO_RETRY_BASE_DELAY: 1000,
  /** Maximum number of errors to keep in the error log */
  MAX_ERROR_LOG_SIZE: 50,
  /** Time window in ms for deduplicating identical errors */
  DEDUP_WINDOW: 5000,
  /** Maximum number of manual retries shown in UI */
  MAX_MANUAL_RETRIES: 5,
} as const;

// ============ Storage Keys ============
export const STORAGE_KEYS = {
  PROPERTY_PANEL_WIDTH: 'propertyPanelWidth',
  REPLAY_PANEL_WIDTH: 'replayPanelWidth',
  REPLAY_PANEL_COLLAPSED: 'replayPanelCollapsed',
  PROPERTY_PANEL_COLLAPSED: 'propertyPanelCollapsed',
  THEME: 'modelerTheme',
} as const;
