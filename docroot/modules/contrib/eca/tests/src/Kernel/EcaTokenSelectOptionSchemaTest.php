<?php

namespace Drupal\Tests\eca\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Element;
use Drupal\KernelTests\KernelTestBase;
use Drupal\eca\Plugin\ECA\Condition\ConditionInterface;
use Drupal\eca\Plugin\ECA\Event\EventInterface;
use Drupal\eca\Plugin\Validation\Constraint\EcaChoiceConstraint;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Validator\Constraints\Choice;

/**
 * Tests that "Defined by token" options are valid configuration values.
 *
 * Every form element flagged with "#eca_token_select_option" gets two extra
 * options injected at build time, and neither of them is part of the option
 * list the element declares itself:
 * - "_eca_token" is always added.
 * - "" is added when the element is not "#required".
 *
 * Both are values a user can select and store, so the configuration schema has
 * to accept them. This test walks the real form elements of all events,
 * conditions and actions, derives the configuration schema key each of them
 * writes to, and asserts that its choice constraint accepts exactly those extra
 * options. It therefore fails as soon as a new sentinel-offering option is
 * added without the matching schema change, or the other way around.
 *
 * Only violations of the choice constraint itself are looked at. Other
 * constraints on the same key are a separate concern, and there is a known one:
 * a few sentinel-offering keys are typed "integer" in schema, where the
 * "PrimitiveType" constraint rejects "_eca_token" no matter what the choice
 * constraint allows.
 *
 * @see \Drupal\eca\Plugin\ECA\PluginFormTrait::updateConfigurationForm()
 * @see \Drupal\eca\Plugin\Validation\Constraint\EcaChoiceConstraint
 */
