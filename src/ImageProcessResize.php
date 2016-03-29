<?php

namespace Rhubarb\ImageProcessing;

/**
 * Class ImageProcessResize
 * @package Rhubarb\Images
 */
class ImageProcessResize extends ImageProcess
{
    private $_maxWidth;
    private $_maxHeight;
    private $_maintainAspectRatio;
    private $_scaleUp;

    public function __construct($maxWidth, $maxHeight, $maintainAspectRatio, $scaleUp)
    {
        $this->_maxWidth = $maxWidth;
        $this->_maxHeight = $maxHeight;
        $this->_maintainAspectRatio = $maintainAspectRatio;
        $this->_scaleUp = $scaleUp;
    }

    public function process(Image $image)
    {
        $metrics = $image->getMetrics();
        $metrics->resize($this->_maxWidth, $this->_maxHeight, $this->_maintainAspectRatio, $this->_scaleUp);

        $image->applyMetricsToCanvas();
    }

    /**
     * Returns a string of text that uniquely identifies the settings for this process.
     *
     * This is used to test if cached versions of the image and process exist to avoid reprocessing.
     *
     * @return mixed
     */
    public function getCachePathString()
    {
        return "rs-" . $this->_maxWidth . "-" . $this->_maxHeight . "-" . $this->_maintainAspectRatio . "-" . $this->_scaleUp;
    }
}
