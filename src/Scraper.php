<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2016 Zend Technologies USA Inc. (http://www.zend.com)
 */

namespace Zikanalytic;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;

class Scraper
{
    /**
     * GuzzleHttp\Client
     */
    protected $httpClient;

    /**
     * GuzzleHttp\Cookie\FileCookieJar
     */
    protected $cookieJar;

    /**
     * array
     */
    protected $configs;

    function __construct($configs)
    {
        $this->configs = $configs;
    }

    /**
     * Get Configs
     *
     * @return array
     */
    public function getConfigs() : array
    {
        return $this->configs;
    }

    /**
     * Get Gookie Jar
     *
     * @return \GuzzleHttp\Cookie\FileCookieJar
     */
    public function getCookieJar() : \GuzzleHttp\Cookie\FileCookieJar
    {
        if ($this->cookieJar === null) {
            $this->cookieJar  = new FileCookieJar($this->getConfigs()['cookie_file'], true);
        }

        return $this->cookieJar;
    }

    /**
     * Get Http Client
     *
     * @return \GuzzleHttp\Client
     */
    public function getHttpClient(): \GuzzleHttp\Client
    {
        if ($this->httpClient === null) {
            $this->httpClient = new Client(
                [
                    'base_uri' => $this->getConfigs()['base_uri'],
                    'cookies'  => $this->getCookieJar()
                ]
            );
        }

        return $this->httpClient;
    }

    /**
     * Check user is login or not
     *
     * @param  FileCookieJar $cookieJar
     * @return bool
     */
    protected function isLogin()
    {
        $isLogin = false;
        $it = $this->getCookieJar()->getIterator();
        while ($it->valid()) {
            // check this cookie exist or not
            if ($it->current()->getName() === '_zlc901285') {
                $isLogin = true;
            }

            $it->next();
        }

        return $isLogin;
    }

    /**
     * Authentication
     *
     * @param string $token
     * @param string $uri
     *
     * @return bool
     */
    protected function auth(string $token, string $uri = '/User/Login') : bool
    {
        $response = $this->getHttpClient()->request(
            'POST',
            $uri,
            [
                'cookies' => $this->getCookieJar(),
                'headers' => $this->getConfigs()['headers'],
                'form_params' => [
                    '__RequestVerificationToken' => $token,
                    'Username' => $this->getConfigs()['username'],
                    'Password' => $this->getConfigs()['password'],
                    'Zuda' => 'False'
                ],
                // 'debug' => true
            ]
        );

        $return = false;
        if ($response->getStatusCode() === 200) {
            $body = json_decode($response->getBody(), true);
            if (isset($body['Url']) && $body['Url'] == '/Index/Index') {
                $return = true;
            } else {
                throw new \RuntimeException($body['Message'], $body['StatusCode']);
            }
        }

        return $return;
    }

    /**
     * Get Login Page
     *
     * @param string  $uri
     *
     * @return string
     * @throw  \RuntimeException
     */
    function getPage(string $uri) : string
    {
        $response = $this->getHttpClient()->request(
            'GET',
            $uri,
            [
            'cookies' => $this->getCookieJar(),
            'headers' => $this->getConfigs()['headers']
            ]
        );
        $html = null;
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException($response->getBody(), $response->getStatusCode());
        }

        $html = $response->getBody();
        return $html;
    }

    /**
     * Get Request Token
     *
     * @param string $html
     *
     * @return string|null
     */
    function getRequestToken(string $html) : ?string
    {
        $matchedToken = [];
        $requestToken = null;
        $isTokenExist = preg_match_all('/\"__RequestVerificationToken\" type=\"hidden\" value=\"([^\ ]*)\"/', $html, $matchedToken, PREG_PATTERN_ORDER);
        if ($isTokenExist !== false) {
            $requestToken = $matchedToken[1][0];
        }

        return $requestToken;
    }

    /**
     * @param string $competitorId
     */
    function searchCompetitor(string $competitorId) : ?string
    {
        if (! $this->isLogin()) {
            $this->getCookieJar()->clear();
            $loginPage  = $this->getPage('/User/Login');
            $loginToken = $this->getRequestToken($loginPage);
            $this->auth($loginToken);
        }

        $searchPage  = $this->getPage('/SearchCompetitor/Index');
        $searchToken = $this->getRequestToken($searchPage);
        return $this->fetchCompetitorData($searchToken, $competitorId);
    }

    /**
     * Search Product based on keyword
     *
     * @param string $keyword
     * @param string $type
     * @param string $location
     * @param string $condition
     * @param string $min
     * @param string $max
     * @param string $minFeedBack
     * @param string $maxFeedBack
     * @param string $drange
     *
     * @return array
     */
    public function searchProduct($keywords, $type, $location, $condition, $min, $max, $negative, $minFeedback, $maxFeedback, $drange) : ?array
    {
        if (! $this->isLogin()) {
            $this->getCookieJar()->clear();
            $loginPage  = $this->getPage('/User/Login');
            $loginToken = $this->getRequestToken($loginPage);
            $this->auth($loginToken);
        }

        return $this->fetchProductData($keywords, $type, $location, $condition, $min, $max, $negative, $minFeedback, $maxFeedback, $drange);
    }

    /**
     * Search Product based on keyword
     *
     * @param string $keyword
     * @param string $type
     * @param string $location
     * @param string $condition
     * @param string $min
     * @param string $max
     * @param string $minFeedBack
     * @param string $maxFeedBack
     * @param string $drange
     *
     * @return array
     */
    protected function fetchProductData($keywords, $type, $location, $condition, $min, $max, $negative, $minFeedback, $maxFeedback, $drange)
    {
        $data = [
            'keywords' => $keywords,
            'type' => $type,
            'location' => $location,
            'condition' => $condition,
            'min' => $min,
            'max' => $max,
            'negative' => $negative,
            'minFeedback' => $minFeedback,
            'maxFeedback' => $maxFeedback,
            'drance' => $drange
        ];
        $response = $this->getHttpClient()->request(
            'POST',
            '/Search/Index',
            [
                'cookies' => $this->getCookieJar(),
                'headers' => $this->getConfigs()['headers'],
                'form_params' => $data
            ]
        );

        $return = null;
        if ($response->getStatusCode() === 200) {
            try {
                $return = $this->parseProductData($response->getBody());
            } catch (\RuntimeException $e) {
                throw new \RuntimeException('Parsing Product Data Failed!');
            }
        } else {
           throw new \RuntimeException('Searching Product Failed!');
        }

        return $return;
    }

    /**
     * Parse Product
     *
     * @param string content
     *
     * @return array
     */
    protected function parseProductData(string $html) : ?array
    {
        libxml_use_internal_errors(false);
        $dom = new \DomDocument();
        $html  = @$dom->loadHTML($html);
        $table = $dom->getElementById('productTBody');
        $nodes = $table->childNodes;
	    $products = [];
        foreach ($nodes as $child) { 
            if ($child->tagName !== 'tr') {
                continue;
            }
        
            $trHtml = $child->ownerDocument->saveXML($child);
            $productUrl = self::parseProductUrl($trHtml);
            $product = [
                'id'  => basename($productUrl),
                'url' => $productUrl,
                'name'  => self::parseProductName($trHtml),
                'price' => self::parseProductPrice($trHtml),
            ];
	    $products[] = $product;
        }

        return $products;
    }

    /**
     * Search Competitor
     *
     * @param string  $token
     * @param string  $competitorId
     *
     * @return string
     */
    protected function fetchCompetitorData($token, $competitorId = '') : ?string
    {
        $uri  = '/SearchCompetitor/LoadCompetitor/';
        $data = [
            'draw'   => 1,
            'start'  => 0,
            'length' => 50,
            'competitor' => $competitorId,
            'drange' => 30,
            'min'  => '',
            'max'  => '',
            'minCurrentPrice'  => '',
            'maxCurrentPrice'  => '',
            'LastSalePriceMin'  => '',
            'LastSalePriceMax'  => '',
            'minamazon'   => '',
            'maxamazon'   => '',
            'searchText'  => '',
            'search'  => ['value' => '', 'regex' => false],
            'columns' => [
                [
                    'data' => 'ItemID',
                    'name' => 'SelectedColumnSales',
                    'searchable' => true,
                    'orderable' => true,
                    'search' => ['value' => '', 'regex' => false]
                ],
                [
                    "data" => 'Title',
                    "name" => 'Title',
                    "searchable" => true,
                    "orderable" => true,
                    "search" => ["value" => '', "regex" => false]
                ],
                [
                    'data' => 'UploadDate',
                    'name' => 'UploadDate',
                    'searchable' => true,
                    'orderable'  => true,
                    'search' => ['value' => '', 'regex' => false]
                ],
                [
                    'data' => 'SelectedColumnSales',
                    'name' => 'SelectedColumnSales',
                    'searchable' => true,
                    'orderable'  => true,
                    'search' => ['value' => '', 'regex' => false]
                ],
                [
                    'data' => 'QuantitySold',
                    'name' => 'QuantitySold',
                    'searchable' => true,
                    'orderable'  => true,
                    'search' => ['value' => '', 'regex' => false]
                ],
                [
                    'data' => 'CurrentPrice',
                    'name' => 'CurrentPrice',
                    'searchable' => true,
                    'orderable'  => true,
                    'search' => ['value' => '', 'regex' => false]
                ],
                [
                    'data' => 6, 'name' => 'CurrentPrice',
                    'searchable' => true,
                    'orderable'  => true,
                    'search' => ['value' => '', 'regex' => false]
                ],
                [
                    'data' => 'UPC',
                    'name' => 'Title',
                    'searchable' => true,
                    'orderable'  => true,
                    'search' => ['value' => '', 'regex' => false]
                ],
                [
                    'data' => 'Title',
                    'name' => 'Title',
                    'searchable' => true,
                    'orderable'  => true,
                    'search' => ['value' => '', 'regex' => false]
                ],
            ]
        ];

        $data['__RequestVerificationToken'] = $token;
        $response = $this->getHttpClient()->request(
            'POST',
            $uri,
            [
                'cookies' => $this->getCookieJar(),
                'headers' => $this->getConfigs()['headers'],
                'form_params' => $data
            ]
        );

        $return = null;
        if ($response->getStatusCode() === 200) {
            $body = json_decode($response->getBody(), true);
            if ($body === null) {
                throw new \RuntimeException('Searching Competitor Failed!');
            } else {
                $return = $response->getBody();
            }
        }

        return $return;
    }

    /**
     * Parse Product Name
     *
     * @param string  html
     */
    protected static function parseProductName(string $html) : ?string
    {
        $productExist = preg_match_all('/<h5>(.*)<\/h5>/', $html, $matchedTr, PREG_PATTERN_ORDER);
        $product = null;
        if ($productExist) { 
           $product = html_entity_decode($matchedTr[1][0]);
        }
    
        return $product;
    }
    
    /**
     * Parse Product Price
     *
     * @param string  html
     */
    protected static function parseProductPrice(string $html) : ?string
    {
        $priceExist = preg_match_all('/<b>(.*)<\/b>/', $html, $matchedTr, PREG_PATTERN_ORDER);
        $price = null;
        if ($priceExist) { 
           $price = $matchedTr[1][0];
        }
    
        return $price;
    }
    
    /**
     * Parse Product URL
     *
     * @param string  html
     */
    protected static function parseProductUrl(string $html) : ?string
    {
        $urlExist = preg_match_all('/href="([^"]*)"/', $html, $matchedTr, PREG_PATTERN_ORDER);
        $url = null;
        if ($urlExist) { 
           $url = $matchedTr[1][0];
        }
    
        return $url;
    }
}
