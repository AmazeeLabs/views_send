<?php

/**
 * @file
 * Defines the class for Entity Operation VBO actions.
 * Belongs to the "action" operation type plugin.
 */

class ViewsSendVBOOperations extends ViewsBulkOperationsBaseOperation {

  /**
   * Contains the options provided by the user in the configuration form.
   *
   * @var array
   */
  public $formOptions = array();

  /**
   * Returns the access bitmask for the operation, used for entity access checks.
   * TODO!
   */
  public function getAccessMask() {
    // Assume edit by default.
    if (!isset($this->operationInfo['behavior'])) {
      $this->operationInfo['behavior'] = array('changes_property');
    }

    $mask = 0;
    if (in_array('views_property', $this->operationInfo['behavior'])) {
      $mask |= VBO_ACCESS_OP_VIEW;
    }
    if (in_array('changes_property', $this->operationInfo['behavior'])) {
      $mask |= VBO_ACCESS_OP_UPDATE;
    }
    if (in_array('creates_property', $this->operationInfo['behavior'])) {
      $mask |= VBO_ACCESS_OP_CREATE;
    }
    if (in_array('deletes_property', $this->operationInfo['behavior'])) {
      $mask |= VBO_ACCESS_OP_DELETE;
    }
    return $mask;
  }

  /**
   * Returns whether the provided account has access to execute the operation.
   *
   * @param $account
   */
  public function access($account) {
    return user_access('mass mailing with views_send', $account);
  }

  /**
   * Returns whether the operation needs the full selected views rows to be
   * passed to execute() as a part of $context.
   */
  public function needsRows() {
    // We always want rows, because we allow our configuration to pick the
    // email off any Views field.
    return TRUE;
  }

  /**
   * Returns the admin options form for the operation.
   *
   * The admin options form is embedded into the VBO field settings and used
   * to configure operation behavior. The options can later be fetched
   * through the getAdminOption() method.
   *
   * @param $dom_id
   *   The dom path to the level where the admin options form is embedded.
   *   Needed for #dependency.
   */
  public function adminOptionsForm($dom_id, $handler) {
    $form = parent::adminOptionsForm($dom_id, $handler);

    //TODO: move this to a patch :(

    $form['settings'] = array(
      '#type' => 'fieldset',
      '#title' => t('Operation settings'),
      '#collapsible' => TRUE,
      '#dependency' => array(
        $dom_id . '-selected' => array(1),
      ),
    );

    $fields = _views_send_get_fields_and_tokens($handler->view, 'fields');
    // TODO: what is this for?
    //$fields_name_text = _views_send_get_fields_and_tokens($handler->view, 'fields_name_text');
    $fields_options = array_merge(array('' => '<' . t('select') . '>'), $fields);

    $form['settings']['views_send_to_name'] = array(
      '#type' => 'select',
      '#title' => t('Field used for recipient\'s name'),
      '#description' => t('Select which field from the current view will be used as recipient\'s name.'),
      '#options' => $fields_options,
      '#default_value' => $handler->options['vbo_operations']['views_send::views_send']['settings']['views_send_to_name'],
    );
    $form['settings']['views_send_to_mail'] = array(
      '#type' => 'select',
      '#title' => t('Field used for recipient\'s e-mail'),
      '#description' => t('Select which field from the current view will be used as recipient\'s e-mail.'),
      '#options' => $fields_options,
      '#default_value' => $handler->options['vbo_operations']['views_send::views_send']['settings']['views_send_to_mail'],
      // TODO: TEMP to get it to work!
      //'#required' => TRUE,
    );

    return $form;
  }

