<?php

namespace Drupal\current_user_id\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use \Drupal\user\Entity\User;
//use Drupal\Core\Cache\Cache;
class UserOrdersMenuLink extends MenuLinkDefault {

  protected $currentUser;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticMenuLinkOverridesInterface $static_override, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);

    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('current_user')
    );
  }

  public function getTitle() {
    return $this->t('Edit Profile');
  }

  /*public function getCacheMaxAge() {
    // If you need to redefine the Max Age for that block
    return 0;
  }

  public function getCacheContexts(){
    return ['url.path', 'url.query_args'];
  }*/

  public function getUrlObject($title_attribute = TRUE) {

    $current_user_ID = $this->currentUser->id();


      return Url::fromUri('internal:/user/' . $current_user_ID . '/edit');


  }

}
