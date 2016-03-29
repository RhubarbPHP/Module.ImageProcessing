<?php

namespace Rhubarb\ImageProcessing;

/**
 * Represents an image processing function.
 */
abstract class ImageProcess
{
    public abstract function process(Image $image);

    /**
     * Returns a string of text that uniquely identifies the settings for this process.
     *
     * This is used to test if cached versions of the image and process exist to avoid reprocessing.
     *
     * @return mixed
     */
    public abstract function getCachePathString();
}
