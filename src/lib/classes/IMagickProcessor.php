<?php
/**
 * @author: mauro
 * Date: 8/9/13
 * Time: 12:24 PM
 */

namespace Bresson;

use Bresson\ImageProcessor;
use Tonic\Exception;

class IMagickProcessor implements ImageProcessor
{
	private $imageResource = null;

	/**
	 * @param string $sImageFileName
	 */
	public function readImageFile( $sImageFileName )
	{
		$this->setImageResource( new \Imagick($sImageFileName) );
	}

	/**
	 * @param string $sImageFileName
	 */
	public function writeImageFile( $sImageFileName )
	{
		/*
		 * Seteo la calidad de imagen a 85% para controlar el tamaño
		 * de las imagenes.
		 */
		$image = $this->getImageResource();
		$image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
		$image->setImageCompressionQuality(85);
		
		$image->writeimage( $sImageFileName );
	}

	/**
	 * @param \Imagick $imageResource
	 */
	private function setImageResource( \Imagick $imageResource )
	{
		$this->imageResource = $imageResource;
	}

	/**
	 * @return \Imagick
	 */
	private function getImageResource()
	{
		return $this->imageResource;
	}

	/**
	 * @param int $width
	 * @param int $height
	 * @return bool
	 */
	public function resize( $width, $height )
	{
		if ( !( $image = $this->getImageResource() ) ) {

			throw new Exception( 'No image resource created' );
		}

		return $image->resizeImage( $width, $height, \Imagick::FILTER_BOX, 0.9 );
	}

	/**
	 * @param $width
	 * @return bool
	 */
	public function scale( $width, $height )
	{
		if ( !( $image = $this->getImageResource() ) ) {

			throw new Exception( 'No image resource created' );
		}
		
		/*
		 * Con el siguiente algortimo defino cual es el lado más grande 
		 * de las medidas con las que va a quedar redimenzionada la foto
		 * para escarlarla según ésta.
		 * Si es un cuadrado lo que quiero se toman lás medidas originales
		 * de la foto para decidir.
		 */
		if($width < $height){
			$width = 0;
		} elseif ($width > $height) {
			$height = 0;
		} else { // cuadrado
			if($image->getimagewidth() > $image->getimageheight()){
				$width = 0;
			} else {
				$height = 0;
			}
		}
		
		return $image->scaleimage( $width, $height, false );
	}

	/**
	 * @param $width
	 * @param $height
	 */
	public function centerCrop( $width , $height )
	{
		if ( !( $image = $this->getImageResource() ) ) {

			throw new Exception( 'No image resource created' );
		}
		
		$newWidth = $image->getimagewidth();
		$newHeight = $image->getimageheight();
		
		if($newWidth == $width){
			$y = abs($height - $newHeight ) / 2;
			$x = 0;
		} elseif ( $newHeight == $height) {
			$y = 0;
			$x = abs( $width - $newWidth ) / 2;
		}

		return $image->cropimage($width, $height, $x , $y);
	}

	/**
	 * @param $degrees
	 */
	public function rotate( $degrees )
	{
		if ( !( $image = $this->getImageResource() ) ) {

			throw new Exception( 'No image resource created' );
		}
		
		return $image->rotateimage(new \ImagickPixel, $degrees);
	}
}