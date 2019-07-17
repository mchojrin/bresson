<?php
/**
 * @author: mauro
 * Date: 8/9/13
 * Time: 3:04 PM
 */

namespace Bresson;

use Aws\Common\Aws;
use Aws\S3\S3Client;

class S3Storage implements Storage
{
	private $sKey;
	private $sSecret;
	private $sBucket;
	private $client;
	private $version;

	/**
	 * @param string $sKey
	 */
	public function setKey( $sKey )
	{
		$this->sKey = $sKey;
	}

	/**
	 * @param string $sSecret
	 */
	public function setSecret( $sSecret )
	{
		$this->sSecret = $sSecret;
	}

	/**
	 * @param string $sVersion
	 */
	public function setVersion( $version )
	{
		$this->version = $version;
	}
	
	/**
	 * @param $aConfig
	 */
	public function __construct( $aConfig )
	{
		$this->setKey( $aConfig['key'] );
		$this->setSecret( $aConfig['secret'] );
		$this->setBucket( $aConfig['bucket'] );
		$this->setVersion( $aConfig['version']);
	}

	/**
	 * @param string $sBucket
	 */
	public function setBucket( $sBucket )
	{
		$this->sBucket = $sBucket;
	}

	/**
	 * @return string
	 */
	public function getBucket()
	{
		return $this->sBucket;
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function elementExists($sKey)
	{
		return $this->getS3Client()->doesObjectExist( $this->getBucket(), $sKey );
	}

	/**
	 * @param string $sKey
	 * @return string
	 */
	public function fetchElement($sKey, $original = false)
	{
		$result = $this->getS3Client()->getObject( array(
			'Bucket' => $this->getBucket(),
			'Key' => $sKey,
		));
		$result['Body']->rewind();

		$return = '';
		
		/**
		 * Si la versión de mi configuración es menor o igual a la versión de la foto, la leo y devuelvo el contenido.
		 * Si la versión de mi configuración es mayor, no leo nada y devuelvo vacio como si no existiera foto.
		 */
		$version_result = array_key_exists( 'Metadata', $result) && array_key_exists( 'version', $result['Metadata'] ) ? version_compare($this->version, $result['Metadata']['version']) : 1;
		if( ($version_result == 0 || $version_result == -1) || $original ){
			while ($data = $result['Body']->read(1024)) {
				$return .= $data;
			}
		}


		return $return;
	}
	
	/**
	 * @param $sKey
	 * @param $contents
	 * @return mixed
	 */
	public function storeElement($sKey, $contents)
	{
		try{
			$this->getS3Client()->putObject( array(
				'Bucket' => $this->getBucket(),
				'Key' => $sKey,
				'Body' => $contents,
				'Metadata' => array(
					'version' => $this->version,
				),
				'ACL' => "public-read"));
		} catch (S3Exception $e) {
			echo "There was an error uploading the file.\n";
		}
	}

	/**
	 * @return string
	 */
	public function getKey()
	{
		return $this->sKey;
	}

	/**
	 * @return string
	 */
	public function getSecret()
	{
		return $this->sSecret;
	}

	/**
	 * @return S3Client
	 */
	private function createClient()
	{
		return $client = S3Client::factory(array(
			'key'    => $this->getKey(),
			'secret' => $this->getSecret(),
		));
	}

	/**
	 * @param S3Client $client
	 */
	public function setS3Client( S3Client $client )
	{
		$this->client = $client;
	}

	/**
	 * @return S3Client
	 */
	public function getS3Client()
	{
		if ( empty($this->client) ) {
			$this->setS3Client( $this->createClient() );
		}

		return $this->client;
	}
}
