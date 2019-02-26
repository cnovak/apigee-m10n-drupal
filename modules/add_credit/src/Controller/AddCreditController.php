<?php

/*
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\apigee_m10n_add_credit\Controller;

use Drupal\apigee_m10n_add_credit\AddCreditConfig;
use Drupal\apigee_m10n_add_credit\Plugin\AddCreditEntityTypeManagerInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class AddCreditController.
 */
class AddCreditController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The commerce_product entity view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $viewBuilder;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The add credit plugin manager.
   *
   * @var \Drupal\apigee_m10n_add_credit\Plugin\AddCreditEntityTypeManagerInterface
   */
  protected $addCreditPluginManager;

  /**
   * AddCreditController constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\EntityViewBuilderInterface $view_builder
   *   The commerce_product entity view builder.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   * @param \Drupal\apigee_m10n_add_credit\Plugin\AddCreditEntityTypeManagerInterface $add_credit_plugin_manager
   *   The add credit plugin manager.
   */
  public function __construct(EntityStorageInterface $storage, EntityViewBuilderInterface $view_builder, ConfigFactoryInterface $config_factory, RouteMatchInterface $route_match, AddCreditEntityTypeManagerInterface $add_credit_plugin_manager) {
    $this->viewBuilder = $view_builder;
    $this->configFactory = $config_factory;
    $this->storage = $storage;
    $this->routeMatch = $route_match;
    $this->addCreditPluginManager = $add_credit_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('commerce_product'),
      $container->get('entity.manager')->getViewBuilder('commerce_product'),
      $container->get('config.factory'),
      $container->get('current_route_match'),
      $container->get('plugin.manager.apigee_add_credit_entity_type')
    );
  }

  /**
   * Returns a renderable array for the add credit page.
   *
   * @param string $currency
   *   The currency id.
   *
   * @return array
   *   A renderable array.
   */
  public function view(string $currency = NULL) {
    // Throw an exception if a product has not been configured for the currency.
    if (!($product = $this->getProductForCurrency($currency))) {
      $this->messenger()->addError($this->t('Cannot add credit to currency @currency_id.', [
        '@currency_id' => $currency,
      ]));
      throw new NotFoundHttpException();
    }

    return $this->viewBuilder->view($product);
  }

  /**
   * Checks access for the add credit routes.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    // Let the plugins determine access.
    return $this->addCreditPluginManager->checkAccessFromRouteMatch($this->routeMatch, $account);
  }

  /**
   * Helper to get the configured product from the currency id.
   *
   * @param string $currency
   *   The currency id.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface|null
   *   A product entity if found. Otherwise null.
   */
  protected function getProductForCurrency(string $currency): ?ProductInterface {
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    if (($product_id = $this->configFactory->get(AddCreditConfig::CONFIG_NAME)->get("products.$currency.product_id"))
      && ($product = $this->storage->load($product_id))) {
      return $product;
    }

    return NULL;
  }

}
