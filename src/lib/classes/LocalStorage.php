<?php
/**
 * @author: mauro
 * Date: 8/9/13
 * Time: 11:56 AM
 */

namespace Bresson;

class LocalStorage implements Storage
{
	private $sBaseDir;

	/**
	 * @param $aConfig
	 */
	public function __construct( $aConfig )
	{
		$this->setBaseDir( $aConfig['base_dir'] );
	}

	/**
	 * @param string $sBaseDir
	 */
	public function setBaseDir( $sBaseDir )
	{
		$this->sBaseDir = $sBaseDir;
	}

	/**
	 * @return string
	 */
	public function getBaseDir()
	{
		return $this->sBaseDir;
	}

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function elementExists($sKey)
	{
		return is_readable( $this->getFullPath($sKey) );
	}

	/**
	 * @param string $sKey
	 * @return mixed|string
	 */
	public function fetchElement( $sKey )
	{
		return file_get_contents( $this->getFullPath($sKey) );
	}

	/**
	 * @param $sKey
	 * @param $contents
	 * @return mixed
	 */
	public function storeElement($sKey, $contents)
	{
		file_put_contents( $this->getFullPath($sKey), $contents );
	}

	/**
	 * @param string $sKey
	 * @return string
	 */
	public function getFullPath($sKey)
	{
		return $this->getBaseDir() . $sKey;
	}
}