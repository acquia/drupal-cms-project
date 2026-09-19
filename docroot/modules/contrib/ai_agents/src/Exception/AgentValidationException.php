<?php

namespace Drupal\ai_agents\Exception;

use Drupal\ai\Exception\AiExceptionInterface;

/**
 * Error for when something broke with the input.
 */
class AgentValidationException extends \Exception implements AiExceptionInterface {
}
