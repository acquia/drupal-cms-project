<?php

namespace Drupal\Tests\eca\Unit\Plugin\Validation\Constraint;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\eca\Plugin\Validation\Constraint\EcaChoiceConstraint;
use Drupal\eca\Plugin\Validation\Constraint\EcaChoiceConstraintValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Tests the EcaChoice constraint validator with and without typed data.
 *
 * The constraint is public API: its own documentation asks for it to be used
 * on every configuration key whose form element offers ECA's companion token
 * options. Nothing restricts it to configuration schema though, so it can also
 * be applied to a value that is not backed by typed data. Doing so must
 * neither crash nor silently switch the choice check off.
 *
 * How the validator behaves against the real configuration schema is covered
 * by \Drupal\Tests\eca\Kernel\EcaTokenSelectOptionSchemaTest. Only the fork
 * between both worlds is asserted here.
 *
 * @see \Drupal\eca\Plugin\Validation\Constraint\EcaChoiceConstraintValidator
 */
#[Group('eca')]
#[Group('eca_core')]
class EcaChoiceConstraintValidatorTest extends UnitTestCase {

  /**
   * The choices the constraint under test declares.
   */
  private const array CHOICES = ['first', 'second'];

  /**
   * Provides values to validate outside of typed data.
   *
   * All three options the user interface can offer are accepted, and anything
   * else is still rejected: without typed data there is no "NotBlank" sibling
   * constraint to consult, so the validated key counts as not required and the
   * "undefined" option stays a valid choice.
   *
   * @return array
   *   Test cases, each of them the value to validate and whether the choice
   *   constraint is expected to reject it.
   */
  public static function providerValuesWithoutTypedData(): array {
    return [
      'declared choice' => ['first', FALSE],
      'token option' => [EcaChoiceConstraint::TOKEN_OPTION, FALSE],
      'undefined option' => [EcaChoiceConstraint::UNDEFINED_OPTION, FALSE],
      'invalid choice' => ['third', TRUE],
    ];
  }

  /**
   * Tests validating a value the execution context has no object for.
   *
   * @param string $value
   *   The value to validate.
   * @param bool $rejected
   *   Whether the value is expected to be rejected.
   */
  #[DataProvider('providerValuesWithoutTypedData')]
  public function testValidationWithoutObject(string $value, bool $rejected): void {
    $this->assertRejected($rejected, $this->validate($value, NULL));
  }

  /**
   * Tests validating a value that belongs to an object other than typed data.
   *
   * @param string $value
   *   The value to validate.
   * @param bool $rejected
   *   Whether the value is expected to be rejected.
   */
  #[DataProvider('providerValuesWithoutTypedData')]
  public function testValidationWithNonTypedDataObject(string $value, bool $rejected): void {
    $this->assertRejected($rejected, $this->validate($value, new \stdClass()));
  }

  /**
   * Tests that typed data without "NotBlank" accepts the "undefined" option.
   */
  public function testTypedDataWithoutNotBlankConstraint(): void {
    $violations = $this->validate(EcaChoiceConstraint::UNDEFINED_OPTION, $this->mockTypedData(FALSE));
    $this->assertRejected(FALSE, $violations);
  }

  /**
   * Tests that typed data with "NotBlank" rejects the "undefined" option.
   */
  public function testTypedDataWithNotBlankConstraint(): void {
    $violations = $this->validate(EcaChoiceConstraint::UNDEFINED_OPTION, $this->mockTypedData(TRUE));
    $this->assertRejected(TRUE, $violations);
  }

  /**
   * Validates a value with the constraint validator under test.
   *
   * @param string $value
   *   The value to validate.
   * @param object|null $object
   *   The object the validated value belongs to, as the execution context
   *   reports it: a typed data object within configuration schema, and
   *   anything else, including nothing at all, everywhere else.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   The violations raised while validating the value.
   */
  private function validate(string $value, ?object $object): ConstraintViolationListInterface {
    $constraint = new EcaChoiceConstraint(choices: self::CHOICES);
    $translator = $this->createStub(TranslatorInterface::class);
    $translator->method('trans')->willReturnArgument(0);
    $context = new ExecutionContext($this->createStub(ValidatorInterface::class), 'root', $translator);
    $context->setNode($value, $object, NULL, 'property.path');
    $context->setConstraint($constraint);

    $validator = new EcaChoiceConstraintValidator();
    $validator->initialize($context);
    $validator->validate($value, $constraint);

    return $context->getViolations();
  }

  /**
   * Builds a typed data object that declares whether a value is required.
   *
   * @param bool $required
   *   Whether the data definition carries a "NotBlank" constraint, which is
   *   how configuration schema expresses that a value is required.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface
   *   The typed data object.
   */
  private function mockTypedData(bool $required): TypedDataInterface {
    $definition = $this->createStub(DataDefinitionInterface::class);
    $definition->method('getConstraints')->willReturn($required ? ['NotBlank' => NULL] : []);
    $data = $this->createStub(TypedDataInterface::class);
    $data->method('getDataDefinition')->willReturn($definition);
    return $data;
  }

  /**
   * Asserts that the choice constraint did or did not reject the value.
   *
   * Only the choice violation itself is asserted, and the whole list of codes
   * is compared, so that a second violation cannot pass unnoticed.
   *
   * @param bool $rejected
   *   Whether the value is expected to be rejected.
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The violations raised while validating the value.
   */
  private function assertRejected(bool $rejected, ConstraintViolationListInterface $violations): void {
    $codes = [];
    foreach ($violations as $violation) {
      $codes[] = $violation->getCode();
    }
    $this->assertSame($rejected ? [Choice::NO_SUCH_CHOICE_ERROR] : [], $codes);
  }

}
