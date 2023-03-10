<?php

namespace Drupal\openai_dblog\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('dblog.event')) {
      $route->setDefaults(
        [
          '_controller' => '\Drupal\openai_dblog\Controller\OpenAIDbLogController::eventDetails',
          '_title' => 'Details',
        ]
      );
    }
  }

}