  /**
   * Returns the configuration form for the operation.
   * Only called if the operation is declared as configurable.
   *
   * @param $form
   *   The views form.
   * @param $form_state
   *   An array containing the current state of the form.
   * @param $context
   *   An array of related data provided by the caller.
   */
  public function form($form, &$form_state, array $context) {
    $view = $context['view'];
    $display = $view->name . ':' . $view->current_display;

    $fields = _views_send_get_fields_and_tokens($view, 'fields');
    $tokens = _views_send_get_fields_and_tokens($view, 'tokens');
    $fields_name_text = _views_send_get_fields_and_tokens($view, 'fields_name_text');
    $fields_options = array_merge(array('' => '<' . t('select') . '>'), $fields);
    
    $handler = _views_bulk_operations_get_field($view);

    // Add the values from configuration to the form now so we have them in
    // submit, as this appears to be the last point at which we have access to
    // the view and our field handler.
    $field_send_to_name = $handler->options['vbo_operations']['views_send::views_send']['settings']['views_send_to_name'];
    $field_send_to_mail = $handler->options['vbo_operations']['views_send::views_send']['settings']['views_send_to_mail'];
    // We have the field names, but we need to have the aliases they use in the
    // view result.
    // Get the field handlers.
    $fields = $view->display_handler->get_handlers('field');
    // Get the field aliases, and store them in the form.
    $form['#views_send_to_name'] = $fields[$field_send_to_name]->field_alias;
    $form['#views_send_to_mail'] = $fields[$field_send_to_mail]->field_alias;
    $form['#views_send_tokens'] = $tokens;
    $form['#view'] = $view;

    $form['from'] = array(
      '#type' => 'fieldset',
      '#title' => t('Sender'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
    $form['from']['views_send_from_name'] = array(
      '#type' => 'textfield',
      '#title' => t('Sender\'s name'),
      '#description' => t("Enter the sender's human readable name."),
      '#default_value' => variable_get('views_send_from_name_' . $display, variable_get('site_name', '')),
      '#maxlen' => 255,
    );
    $form['from']['views_send_from_mail'] = array(
      '#type' => 'textfield',
      '#title' => t('Sender\'s e-mail'),
      '#description' => t("Enter the sender's e-mail address."),
      '#required' => TRUE,
      '#default_value' => variable_get('views_send_from_mail_' . $display, variable_get('site_mail', ini_get('sendmail_from'))),
      '#maxlen' => 255,
    );

    $form['mail'] = array(
      '#type' => 'fieldset',
      '#title' => t('E-mail content'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
    $form['mail']['views_send_subject'] = array(
      '#type' => 'textfield',
      '#title' => t('Subject'),
      '#description' => t('Enter the e-mail\'s subject. You can use tokens in the subject.'),
      '#maxlen' => 255,
      '#required' => TRUE,
      // TEMP FOR DEV!
      '#default_value' => 'SUBJECT', // variable_get('views_send_subject_' . $display, ''),
    );

    $saved_message = variable_get('views_send_message_' . $display);
    $form['mail']['views_send_message'] = array(
      '#type' => 'text_format',
      '#format' => isset($saved_message['format']) ? $saved_message['format'] : 'plain_text',
      '#title' => t('Message'),
      '#description' => t('Enter the body of the message. You can use tokens in the message.'),
      '#required' => TRUE,
      '#rows' => 10,
      // TEMP FOR DEV!
      '#default_value' => 'Dear [views-send:name], it is [current-date:long] today.', //isset($saved_message['value']) ? $saved_message['value'] : '',
    );

    $form['mail']['token'] = array(
      '#type' => 'fieldset',
      '#title' => t('Tokens'),
      '#description' => t('You can use the following tokens in the subject or message.'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    if (!module_exists('token')) {
      $form['mail']['token']['tokens'] = array(
        '#markup' => theme('views_send_token_help', $fields_name_text)
      );
    }
    else {
      $form['mail']['token']['views_send'] = array(
        '#type' => 'fieldset',
        '#title' => t('Views Send specific tokens'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      );
      $form['mail']['token']['views_send']['tokens'] = array(
        '#markup' => theme('views_send_token_help', $fields_name_text)
      );
      $form['mail']['token']['general'] = array(
        '#type' => 'fieldset',
        '#title' => t('General tokens'),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
      );
      $token_types = array('site', 'user', 'node', 'current-date');
      $form['mail']['token']['general']['tokens'] = array(
        '#markup' => theme('token_tree', array('token_types' => $token_types))
      );
    }

    if (VIEWS_SEND_MIMEMAIL && user_access('attachments with views_send')) {
      // set the form encoding type
      $form['#attributes']['enctype'] = "multipart/form-data";

      // add a file upload file
      $form['mail']['views_send_attachments'] = array(
        '#type' => 'file',
        '#title' => t('Attachment'),
        '#description' => t('NB! The attached file is stored once per recipient in the database if you aren\'t sending the message directly.'),
      );
    }

    return $form;
  }

  /**
   * Validates the configuration form.
   * Only called if the operation is declared as configurable.
   *
   * @param $form
   *   The views form.
   * @param $form_state
   *   An array containing the current state of the form.
   */
  public function formValidate($form, &$form_state) {
    // TODO.
  }

  /**
   * Handles the submitted configuration form.
   * This is where the operation can transform and store the submitted data.
   * Only called if the operation is declared as configurable.
   *
   * @param $form
   *   The views form.
   * @param $form_state
   *   An array containing the current state of the form.
   */
  public function formSubmit($form, &$form_state) {

    $subject = $form_state['values']['views_send_subject'];
    $body = $form_state['values']['views_send_message']['value'];
    $params['format'] = $form_state['values']['views_send_message']['format'];

    // Frankencoding from views_send_queue_mail() from here on!
    // TODO: Clean up, add the bits I've missed!

    // This shouldn't happen, but better be 100% sure.
    // TODO! move this to validate() step!
    /*
    if (!filter_access($formats[$params['format']])) {
      drupal_set_message(t('No mails sent since an illegal format is selected for the message.'));
      return;
    }
    */

    $body = check_markup($body, $params['format']);

    // TODO: tokens, etc.


    $attachments = array();
    if (VIEWS_SEND_MIMEMAIL && user_access('attachments with views_send') && isset($_FILES['files']) && is_uploaded_file($_FILES['files']['tmp_name']['views_send_attachments'])) {
      $dir = file_default_scheme() . '://views_send_attachments';
      file_prepare_directory($dir, FILE_CREATE_DIRECTORY);
      $file = file_save_upload('views_send_attachments', array(), $dir);
      if ($file) {
        $attachments[] = (array) $file;
      }
    }


    $this->formOptions = array(
      'subject' => strip_tags($subject),
      'body' => $body,
      'views_send_from_name' => $form_state['values']['views_send_from_name'],
      'views_send_from_mail' => $form_state['values']['views_send_from_mail'],
      'views_send_to_name' => $form['#views_send_to_name'],
      'views_send_to_mail' => $form['#views_send_to_mail'],
      'views_send_tokens' => $form['#views_send_tokens'],
      'view' => $form['#view'],
      'views_send_attachments' => $attachments,
    );
  }

  /**
   * Executes the selected operation on the provided data.
   *
   * @param $data
   *   The data to to operate on. An entity or an array of entities.
   * @param $context
   *   An array of related data (selected views rows, etc).
   */
  public function execute($data, array $context) {
    // When in batch context, $data is an entity, and $context[rows] is just a
    // single view result row.
    $entity = $data;
    $row = reset($context['rows']);
    $row_id = key($context['rows']);

    $view = $this->formOptions['view'];

    global $user;

    // Frankencoding from views_send_queue_mail() from here on!
    // TODO: Clean up, add the bits I've missed!

    // From: parts.
    $from_name = $this->formOptions['views_send_from_name'];
    $from_mail = $this->formOptions['views_send_from_mail'];

    // To: parts.
    $to_name_key = $this->formOptions['views_send_to_name'];
    $to_name = trim(strip_tags($row->{$to_name_key}));

    $to_mail_key = $this->formOptions['views_send_to_mail'];
    $to_mail = trim(strip_tags($row->{$to_mail_key}));

    /*
    // TODO: move to formSubmit()
    if (!VIEWS_SEND_MIMEMAIL || (variable_get('mimemail_format', 'plain_text') == 'plain_text')) {
      $body = drupal_html_to_text($body);
    }
    */

    // TODO: temp code to get this work! clean up!
    $params['format'] = 'plain_text';
    if ($params['format'] == 'plain_text') {
      $plain_format = TRUE;
    }
    else {
      $plain_format = FALSE;
    }

    // Populate row/context tokens.
    $token_keys = $token_values = array();
    foreach ($this->formOptions['views_send_tokens'] as $field_key => $field_name) {
      $token_keys[] = VIEWS_SEND_TOKEN_PREFIX .  sprintf(VIEWS_SEND_TOKEN_PATTERN, $field_name) . VIEWS_SEND_TOKEN_POSTFIX;
      $token_values[] = $this->formOptions['view']->style_plugin->get_field($row_id, $field_name);
    }

    // Views Send specific token replacements
    $subject = str_replace($token_keys, $token_values, $this->formOptions['subject']);
    $body = str_replace($token_keys, $token_values, $this->formOptions['body']);

    // Global token replacement, and node/user token replacements
    // if a nid/uid is found in the views result row.
    // TODO: this could be made generic, based on the view base entity.
    $token_data = array();
    if (property_exists($row, 'uid')) {
      $token_data['user'] = user_load($view->result[$row_id]->uid);
    }
    if (property_exists($row, 'nid')) {
      $token_data['node'] = node_load($view->result[$row_id]->nid);
    }
    $subject = token_replace($subject, $token_data);
    $body = token_replace($body, $token_data);


    // TODO: MIMEMAIL



    // We transform receipt, priority in headers,
    // merging them to the user defined headers.
    // TODO: temp code to get it to work!
    $params += array(
      'views_send_receipt' => FALSE,
      'views_send_priority' => 1,
      'views_send_headers' => '',
    );
    $headers = _views_send_headers($params['views_send_receipt'], $params['views_send_priority'], $from_mail, $params['views_send_headers']);

    $attachments = isset($this->formOptions['views_send_attachments']) ?  $this->formOptions['views_send_attachments'] : array();

    $message = array(
      'uid' => $user->uid,
      'timestamp' => time(),
      'from_name' => $from_name,
      'from_mail' => $from_mail,
      'to_name' => $to_name,
      'to_mail' => $to_mail,
      'subject' => $subject,
      'body' => $body,
      'headers' => $headers,
    );

    // Enable other modules to alter the actual message before queueing it
    // by providing the hook 'views_send_mail_alter'.
    drupal_alter('views_send_mail', $message);

    _views_send_prepare_mail($message, $plain_format, $attachments);
    views_send_deliver($message);

    module_invoke_all('views_send_email_sent', $message);

  }

}
