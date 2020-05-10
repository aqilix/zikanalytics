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

// setup autoloading
require 'vendor/autoload.php';

use Zikanalytic\Scraper;

$configs = include_once 'config/local.php';
$scraper = new Scraper($configs);
$competitor  = $_GET['competitor'] ?? '';
header('Content-Type: application/json');
try {
    echo $scraper->searchCompetitor($competitor);
} catch (\RuntimeException $e) {
    $response = ['status' => 'error', 'mesage' => $e->getMessage()];
    echo json_encode($response);
}
