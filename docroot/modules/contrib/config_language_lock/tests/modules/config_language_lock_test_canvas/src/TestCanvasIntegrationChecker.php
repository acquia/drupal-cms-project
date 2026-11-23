<?php

declare(strict_types=1);

namespace Drupal\config_language_lock_test_canvas;

use Drupal\config_language_lock\CanvasIntegrationChecker;

/**
 * Test override that checks for the test module instead of the real canvas.
 */
class TestCanvasIntegrationChecker extends CanvasIntegrationChecker {

  protected const CANVAS_MODULE = 'config_language_lock_test_canvas';

}
