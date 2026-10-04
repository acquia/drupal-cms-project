<?php

namespace Drupal\eca\Plugin;

use Drupal\modeler_api\Api;

/**
 * Provides a list of favorite plugins.
 */
class FavoritePlugins {

  /**
   * The list of favorite plugin IDs.
   *
   * @var array|array[]
   */
  public static array $favoritePlugins = [
    Api::COMPONENT_TYPE_START => [
      'eca_base:eca_custom',
      'eca_base:eca_tool',
      'content_entity:update',
      'content_entity:presave',
      'content_entity:custom',
      'content_entity:insert',
      'user:login',
      'form:form_build',
      'form:form_validate',
      'eca_endpoint:response',
      'eca_endpoint:access',
      'log:log_message',
      'eca_base:eca_cron',
      'kernel:controller',
    ],
    Api::COMPONENT_TYPE_LINK => [
      'eca_scalar',
      'eca_entity_field_value',
      'eca_count',
      'eca_current_user_role',
      'eca_user_role',
      'eca_entity_field_value_empty',
      'eca_entity_type_bundle',
      'eca_entity_exists',
      'eca_route_match',
      'eca_entity_is_new',
      'eca_tamper_condition:math',
      'eca_current_user_permission',
    ],
    Api::COMPONENT_TYPE_ELEMENT => [
      'action_message_action',
      'eca_token_set_value',
      'eca_set_field_value',
      'eca_switch_account',
      'eca_token_load_entity',
      'eca_set_tool_output',
      'eca_list_remove',
      'eca_trigger_content_entity_custom_event',
      'action_send_email_action',
      'eca_views_query',
      'eca_tamper:math',
      'eca_get_field_value',
      'eca_void_and_condition',
      'eca_save_entity',
      'eca_new_entity',
      'action_goto_action',
      'eca_trigger_custom_event',
      'eca_token_load_route_param',
      'eca_tamper:explode',
      'eca_access_set_result',
    ],
  ];

}
