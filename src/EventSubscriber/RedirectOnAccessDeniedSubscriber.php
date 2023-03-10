<?php

namespace Drupal\opigno_dashboard\EventSubscriber;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class RedirectOnAccessDeniedSubscriber.
 */
class RedirectOnAccessDeniedSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * Constructs a new ResponseSubscriber instance.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(AccountInterface $current_user) {
    $this->user = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * @return \Drupal\Core\Session\AccountInterface
   */
  public function onKernelRequest(RequestEvent $event) {
    $is_anonymous = $this->user->isAnonymous();
    // Add the route name as an extra class to body.
    $route = (string) \Drupal::routeMatch()->getRouteName();
    if($is_anonymous && !in_array($route, [
      'user.login',
      'user.register',
      'user.pass',
      'view.frontpage.page_1',
      'view.opigno_training_catalog.training_catalogue',
      'system.403',
    ])) {
      $request = $event->getRequest();
      $access_result = AccessResult::neutral();
      if (!$access_result->isAllowed()) {
        if ($access_result instanceof CacheableDependencyInterface && $request->isMethodCacheable()) {
          throw new CacheableAccessDeniedHttpException($access_result, $access_result instanceof AccessResultReasonInterface ? $access_result->getReason() : NULL);
        }
        else {
          throw new AccessDeniedHttpException($access_result instanceof AccessResultReasonInterface ? $access_result->getReason() : NULL);
        }
      }
    }
  }

  /**
   * Redirect if 403 and node an event.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The route building event.
   */
  public function redirectOn403(ResponseEvent $event) {
    $route_name = \Drupal::routeMatch()->getRouteName();
    $status_code = $event->getResponse()->getStatusCode();
    $is_anonymous = $this->user->isAnonymous();

    // Do not redirect if there is REST request.
    if (strpos($route_name, 'rest.') !== FALSE) {
      return;
    }

    // Do not redirect if there is a token authorization.
    $auth_header = $event->getRequest()->headers->get('Authorization') ?? '';
    if ($is_anonymous && preg_match('/^Bearer (.*)/', $auth_header)) {
      return;
    }

    if ($is_anonymous && $status_code === 403) {
      $current_path = \Drupal::service('path.current')->getPath();

      // Filter out ajax requests from opigno_social from redirect.
      if (!str_contains($current_path, '/ajax/')) {
        $response = new RedirectResponse(\Drupal::request()
          ->getBasePath() . "/user/login/?prev_path={$current_path}");

        $event->setResponse($response);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['redirectOn403'];
    return $events;
  }

}
