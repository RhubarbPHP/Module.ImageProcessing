<?php

namespace Rhubarb\ImageProcessing;

use Rhubarb\Crown\Deployment\ResourceDeploymentProvider;
use Rhubarb\Crown\Exceptions\FileNotFoundException;

/**
 * Represents an image and provides basic manipulation functionality.
 */
class Image
{
	/**
	 * @var ImageMetrics
	 */
	private $_metrics;

	protected $filePath = "";

	private $_canvas = null;

	private $_requiresProcessing = false;

	/**
	 * An array of ImageProcess objects that will be used to generate the final output.
	 *
	 * @var ImageProcess[]
	 */
	private $_processes = [];

	public function __construct( $filePath )
	{
		if ( !file_exists( $filePath ) )
		{
			throw new FileNotFoundException( $filePath );
		}

		$this->filePath = $filePath;
		$this->_metrics = new ImageMetrics( $filePath );
	}

	public static function fromUrl( $fileUrl )
	{
		$tmpFile = tempnam( "temp", "img" );

		$fileContent = file_get_contents( $fileUrl );
		file_put_contents( $tmpFile, $fileContent );

		return new Image( $tmpFile );
	}

	public function getMetrics()
	{
		return $this->_metrics;
	}

	public function getCanvas()
	{
		if ( $this->_canvas == null )
		{
			switch( $this->_metrics->sourceFormat )
			{
				case 1: #GIF
					$this->_canvas = imageCreateFromGIF( $this->filePath );
					break;
				case 2: #JPG
					$this->_canvas = imageCreateFromJPEG( $this->filePath );
					break;
				case 3: #PNG
					$this->_canvas = imageCreateFromPNG( $this->filePath );
					break;
			}
		}

		return $this->_canvas;
	}

	public function setCanvas( $canvas )
	{
		$this->_canvas = $canvas;
	}

	public function writeImage( $destinationPath = "", $jpegQuality = 95 )
	{
		if ( $destinationPath == "" )
		{
			$destinationPath = $this->getCacheImagePath();

			if ( $this->checkCacheValid( $destinationPath ) )
			{
				return $destinationPath;
			}
		}

		$this->process();

		if ( !file_exists( dirname( $destinationPath ) ) )
		{
			mkdir( dirname( $destinationPath ), 0777, true );
		}

		$canvas = $this->GetCanvas();

		switch ( $this->_metrics->sourceFormat )
		{
			case 3:	#png
				imagepng( $canvas, $destinationPath );
				break;
			case 1: #gif
				imagegif( $canvas, $destinationPath );
				break;
			default: #jpeg
				imagejpeg( $canvas, $destinationPath, $jpegQuality );
				break;
		}

		return $destinationPath;
	}

	private function getCacheImagePath()
	{
		$path = "cache/".dirname( $this->_metrics->sourcePath )."/";

		foreach( $this->_processes as $process )
		{
			$path .= $process->getCachePathString();
		}

		$path .= "_".basename( $this->filePath );

		if( !pathinfo( $this->_metrics->sourcePath, PATHINFO_EXTENSION ) )
		{
			switch( $this->_metrics->sourceFormat )
			{
				case IMAGETYPE_GIF:
					$path .= ".gif";
					break;
				case IMAGETYPE_JPEG:
					$path .= ".jpg";
					break;
				case IMAGETYPE_PNG:
					$path .= ".png";
					break;
				case IMAGETYPE_BMP:
					$path .= ".bmp";
					break;
				case IMAGETYPE_TIFF_II:
				case IMAGETYPE_TIFF_MM:
					$path .= ".tiff";
					break;
			}
		}

		return $path;
	}

	/**
	 * Returns true if the cache file exists and is current.
	 *
	 * @param mixed $cacheFile
	 * @return bool
	 */
	private function checkCacheValid( $cacheFile )
	{
		if ( file_exists( $cacheFile ) && ( filemtime( $cacheFile ) >  filemtime( $this->filePath ) ) )
		{
			return true;
		}

		return false;
	}

	public function deployImage()
	{
		$path = $this->WriteImage();

		$handler = ResourceDeploymentProvider::getResourceDeploymentProvider();
		$url = $handler->deployResource( $path );

		return $url;
	}

	/**
	 * Makes sure that the current image metrics are applied to the canvas, essentially committing the
	 * changes.
	 *
	 */
	public function ApplyMetricsToCanvas()
	{
		$destinationCanvas = @imageCreateTrueColor( $this->_metrics->frameWidth, $this->_metrics->frameHeight );

		$backColor = imagecolorallocate( $destinationCanvas, 255, 255, 255 );

		if( $this->_metrics->sourceFormat == 3 )
		{
			$backColor = imagecolorallocatealpha( $destinationCanvas, 255, 255, 255, 127 );
			imagealphablending( $destinationCanvas, true );
			imagesavealpha( $destinationCanvas, true );
		}

		imagefill( $destinationCanvas, 1, 1, $backColor );

		@imageCopyResampled(
			$destinationCanvas,
			$this->GetCanvas(),
			$this->_metrics->offsetX, $this->_metrics->offsetY, 0, 0,
			$this->_metrics->scaleWidth, $this->_metrics->scaleHeight,
			$this->_metrics->sourceWidth, $this->_metrics->sourceHeight );

		$this->SetCanvas( $destinationCanvas );

		$this->_metrics->Commit();
	}

	public function AddProcess( ImageProcess $process )
	{
		$this->_processes[] = $process;

		$this->_requiresProcessing = true;
	}

	private function Process()
	{
		if ( !$this->_requiresProcessing )
		{
			return;
		}

		foreach( $this->_processes as $process )
		{
			$process->Process( $this );
		}

		$this->_requiresProcessing = false;
	}
}
