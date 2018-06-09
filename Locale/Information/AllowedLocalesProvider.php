<?php

namespace NetBull\CoreBundle\Locale\Information;

/**
 * Class AllowedLocalesProvider
 * @package NetBull\CoreBundle\Locale\Information
 */
class AllowedLocalesProvider
{
    /**
     * @var array|null
     */
    protected $allowedLocales;

    /**
     * AllowedLocalesProvider constructor.
     * @param array|null $allowedLocales
     */
    public function __construct(array $allowedLocales = null)
    {
        $this->allowedLocales = $allowedLocales;
    }

    /**
     * Return a list of the allowed locales
     * @return array
     */
    public function getAllowedLocales()
    {
        return $this->allowedLocales;
    }

    /**
     * Set the list of the allowed locales
     * @param array $allowedLocales
     */
    public function setAllowedLocales($allowedLocales)
    {
        $this->allowedLocales = $allowedLocales;
    }
}
