<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../vendor/pimple/pimple/lib/Pimple.php';

/**
 * Mantener el orden de dependencias. En este caso primero Interfaces después clases.
 */
$filenames = array(
	__DIR__.'/../src/lib/interfaces/*.php',
	__DIR__.'/../src/lib/classes/*.php',
);

foreach ($filenames as $glob) {
	$globs = glob($glob);
	if ($globs) {
		foreach ($globs as $filename) {
			require_once $filename;
		}
	}
}

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$config = array(
    'load' => array(
        __DIR__.'/../src/resources/*.php',
    ),
	'cache' => new Tonic\MetadataCacheFile(__DIR__.'/../cache/tonic.cache'),
);

$app = new Tonic\Application($config);
$app->container = new Pimple();

$configFileName = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'config.yml';

$bressonConfig = array(
	'process_rules' => array(),
	'data_source' => array(
		'class' => 'Bresson\LocalStorage',
		'init_params' => array(
			'base_dir' => __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'files',
		),
	),
	'image_processor' => array(
		'class' => 'Bresson\IMagickProcessor',
	),
	'log' => array(
		'filename' => __DIR__.'/../log/bresson.log',
		'level' => 'DEBUG',
	),
);

if ( !is_readable($configFileName) ) {
	trigger_error( 'Config file can\'t be read. Default configuration will be used instead');
} else {
	$bressonConfig = array_merge_recursive_distinct($bressonConfig, Symfony\Component\Yaml\Yaml::parse($configFileName));
}

$app->container['config'] = $bressonConfig;
$app->container['DS'] = new $app->container['config']['data_source']['class']( $app->container['config']['data_source']['init_params'] );
$app->container['imageProcessor'] = new $app->container['config']['image_processor']['class']();
$logger = new Logger('bresson');

$filename = $bressonConfig['log']['filename'];
if ( substr($filename, 0, 1) != DIRECTORY_SEPARATOR ) {
	$filename = __DIR__.DIRECTORY_SEPARATOR.$filename;
}

$logger->pushHandler( new StreamHandler( $filename, Logger::getLevels()[$bressonConfig['log']['level']] ) );
$app->container['logger'] = $logger;

$request = new Tonic\Request();

try {
    $resource = $app->getResource($request);
    $response = $resource->exec();
} catch (Tonic\NotFoundException $e) {
    $response = new Tonic\Response(404, $e->getMessage());
} catch (Tonic\UnauthorizedException $e) {
    $response = new Tonic\Response(401, $e->getMessage());
    $response->wwwAuthenticate = 'Basic realm="My Realm"';
} catch (Tonic\Exception $e) {
    $response = new Tonic\Response($e->getCode(), $e->getMessage());
}

$response->output();


/**
 * Función para fusionar dos arrays y sus elementos internos de forma recursiva sin
 * generar duplicados con respecto a las keys.
 * @param array $array1
 * @param array $array2
 * @return array
 */
function array_merge_recursive_distinct ( array &$array1, array &$array2 )
{
  $merged = $array1;
  foreach ( $array2 as $key => &$value )
  {
    if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) ) {
      $merged [$key] = array_merge_recursive_distinct ( $merged [$key], $value );
    }
    else {
      $merged [$key] = $value;
    }
  }
  return $merged;
}
