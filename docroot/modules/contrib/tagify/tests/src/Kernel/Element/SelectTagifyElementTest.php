<?php

declare(strict_types=1);

namespace Drupal\Tests\tagify\Kernel\Element;

use Drupal\Core\Form\FormState;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\tagify\Kernel\TagifyKernelTestBase;

/**
 * Tests the select_tagify form element.
 *
 * @group tagify
 */
#[Group('tagify')]
#[RunTestsInSeparateProcesses]
final class SelectTagifyElementTest extends TagifyKernelTestBase {

  /**
   * Tests a multiple element without #default_value builds without errors.
   *
   * Without a #default_value, the form builder falls back to '' for #value,
   * which processSelectTagify() must not treat as an array of selected
   * values.
   */
  public function testMultipleWithoutDefaultValue(): void {
    $form = $this->container->get('form_builder')->getForm(SelectTagifyTestForm::class);

    $this->assertSame(['a' => 'A', 'b' => 'B'], $form['tags']['#options']);
  }

  /**
   * Tests that a value inside an option group passes validation.
   *
   * FormValidator::performRequiredValidation() only flattens optgroups for
   * elements whose #type is literally 'select', so select_tagify has to
   * present itself as one. Without that, every grouped value is rejected as
   * "The submitted value ... is not allowed".
   *
   * @see https://git.drupalcode.org/project/tagify/-/issues/3590693
   */
  public function testGroupedOptionValueValidates(): void {
    $options = [
      'Editorial' => [
        'draft' => 'Draft',
        'published' => 'Published',
      ],
      'Archive' => [
        'archived' => 'Archived',
      ],
    ];

    $form_state = new FormState();
    $form_state->addBuildInfo('args', [$options]);
    $form_state->setValues(['tags' => ['draft', 'archived']]);
    $form_state->setUserInput(['tags' => ['draft', 'archived'], 'op' => 'Save']);
    $form_state->setProgrammed();
    $form_state->setSubmitted();

    $this->container->get('form_builder')
      ->submitForm(SelectTagifyTestForm::class, $form_state);

    $this->assertSame([], array_map('strval', $form_state->getErrors()));
  }

  /**
   * Tests that grouped options survive processing as optgroups.
   *
   * The JS reads the <optgroup> label to build the dropdown headings, so the
   * groups must not be flattened away server-side.
   */
  public function testGroupedOptionsAreNotFlattened(): void {
    $options = [
      'Editorial' => [
        'draft' => 'Draft',
      ],
    ];
    $form = $this->container->get('form_builder')
      ->getForm(SelectTagifyTestForm::class, $options);

    $this->assertSame($options, $form['tags']['#options']);
  }

}
