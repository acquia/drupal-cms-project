<?php

namespace Drupal\eca\Service;

use Drupal\eca\Token\TokenInterface;
use Symfony\Component\Yaml\Parser;

/**
 * Service for parsing a YAML-formatted string into an array.
 */
class YamlParser {

  /**
   * The instantiated parser.
   *
   * @var \Symfony\Component\Yaml\Parser
   */
  protected Parser $parser;

  /**
   * The token service.
   *
   * @var \Drupal\eca\Token\TokenInterface
   */
  protected TokenInterface $token;

  /**
   * Constructs a new YamlParser service.
   *
   * @param \Drupal\eca\Token\TokenInterface $token
   *   The token service.
   */
  public function __construct(TokenInterface $token) {
    $this->parser = new Parser();
    $this->token = $token;
  }

  /**
   * Parses the given string.
   *
   * @param string $yaml_string
   *   The string to parse.
   * @param bool $replace_tokens
   *   Whether to replace tokens. Default is TRUE.
   *
   * @return mixed
   *   The parsed value.
   *
   * @throws \Symfony\Component\Yaml\Exception\ParseException
   *   When parsing failed.
   */
  public function parse(string $yaml_string, bool $replace_tokens = TRUE): mixed {
    $parsed = $this->parser->parse($yaml_string);
    if ($replace_tokens) {
      if (is_array($parsed)) {
        $parsed = $this->replaceTokens($parsed);
      }
      elseif (is_string($parsed)) {
        $parsed = (string) $this->token->replaceClear($parsed);
      }
    }
    return $parsed;
  }

  /**
   * Recursively replaces tokens in array keys and values.
   *
   * @param array $data
   *   The array to process.
   *
   * @return array
   *   The array with tokens replaced in both keys and values.
   */
  protected function replaceTokens(array $data): array {
    $result = [];
    foreach ($data as $key => $value) {
      if (is_string($key)) {
        $key = (string) $this->token->replaceClear($key);
      }
      if (is_array($value)) {
        $value = $this->replaceTokens($value);
      }
      elseif (!empty($value) && is_string($value)) {
        $value = (string) $this->token->replaceClear($value);
      }
      $result[$key] = $value;
    }
    return $result;
  }

}
