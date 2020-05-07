<?php
/**
 * @license   http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause
 * @copyright Copyright (c) 2014-2016 Zend Technologies USA Inc. (http://www.zend.com)
 */

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

// Decline static file requests back to the PHP built-in webserver
if (php_sapi_name() === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

if (! file_exists('vendor/autoload.php')) {
    throw new RuntimeException(
        'Unable to load application.' . PHP_EOL
        . '- Type `composer install` if you are developing locally.' . PHP_EOL
        . '- Type `vagrant ssh -c \'composer install\'` if you are using Vagrant.' . PHP_EOL
        . '- Type `docker-compose run apigility composer install` if you are using Docker.'
    );
}

// Setup autoloading
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;

// file to store cookie data
$cookieFile = getcwd() . '/data/cookies/jar.txt';
$cookieJar  = new FileCookieJar($cookieFile, true);
$baseUri    = 'https://www.zikanalytics.com';
$username   = 'aroosya18@gmail.com';
$password   = 'zik96981';
$defaultUserAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) '
                  . 'Chrome/81.0.4044.122 Safari/537.36';
$defaultHeaders = [
    'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
    'User-Agent' => $defaultUserAgent,
    "Accept-Encoding" =>  'gzip, deflate, br',
    'Origin'     => $baseUri,
    'set-fetch-dest' => 'empty',
    'set-fetch-mode' => 'cors',
    'set-fetch-site' => 'same-origin'
]; 

$client = new Client(
    [
    'base_uri' => $baseUri,
    'cookies'  => $cookieJar
    ]
);

header('Content-Type: application/json');
try {
    if (!isLogin($cookieJar)) {
        $loginPage  = getPage($client, $cookieJar, '/User/Login');
        $loginToken = getRequestToken($loginPage);
        doAuthentication($client, $username, $password, $loginToken, $cookieJar);
    }

    $searchPage  = getPage($client, $cookieJar, '/SearchCompetitor/Index');
    $searchToken = getRequestToken($searchPage);
    $competitor  = $_GET['competitor'] ?? ''; 
    echo searchCompetitor($client, $searchToken, $cookieJar, $competitor);
} catch (\RuntimeException $e) {
    $response = ['status' => 'error', 'mesage' => $e->getMessage()];
    echo json_encode($response);
}

/**
 * Check user is login or not
 *
 * @param  FileCookieJar $cookieJar
 * @return bool
 */
function isLogin($cookieJar)
{
    $isLogin = false;
    $it = $cookieJar->getIterator();
    while ($it->valid()) {
        // check this cookie exist or not
        if ($it->current()->getName() === 'UserAlready') {
            $isLogin = true;
        }

        $it->next();
    }

    return $isLogin;
}

/**
 * Authentication 
 *
 * @param GuzzleHttp\Client $client
 * @param string            $username
 * @param string            $password
 * @param string            $token
 * @param FileCookieJar     $cookieJar
 * @param string            $uri
 *
 * @return bool
 */
function doAuthentication(GuzzleHttp\Client $client, string $username, string $password, string $token, $cookieJar, string $uri = '/User/Login')
{
    global $defaultHeaders;
    $response = $client->request(
        'POST', $uri, [
            'cookies' => $cookieJar,
            'headers' => $defaultHeaders,
            'form_params' => [
                '__RequestVerificationToken' => $token,
                'Username' => $username,
                'Password' => $password,
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
            throw new RuntimeException($body['Message'], $body['StatusCode']);
        }
    }

    return $return;
}

/**
 * Get Login Page
 *
 * @param GuzzleHttp\Client $client
 * @param FileCookieJar     $cookieJar
 * @param string            $uri
 *
 * @return string
 * @throw  \RuntimeException
 */
function getPage(GuzzleHttp\Client $client, $cookieJar, string $uri)
{ 
    global $defaultHeaders;
    $response = $client->request(
        'GET', $uri, [
        'cookies' => $cookieJar,
        'headers' => $defaultHeaders
        ]
    );
    $html = null;
    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException($response->getBody(), $response->getStatusCode());
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
function getRequestToken(string $html)
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
 * Search Competitor
 *
 * @param GuzzleHttp\Client $client
 * @param string            $token 
 * @param FileCookieJar     $cookieJar
 * @param string            $id
 *
 * @return string
 */
function searchCompetitor(GuzzleHttp\Client $client, $token, $cookieJar, $id = '')
{
    global $defaultHeaders;
    $uri  = '/SearchCompetitor/LoadCompetitor/';
    $data = [
        'draw'   =>  1,
        'start'  =>  0,
        'length' => 50,
        'competitor' => $id,
        'drange' =>  30,
        'min'  =>  '',
        'max'  =>  '',
        'minCurrentPrice'  =>  '',
        'maxCurrentPrice'  =>  '',
        'LastSalePriceMin'  =>  '',
        'LastSalePriceMax'  =>  '',
        'minamazon'   =>  '',
        'maxamazon'   =>  '',
        'searchText'  =>  '',
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
                'data' => 6,
                'name' => 'CurrentPrice',
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
    $response = $client->request(
        'POST', $uri, [
            'cookies' => $cookieJar,
            'headers' => $defaultHeaders,
            'form_params' => $data
        ]
    );

    $return = null;
    if ($response->getStatusCode() === 200) {
        $body = json_decode($response->getBody(), true);
        if ($body === null) {
            throw new RuntimeException('Searching Competitor Failed!');
        } else {
            $return = $response->getBody();
        }
    }

    return $return;
}
