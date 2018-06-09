<?php

namespace NetBull\CoreBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use NetBull\CoreBundle\Locale\Events;
use NetBull\CoreBundle\Locale\Cookie\LocaleCookie;
use NetBull\CoreBundle\Locale\Session\LocaleSession;
use NetBull\CoreBundle\Event\FilterLocaleSwitchEvent;

/**
 * Class LocaleUpdateListener
 * @package NetBull\CoreBundle\EventListener
 */
class LocaleUpdateListener implements EventSubscriberInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var string
     */
    private $locale;

    /**
     * @var LocaleSession
     */
    private $session;

    /**
     * @var LocaleCookie
     */
    private $localeCookie;

    /**
     * @var array
     */
    private $registeredGuessers;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * LocaleUpdateListener constructor.
     * @param EventDispatcherInterface $dispatcher
     * @param LocaleCookie $localeCookie
     * @param LocaleSession|null $session
     * @param array $registeredGuessers
     * @param LoggerInterface|null $logger
     */
    public function __construct(EventDispatcherInterface $dispatcher,
        LocaleCookie $localeCookie,
        LocaleSession $session = null,
        $registeredGuessers = array(),
        LoggerInterface $logger = null)
    {
        $this->localeCookie = $localeCookie;
        $this->session = $session;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
        $this->registeredGuessers = $registeredGuessers;
    }

    /**
     * Processes the locale updates. Adds listener for the cookie and updates the session.
     *
     * @param FilterLocaleSwitchEvent $event
     */
    public function onLocaleChange(FilterLocaleSwitchEvent $event)
    {
        $this->locale = $event->getLocale();
        $this->updateCookie($event->getRequest(), $this->localeCookie->setCookieOnChange());
        $this->updateSession();
    }

    /**
     * @param Request $request
     * @param $update
     * @return bool
     */
    public function updateCookie(Request $request, $update)
    {
        if ($this->checkGuesser('cookie')
            && $update === true
            && $request->cookies->get($this->localeCookie->getName()) !== $this->locale
        ) {
            $this->dispatcher->addListener(KernelEvents::RESPONSE, [$this, 'updateCookieOnResponse']);
            return true;
        }
        return false;
    }

    /**
     * Event for updating the cookie on response
     *
     * @param FilterResponseEvent $event
     *
     * @return \Symfony\Component\HttpFoundation\Response;
     */
    public function updateCookieOnResponse(FilterResponseEvent $event)
    {
        $response = $event->getResponse();
        $cookie = $this->localeCookie->getLocaleCookie($this->locale);
        $response->headers->setCookie($cookie);
        if (null !== $this->logger) {
            $this->logger->info(sprintf('Locale Cookie set to [ %s ]', $this->locale));
        }
        return $response;
    }

    /**
     * Update Session section
     *
     * @return bool
     */
    public function updateSession()
    {
        if ($this->session && $this->checkGuesser('session') && $this->session->hasLocaleChanged($this->locale)) {
            if (null !== $this->logger) {
                $this->logger->info(sprintf('Session var \'%s\' set to [ %s ]', $this->session->getSessionVar(), $this->locale));
            }
            $this->session->setLocale($this->locale);
            return true;
        }
        return false;
    }

    /**
     * Returns if a guesser is
     *
     * @param string $guesser Name of the guesser to check
     *
     * @return bool
     */
    private function checkGuesser($guesser)
    {
        return in_array($guesser, $this->registeredGuessers);
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            // must be registered after the Router to have access to the _locale and before the Symfony LocaleListener
            Events::ON_LOCALE_CHANGE => ['onLocaleChange'],
        ];
    }
}