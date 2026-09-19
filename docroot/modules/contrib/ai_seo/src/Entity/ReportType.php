<?php

namespace Drupal\ai_seo\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the Report Type entity.
 *
 * @ConfigEntityType(
 *   id = "ai_seo_report_type",
 *   label = @Translation("AI SEO Report Type"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\ai_seo\ReportTypeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_seo\Form\ReportTypeForm",
 *       "edit" = "Drupal\ai_seo\Form\ReportTypeForm",
 *       "delete" = "Drupal\ai_seo\Form\ReportTypeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\ai_seo\ReportTypeHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "report_type",
 *   admin_permission = "administer ai seo settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/ai-seo/report-types/{ai_seo_report_type}",
 *     "add-form" = "/admin/config/ai-seo/report-types/add",
 *     "edit-form" = "/admin/config/ai-seo/report-types/{ai_seo_report_type}/edit",
 *     "delete-form" = "/admin/config/ai-seo/report-types/{ai_seo_report_type}/delete",
 *     "collection" = "/admin/config/ai-seo/report-types"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "prompt",
 *     "status",
 *     "default_prompt_hash"
 *   }
 * )
 */
class ReportType extends ConfigEntityBase implements ReportTypeInterface {

  /**
   * The Report Type ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Report Type label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Report Type description.
   *
   * @var string
   */
  protected $description;

  /**
   * The prompt text for this report type.
   *
   * @var string
   */
  protected $prompt;

  /**
   * MD5 of the shipped default prompt at the time this entity was last
   * initialised or auto-updated from a module default.
   *
   * Never written by the admin UI form, so comparing md5(prompt) against this
   * value reveals whether the admin has customised the prompt since the last
   * default update.
   *
   * @var string|null
   */
  protected $default_prompt_hash;

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrompt() {
    return $this->prompt;
  }

  /**
   * {@inheritdoc}
   */
  public function setPrompt($prompt) {
    $this->prompt = $prompt;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultPromptHash(): ?string {
    return $this->default_prompt_hash ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultPromptHash(string $hash): static {
    $this->default_prompt_hash = $hash;
    return $this;
  }

}
