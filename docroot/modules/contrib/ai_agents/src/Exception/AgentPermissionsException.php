<?php

namespace Drupal\ai_agents\Exception;

use Drupal\ai\Exception\AiExceptionInterface;

/**
 * Error for when something broke with permissions.
 */
class AgentPermissionsException extends \Exception implements AiExceptionInterface {
}
