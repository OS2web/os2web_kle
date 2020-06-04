<?php

namespace Drupal\os2web_kle\Form;

/**
 * @file
 * Contains \Drupal\os2web_kle\Form\SettingsForm.
 */

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * OS2Web KLE settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Name of the config.
   *
   * @var string
   */
  public static $configName = 'os2web_kle.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'os2web_kle_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [SettingsForm::$configName];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(SettingsForm::$configName);

    $form['webservice_url'] = [
      '#title' => t('URL to KLE XML fil'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('webservice_url'),
      '#description' => t('URL of the webservice for KLE'),
    ];

    $form['local_file_url'] = [
      '#title' => t('URL til lokal KLE XML fil'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('local_file_url'),
      '#description' => t('Path to the file where the converted XML shall be placed (e.g. public://kle.xml).'),
    ];

    $form['retsinfo_url'] = [
      '#title' => t('Base URL to retsinfo (MUST end with "/")'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('retsinfo_url'),
      '#description' => t('For example, http://www.retsinfo.dk/_GETDOC_/ACCN/'),
    ];

    $form['import_interval_days'] = [
      '#title' => t('Amount of days between imports'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $config->get('import_interval_days'),
      '#description' => t('Import will only be run if the specified amount of days has passed'),
    ];

    $form['feeds_remove_deleted_kle'] = [
      '#title' => t('Remove obsolete KLE from Feeds file'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('feeds_remove_deleted_kle'),
      '#description' => t('Upon regeneration of Feeds file, the obsolete KLE will be removed (requires reimport)'),
    ];

    $form['autocomplete_hide_deleted_kle'] = [
      '#title' => t('Hide obsolete KLE from autocomplete list'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('autocomplete_hide_deleted_kle'),
      '#description' => t('Obsolete KLE will not be part of autocomplete, when KLE is used as autocomplete term reference (does not require reimport)'),
    ];

    $state = \Drupal::service('state');

    $lastImport = $state->get('os2web_kle.import_last_run');
    $lastImport_date = ($lastImport != 0 ? \Drupal::service('date.formatter')
      ->format($lastImport, 'long') : t('Never'));

    // Display when import last ran,
    $form[] = [
      '#prefix' => '<p>',
      '#suffix' => '</p>',
      '#markup' => t('Last import run:') . '<em>' . $lastImport_date . '</em>',
    ];

    // Display when next import will take place.
    $days = $config->get('import_interval_days');

    if ($lastImport) {
      $next_scheduled_run = strtotime("+$days days", $lastImport);
      $next_scheduled_run_date = \Drupal::service('date.formatter')
        ->format($next_scheduled_run, 'long');
    }
    else {
      $next_scheduled_run_date = t('Now');
    }

    $form[] = [
      '#prefix' => '<p>',
      '#suffix' => '</p>',
      '#markup' => t('Scheduled next run: %next_scheduled_run_date', ['%next_scheduled_run_date' => $next_scheduled_run_date]),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $config = $this->config(SettingsForm::$configName);
    foreach ($values as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
