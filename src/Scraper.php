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
     *
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
     *
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

    // public function searchCompetitor(

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
                // echo $it->current()->getExpires(), PHP_EOL;
                $isLogin = true;
            }

            $it->next();
        }

        return $isLogin;
    }

    /**
     * Authentication
     *
     * @param string            $token
     * @param string            $uri
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
}