#[Group('eca')]
#[Group('eca_core')]
#[RunTestsInSeparateProcesses]
class EcaTokenSelectOptionSchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'filter',
    'image',
    'language',
    'migrate',
    'node',
    'breakpoint',
    'responsive_image',
    'serialization',
    'views',
    'workflows',
    'content_moderation',
    'modeler_api',
    'eca',
    'eca_access',
    'eca_base',
    'eca_cache',
    'eca_config',
    'eca_content',
    'eca_endpoint',
    'eca_file',
    'eca_form',
    'eca_htmx',
    'eca_language',
    'eca_log',
    'eca_menu',
    'eca_migrate',
    'eca_misc',
    'eca_node_access',
    'eca_queue',
    'eca_render',
    'eca_ui',
    'eca_user',
    'eca_views',
    'eca_workflow',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Some action plugins read their default configuration while they are being
    // created, and are silently skipped when that configuration is missing. The
    // list of actions is cached statically and installing the modules above
    // already populated it, so it has to be rebuilt once the configuration is
    // in place. Without this, those plugins are missing from this test.
    $this->installConfig(['eca', 'user']);
    drupal_static_reset('eca_actions');
  }

  /**
   * Tests that every "Defined by token" option passes schema validation.
   */
  public function testTokenSelectOptionsAreValidChoices(): void {
    $elements = $this->collectTokenSelectElements();
    // A safety net: should collecting the elements ever silently stop working,
    // all assertions below would pass without testing anything.
    $this->assertGreaterThan(100, count($elements));

    $errors = [];
    foreach ($elements as $element) {
      [$schemaType, $key, $required] = $element;
      $constraint = $this->getChoiceConstraintName($schemaType, $key);
      if ($constraint === NULL) {
        // The key does not restrict its value to a list of choices, so there is
        // nothing that could reject the extra options.
        continue;
      }
      $label = sprintf('%s:%s (%s)', $schemaType, $key, $constraint);
      if ($this->isRejectedChoice($schemaType, $key, EcaChoiceConstraint::TOKEN_OPTION)) {
        $errors[] = sprintf('%s must accept the value "%s".', $label, EcaChoiceConstraint::TOKEN_OPTION);
      }
      $undefinedRejected = $this->isRejectedChoice($schemaType, $key, EcaChoiceConstraint::UNDEFINED_OPTION);
      if ($required && !$undefinedRejected) {
        $errors[] = sprintf('%s is required, so it must not accept an empty value.', $label);
      }
      if (!$required && $undefinedRejected) {
        $errors[] = sprintf('%s is not required, so it must accept an empty value.', $label);
      }
    }
    $this->assertSame([], $errors);
  }

  /**
   * Tests that no plugin can silently drop out of the test above.
   *
   * The services that collect events, conditions and actions all swallow a
   * failing plugin instantiation: they log it, or ignore it altogether, and
   * carry on with a shorter list. A plugin that stops being instantiable
   * therefore breaks nothing visibly, it just disappears from the test above
   * and quietly takes its configuration keys out of that test's coverage.
   *
   * Asserting an expected number of plugins would only be a tripwire that every
   * new plugin trips, so the cause is asserted instead of the symptom.
   *
   * @see \Drupal\eca\Service\Actions::createInstance()
   * @see \Drupal\eca\Service\Conditions::createInstance()
   * @see \Drupal\eca\Service\Events::events()
   */
  public function testAllPluginsCanBeInstantiated(): void {
    $managers = [
      // The decorated manager is the one the action service collects from.
      $this->container->get('plugin.manager.eca.action')->getDecoratedActionManager(),
      $this->container->get('plugin.manager.eca.condition'),
      $this->container->get('plugin.manager.eca.event'),
    ];
    $failed = [];
    foreach ($managers as $manager) {
      foreach (array_keys($manager->getDefinitions()) as $pluginId) {
        try {
          $manager->createInstance($pluginId);
        }
        catch (\Throwable $e) {
          $failed[$pluginId] = get_class($e) . ': ' . $e->getMessage();
        }
      }
    }
    $this->assertSame([], $failed);
  }

  /**
   * Collects all form elements that offer the "Defined by token" option.
   *
   * The plugins are collected the same way the user interface collects them, so
   * that this covers exactly the set of plugins a user can configure.
   *
   * @return array
   *   A list of elements, each of them an array containing the configuration
   *   schema type, the configuration key and whether the element is required.
   */
  private function collectTokenSelectElements(): array {
    $plugins = array_merge(
      $this->container->get('eca.service.event')->events(),
      $this->container->get('eca.service.condition')->conditions(),
      $this->container->get('eca.service.action')->actions(),
    );

    $elements = [];
    $failedForms = [];
    $actionsService = $this->container->get('eca.service.action');
    foreach ($plugins as $plugin) {
      $formState = new FormState();
      $schemaType = match (TRUE) {
        $plugin instanceof EventInterface => 'eca.event.plugin.',
        $plugin instanceof ConditionInterface => 'eca.condition.plugin.',
        default => 'action.configuration.',
      } . $plugin->getPluginId();
      try {
        if ($plugin instanceof EventInterface || $plugin instanceof ConditionInterface) {
          $form = $plugin instanceof PluginFormInterface ? $plugin->buildConfigurationForm([], $formState) : [];
        }
        else {
          // Actions only get the ECA specific options added by this service.
          $form = $actionsService->getConfigurationForm($plugin, $formState);
        }
      }
      catch (\Throwable) {
        $form = NULL;
      }
      if ($form === NULL) {
        $failedForms[] = $schemaType;
        continue;
      }
      $elements = array_merge($elements, $this->extractTokenSelectElements($form, $schemaType));
    }

    // A plugin whose configuration form cannot be built would be skipped
    // without being noticed, quietly reducing the coverage of this test.
    $this->assertSame([], $failedForms);

    return $elements;
  }

  /**
   * Extracts the sentinel-offering elements of a single plugin form.
   *
   * @param array $form
   *   The plugin configuration form.
   * @param string $schemaType
   *   The configuration schema type the plugin stores its configuration in.
   *
   * @return array
   *   A list of elements, in the format documented for
   *   self::collectTokenSelectElements().
   */
  private function extractTokenSelectElements(array $form, string $schemaType): array {
    $elements = [];
    // Only the direct children are looked at, because those are the only ones
    // that get the extra options injected.
    // @see \Drupal\eca\Plugin\ECA\PluginFormTrait::updateConfigurationForm()
    foreach (Element::children($form) as $key) {
      $element = $form[$key];
      if (empty($element['#eca_token_select_option']) || !isset($element['#options']) || !is_array($element['#options'])) {
        continue;
      }
      $elements[] = [
        $schemaType,
        (string) $key,
        ($element['#required'] ?? FALSE) !== FALSE,
      ];
    }
    return $elements;
  }

  /**
   * Gets the name of the choice constraint of a configuration key, if any.
   *
   * @param string $schemaType
   *   The configuration schema type.
   * @param string $key
   *   The configuration key.
   *
   * @return string|null
   *   The constraint name, or NULL if the key has no choice constraint.
   */
  private function getChoiceConstraintName(string $schemaType, string $key): ?string {
    $definition = $this->container->get('config.typed')->getDefinition($schemaType);
    $constraints = $definition['mapping'][$key]['constraints'] ?? [];
    foreach (['EcaChoice', 'Choice'] as $name) {
      if (array_key_exists($name, $constraints)) {
        return $name;
      }
    }
    return NULL;
  }

  /**
   * Determines whether a value is rejected by the key's choice constraint.
   *
   * @param string $schemaType
   *   The configuration schema type.
   * @param string $key
   *   The configuration key.
   * @param string $value
   *   The value to validate.
   *
   * @return bool
   *   TRUE if the choice constraint rejects the value, FALSE otherwise.
   */
  private function isRejectedChoice(string $schemaType, string $key, string $value): bool {
    $typedConfigManager = $this->container->get('config.typed');
    $data = [$key => $value];
    $definition = $typedConfigManager->getDefinition($schemaType);
    $dataDefinition = $typedConfigManager->buildDataDefinition($definition, $data);
    $element = $typedConfigManager->create($dataDefinition, $data);
    foreach ($element->validate() as $violation) {
      if ($violation->getPropertyPath() === $key && $violation->getCode() === Choice::NO_SUCH_CHOICE_ERROR) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
