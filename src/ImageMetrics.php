<?php

namespace Rhubarb\ImageProcessing;

use Rhubarb\Crown\Exceptions\FileNotFoundException;

/**
 * Allows for modelling and manipulating image bounds without requiring an actual image.
 */
class ImageMetrics
{
	public $sourcePath = "";
	public $sourceFormat;

	public $sourceWidth = 0;
	public $sourceHeight = 0;

	public $frameWidth = 0;
	public $frameHeight = 0;

	public $offsetX = 0;
	public $offsetY = 0;

	public $scaleWidth = 0;
	public $scaleHeight = 0;

	public $aspectRatio = 0;

	public $focalPercentageX = false;
	public $focalPercentageY = false;
	public $focalPercentageDistance = false;

	public function __construct($path)
	{
		$this->sourcePath = $path;

		$this->setSourceDetails();

		$this->focalPercentageX = 50;
		$this->focalPercentageY = 50;
		$this->focalPercentageDistance = 50;
	}

	/**
	 * Resets the image metrics so as to reflect the image should the current settings be applied.
	 */
	public function commit()
	{
		$this->sourceWidth = $this->frameWidth;
		$this->sourceHeight = $this->frameHeight;
		$this->offsetX = 0;
		$this->offsetY = 0;
		$this->scaleWidth = $this->frameWidth;
		$this->scaleHeight = $this->frameHeight;

		$this->getFocalPoint();
	}

	/**
	 * Returns an array with the X, Y co-ords of the focal point of the current crop.
	 *
	 * The measurement is relative to the original source image.
	 *
	 */
	public function getFocalPoint()
	{
		$scale = $this->sourceWidth / $this->scaleWidth;

		$focalX = (($this->frameWidth / 2) - $this->offsetX) * $scale;
		$focalY = (($this->frameHeight / 2) - $this->offsetY) * $scale;

		return [$focalX, $focalY];
	}

	public function setFocalPointPercentage($x, $y, $focalDistance)
	{
		$this->SetFocalPoint(($x / 100) * $this->sourceWidth, ($y / 100) * $this->sourceHeight, ($focalDistance / 100) * $this->sourceWidth);
	}

	public function setFocalPoint($x, $y, $focalDistance)
	{
		// The focal distance is a measurement of the distance from the center point to the edge of the focal area.

		$scaler = $this->sourceWidth / ($focalDistance * 2);
		$this->scaleWidth = $this->sourceWidth * $scaler;
		$this->scaleHeight = $this->sourceHeight * $scaler;

		$scaleX = $this->sourceWidth / $this->scaleWidth;
		$scaleY = $this->sourceHeight / $this->scaleHeight;

		$this->offsetX = -(($x / $scaleX) - ($this->frameWidth / 2));
		$this->offsetY = -(($y / $scaleY) - ($this->frameHeight / 2));
	}

	/**
	 * Reads basic information about the metrics of the source image.
	 *
	 * @throws FileNotFoundException    Thrown if the source image couldn't be found.
	 */
	public function setSourceDetails()
	{
		if (!file_exists($this->sourcePath)) {
			throw new FileNotFoundException($this->sourcePath);
		}

		if (is_dir($this->sourcePath)) {
			throw new FileNotFoundException($this->sourcePath);
		}

		$imageDetails = getimagesize($this->sourcePath);

		$this->sourceWidth = $imageDetails[0];
		$this->sourceHeight = $imageDetails[1];

		$this->scaleWidth = $this->sourceWidth;
		$this->scaleHeight = $this->sourceHeight;

		$this->aspectRatio = $this->sourceWidth / $this->sourceHeight;

		$this->frameWidth = $this->sourceWidth;
		$this->frameHeight = $this->sourceHeight;

		$this->sourceFormat = $imageDetails[2];
	}

