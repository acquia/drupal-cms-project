<?php

namespace Drupal\token_or;

use Drupal\token\TokenInterface;

/**
 * Tokens Pre Alter Service.
 */
class TokenOrTokensPreAlter {

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\TokenInterface
   */
  protected $token;

  /**
   * Constructor.
   *
   * @param \Drupal\token\TokenInterface $token
   *   The token.
   */
  public function __construct(TokenInterface $token) {
    $this->token = $token;
  }

  /**
   * Performs a tokens pre alter.
   */
  public function tokensPreAlter(&$text, $data, $options) {
    $matches = [];

    if (!$text) {
      return;
    }

    preg_match_all("/\[[^\[\]]+\]/", $text, $matches);

    if (empty($matches)) {
      return;
    }

    foreach (reset($matches) as $match) {
      if (strpos($match, '|') !== FALSE) {
        $match_clean = substr(substr($match, 1), 0, -1);
        // Split on pipes outside quoted strings, in a single linear pass.
        $sub_tokens = [''];
        $in_quotes = FALSE;
        foreach (str_split($match_clean) as $char) {
          if ($char === '|' && !$in_quotes) {
            $sub_tokens[] = '';
            continue;
          }
          if ($char === '"') {
            $in_quotes = !$in_quotes;
          }
          $sub_tokens[count($sub_tokens) - 1] .= $char;
        }

        // A single part means every pipe is inside quotes, as in
        // [widget:name attr="a|b"]: not an OR token. Replacing it would call
        // the token service on the same text again, without end.
        if (count($sub_tokens) < 2) {
          continue;
        }

        // Leave other bracketed text with pipes alone, such as Microsoft
        // Office conditional comments ([if mso | IE]): at least one part must
        // be a token ($type:$name) or a quoted string.
        if (!preg_grep('/^([^\s\[\]:]+:[^\[\]]+|".*")$/', $sub_tokens)) {
          continue;
        }

        foreach ($sub_tokens as $sub_token) {
          if (substr($sub_token, 0, 1) === '"' && substr($sub_token, -1, 1) === '"') {
            // This is a string replacement.
            $result = substr(substr($sub_token, 1), 0, -1);
          }
          else {
            // This is a token replacement.
            $sub_token = "[$sub_token]";
            $result = $this->token->replace($sub_token, $data, $options);
          }
          if ($result && $result != $sub_token) {
            $text = str_replace($match, $result, $text);
            break;
          }
        }
        if (!empty($options['clear'])) {
          $text = str_replace($match, '', $text);
        }
      }
    }
  }

}
