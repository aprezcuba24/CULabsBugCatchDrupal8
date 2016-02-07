<?php

/**
 * @author: Renier Ricardo Figueredo
 * @mail: aprezcuba24@gmail.com
 */

namespace Drupal\bugcatch;

use CULabs\BugCatch\ErrorHandler\ErrorHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use CULabs\BugCatch\Client\ClientFactory;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class ExceptionSubscriber implements EventSubscriberInterface
{
  /**
   * @inheritdoc
   */
  public static function getSubscribedEvents()
  {
	$events[KernelEvents::EXCEPTION][] = array('onException', -100);
	$events[KernelEvents::REQUEST][] = array('onRequest', 100);

	return $events;
  }

  public function onRequest(GetResponseEvent $event)
  {
    set_error_handler(array($this->getErrorHandler(), "errorHandler"));
  }

  public function onException(GetResponseForExceptionEvent $event)
  {
	$exception = $event->getException();
    if ($exception instanceof HttpException) {
      return;
    }
    $errorHandler = $this->getErrorHandler();
    $request = $event->getRequest();
    $files = array();
    /**@var $file UploadedFile*/
    foreach ($request->files->all() as $file) {
      $files[] = $errorHandler->processObject($file);
    }
    $errorHandler->setFiles($files);
    $errorHandler->setCookie($request->cookies->all());
    $errorHandler->setGet($request->query->all());
    $errorHandler->setPost($request->request->all());
    $user = $errorHandler->processObject(\Drupal::currentUser()->getAccount());
    $errorHandler->setUserData($user);
    $errorHandler->notifyException($exception);
  }

  protected function getErrorHandler()
  {
    $apiKey = trim(\Drupal::config('bugcatch.settings')->get('bugcatch_apikey'));
    $active = trim(\Drupal::config('bugcatch.settings')->get('bugcatch_active'));
    $clientFactory = new ClientFactory($apiKey);

    return new ErrorHandler($clientFactory->getClient(), $active);
  }
}