	/**
	 * Resizes the image with the given bounds.
	 *
	 * @param mixed $width
	 * @param mixed $height
	 * @param mixed $maintainAspect
	 * @param bool $scaleUp
	 */
	public function resize($width, $height, $maintainAspect = false, $scaleUp = true)
	{
		$oldFrameWidth = $this->frameWidth;
		$aspectRatio = $this->frameWidth / $this->frameHeight;

		if ($maintainAspect) {
			// Try width first
			$newHeight = round($width / $aspectRatio);

			if ($newHeight > $height) {
				$newWidth = round($height * $aspectRatio);
				$newHeight = $height;
			} else {
				$newWidth = $width;
			}

			if (!$scaleUp && (($newWidth > $this->frameWidth) || ($newHeight > $this->frameHeight))) {
				// Make no changes as we don't want to scale up.
				return;
			}

			// This is effectively changing the width of our frame.
			$this->frameWidth = $newWidth;
			$this->frameHeight = $newHeight;

			// Use the change in width as a ratio to scale the offsets etc.
			$scale = $oldFrameWidth / $this->frameWidth;

			$this->scaleWidth = ceil($this->scaleWidth / $scale);
			$this->scaleHeight = ceil($this->scaleHeight / $scale);

			$this->offsetX = ceil($this->offsetX / $scale);
			$this->offsetY = ceil($this->offsetY / $scale);
		} else {
			if (!$scaleUp && (($width > $this->scaleWidth) || ($height > $this->scaleHeight))) {
				// Make no changes as we don't want to scale up
				return;
			}

			$this->scaleWidth = $width;
			$this->scaleHeight = $height;
			$this->frameWidth = $width;
			$this->frameHeight = $height;
		}

		$this->aspectRatio = $this->scaleWidth / $this->scaleHeight;
	}

