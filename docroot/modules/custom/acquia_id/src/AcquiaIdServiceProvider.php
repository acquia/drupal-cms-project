<?php

declare(strict_types=1);

namespace Drupal\acquia_id;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Acquia\Drupal\RecommendedSettings\Helpers\EnvironmentDetector;

final class AcquiaIdServiceProvider extends ServiceProviderBase {

  public function alter(ContainerBuilder $container): void {
    parent::alter($container);

    if (EnvironmentDetector::isProdEnv()) {
      $idp_base_uri = 'https://id.acquia.com/oauth2/default';
      $cloud_api_base_uri = 'https://cloud.acquia.com';
    }
    else {
      $idp_base_uri = 'https://staging.id.acquia.com/oauth2/default';
      $cloud_api_base_uri = 'https://staging.cloud.acquia.com';
    }

    $container->setParameter('acquia_id.idp_base_uri', $idp_base_uri);
    $container->setParameter('acquia_id.cloud_api_base_uri', $cloud_api_base_uri);
    $container->setParameter('acquia_id.idp_logout_redirect_uri', $cloud_api_base_uri);
  }

}
