<?php

namespace Drupal\custom_field\Plugin\views\filter;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeType;
use Drupal\custom_field\Plugin\CustomField\FieldType\DateTimeTypeInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\Date as NumericDate;
use Drupal\views\Plugin\views\query\Sql;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Date/time views filter.
 *
 * Even though dates are stored as strings, the numeric filter is extended
 * because it provides more sensible operators.
 *
 * @ingroup views_filter_handlers
 */
#[ViewsFilter("custom_field_datetime")]
class CustomFieldDate extends NumericDate implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Date format for SQL conversion.
   *
   * @var string
   *
   * @see \Drupal\views\Plugin\views\query\Sql::getDateFormat()
   */
  protected string $dateFormat = DateTimeTypeInterface::DATETIME_STORAGE_FORMAT;

  /**
   * The date type.
   *
   * @var string
   */
  protected string $dateType = DateTimeType::DATETIME_TYPE_DATETIME;

  /**
   * Determines if the timezone offset is calculated.
   *
   * @var bool
   */
  protected bool $calculateOffset = TRUE;

  /**
   * Constructs a new Date handler.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;

    if ($configuration['datetime_type'] === DateTimeType::DATETIME_TYPE_DATE) {
      // Date format depends on field storage format.
      $this->dateFormat = DateTimeTypeInterface::DATE_STORAGE_FORMAT;
      // Timezone offset calculation is not applicable to dates that are stored
      // as date-only.
      $this->calculateOffset = FALSE;
      $this->dateType = DateTimeType::DATETIME_TYPE_DATE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['expose']['contains']['filter_type'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultExposeOptions(): void {
    parent::defaultExposeOptions();
    $this->options['expose']['filter_type'] = $this->dateType;
  }

  /**
   * Add a type selector to the value form.
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    $form['value']['#tree'] = TRUE;
    $user_input = $form_state->getUserInput();
    $type = NestedArray::getValue($user_input, ['options', 'value', 'type']) ?? $this->value['type'];
    $selected_operator = NestedArray::getValue($user_input, ['options', 'operator']) ?? $this->operator;
    $between_operator = in_array($selected_operator, ['between', 'not between']);
    $identifier = $this->options['expose']['identifier'];
    if (!$form_state->get('exposed')) {
      $form['value']['type'] = [
        '#type' => 'radios',
        '#title' => $this->t('Value type'),
        '#options' => [
          'date' => $this->t('A date in any machine readable format. CCYY-MM-DD HH:MM:SS is preferred.'),
          'offset' => $this->t('An offset from the current time such as "@example1" or "@example2"', [
            '@example1' => '+1 day',
            '@example2' => '-2 hours -30 minutes',
          ]),
        ],
        '#default_value' => !empty($this->value['type']) ? $this->value['type'] : 'date',
      ];
      // This doesn't quite work right with groups.
      if (!$this->isAGroup()) {
        $form['value']['type']['#options']['date'] = $this->t('Date');
        $form['value']['type']['#ajax'] = [
          // @phpstan-ignore-next-line
          'url' => views_ui_build_form_url($form_state),
        ];
      }
    }

    // We have to make some choices when creating this as an exposed
    // filter form. For example, if the operator is locked and thus
    // not rendered, we can't render dependencies; instead we only
    // render the form items we need.
    $which = 'all';
    $source = '';
    if (!empty($form['operator'])) {
      $source = ':input[name="options[operator]"]';
    }

    if ($exposed = $form_state->get('exposed')) {
      if (empty($this->options['expose']['use_operator']) || empty($this->options['expose']['operator_id'])) {
        // Exposed and locked.
        $which = in_array($this->operator, $this->operatorValues(2)) ? 'minmax' : 'value';
      }
      else {
        $source = ':input[name="' . $this->options['expose']['operator_id'] . '"]';
      }
    }

    if ($which == 'all') {
      $value_title = $type === 'date' ? 'Date' : 'Offset';
      $form['value']['value'] = $this->buildFormField($form_state, $type, 'value', $value_title, !$between_operator);
      // Setup #states for all operators with one value.
      foreach ($this->operatorValues(1) as $operator) {
        $form['value']['value']['#states']['visible'][] = [
          $source => ['value' => $operator],
        ];
      }
      if ($exposed && !isset($user_input[$identifier]['value'])) {
        $user_input[$identifier]['value'] = $this->value['value'];
        $form_state->setUserInput($user_input);
      }
    }
    elseif ($which == 'value') {
      // When exposed we drop the value-value and just do value if
      // the operator is locked.
      $form['value'] = $this->buildFormField($form_state, $type, 'value', 'Date');
      if ($exposed && !isset($user_input[$identifier])) {
        $user_input[$identifier] = $this->value['value'];
        $form_state->setUserInput($user_input);
      }
    }

    // Minimum and maximum form fields are associated to some specific operators
    // like 'between'. Ensure that min and max fields are only visible if
    // the associated operator is not excluded from the operator list.
    $two_value_operators_available = ($which == 'all' || $which == 'minmax');

    if (!empty($this->options['expose']['operator_limit_selection']) &&
      !empty($this->options['expose']['operator_list'])) {
      $two_value_operators_available = FALSE;
      foreach ($this->options['expose']['operator_list'] as $operator) {
        if (in_array($operator, $this->operatorValues(2), TRUE)) {
          $two_value_operators_available = TRUE;
          break;
        }
      }
    }

    if ($two_value_operators_available) {
      $form['value']['min'] = $this->buildFormField($form_state, $type, 'min', 'From', $between_operator);
      $form['value']['max'] = $this->buildFormField($form_state, $type, 'max', 'To');

      if ($which == 'all') {
        $states = [];
        // Setup #states for all operators with two values.
        foreach ($this->operatorValues(2) as $operator) {
          $states['#states']['visible'][] = [
            $source => ['value' => $operator],
          ];
        }
        $form['value']['min'] += $states;
        $form['value']['max'] += $states;
      }
      if ($exposed && !isset($user_input[$identifier]['min'])) {
        $user_input[$identifier]['min'] = $this->value['min'];
        $form_state->setUserInput($user_input);
      }
      if ($exposed && !isset($user_input[$identifier]['max'])) {
        $user_input[$identifier]['max'] = $this->value['max'];
        $form_state->setUserInput($user_input);
      }

      if (!isset($form['value'])) {
        // Ensure there is something in the 'value'.
        $form['value'] = [
          '#type' => 'value',
          '#value' => NULL,
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function valueSubmit($form, FormStateInterface $form_state): void {
    $value = $form_state->getValue(['options', 'value']);
    foreach (['value', 'min', 'max'] as $value_key) {
      if (!\array_key_exists($value_key, $value)) {
        continue;
      }
      if (!empty($value[$value_key])) {
        // Convert date objects to string.
        if ($value[$value_key] instanceof DrupalDateTime) {
          $value[$value_key] = $value[$value_key]->format('Y-m-d\TH:i:s');
        }
      }
    }

    $form_state->setValue(['options', 'value'], $value);
    parent::valueSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function buildGroupSubmit($form, FormStateInterface $form_state): void {
    $group_items = $form_state->getValue(['options', 'group_info', 'group_items']);

    foreach ($group_items as $key => $group_item) {
      $value = $group_item['value']['value'];
      if ($value instanceof DrupalDateTime) {
        $group_items[$key]['value']['value'] = $value->format('Y-m-d H:i:s');
      }
    }
    $form_state->setValue(['options', 'group_info', 'group_items'], $group_items);

    parent::buildGroupSubmit($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input): bool {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    // Rewrite the input value so that it's in the correct format so that
    // the parent gets the right data.
    $key = $this->isAGroup() ? 'group_info' : 'expose';
    if (empty($this->options[$key]['identifier'])) {
      // Invalid identifier configuration. Value can't be resolved.
      return FALSE;
    }
    $value = &$input[$this->options[$key]['identifier']];
    if (!is_array($value)) {
      $value = [
        'value' => $value,
      ];
    }

    $rc = parent::acceptExposedInput($input);

    if (empty($this->options['expose']['required'])) {
      // We have to do some of our own checking for non-required filters.
      $info = $this->operators();
      $values = $info[$this->operator]['values'] ?? '';
      if (!empty($values)) {
        switch ($values) {
          case 1:
            if ($value['value'] === '') {
              return FALSE;
            }
            break;

          case 2:
            if ($value['min'] === '' && $value['max'] === '') {
              return FALSE;
            }
            break;
        }
      }
    }

    return $rc;
  }

  /**
   * Validate that the time values convert to something usable.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $operator
   *   The operator type.
   * @param mixed $value
   *   The value.
   */
  public function validateValidTime(&$form, FormStateInterface $form_state, $operator, $value): void {
    $operators = $this->operators();
    $user_input = $form_state->getUserInput();
    $type = NestedArray::getValue($user_input, ['options', 'value', 'type']) ?? $this->value['type'];
    $values = $operators[$operator]['values'] ?? NULL;
    if ($values == 1) {
      if ($type === 'date') {
        $date = $value['value'] instanceof DrupalDateTime;
        if (!$date) {
          $form_state->setError($form['value'], $this->t('Invalid date format.'));
        }
      }
      if ($type === 'offset' && empty($value['value'])) {
        $form_state->setError($form['value'], $this->t('Invalid offset date format.'));
      }
    }
    elseif ($values == 2) {
      if ($type === 'date') {
        $min_date = $value['min'] instanceof DrupalDateTime;
        if (!$min_date) {
          $form_state->setError($form['min'], $this->t('Invalid from date format.'));
        }
        $max_date = $value['max'] instanceof DrupalDateTime;
        if (!$max_date) {
          $form_state->setError($form['max'], $this->t('Invalid to date format.'));
        }
      }
      if ($type === 'offset') {
        if (empty($value['min'])) {
          $form_state->setError($form['min'], $this->t('Invalid min offset date format.'));
        }
        if (empty($value['max'])) {
          $form_state->setError($form['max'], $this->t('Invalid max offset date format.'));
        }
      }
    }
  }

  /**
   * Builds a group form.
   *
   * The form contains a group of operator or values to apply as a single
   * filter. Implementing our own version to get around bugs.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @link https://www.drupal.org/project/drupal/issues/3523482#comment-16143268
   *  Related issue discussion.
   */
  public function groupForm(&$form, FormStateInterface $form_state): void {
    $groups = [];
    if (!empty($this->options['group_info']['optional']) && !$this->multipleExposedInput()) {
      $groups = ['All' => $this->t('- Any -')];
    }
    foreach ($this->options['group_info']['group_items'] as $id => $group) {
      if (!empty($group['title'])) {
        $groups[$id] = $group['title'];
      }
    }

    if (count($groups)) {
      $value = (string) $this->options['group_info']['identifier'];

      $form[$value] = [
        '#title' => $this->options['group_info']['label'],
        '#type' => $this->options['group_info']['widget'],
        '#default_value' => $this->options['group_info']['default_group'],
        '#options' => $groups,
      ];
      $user_input = $form_state->getUserInput();
      if (!empty($user_input[$value]) && (!is_scalar($user_input[$value]) || !array_key_exists($user_input[$value], $groups))) {
        $user_input[$value] = $this->options['group_info']['default_group'];
        $form_state->setUserInput($user_input);
      }
      if (!empty($this->options['group_info']['multiple'])) {
        if (count($groups) < 5) {
          $form[$value]['#type'] = 'checkboxes';
        }
        else {
          $form[$value]['#type'] = 'select';
          $form[$value]['#size'] = 5;
          $form[$value]['#multiple'] = TRUE;
        }
        unset($form[$value]['#default_value']);
        $user_input = $form_state->getUserInput();
        if (empty($user_input[$value])) {
          $user_input[$value] = array_filter($this->options['group_info']['default_group_multiple']);
          $form_state->setUserInput($user_input);
        }
      }

      $this->options['expose']['label'] = '';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state): void {
    parent::buildExposeForm($form, $form_state);
    $user_input = $form_state->getUserInput();
    $type = NestedArray::getValue($user_input, ['options', 'value', 'type']) ?? $this->options['value']['type'];
    // Add a filter type to determine exposed form element.
    $form['expose']['filter_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Filter type'),
      '#description' => $this->t('Choose the form element type for the exposed filter.'),
      '#description_display' => 'before',
      '#default_value' => $this->options['expose']['filter_type'],
      '#options' => [
        'date' => $this->t('Date'),
        'datetime' => $this->t('Date and time (separate elements)'),
        'datetime_local' => $this->t('Date and time (combined elements)'),
      ],
      '#access' => $type === 'date' && $this->dateType !== DateTimeType::DATETIME_TYPE_DATE,
    ];
    if ($type === 'date') {
      // Placeholders are only applicable when a value type is offset.
      foreach (['placeholder', 'min_placeholder', 'max_placeholder'] as $key) {
        $form['expose'][$key]['#access'] = FALSE;
      }
    }
  }

  /**
   * Override parent method to change input type.
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state): void {
    parent::buildExposedForm($form, $form_state);
    if ($this->value['type'] === 'date') {
      $filter_type = $this->options['expose']['filter_type'];
      $field_identifier = $this->options['expose']['identifier'];
      // The form elements might be encased in a wrapper, so check that first.
      $field_wrapper = $field_identifier . '_wrapper';
      if (isset($form[$field_wrapper])) {
        foreach (Element::children($form[$field_wrapper][$field_identifier]) as $child) {
          $label = '';
          if ($child === 'min') {
            $label = $this->t('From');
          }
          elseif ($child === 'max') {
            $label = $this->t('To');
          }
          if (!empty($label)) {
            $form[$field_wrapper][$field_identifier][$child]['#title'] = $label;
          }
        }
      }
      elseif (isset($form[$field_identifier])) {
        $label = $this->exposedInfo()['label'];
        if (!empty($label)) {
          $form[$field_identifier]['#title'] = $label;
        }
      }

      if (in_array($this->operator, ['between', 'not between'], TRUE)) {
        // Check the element input matches the form structure.
        $input = $form_state->getUserInput();
        if (isset($input[$field_identifier], $input[$field_identifier]['min']) && !is_array($input[$field_identifier]['min']) && $value = $input[$field_identifier]['min']) {
          $date = new DrupalDateTime($value);
          if ($filter_type === 'datetime') {
            $input[$field_identifier]['min'] = [
              'date' => $date->format('Y-m-d'),
              'time' => $date->format('H:i:s'),
            ];
          }
          else {
            $input[$field_identifier]['min'] = [
              'date' => $date->format($this->dateFormat),
            ];
          }
        }
        if (isset($input[$field_identifier], $input[$field_identifier]['max']) && !is_array($input[$field_identifier]['max']) && $value = $input[$field_identifier]['max']) {
          $date = new DrupalDateTime($value);
          if ($filter_type === 'datetime') {
            $input[$field_identifier]['max'] = [
              'date' => $date->format('Y-m-d'),
              'time' => $date->format('H:i:s'),
            ];
          }
          else {
            $input[$field_identifier]['max'] = [
              'date' => $date->format($this->dateFormat),
            ];
          }
        }
        $form_state->setUserInput($input);
      }
      else {
        // Check the element input matches the form structure.
        $input = $form_state->getUserInput();
        if (isset($input[$field_identifier]) && !is_array($input[$field_identifier]) && $value = $input[$field_identifier]) {
          $date = new DrupalDateTime($value);
          if ($filter_type === 'datetime') {
            $input[$field_identifier] = [
              'date' => $date->format('Y-m-d'),
              'time' => $date->format('H:i:s'),
            ];
          }
          else {
            $input[$field_identifier] = [
              'date' => $date->format($this->dateFormat),
            ];
          }
        }
        $form_state->setUserInput($input);
      }
    }
  }

  /**
   * Override parent method, which deals with dates as integers.
   */
  protected function opBetween($field): void {
    $origin_offset = 0;

    if ($this->value['type'] === 'date' && !$this->isAGroup()) {
      $min_date = $this->value['min'];
      $max_date = $this->value['max'];
      if (!$min_date instanceof DrupalDateTime || !$max_date instanceof DrupalDateTime) {
        return;
      }
    }
    else {
      $timezone = $this->getTimezone();
      $origin_offset = $this->getOffset($this->value['min'], $timezone);
      // Although both 'min' and 'max' values are required, default empty 'min'
      // value as UNIX timestamp 0.
      $min = (!empty($this->value['min'])) ? $this->value['min'] : '@0';
      $min_date = new DateTimePlus($min, new \DateTimeZone($timezone));
      $max_date = new DateTimePlus($this->value['max'], new \DateTimeZone($timezone));
    }

    // Convert to ISO format and format for query. UTC timezone is used since
    // dates are stored in UTC.
    $a = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($min_date->getTimestamp() + $origin_offset, 'custom', DateTimeTypeInterface::DATETIME_STORAGE_FORMAT, DateTimeTypeInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    $b = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($max_date->getTimestamp() + $origin_offset, 'custom', DateTimeTypeInterface::DATETIME_STORAGE_FORMAT, DateTimeTypeInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    // This is safe because we are manually scrubbing the values.
    $operator = strtoupper($this->operator);
    // The parent class defines $field as an object for some reason but
    // getDateField() expects a string.
    // @phpstan-ignore argument.type
    $field = $this->query->getDateFormat($this->query->getDateField($field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    if ($this->query instanceof Sql) {
      $this->query->addWhereExpression($this->options['group'], "$field $operator $a AND $b");
    }
  }

  /**
   * Override parent method, which deals with dates as integers.
   *
   * @param string $field
   *   The field.
   *
   * @throws \DateInvalidTimeZoneException
   * @throws \DateMalformedStringException
   */
  protected function opSimple($field): void {
    $origin_offset = 0;

    if ($this->value['type'] === 'date' && !$this->isAGroup()) {
      if (empty($this->value['value'])) {
        return;
      }
      if ($this->value['value'] instanceof DrupalDateTime) {
        $date = $this->value['value'];
      }
      else {
        $date = new DrupalDateTime($this->value['value']);
      }
    }
    else {
      $timezone = $this->getTimezone();
      $origin_offset = $this->getOffset($this->value['value'], $timezone);
      // Convert to ISO. UTC timezone is used since dates are stored in UTC.
      $date = new DateTimePlus($this->value['value'], new \DateTimeZone($timezone));
    }

    // Convert to ISO. UTC timezone is used since dates are stored in UTC.
    $value = $this->query->getDateFormat($this->query->getDateField("'" . $this->dateFormatter->format($date->getTimestamp() + $origin_offset, 'custom', DateTimeTypeInterface::DATETIME_STORAGE_FORMAT, DateTimeTypeInterface::STORAGE_TIMEZONE) . "'", TRUE, $this->calculateOffset), $this->dateFormat, TRUE);

    // This is safe because we are manually scrubbing the value.
    $field = $this->query->getDateFormat($this->query->getDateField($field, TRUE, $this->calculateOffset), $this->dateFormat, TRUE);
    if ($this->query instanceof Sql) {
      $this->query->addWhereExpression($this->options['group'], "$field $this->operator $value");
    }
  }

  /**
   * Get the proper time zone to use in computations.
   *
   * Date-only fields do not have a time zone associated with them, so the
   * filter input needs to use UTC for reference. Otherwise, use the time zone
   * for the current user.
   *
   * @return string
   *   The time zone name.
   */
  protected function getTimezone(): string {
    return $this->dateFormat === DateTimeTypeInterface::DATE_STORAGE_FORMAT
      ? DateTimeTypeInterface::STORAGE_TIMEZONE
      : date_default_timezone_get();
  }

  /**
   * Get the proper offset from UTC to use in computations.
   *
   * @param string $time
   *   A date/time string compatible with \DateTime. It is used as the
   *   reference for computing the offset, which can vary based on the time
   *   zone rules.
   * @param string $timezone
   *   The time zone that $time is in.
   *
   * @return int
   *   The computed offset in seconds.
   *
   * @throws \DateInvalidTimeZoneException
   * @throws \DateMalformedStringException
   */
  protected function getOffset(string $time, string $timezone): int {
    // Date-only fields do not have a time zone or offset from UTC associated
    // with them. For relative (i.e. 'offset') comparisons, we need to compute
    // the user's offset from UTC for use in the query.
    $origin_offset = 0;
    if ($this->dateFormat === DateTimeTypeInterface::DATE_STORAGE_FORMAT && $this->value['type'] === 'offset') {
      $origin_offset = $origin_offset + timezone_offset_get(new \DateTimeZone(date_default_timezone_get()), new \DateTime($time, new \DateTimeZone($timezone)));
    }

    return $origin_offset;
  }

  /**
   * Helper function to build a form field.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $type
   *   The value type selected from filter settings.
   * @param string $key
   *   The form element key.
   * @param string $title
   *   The title of the form element.
   * @param bool $auto_focus
   *   A boolean value to set autofocus when not exposed.
   *
   * @return array<string, mixed>
   *   The field array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function buildFormField(FormStateInterface $form_state, string $type, string $key, string $title, bool $auto_focus = FALSE): array {
    $filter_type = $this->options['expose']['filter_type'];
    $exposed = (bool) $form_state->get('exposed');
    if ($type === 'date' && !$this->isAGroup()) {
      $value_date = NULL;
      if (!empty($this->value[$key])) {
        $value_date = new DrupalDateTime($this->value[$key]);
      }
      $date_storage = $this->entityTypeManager->getStorage('date_format');
      $element_type = 'custom_field_datetime_date';
      $date_element = $exposed ? 'date' : 'datetime-local';
      $time_element = 'none';
      $date_format = DateTimeTypeInterface::DATE_STORAGE_FORMAT;
      $time_format = '';
      $date_timezone = date_default_timezone_get();
      if ($exposed) {
        switch ($filter_type) {
          case 'datetime':
            $element_type = 'custom_field_datetime';
            $time_element = 'time';
            $time_format = $date_storage->load('html_time')->getPattern();
            break;

          case 'date':
            $date_timezone = DateTimeTypeInterface::STORAGE_TIMEZONE;
            break;

          case 'datetime_local':
            $date_element = 'datetime-local';
            $date_format = DateTimeTypeInterface::DATETIME_STORAGE_FORMAT;
            break;
        }
      }
      $element = [
        '#type' => $element_type,
        '#date_increment' => 1,
        '#date_date_element' => $date_element,
        '#date_date_format' => $date_format,
        '#date_date_callbacks' => [],
        '#date_time_element' => $time_element,
        '#date_time_format' => $time_format,
        '#date_time_callbacks' => [],
        '#date_timezone' => $date_timezone,
        '#default_value' => $value_date,
        '#attached' => [
          'library' => ['custom_field/custom-field-widget'],
        ],
        '#wrapper_attributes' => [
          'class' => ['views-exposed-form__item'],
        ],
      ];
      if ($filter_type === 'datetime' && $exposed) {
        $element['#theme'] = 'custom_field_flex_wrapper';
        $element['#theme_wrappers'] = ['fieldset'];
      }
    }
    else {
      $element = [
        '#type' => 'textfield',
        '#size' => 30,
        '#default_value' => $this->value[$key],
      ];
      if ($key === 'value' && !empty($this->options['expose']['placeholder'])) {
        $element['#attributes']['placeholder'] = $this->options['expose']['placeholder'];
      }
      elseif ($key === 'min' && !empty($this->options['expose']['min_placeholder'])) {
        $element['#attributes']['placeholder'] = $this->options['expose']['min_placeholder'];
      }
      elseif ($key === 'max' && !empty($this->options['expose']['max_placeholder'])) {
        $element['#attributes']['placeholder'] = $this->options['expose']['max_placeholder'];
      }
    }

    if (!$exposed) {
      $element['#attributes']['autofocus'] = $auto_focus;
    }

    if (!empty($title)) {
      $element['#title'] = $this->t('@title', ['@title' => $title]);
    }

    return $element;
  }

  /**
   * Override parent method to remove 'regular_expression' as an option.
   *
   * Since we're operating on date fields, and have a date (and maybe time)
   * picker as the widget (not a text field), a 'Regular expression' operation
   * makes no sense.
   *
   * @return mixed[]
   *   An array of operators.
   */
  public function operators(): array {
    $operators = parent::operators();
    unset($operators['regular_expression']);
    unset($operators['not_regular_expression']);
    return $operators;
  }

}
