<?php

declare(strict_types=1);

namespace Drupal\ai_agents\Exception;

/**
 * Thrown when an AI agent override references a missing parent agent.
 */
final class ParentAgentNotFoundException extends \RuntimeException {
}
