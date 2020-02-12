<?php

namespace Drupal\commerce_facebook_catalog\Controller;

use Drupal\commerce_facebook_catalog\Service\FacebookCatalogBuilder;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class FacebookCatalogController.
 *
 * @package Drupal\commerce_facebook_catalog\Controller
 */
class FacebookCatalogController extends ControllerBase {

  /**
   * The facebook catalog builder.
   *
   * @var \Drupal\commerce_facebook_catalog\Service\FacebookCatalogBuilder
   */
  protected $facebookCatalogBuilder;

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * FacebookCatalogController constructor.
   *
   * @param \Drupal\commerce_facebook_catalog\Service\FacebookCatalogBuilder $facebook_catalog_builder
   *   The facebook catalog builder.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config.
   */
  public function __construct(FacebookCatalogBuilder $facebook_catalog_builder, ConfigFactoryInterface $config_factory) {
    $this->facebookCatalogBuilder = $facebook_catalog_builder;
    $this->config = $config_factory->get('commerce_facebook_catalog.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_facebook_catalog.facebook_catalog_builder'),
      $container->get('config.factory')
    );
  }

  /**
   * Returns Facebook Catalog feed.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response.
   */
  public function content() {
    if ((bool) $this->config->get('facebook_catalog_enabled') == FALSE) {
      return new Response();
    }

    $facebook_catalog_xml = $this->facebookCatalogBuilder->getFacebookCatalogXml();
    $response = new Response();
    $response->setContent($facebook_catalog_xml);
    return $response;
  }

}
