<?php

declare(strict_types=1);

namespace Drupal\Tests\linkit\Kernel;

use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ckeditor5\Kernel\CKEditor5ValidationTestTrait;
use Drupal\Tests\SchemaCheckTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * @covers \Drupal\linkit\Plugin\CKEditor5Plugin\Linkit::validChoices
 * @covers \Drupal\linkit\Plugin\CKEditor5Plugin\Linkit::requireProfileIfEnabled
 * @see linkit.schema.yml
 *
 * @group linkit
 */
abstract class ValidatorsTest extends KernelTestBase {

  use SchemaCheckTestTrait;
  use CKEditor5ValidationTestTrait;

  /**
   * The typed config manager.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface
   */
  protected $typedConfig;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ckeditor5',
    'ckeditor5_plugin_conditions_test',
    'editor',
    'filter',
    'filter_test',
    'media',
    'image',
    'media_library',
    'system',
    'user',
    'views',
    'node',
    'linkit',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->typedConfig = $this->container->get('config.typed');
    // Avoid needing to install the Stark theme.
    $this->config('system.theme')->delete();
    // @see config/optional/linkit.linkit_profile.default.yml
    $this->installConfig(['linkit']);

  }

}

if (version_compare(\Drupal::VERSION, '10.3', '>')) {
  /**
   * {@inheritdoc}
   *
   * @group linkit
   */
  class LinkitValidatorsTest extends ValidatorsTest {

    /**
     * Tests .
     *
     * @param array $ckeditor5_settings
     *   The CKEditor 5 settings to test.
     * @param array $expected_violations
     *   All expected violations for the given CKEditor 5 settings, property
     *   path as keys and message as values.
     *
     * @legacy-covers \Drupal\ckeditor5\Plugin\Validation\Constraint\CKEditor5ElementConstraintValidator
     * @legacy-covers \Drupal\ckeditor5\Plugin\Validation\Constraint\StyleSensibleElementConstraintValidator
     * @legacy-covers \Drupal\ckeditor5\Plugin\Validation\Constraint\UniqueLabelInListConstraintValidator
     */
    #[DataProvider('provider')]
    public function test(array $ckeditor5_settings, array $expected_violations): void {
      // The data provider is unable to access services, so the test scenario of
      // testing with CKEditor 5's default settings is partially provided here.
      if ($ckeditor5_settings === ['__DEFAULT__']) {
        $ckeditor5_settings = \Drupal::service('plugin.manager.editor')->createInstance('ckeditor5')->getDefaultSettings();
      }

      FilterFormat::create([
        'format' => 'dummy',
        'name' => 'Dummy',
      ])->save();
      $editor = Editor::create([
        'format' => 'dummy',
        'editor' => 'ckeditor5',
        'settings' => $ckeditor5_settings,
        'image_upload' => [
          'status' => FALSE,
        ],
      ]);

      $typed_config = $this->typedConfig->createFromNameAndData(
        $editor->getConfigDependencyName(),
        $editor->toArray(),
      );
      $violations = $typed_config->validate();

      $this->assertSame($expected_violations, self::violationsToArray($violations));

      if (empty($expected_violations)) {
        $this->assertConfigSchema(
          $this->typedConfig,
          $editor->getConfigDependencyName(),
          $typed_config->getValue()
        );
      }
    }

    /**
     * Transforms a constraint violation list object to an assertable array.
     *
     * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
     *   Validation constraint violations.
     *
     * @return array
     *   An array with property paths as keys and violation messages as values.
     */
    private static function violationsToArray(ConstraintViolationListInterface $violations): array {
      $actual_violations = [];
      foreach ($violations as $violation) {
        if (!isset($actual_violations[$violation->getPropertyPath()])) {
          $actual_violations[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }
        else {
          // Transform value from string to array.
          if (is_string($actual_violations[$violation->getPropertyPath()])) {
            $actual_violations[$violation->getPropertyPath()] = (array) $actual_violations[$violation->getPropertyPath()];
          }
          // And append.
          $actual_violations[$violation->getPropertyPath()][] = (string) $violation->getMessage();
        }
      }
      return $actual_violations;
    }

    /**
     * {@inheritdoc}
     */
    public static function provider(): array {
      $linkit_test_cases_toolbar_settings = ['items' => ['link']];

      $data = [];
      $data['VALID: installing the linkit module without configuring the existing text editors'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [],
        ],
        'expected_violations' => [],
      ];
      $data['INVALID: linkit — invalid manually created configuration'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [
            'linkit_extension' => [
              'linkit_enabled' => 'no',
            ],
          ],
        ],
        'expected_violations' => [
          'settings.plugins.linkit_extension.linkit_enabled' => 'This value should be of the correct primitive type.',
        ],
      ];
      $data['VALID: linkit off'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [
            'linkit_extension' => [
              'linkit_enabled' => FALSE,
            ],
          ],
        ],
        'expected_violations' => [],
      ];
      $data['VALID: linkit off, profile selected'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [
            'linkit_extension' => [
              'linkit_enabled' => TRUE,
              'linkit_profile' => 'default',
            ],
          ],
        ],
        'expected_violations' => [],
      ];
      $data['INVALID: linkit on, no profile selected'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [
            'linkit_extension' => [
              'linkit_enabled' => TRUE,
            ],
          ],
        ],
        'expected_violations' => [
          'settings.plugins.linkit_extension.linkit_profile' => 'Linkit is enabled, please select the Linkit profile you wish to use.',
        ],
      ];
      $data['INVALID: linkit on, non-existent profile selected'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [
            'linkit_extension' => [
              'linkit_enabled' => TRUE,
              'linkit_profile' => 'nonexistent',
            ],
          ],
        ],
        'expected_violations' => [
          'settings.plugins.linkit_extension.linkit_profile' => 'The value you selected is not a valid choice.',
        ],
      ];
      $data['VALID: linkit on, existing profile selected'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [
            'linkit_extension' => [
              'linkit_enabled' => TRUE,
              'linkit_profile' => 'default',
            ],
          ],
        ],
        'expected_violations' => [],
      ];
      return $data;
    }

  }
}
else {
  /**
   * {@inheritdoc}
   *
   * @group linkit
   */
  class LinkitValidatorsTest extends ValidatorsTest {

    /**
     * {@inheritdoc}
     */
    public static function providerPair(): array {
      // Linkit is 100% independent of the text format, so no need for this
      // test.
      return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function provider(): array {
      $linkit_test_cases_toolbar_settings = ['items' => ['link']];

      $data = [];
      $data['VALID: installing the linkit module without configuring the existing text editors'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [],
        ],
        'expected_violations' => [],
      ];
      $data['INVALID: linkit — invalid manually created configuration'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [
            'linkit_extension' => [
              'linkit_enabled' => 'no',
            ],
          ],
        ],
        'expected_violations' => [
          'settings.plugins.linkit_extension.linkit_enabled' => 'This value should be of the correct primitive type.',
        ],
      ];
      $data['VALID: linkit off'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [
            'linkit_extension' => [
              'linkit_enabled' => FALSE,
            ],
          ],
        ],
        'expected_violations' => [],
      ];
      $data['VALID: linkit off, profile selected'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [
            'linkit_extension' => [
              'linkit_enabled' => TRUE,
              'linkit_profile' => 'default',
            ],
          ],
        ],
        'expected_violations' => [],
      ];
      $data['INVALID: linkit on, no profile selected'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [
            'linkit_extension' => [
              'linkit_enabled' => TRUE,
            ],
          ],
        ],
        'expected_violations' => [
          'settings.plugins.linkit_extension.linkit_profile' => 'Linkit is enabled, please select the Linkit profile you wish to use.',
        ],
      ];
      $data['INVALID: linkit on, non-existent profile selected'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [
            'linkit_extension' => [
              'linkit_enabled' => TRUE,
              'linkit_profile' => 'nonexistent',
            ],
          ],
        ],
        'expected_violations' => [
          'settings.plugins.linkit_extension.linkit_profile' => 'The value you selected is not a valid choice.',
        ],
      ];
      $data['VALID: linkit on, existing profile selected'] = [
        'ckeditor5_settings' => [
          'toolbar' => $linkit_test_cases_toolbar_settings,
          'plugins' => [
            'linkit_extension' => [
              'linkit_enabled' => TRUE,
              'linkit_profile' => 'default',
            ],
          ],
        ],
        'expected_violations' => [],
      ];
      return $data;
    }

  }
}
