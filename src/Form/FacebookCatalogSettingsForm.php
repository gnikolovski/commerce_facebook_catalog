<?php

namespace Drupal\commerce_facebook_catalog\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class FacebookCatalogSettingsForm.
 *
 * @package Drupal\commerce_facebook_catalog\Form
 */
class FacebookCatalogSettingsForm extends ConfigFormBase {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * FacebookCatalogSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RequestStack $request_stack) {
    parent::__construct($config_factory);
    $this->currentRequest = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_facebook_catalog_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'commerce_facebook_catalog.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('commerce_facebook_catalog.settings');

    $form['facebook_catalog_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Facebook Catalog'),
      '#default_value' => $config->get('facebook_catalog_enabled'),
    ];

    $form['what_to_include'] = [
      '#type' => 'radios',
      '#title' => $this->t('What to include?'),
      '#options' => [
        'all_variations' => $this->t('All Variations'),
        'default_variation' => $this->t('Default Variation'),
      ],
      '#default_value' => !empty($config->get('what_to_include')) ? $config->get('what_to_include') : 'default_variation',
      '#states' => [
        'visible' => [
          ':input[name="facebook_catalog_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['facebook_catalog_feed_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facebook Catalog feed url'),
      '#disabled' => TRUE,
      '#default_value' => $this->currentRequest->getSchemeAndHttpHost() . '/facebook/catalog',
      '#states' => [
        'visible' => [
          ':input[name="facebook_catalog_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['facebook_catalog_feed_url_open_page'] = [
      '#type' => 'link',
      '#title' => $this->t('Open feed page'),
      '#url' => Url::fromRoute('commerce_facebook_catalog.facebook_catalog'),
      '#attributes' => [
        'target' => [
          '_blank',
        ],
      ],
      '#suffix' => '<br><br>',
    ];

    $form['brand'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Brand name'),
      '#default_value' => $config->get('brand'),
      '#states' => [
        'visible' => [
          ':input[name="facebook_catalog_enabled"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('commerce_facebook_catalog.settings');

    $config
      ->set('facebook_catalog_enabled', $form_state->getValue('facebook_catalog_enabled'))
      ->set('what_to_include', $form_state->getValue('what_to_include'))
      ->set('brand', $form_state->getValue('brand'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
