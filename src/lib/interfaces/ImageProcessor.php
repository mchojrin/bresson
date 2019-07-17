<?php
/**
 * @author: mauro
 * Date: 8/15/13
 * Time: 3:22 PM
 */

namespace Bresson;

interface ImageProcessor
{
	/**
	 * Resizes the image to exactly ($width, $height)
	 * @param string $sImageFileName
	 * @param int $width
	 * @param int $height
	 * @return bool
	 */
	public function resize( $width, $height );

	/**
	 * Scales the image to the best possible fit given ($width, $height)
	 * @param string $sImageFileName
	 * @param int $width
	 * @return bool
	 */
	public function scale( $width, $height );
}