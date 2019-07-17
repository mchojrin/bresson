<?php
/**
 * @author: mauro
 * Date: 8/8/13
 * Time: 4:35 PM
 */

namespace Bresson;

use Tonic\Resource;
use Tonic\Exception;
use Tonic\Response;

/**
 * @uri /(.+)-(\d+)x(\d+)
 * @uri /(.+)-(\d+)x(\d+)-r(\d+)
 * @uri /(.+)-(th)
 * @uri /(.+)-(bg)
 * @uri /(.+)
 */

class ImageResource extends Resource
{
	const MAX_RETRIES = 20;

	/**
	 * @method get
	 * @provides image/jpeg
	 */
	public function get($prefix, $width = null, $height = null, $degrees = null)
	{
		$dataSource = $this->app->container['DS'];
		/**
		 * @todo Esto es muy feo, pero no encontré por ahora una forma mejor de llegar a la extensión del archivo a través del FW
		 */
		$sOriginalRequest = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
		$this->app->container['logger']->addDebug('Buscando '.$sOriginalRequest);
		if(empty($height) && empty($width)){
			$original = true;
		} else {
			$original = false;
		}
		if ( $dataSource->elementExists($sOriginalRequest) && $contents = $dataSource->fetchElement($sOriginalRequest,$original) ) {
			$this->app->container['logger']->addInfo( $sOriginalRequest. ' encontrado en el storage');
			$this->app->container['logger']->addDebug('Contenido obtenido');

			return new Response(
				Response::OK,
				$contents,
				array(
					'Content-Length' => strlen($contents),
				));
		} else {
			$this->app->container['logger']->addInfo($sOriginalRequest.' NO encontrado en el storage');
			if ( !empty($width) && !$original) {
				$sOriginalName = '/'.$prefix.substr( $sOriginalRequest, strrpos( $sOriginalRequest, '.' ) );
				
				$this->app->container['logger']->addDebug('Request contiene sufijo, buscando imagen original', array( 'imagenOriginal' => $sOriginalName ) );
				if ( !$dataSource->elementExists( $sOriginalName ) ) {
					$this->app->container['logger']->addDebug('Archivo original no encontrado', array( 'Archivo' => $sOriginalName ) );
					$sOriginalName = '/'.$prefix.'-bg'.substr( $sOriginalRequest, strrpos( $sOriginalRequest, '.' ) );
					if ( !$dataSource->elementExists( $sOriginalName ) ) {
						$this->app->container['logger']->addDebug('Archivo bg no encontrado', array( 'Archivo' => $sOriginalName ) );
						$sOriginalName = '/'.$prefix.'-th'.substr( $sOriginalRequest, strrpos( $sOriginalRequest, '.' ) );
						if ( !$dataSource->elementExists( $sOriginalName ) ) {
							$this->app->container['logger']->addDebug('Archivo th no encontrado', array( 'Archivo' => $sOriginalName ) );

							throw new \Tonic\NotFoundException;					
						}
					}
				}
				$this->app->container['logger']->addDebug('Imagen original encontrada en el storage', array( 'imagenOriginal' => $sOriginalName ) );
				try {
					$sLockFileName = '/tmp/'.preg_replace( '|/|', '.', $sOriginalRequest ) .'.lck';
					if ( @$fp = fopen( $sLockFileName, 'x' ) ) {
						$this->app->container['logger']->addDebug('Lock obtenido', array( 'lockFile' => $sLockFileName ) );
						$processor = $this->app->container['imageProcessor'];
						$tmpFile = tempnam(__DIR__, 'img');
						$this->app->container['logger']->addDebug('Descargando archivo original a temporal', array( 'tmpFile' => $tmpFile ) );
						file_put_contents( $tmpFile, $dataSource->fetchElement($sOriginalName,true) );
						$processor->readImageFile( $tmpFile );

						$this->app->container['logger']->addInfo('Archivo original obtenido, procesando imagen');

						if ($degrees) {
							$processor->rotate($degrees);
						}

						if ( !is_numeric($width) ) {
							$this->app->container['logger']->addDebug('El ancho no es numérico, se busca una regla de redimensión', array( 'width' => $width ) );
							$aParts = explode("/", $sOriginalName);
							$taxonomy = $aParts[1];
							if ( !array_key_exists($taxonomy, $this->app->container['config']['process_rules'] ) ) {
								$this->app->container['logger']->addError('Se pidio una taxonomía desconocida', array( 'taxonomy' => $taxonomy ) );

								throw new \Tonic\Exception( 'Unknown taxonomy "'.$taxonomy.'"', 400 );
							}

							$rules = $this->app->container['config']['process_rules'][$taxonomy];

							if ( !array_key_exists( $width, $rules ) ) {
								$this->app->container['logger']->addError('Se pidio un tamaño desconocido para la taxonomia', array( 'taxonomy' => $taxonomy, 'width' => $width ) );

								throw new \Tonic\Exception( 'Unknown size "'.$width.'", available sizes for "'.$taxonomy.'": ['.implode(', ', array_keys($rules)).']', 400 );
							}

							$size = $rules[$width];
							list( $width, $height ) = getimagesize($tmpFile);

							if($width > $height){
								$width = $size;
								$height = 0;
							} else {
								$width = 0;
								$height = $size;
							}

							if ( !$processor->scale( $width, $height ) ) {
								$this->app->container['logger']->addCritical('No se pudo hacer el resizing' );

								throw new \Tonic\Exception( 'Image couldn\'t be scaled', 500 );
							}
						} else {
							$result = $processor->scale( $width, $height );
							if ( !$result ) {

								$this->app->container['logger']->addCritical('No se pudo hacer el resizing' );

								throw new \Tonic\Exception( 'Image couldn\'t be resized', 500 );
							}

							$processor->centerCrop( $width, $height);
						}

						$processor->writeImageFile( $tmpFile );
						$contents = file_get_contents($tmpFile);
						unlink($tmpFile);

						$this->app->container['logger']->addDebug('Almacenando en el storage' );
						/**
						 * @todo Agregar manejo de exception y logging (En todo caso, si no se puede guardar que devuvelva y vuelva a calcular en el próximo request
						 */
						$dataSource->storeElement( $sOriginalRequest, $contents );

						$this->app->container['logger']->addDebug('Liberando el lock' );
						fclose($fp);
						unlink($sLockFileName);
					} else {
						$this->app->container['logger']->addDebug('No se pudo obtener el lock, otro proceso debe estar generando' );
						$tries = 0;
						do {
							usleep( 10 );
							$tries++;
							$elementExists = $dataSource->elementExists($sOriginalRequest);
						} while ( !$elementExists && $tries < ImageResource::MAX_RETRIES );

						if ( $elementExists ) {
							$this->app->container['logger']->addDebug('Otro proceso generó el archivo buscado' );
							$contents = $dataSource->fetchElement($sOriginalRequest);
						} else {
							$this->app->container['logger']->addCritical('Se agotaron los reintentos' );

							throw new \Tonic\NotFoundException;
						}
					}

					return new Response(
						Response::OK,
						$contents,
						array(
							'Content-Length' => strlen($contents),
						));
				} catch ( Exception $e ) {
					$this->app->container['logger']->addCritical('Exception inesperada: ', array( 'exception' => $e->getMessage() ) );

					throw new Exception( 500, $e->getMessage() );
				}
			} else {
				$this->app->container['logger']->addDebug('Archivo no encontrado',array('Archivo' => $sOriginalRequest) );

				throw new \Tonic\NotFoundException;
			}
		}
	}
}
