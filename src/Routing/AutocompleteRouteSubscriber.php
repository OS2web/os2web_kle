<?php

namespace Drupal\os2web_kle\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * OS2Web KLE AutocompleteRouteSubscriber.
 */
class AutocompleteRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('system.entity_autocomplete')) {
      $route->setDefault('_controller', '\Drupal\os2web_kle\Controller\KleAutocompleteController::handleAutocomplete');
    }
  }

}
