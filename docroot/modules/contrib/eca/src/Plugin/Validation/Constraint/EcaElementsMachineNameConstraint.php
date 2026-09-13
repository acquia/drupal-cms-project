<?php

namespace Drupal\eca\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates an ECA elements machine name.
 *
 * Use this for every configuration key whose form element is validated by
 * \Drupal\eca\Plugin\FormFieldMachineName::validateElementsMachineName(), and
 * only for those. That validator implements three contracts, selected by the
 * flags on the form element, and this constraint offers the same three through
 * its "contract" option:
 * - "machine_name" for an element carrying neither flag.
 * - "token_reference" for an element flagged "#eca_token_reference".
 * - "token_replacement" for an element flagged "#eca_token_replacement".
 *
 * Both sides run through the very same function, so the user interface and
 * configuration validation cannot drift apart.
 *
 * All three contracts accept the empty string, exactly like the form element
 * validator does. Being required is not declared a second time here: it is
 * expressed by an additional "NotBlank" constraint on the very same
 * configuration key, mirroring "#required" on the form element.
 *
 * Rather than using this constraint directly, prefer one of the three schema
 * types that wrap it, which keeps the contract readable at the call site:
 * - "eca.machine_name"
 * - "eca.token_reference"
 * - "eca.token_replaced_machine_name"
 *
 * @see \Drupal\eca\Plugin\FormFieldMachineName
 * @see \Drupal\eca\Plugin\Validation\Constraint\EcaElementsMachineNameConstraintValidator
 */
#[Constraint(
  id: 'EcaElementsMachineName',
  label: new TranslatableMarkup('ECA elements machine name', [], ['context' => 'Validation'])
)]
class EcaElementsMachineNameConstraint extends SymfonyConstraint {

  /**
   * Contract accepting characters, numbers, hyphens and underscores.
   */
  public const string CONTRACT_MACHINE_NAME = 'machine_name';

  /**
   * Contract additionally accepting colons, to reference a token.
   */
  public const string CONTRACT_TOKEN_REFERENCE = 'token_reference';

  /**
   * Contract additionally accepting brackets, to hold tokens and nested paths.
   */
  public const string CONTRACT_TOKEN_REPLACEMENT = 'token_replacement';

  /**
   * The contract to validate against, one of the CONTRACT_* constants.
   *
   * Defaults to the most permissive contract, which is the one the majority of
   * ECA's machine-name-like fields implement.
   */
  public string $contract = self::CONTRACT_TOKEN_REPLACEMENT;

  /**
   * The message shown when the value is invalid.
   */
  public string $message = 'The value %value is not a valid elements machine name.';

  /**
   * Constructs an ECA elements machine name constraint.
   */
  #[HasNamedArguments]
  public function __construct(
    ?string $contract = NULL,
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct(groups: $groups, payload: $payload);
    $this->contract = $contract ?? $this->contract;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): string {
    return 'contract';
  }

}
