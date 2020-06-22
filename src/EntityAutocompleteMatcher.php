<?php

namespace Drupal\os2web_kle;

use Drupal\os2web_kle\Form\SettingsForm;

class EntityAutocompleteMatcher extends \Drupal\Core\Entity\EntityAutocompleteMatcher {

  /**
   * {@inheritdoc}
   */
  public function getMatches($target_type, $selection_handler, $selection_settings, $string = '') {
    $matches = parent::getMatches($target_type, $selection_handler, $selection_settings, $string);

    $config = \Drupal::config(SettingsForm::$configName);
    $hide_deleted = $config->get('autocomplete_hide_deleted_kle');

    if ($hide_deleted) {
      // Removing those that have [udgået] in the label.
      $count = count($matches);
      for ($i = 0; $i < $count; $i++) {
        $label = $matches[$i]['label'];
        if (preg_match('/\[udgået\]/', $label)) {
          unset($matches[$i]);
        }
      }

      // Resetting the indexes.
      $matches = array_values($matches);
    }

    return $matches;
  }

}
