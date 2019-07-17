<?php
/**
 * @author: mauro
 * Date: 8/9/13
 * Time: 11:51 AM
 */

namespace Bresson;

interface Storage
{
	/**
	 * @param $aConfig
	 */
	public function __construct( $aConfig );

	/**
	 * @param string $sKey
	 * @return bool
	 */
	public function elementExists( $sKey );

	/**
	 * @param string $sKey
	 * @return string Contenido del objeto almacenado (null si no se encuetra el elemento)
	 */
	public function fetchElement( $sKey );

	/**
	 * @param $sKey
	 * @param $contents
	 * @return mixed
	 */
	public function storeElement( $sKey, $contents );
}