<?php

namespace Rhubarb\ImageProcessing;

/**
 * Applies a watermark to any corner of the image.
 */
class ImageProcessAddWatermark extends ImageProcess
{
	const TOP_LEFT = 1;
	const TOP_RIGHT = 2;
	const BOTTOM_LEFT = 3;
	const BOTTOM_RIGHT = 4;

	private $position;
	private $watermarkImagePath;
	private $watermarkMetrics;

	public function __construct($waterMarkImagePath, $position = self::TOP_LEFT )
	{
		$this->position = $position;
		$this->watermarkImagePath = $waterMarkImagePath;
		$this->watermarkMetrics = getimagesize( $this->watermarkImagePath );
	}

	public function process( Image $image )
	{
		$destCanvas = $image->getCanvas();
		$destImageMetrics = $image->getMetrics();
		$watermarkImage = $this->getWaterMarkSource();
		$wmWidth = $this->watermarkMetrics[0];
		$wmHeight = $this->watermarkMetrics[1];
		$wmImageType =$this->watermarkMetrics[ 2 ];
		$destinationX = "";
		$destinationY = "";

		switch( $this->position )
		{
			case self::TOP_LEFT:
				$destinationX = ( $destImageMetrics->sourceWidth - $destImageMetrics->sourceWidth );
				$destinationY = ( $destImageMetrics->sourceHeight - $destImageMetrics->sourceHeight );
			break;

			case self::TOP_RIGHT:
				$destinationX = ( $destImageMetrics->sourceWidth - $wmWidth - 15 );
				$destinationY = ( $destImageMetrics->sourceHeight - $destImageMetrics->sourceHeight );
				break;

			case self::BOTTOM_LEFT:
				$destinationX = ( $destImageMetrics->sourceWidth - $destImageMetrics->sourceWidth );
				$destinationY = ( $destImageMetrics->sourceHeight - $wmHeight - 15 );
				break;

			case self::BOTTOM_RIGHT:
				$destinationX = ( $destImageMetrics->sourceWidth - $wmWidth - 15 );
				$destinationY = ( $destImageMetrics->sourceHeight - $wmHeight - 15 );
				break;
		}

		imagecopy( $destCanvas, $watermarkImage, $destinationX , $destinationY , 0, 0, $wmWidth, $wmHeight );
	}

	public function getCachePathString()
	{
		return "wm-".$this->watermarkImagePath."-".$this->watermarkMetrics[0]."-".$this->watermarkMetrics[1]."-".$this->watermarkMetrics[2]."-".$this->position;
	}

	private function getWaterMarkSource()
	{
		$imageType = $this->watermarkMetrics[ 2 ];
		imageCreateFromPNG( $this->watermarkImagePath );

		switch( $imageType )
		{
			case 1: //GIF
				$sourceCanvas = imagecreatefromgif( $this->watermarkImagePath );
				break;
			case 2: //JPG
				$sourceCanvas = imagecreatefromjpeg( $this->watermarkImagePath );
				break;
			case 3: //PNG
				$sourceCanvas = imagecreatefrompng( $this->watermarkImagePath );
				break;
		}

		return $sourceCanvas;
	}
}
