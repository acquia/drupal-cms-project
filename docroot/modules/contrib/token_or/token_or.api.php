<?php

/**
 * @file
 * Hooks provided by the Token OR module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter text before its tokens are replaced.
 *
 * Invoked by the token service's replace() and replacePlain() methods before
 * any token replacement happens. Token OR resolves its own [a|b|"c"] syntax
 * in an implementation of this hook, so other implementations may see text
 * before or after that, depending on module order.
 *
 * @param string|null $text
 *   The text containing tokens. May be NULL when replace() is called with
 *   NULL.
 * @param array $data
 *   The token data passed to the token service, such as ['node' => $node].
 * @param array $options
 *   The token replacement options, such as 'clear' and 'langcode'.
 *
 * @see \Drupal\token_or\Token::replace()
 * @see \Drupal\token_or\Token::replacePlain()
 */
function hook_tokens_pre_alter(&$text, array $data, array $options) {
  // Replace a custom placeholder before tokens are processed.
  if ($text) {
    $text = str_replace('{{site}}', '[site:name]', $text);
  }
}

/**
 * @} End of "addtogroup hooks".
 */