	public function smartCrop($cropWidth, $cropHeight, $maintainFocalPoint = true, $resizeMethod = SCALE_NO_SCALE, $boundaryCompensation = BOUNDARY_COMPENSATION_MOVE_THEN_SCALE)
	{
		if ($this->focalPercentageX !== false) {
			$this->setFocalPointPercentage($this->focalPercentageX, $this->focalPercentageY, $this->focalPercentageDistance);
		}

		$focalPoint = $this->getFocalPoint();

		switch ($resizeMethod) {
			case SCALE_MATCH:
				$hypOriginal = sqrt($this->frameWidth * $this->frameWidth + $this->frameHeight * $this->frameHeight);
				$hypNew = sqrt($cropWidth * $cropWidth + $cropHeight * $cropHeight);

				$scaleDifference = $hypNew / $hypOriginal;

				if ($scaleDifference < 0) {
					$scaleDifference = 1 + abs($scaleDifference);
				}

				$this->scaleWidth *= $scaleDifference;
				$this->scaleHeight *= $scaleDifference;
				break;
			case SCALE_FIT_TO_FRAME:
				$suggestedHeight = ($cropWidth / $this->sourceWidth) * $this->sourceHeight;
				$suggestedHeightDifference = $cropHeight - $suggestedHeight;

				$suggestedWidth = ($cropHeight / $this->sourceHeight) * $this->sourceWidth;
				$suggestedWidthDifference = $cropWidth - $suggestedWidth;

				if ($suggestedHeightDifference > $suggestedWidthDifference) {
					// Go with the suggested width difference as it has a smaller gap.
					$this->scaleWidth = $suggestedWidth;
					$this->scaleHeight = $cropHeight;
				} else {
					$this->scaleHeight = $suggestedHeight;
					$this->scaleWidth = $cropWidth;
				}
				break;
		}

		$scale = $this->sourceWidth / $this->scaleWidth;

		$this->frameWidth = $cropWidth;
		$this->frameHeight = $cropHeight;

		if ($maintainFocalPoint) {
			$focalX = $focalPoint[0];
			$focalY = $focalPoint[1];

			$this->offsetX = -(($focalX / $scale) - ($this->frameWidth / 2));
			$this->offsetY = -(($focalY / $scale) - ($this->frameHeight / 2));
		}

		$boundaryBleedLeft = ($this->offsetX > 0) ? $this->offsetX : 0;
		$boundaryBleedRight = max($this->frameWidth - ($this->scaleWidth + $this->offsetX), 0);

		$boundaryBleedTop = ($this->offsetY > 0) ? $this->offsetY : 0;
		$boundaryBleedBottom = max($this->frameHeight - ($this->scaleHeight + $this->offsetY), 0);

		if ($boundaryBleedLeft || $boundaryBleedRight || $boundaryBleedTop || $boundaryBleedBottom) {
			if ($boundaryCompensation == BOUNDARY_COMPENSATION_AUTO) {
				$hypFrame = sqrt($this->frameWidth * $this->frameWidth + $this->frameHeight * $this->frameHeight);
				$hypResized = sqrt($this->scaleWidth * $this->scaleWidth + $this->scaleHeight * $this->scaleHeight);

				if ($hypFrame > $hypResized) {
					$boundaryCompensation = BOUNDARY_COMPENSATION_SCALE;
				} else {
					$boundaryCompensation = BOUNDARY_COMPENSATION_MOVE_THEN_SCALE;
				}
			}

			switch ($boundaryCompensation) {
				case BOUNDARY_COMPENSATION_MOVE_THEN_SCALE:

					if ($boundaryBleedLeft) {
						$moveLeft = -(($boundaryBleedLeft / ($boundaryBleedLeft + $boundaryBleedRight)) * $boundaryBleedLeft);
					} else {
						$moveLeft = $boundaryBleedRight;
					}

					if ($boundaryBleedTop) {
						$moveTop = -(($boundaryBleedTop / ($boundaryBleedTop + $boundaryBleedBottom)) * $boundaryBleedTop);
					} else {
						$moveTop = $boundaryBleedBottom;
					}

					$this->offsetX += $moveLeft;
					$this->offsetY += $moveTop;

					$boundaryBleedLeft = ($this->offsetX > 0) ? $this->offsetX : 0;
					$boundaryBleedRight = max($this->frameWidth - ($this->scaleWidth + $this->offsetX), 0);

					$boundaryBleedTop = ($this->offsetY > 0) ? $this->offsetY : 0;
					$boundaryBleedBottom = max($this->frameHeight - ($this->scaleHeight + $this->offsetY), 0);

					// Fall through to scaling if necessary
					if (!$boundaryBleedLeft && !$boundaryBleedRight && !$boundaryBleedTop && !$boundaryBleedBottom) {
						break;
					}

				case BOUNDARY_COMPENSATION_SCALE:

					$bleeds = [
						"leftright" => $boundaryBleedLeft + $boundaryBleedRight,
						"topbottom" => $boundaryBleedTop + $boundaryBleedBottom
					];

					$maxBleed = 0;
					$direction = "";

					foreach ($bleeds as $type => $boundaryAmount) {
						if ($boundaryAmount > $maxBleed) {
							$maxBleed = $boundaryAmount;
							$direction = $type;
						}
					}

					if ($maxBleed > 0) {
						switch ($direction) {
							case "leftright":
								$scaleAdjustment = 1 + ((ceil($boundaryBleedLeft + $boundaryBleedRight)) / $this->scaleWidth);

								$this->offsetX -= $boundaryBleedLeft;
								$this->offsetY = -(((-$this->offsetY + ($this->frameHeight / 2)) * $scaleAdjustment) - ($this->frameHeight / 2));
								break;

							case "topbottom":
								$scaleAdjustment = 1 + ((ceil($boundaryBleedTop + $boundaryBleedBottom)) / $this->scaleHeight);

								$this->offsetY -= $boundaryBleedTop;
								$this->offsetX = -(((-$this->offsetX + ($this->frameWidth / 2)) * $scaleAdjustment) - ($this->frameWidth / 2));
								break;
						}

						$this->scaleWidth *= $scaleAdjustment;
						$this->scaleHeight *= $scaleAdjustment;
					}

					break;
			}
		}
	}

	public function crop($cropWidth, $cropHeight, $maintainFocalPoint = true, $fitToFrame = true)
	{
		$this->smartCrop($cropWidth, $cropHeight, $maintainFocalPoint, ($fitToFrame ? SCALE_FIT_TO_FRAME : SCALE_MATCH), BOUNDARY_COMPENSATION_AUTO);
	}

	public function centerWidth()
	{
		// Calculates the offsets needed to crop this image in the center.
		$this->offsetX = ($this->frameWidth - $this->resizedWidth) / 2;
	}

	public function centerHeight()
	{
		// Calculates the offsets needed to crop this image in the center.
		$this->offsetY = ($this->frameHeight - $this->resizedHeight) / 2;
	}
}

define("SCALE_NO_SCALE", 0);
define("SCALE_FIT_TO_FRAME", 1);
define("SCALE_MATCH", 2);

define("BOUNDARY_COMPENSATION_NONE", 0);
define("BOUNDARY_COMPENSATION_MOVE_THEN_SCALE", 1);
define("BOUNDARY_COMPENSATION_SCALE", 2);
define("BOUNDARY_COMPENSATION_AUTO", 4);
