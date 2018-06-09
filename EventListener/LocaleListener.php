<?php

namespace NetBull\CoreBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use NetBull\CoreBundle\Locale\Events;
use NetBull\CoreBundle\Locale\LocaleGuesserManager;
use NetBull\CoreBundle\Event\FilterLocaleSwitchEvent;
use NetBull\CoreBundle\Locale\Matcher\BestLocaleMatcherInterface;

/**
 * Class LocaleListener
 * @package NetBull\CoreBundle\EventListener
 */
class LocaleListener implements EventSubscriberInterface
{
    /**
     * @var string Default framework locale
     */
    private $defaultLocale;

    /**
     * @var LocaleGuesserManager
     */
    private $guesserManager;

    /**
     * @var BestLocaleMatcherInterface
     */
    private $bestLocaleMatcher;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EventDispatcher
     */
    private $dispatcher;

    /**
     * @var boolean
     */
    private $disableVaryHeader = false;

    /**
     * @var string
     */
    private $excludedPattern;

    /**
     * LocaleListener constructor.
     * @param string $defaultLocale
     * @param LocaleGuesserManager $guesserManager
     * @param BestLocaleMatcherInterface|null $bestLocaleMatcher
     * @param LoggerInterface|null $logger
     */
    public function __construct($defaultLocale = 'en', LocaleGuesserManager $guesserManager, BestLocaleMatcherInterface $bestLocaleMatcher = null, LoggerInterface $logger = null)
    {
        $this->defaultLocale    = $defaultLocale;
        $this->guesserManager   = $guesserManager;
        $this->bestLocaleMatcher = $bestLocaleMatcher;
        $this->logger           = $logger;
    }

    /**
     * Called at the "kernel.request" event
     *
     * Call the LocaleGuesserManager to guess the locale
     * by the activated guessers
     *
     * Sets the identified locale as default locale to the request
     *
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        if ($this->excludedPattern && preg_match(sprintf('#%s#', $this->excludedPattern), $request->getPathInfo())) {
            return;
        }
        $request->setDefaultLocale($this->defaultLocale);
        $manager = $this->guesserManager;
        $locale = $manager->runLocaleGuessing($request);
        if ($locale && $this->bestLocaleMatcher) {
            $locale = $this->bestLocaleMatcher->match($locale);
        }

        if ($locale) {
            $this->logEvent('Setting [ %s ] as locale for the (Sub-)Request', $locale);
            $request->setLocale($locale);
            $request->attributes->set('_locale', $locale);
            if (($event->getRequestType() === HttpKernelInterface::MASTER_REQUEST || $request->isXmlHttpRequest())
                && ($manager->getGuesser('session') || $manager->getGuesser('cookie'))
            ) {
                $localeSwitchEvent = new FilterLocaleSwitchEvent($request, $locale);
                $this->dispatcher->dispatch(Events::onLocaleChange, $localeSwitchEvent);
            }
        }
    }

    /**
     * This Listener adds a vary header to all responses.
     *
     * @param FilterResponseEvent $event
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function onLocaleDetectedSetVaryHeader(FilterResponseEvent $event)
    {
        $response = $event->getResponse();
        if (!$this->disableVaryHeader) {
            $response->setVary('Accept-Language', false);
        }
        return $response;
    }

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param boolean $disableVaryHeader
     */
    public function setDisableVaryHeader($disableVaryHeader)
    {
        $this->disableVaryHeader = $disableVaryHeader;
    }

    /**
     * @param string $excludedPattern
     */
    public function setExcludedPattern($excludedPattern)
    {
        $this->excludedPattern = $excludedPattern;
    }

    /**
     * Log detection events
     *
     * @param string $logMessage
     * @param string $parameters
     */
    private function logEvent($logMessage, $parameters = null)
    {
        if (null !== $this->logger) {
            $this->logger->info(sprintf($logMessage, $parameters));
        }
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            // must be registered after the Router to have access to the _locale and before the Symfony LocaleListener
            KernelEvents::REQUEST => [['onKernelRequest', 36]],
            KernelEvents::RESPONSE => ['onLocaleDetectedSetVaryHeader']
        ];
    }
}
