<?php

namespace Drupal\ai_agents\Exception;

use Drupal\ai\Exception\AiExceptionInterface;

/**
 * Error for when something broke with processing.
 */
class AgentProcessingException extends \Exception implements AiExceptionInterface {
}
