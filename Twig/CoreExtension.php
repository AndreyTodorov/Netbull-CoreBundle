<?php

namespace NetBull\CoreBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Intl;
use Symfony\Component\DomCrawler\Crawler;

use NetBull\CoreBundle\Utils\Inflect;
use NetBull\CoreBundle\Utils\TranslationGuesser;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class CoreExtension
 * @package NetBull\CoreBundle\Twig
 */
class CoreExtension extends \Twig_Extension
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * CoreExtension constructor.
     * @param RouterInterface $router
     * @param RequestStack $requestStack
     */
    public function __construct(RouterInterface $router, RequestStack $requestStack)
    {
        $this->router = $router;
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions() {
        return [
            new \Twig_SimpleFunction('pagination_sortable', [$this, 'sortable'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('queryInputs', [$this, 'buildQueryInputs'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction('helperText', [$this, 'buildHelperText'], ['is_safe' => ['html']]),
            new \Twig_SimpleFunction ('lipsum', [$this, 'loremIpsum'])
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('guessTranslation', [$this, 'guessTranslation']),
            new \Twig_SimpleFilter('getTranslation', [$this, 'getTranslation']),
            new \Twig_SimpleFilter('language', [$this, 'languageFromLocale']),
            new \Twig_SimpleFilter('rename_pipe', [$this, 'renameByPipe']),
            new \Twig_SimpleFilter('inflect', [$this, 'inflect']),
            new \Twig_SimpleFilter('titleize', [$this, 'titleize']),
            new \Twig_SimpleFilter('country', [$this, 'getCountryName']),
            new \Twig_SimpleFilter('format_page_title', [$this, 'formatPageTitle']),
            new \Twig_SimpleFilter('strip_tags_super', [$this, 'stripTagsSuper'])
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getTests()
    {
        return [
            new \Twig_SimpleTest('numeric', [$this, 'numericTest'])
        ];
    }

    #########################################
    #              Functions                #
    #########################################
    /**
     * @param $pagination
     * @param $label
     * @param $field
     * @return string
     */
    public function sortable($pagination, $label, $field)
    {
        if (in_array($field, $pagination['sort'])) {
            $direction = 'asc';
            if (isset($pagination['sort']['direction']) && ($pagination['sort']['direction'] == 'asc' || $pagination['sort']['direction'] == 'desc')) {
                $direction = $pagination['sort']['direction'];
            }

            $newDirection = ($direction == 'asc') ? 'desc' : 'asc';
            $hint = ($direction === 'asc') ? 'Descending' : 'Ascending';

            // If we are on DESC sorting next should be the initial state to clear the sorting
            if ($direction == 'desc') {
                unset($pagination['sort']['field']);
                unset($pagination['sort']['direction']);
                unset($pagination['routeParams']['field']);
                unset($pagination['routeParams']['direction']);
                $hint = 'clear';
                $params = [];
            } else {
                $params = array_merge($pagination['sort'], [
                    'field'     => $field,
                    'direction' => $newDirection
                ]);
            }
            $link = $this->router->generate($pagination['route'], array_merge($pagination['routeParams'], $params));

            $string = sprintf('<a class="text-success" href="%s" title="Sort %s">%s <i class="fa fa-sort-%s"></i></a>', $link, $hint, $label, $direction);
        } else {
            $link = $this->router->generate($pagination['route'], array_merge($pagination['routeParams'], $pagination['sort'], [
                'field'     => $field,
                'direction' => 'asc'
            ]));
            $string = sprintf('<a class="text-primary" href="%s" title="Sort Ascending">%s <i class="fa fa-sort"></i></a>', $link, $label);
        }

        return $string;
    }

    /**
     * Build Hidden fields based on the URL parameters
     * @param $currentField
     * @return string
     */
    public function buildQueryInputs($currentField)
    {
        $request = $this->requestStack->getCurrentRequest();
        $fields = '';
        foreach ($request->query->all() as $field => $value) {
            // Exclude the current field and the PAGE parameter
            if($field !== $currentField && $field !== 'page'){
                $fields .= sprintf('<input type="hidden" name="%s" value="%s">', $field, $value);
            }
        }

        return $fields;
    }

    /**
     * Build Helper icon
     * @param $text
     * @return mixed
     */
    public function buildHelperText($text)
    {
        return sprintf('<i class="fa fa-question-circle text-primary helper-text" title="%s"></i>', $text);
    }

    /**
     * @param int $length
     * @return string
     */
    public function loremIpsum($length = 30) {
        $string = [];
        $words = [
            'lorem',        'ipsum',       'dolor',        'sit',
            'amet',         'consectetur', 'adipiscing',   'elit',
            'a',            'ac',          'accumsan',     'ad',
            'aenean',       'aliquam',     'aliquet',      'ante',
            'aptent',       'arcu',        'at',           'auctor',
            'augue',        'bibendum',    'blandit',      'class',
            'commodo',      'condimentum', 'congue',       'consequat',
            'conubia',      'convallis',   'cras',         'cubilia',
            'cum',          'curabitur',   'curae',        'cursus',
            'dapibus',      'diam',        'dictum',       'dictumst',
            'dignissim',    'dis',         'donec',        'dui',
            'duis',         'egestas',     'eget',         'eleifend',
            'elementum',    'enim',        'erat',         'eros',
            'est',          'et',          'etiam',        'eu',
            'euismod',      'facilisi',    'facilisis',    'fames',
            'faucibus',     'felis',       'fermentum',    'feugiat',
            'fringilla',    'fusce',       'gravida',      'habitant',
            'habitasse',    'hac',         'hendrerit',    'himenaeos',
            'iaculis',      'id',          'imperdiet',    'in',
            'inceptos',     'integer',     'interdum',     'justo',
            'lacinia',      'lacus',       'laoreet',      'lectus',
            'leo',          'libero',      'ligula',       'litora',
            'lobortis',     'luctus',      'maecenas',     'magna',
            'magnis',       'malesuada',   'massa',        'mattis',
            'mauris',       'metus',       'mi',           'molestie'
        ];

        for ($i=0; $i < $length; $i++) {
            $string[] = $words[rand(0, 99)];
        }

        return implode(' ', $string);
    }

    #########################################
    #                Filters                #
    #########################################

    /**
     * @param array $array
     * @param $field
     * @param null $locale
     * @param bool $strict
     * @return mixed|string
     */
    public function guessTranslation(array $array, $field, $locale = null, $strict = false)
    {
        if (empty($array)) {
            return '';
        }

        $locale = ($locale) ? $locale : $this->requestStack->getCurrentRequest()->getLocale();

        return TranslationGuesser::guess($array, $field, $locale, $strict);
    }

    /**
     * @param array $array
     * @param null $locale
     * @param bool $strict
     * @return mixed|string
     */
    public function getTranslation(array $array, $locale = null, $strict = false)
    {
        if (empty($array)) {
            return '';
        }

        $locale = ($locale) ? $locale : $this->requestStack->getCurrentRequest()->getLocale();

        return TranslationGuesser::get($array, $locale, $strict);
    }

    /**
     * Return Language representation for a given Locale
     * @param $locale
     * @param string $toLocale
     * @return string
     */
    public function languageFromLocale($locale, $toLocale = null)
    {
        $request = $this->requestStack->getCurrentRequest();
        $auto = $request ? $request->getLocale() : 'en';
        $toLocale = ($toLocale)?$toLocale:$auto;
        $language = \Locale::getDisplayLanguage($locale, $toLocale);

        return mb_convert_case($language, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Pluralize or Singularize a string
     * @param string $string
     * @param int $pluralize
     * @return string
     */
    public function inflect(string $string, $pluralize = 0) : string
    {
        return ($pluralize) ? Inflect::pluralize($string) : Inflect::singularize($string);
    }

    /**
     * @param string $string
     * @return mixed|null|string|string[]
     */
    public function titleize(string $string)
    {
        return Inflect::titleize($string);
    }

    /**
     * @param string $code
     * @param string $locale
     * @return string
     */
    public function getCountryName(string $code, string $locale = '') : string
    {
        $countries = Intl::getRegionBundle()->getCountryNames($locale);

        return array_key_exists($code, $countries)
            ? $countries[$code]
            : $code;
    }

    /**
     * @param string $title
     * @return string
     */
    public function formatPageTitle(string $title) : string
    {
        return sprintf('%s - %s', $title, 'NetBull');
    }

    /**
     * @param string $string
     * @return string
     */
    public function stripTagsSuper(string $string) : string
    {
        if (false === strpos($string, '<body')) {
            $text = $string;
        } else {
            $crawler = new Crawler($string);
            $body = $crawler->filter('body');
            $text = $body->text();
        }

        return $text;
    }

    #########################################
    #                 Tests                 #
    #########################################

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'core.extension';
    }
}