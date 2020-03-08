<?php

namespace Drupal\commerce_facebook_catalog\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class FacebookCatalogBuilder.
 *
 * @package Drupal\commerce_facebook_catalog\Service
 */
class FacebookCatalogBuilder {

  /**
   * The cache.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * FacebookCatalogBuilder constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(CacheBackendInterface $cache, ConfigFactoryInterface $config_factory, RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager) {
    $this->cache = $cache;
    $this->configFactory = $config_factory;
    $this->currentRequest = $request_stack->getCurrentRequest();
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Builds Facebook catalog XML.
   *
   * @return string
   *   The product XML.
   */
  public function getFacebookCatalogXml() {
    $cid = 'commerce_facebook_catalog';
    $cache = $this->cache->get($cid);

    if ($cache) {
      return $cache->data;
    }
    else {
      $commerce_product_storage = $this->entityTypeManager
        ->getStorage('commerce_product');
      $query = $commerce_product_storage->getQuery();

      $commerce_products = $query
        ->condition('status', TRUE)
        ->condition('body', '', '<>')
        ->condition($query->orConditionGroup()
          ->notExists('field_dont_include_in_fb_catalog')
          ->condition('field_dont_include_in_fb_catalog', FALSE)
        )
        ->execute();
      $commerce_products = $commerce_product_storage->loadMultiple($commerce_products);

      $products = [];
      $base_url = $this->currentRequest->getSchemeAndHttpHost();

      /** @var \Drupal\commerce_product\Entity\Product $commerce_product */
      foreach ($commerce_products as $commerce_product) {
        $what_to_include = $this->configFactory
          ->get('commerce_facebook_catalog.settings')
          ->get('what_to_include');

        if ($what_to_include == 'all_variations') {
          $commerce_product_variations = $commerce_product->getVariations();
        }
        else {
          $commerce_product_variations = [$commerce_product->getDefaultVariation()];
        }

        /** @var \Drupal\commerce_product\Entity\ProductVariation $commerce_product_variation */
        foreach ($commerce_product_variations as $commerce_product_variation) {
          if (!$commerce_product_variation || $commerce_product_variation->isPublished() == FALSE) {
            continue;
          }

          $id = $commerce_product_variation->getSku();
          $title = ucfirst(strtolower($commerce_product_variation->getTitle()));
          $availability = $commerce_product_variation->field_stock->value != NULL ? 'in stock' : 'out of stock';
          $link = $base_url . Url::fromRoute('entity.commerce_product.canonical', [
            'commerce_product' => $commerce_product->id(),
          ], [
            'query' => ['v' => $commerce_product_variation->id()],
          ])->toString();
          $description = strip_tags($commerce_product->body->value);
          $image_link = NULL;

          if (!$commerce_product_variation->get('field_image')->first()) {
            continue;
          }

          if ($commerce_product_variation->get('field_image')->first()->entity) {
            $image_link = file_create_url($commerce_product_variation->get('field_image')->first()->entity->getFileUri());
          }
          $brand = $this->configFactory->get('commerce_facebook_catalog.settings')->get('brand');
          $price = number_format($commerce_product_variation->getPrice()->getNumber(), '2') . ' ' . $commerce_product_variation->getPrice()->getCurrencyCode();
          $google_product_category = NULL;
          if ($commerce_product->field_catalog->entity && $commerce_product->field_catalog->entity->field_google_product_category_id->value) {
            $google_product_category = $commerce_product->field_catalog->entity->field_google_product_category_id->value;
          }
          $sale_price = NULL;
          if ((bool) $commerce_product_variation->field_on_sale->value == TRUE) {
            if ($commerce_product_variation->get('field_sale_price')->getValue()) {
              $field_sale_price = $commerce_product_variation->get('field_sale_price')->first()->toPrice();
              $sale_price = number_format($field_sale_price->getNumber(), '2') . ' ' . $field_sale_price->getCurrencyCode();
            }
          }

          $product = [
            'g:id' => $id,
            'g:title' => $title,
            'g:availability' => $availability,
            'g:condition' => 'new',
            'g:link' => $link,
            'g:description' => $description,
            'g:image_link' => $image_link,
            'g:brand' => $brand,
            'g:price' => $price,
          ];

          if ($google_product_category) {
            $product['g:google_product_category'] = $google_product_category;
          }

          if ($sale_price) {
            $product['g:sale_price'] = $sale_price;
          }

          $products[] = $product;
        }
      }

      $xml = $this->convertToXml($products);
      $this->cache->set($cid, $xml, CacheBackendInterface::CACHE_PERMANENT, ['commerce_product_list', 'commerce_product_variation_list']);
      return $xml;
    }
  }

  /**
   * Converts array to XML string.
   *
   * @param array $products
   *   The product list.
   *
   * @return string
   *   The product XML.
   */
  protected function convertToXml(array $products) {
    $site_name = $this->configFactory->get('system.site')->get('name');
    $site_url = $this->currentRequest->getSchemeAndHttpHost();

    $xml = '<?xml version="1.0"?>';
    $xml .= '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:g="http://base.google.com/ns/1.0">';
    $xml .= '<title>' . $site_name . '</title>';
    $xml .= '<link rel="self" href="' . $site_url . '" />';

    foreach ($products as $product) {
      $xml .= '<entry>';
      foreach ($product as $key => $value) {
        $xml .= "<$key>" . htmlspecialchars(trim($value)) . "</$key>";
      }
      $xml .= '</entry>';
    }

    return $xml . '</feed>';
  }

}
