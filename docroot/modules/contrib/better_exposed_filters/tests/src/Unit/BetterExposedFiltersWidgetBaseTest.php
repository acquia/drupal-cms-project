<?php

namespace Drupal\Tests\better_exposed_filters\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\better_exposed_filters\Plugin\BetterExposedFiltersWidgetBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests protected helpers on BetterExposedFiltersWidgetBase.
 */
#[CoversClass(BetterExposedFiltersWidgetBase::class)]
#[Group('better_exposed_filters')]
class BetterExposedFiltersWidgetBaseTest extends UnitTestCase {

  /**
   * A crafted URL that array-nests a checkbox value must not fatal.
   */
  public function testAddElementToGroupIgnoresArrayNestedUserInput(): void {
    $widget = $this->buildWidget();

    $form = [
      'my_element' => [
        '#multiple' => TRUE,
        '#options' => ['12' => 'Term 12'],
        '#default_value' => [],
      ],
      'my_group' => [],
    ];
    $form_state = new FormState();
    // Simulates ?identifier[12][]=1 — after PHP parses the query string, the
    // element value is a nested array instead of the expected scalar.
    $form_state->setUserInput(['my_element' => ['12' => [1]]]);

    $widget->callAddElementToGroup($form, $form_state, 'my_element', 'my_group');

    $this->assertArrayNotHasKey('#open', $form['my_group']);
  }

  /**
   * A well-formed selection still auto-opens the group.
   */
  public function testAddElementToGroupOpensOnValidSelection(): void {
    $widget = $this->buildWidget();

    $form = [
      'my_element' => [
        '#multiple' => TRUE,
        '#options' => ['12' => 'Term 12'],
        '#default_value' => [],
      ],
      'my_group' => [],
    ];
    $form_state = new FormState();
    $form_state->setUserInput(['my_element' => ['12' => 12]]);

    $widget->callAddElementToGroup($form, $form_state, 'my_element', 'my_group');

    $this->assertTrue($form['my_group']['#open']);
  }

  /**
   * Builds a test widget.
   */
  private function buildWidget(): object {
    return new class([], 'test', [], $this->createMock(Request::class), $this->createMock(ConfigFactoryInterface::class)) extends BetterExposedFiltersWidgetBase {

      /**
       * {@inheritdoc}
       */
      public static function isApplicable(mixed $handler = NULL, array $options = []): bool {
        return TRUE;
      }

      /**
       * {@inheritdoc}
       */
      public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
        return $form;
      }

      /**
       * {@inheritdoc}
       */
      public function exposedFormAlter(array &$form, FormStateInterface $form_state) {}

      /**
       * Public passthrough to the protected addElementToGroup().
       */
      public function callAddElementToGroup(array &$form, FormStateInterface $form_state, string $element, string $group): void {
        $this->addElementToGroup($form, $form_state, $element, $group);
      }

    };
  }

}
