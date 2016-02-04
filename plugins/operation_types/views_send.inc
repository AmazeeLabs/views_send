<?php

/**
 * @file
 * CTools plugin. Provides support for Entity Operations to VBO.
 */

$plugin = array(
  'title' => t('Views Send'),
  'list callback' => 'views_send_vbo_views_send_list',
  'handler' => array(
    'file' => 'views_send.class.php',
    'class' => 'ViewsSendVBOOperations',
  ),
);

/**
 * Returns a prepared list of available actions.
 *
 * Actions are fetched by invoking hook_action_info() and by loading
 * advanced actions from the database.
 *
 * @param $operation_id
 *   The full, prefixed operation_id of the operation (in this case, action)
 *   to return, or NULL to return an array with all operations.
 */
function views_send_vbo_views_send_list($operation_id = NULL) {
  // We only provide one operation, and it's not dynamic, so not worth static
  // caching.
  $operations['views_send::views_send'] = array(
    'operation_type' => 'views_send',
    // Hack to allow the action to be available on any view.
    // We need it for http://jira.amazeelabs.com/browse/VNT-783
    'type' => 'system',
    // This can apply to any entity type, as any entity could have a Views field
    // that can be used as an email.
    //'type' => 'user', // TODO! nope, will need one of these per entity type!! array_keys(entity_get_info()),
    // TODO! Can't use 'Send email', there is a core action called this already!
    'label' => t('Send e-mail (via views_send)'),
    // Argh no idea what most of these do. Documentation would be nice!
    'parameters' => array(),
    'configurable' => TRUE,
    // We can't use aggregate mode as we won't get all rows -- have to batch.
    'aggregate' => FALSE,
    'behavior' => array(),
    // Whole load of TODO here!
    'permissions' => NULL,
    'pass rows' => TRUE,
  );

  if (isset($operation_id)) {
    return isset($operations[$operation_id]) ? $operations[$operation_id] : FALSE;
  }
  else {
    return $operations;
  }
}
