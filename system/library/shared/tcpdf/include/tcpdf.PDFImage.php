<?php

/**
 * Basic class for holding properties and methods for preparing an image for inclusion in a TCPDF document
 *
 * @author Bretton Eveleigh
 * @author
 */
class PDFImage
{
    private $_ImagePath;
    private $_ImageSrc; // custom SVG data
    private $_rawImageWidth; // in pixels, in case we need to scale multiple times
    private $_rawImageHeight; // in pixels, in case we need to scale multiple times
    private $_ImageWidth; // in pixels
    private $_ImageHeight; // in pixels
    private $_ImageRes = 72; // dots per inch, 72 dpi by default
    private $_ImageExists;
    private $_HeightWidthRatio;
    private $_MaintainAspectRatio = true; // for scaling methods
    private $_HorizontalAlignment = "left"; // left, right,center
    private $_VerticalAlignment = "top"; // top, middle, bottom
    private $_AltImagePath; // will hold the path to a substitute image if the original image is not found
    private $_ImageFileType = "JPEG"; // added 25-Jan-2010... for Adrian... for PNG support... if not many more formats supported by GD2 lib

    function __construct($ImageFileName, $DirectoryPath, $ImageHeight = null, $ImageWidth = null, $AlternateImagePath = null, $ImageFileType = null)
    {
        if ($ImageFileType) {
            // not doing any valid format checking, user must make sure it's valid, like JPEG, PNG etc...
            $this->_ImageFileType = $ImageFileType;
        }

        // custom svg inline
        if ($ImageFileName && $ImageFileName[0] == '@') {
            $this->SetImageSrc($ImageFileName);
            $this->_ImageExists = true;
            $this->_ImageHeight = (int) $ImageHeight;
            $this->_ImageWidth = (int) $ImageWidth;
        } elseif (strlen($ImageFileName) > 0 && strlen($DirectoryPath) > 0) {
            $this->_ImagePath = $DirectoryPath . $ImageFileName;
            $this->_Inst_ImageExists();
            if ($this->_ImageExists) {
                $this->_rawImageHeight = $this->_ImageHeight = (int)$ImageHeight;
                $this->_rawImageWidth = $this->_ImageWidth = (int)$ImageWidth;

                if (!$ImageFileType) {
                    $imageSize = getimagesize($this->_ImagePath);

                    switch($imageSize[2]) {
                        case IMAGETYPE_GIF: $this->_ImageFileType = 'GIF';
                        case IMAGETYPE_PNG: $this->_ImageFileType = 'PNG'; break;
                        default: $this->_ImageFileType = 'JPEG'; break;
                    }
                }
            } elseif ($AlternateImagePath && strlen($AlternateImagePath) > 0) { // try use the alternate image...
                $this->_ImageExists = null;
                $this->_ImagePath = $AlternateImagePath;
                $this->_Inst_ImageExists();
                if ($this->_ImageExists) {
                    $this->_Inst_ImageDimensions();
                }
            }
        }
    }

    // SETTERS:
    public function SetImageHeight($var)
    {
        $this->_ImageHeight = (int) $var;
    }

    public function SetImageWidth($var)
    {
        $this->_ImageWidth = (int) $var;
    }

    public function SetImageResolution($var)
    {
        $this->_ImageRes = (int) $var;
    }

    public function SetImageFileType($var)
    {
        $this->_ImageFileType = strtoupper($var);
    }

    public function SetMaintainAspectRatio($var)
    {
        $this->_MaintainAspectRatio = (bool) $var;
    }

    public function SetHorizontalAlignment($var)
    {
        $this->_HorizontalAlignment = $var;
    }

    public function SetVerticalAlignment($var)
    {
        $this->_VerticalAlignment = $var;
    }

    public function SetAltImagePath($var)
    {
        $this->_AltImagePath = $var;
    }

    public function SetImageSrc($var)
    {
        $this->_ImageSrc = ltrim($var, '@');
    }

    // GETTERS:
    public function GetImageHeight()
    {
        if ($this->_ImageFileType != 'SVG') {
            $this->_Inst_ImageDimensions();
        }
        return ($this->_ImageHeight);
    }

    public function GetImageWidth()
    {
        if ($this->_ImageFileType != 'SVG') {
            $this->_Inst_ImageDimensions();
        }
        return ($this->_ImageWidth);
    }

    public function GetImageResolution($var)
    {
        return ($this->_ImageRes);
    }

    public function GetImageSrc()
    {
        return ($this->_ImageSrc);
    }

    public function GetImagePath()
    {
        return ($this->_ImagePath);
    }

    public function GetImageFileType()
    {
        return (strtoupper($this->_ImageFileType)); // Like "JPEG", "PNG", "TIFF", "GIF"... etc..
    }

    public function GetHorizontalAlignment()
    {
        return ($this->_HorizontalAlignment);
    }

    public function GetVerticalAlignment()
    {
        return ($this->_VerticalAlignment);
    }

    public function Exists()
    {
        // custom
        if ($this->_ImageSrc) {
            return true;
        }

        $this->_Inst_ImageExists();
        return ($this->_ImageExists);
    }

    // PUBLIC PROCESSING METHODS:
    public function ScaleWidthTo($maxWidth)
    {
        $this->_Inst_HeightWidthRatio();
        $this->_ImageWidth = $maxWidth;
        $this->_ImageHeight = round($maxWidth * $this->_HeightWidthRatio, 2);
    }

    public function ScaleHeightTo($maxHeight)
    {
        $this->_Inst_HeightWidthRatio();
        $this->_ImageHeight = $maxHeight;
        $this->_ImageWidth = round($maxHeight * $this->_HeightWidthRatio, 2);
    }

    public function ScaleLongestSideTo($maxSize)
    {
        $this->_Inst_ImageDimensions(); // make sure we have the raw image dimensions...
        if (! $this->_ImageExists) {
            $this->_rawImageHeight = $this->_ImageHeight = 0;
            $this->_rawImageWidth = $this->_ImageWidth = 0;
            return (false); // can't scale a non-existent image...
        }
        $this->_Inst_HeightWidthRatio();
        if ($this->_rawImageWidth > $this->_rawImageHeight) { // landscape:
            $this->_ImageWidth = $maxSize;
            $this->_ImageHeight = round($maxSize * $this->_HeightWidthRatio, 1);
        } else
            if ($this->_rawImageWidth < $this->_rawImageHeight) { // portrait:
                $this->_ImageHeight = $maxSize;
                $this->_ImageWidth = round($maxSize * $this->_HeightWidthRatio, 1);
            } else { // must be square:
                $this->_ImageWidth = $this->_ImageHeight = $maxSize;
            }
        return (true);
    }

    public function ScaleShortestSideTo($maxSize)
    {
        $this->_Inst_ImageDimensions(); // make sure we have the raw image dimensions...
        if (! $this->_ImageExists) {
            $this->_rawImageHeight = $this->_ImageHeight = 0;
            $this->_rawImageWidth = $this->_ImageWidth = 0;
            return (false); // can't scale a non-existent image...
        }
        if ($this->_rawImageWidth < $this->_rawImageHeight) { // landscape:
            $this->_ImageWidth = $maxSize;
            $ratio = $this->_rawImageHeight / $this->_rawImageWidth;
            $this->_ImageHeight = round($maxSize * $ratio, 1);
        } else
            if ($this->_rawImageWidth > $this->_rawImageHeight) { // portrait:
                $this->_ImageHeight = $maxSize;
                $ratio = $this->_rawImageWidth / $this->_rawImageHeight;
                $this->_ImageWidth = round($maxSize * $ratio, 1);
            } else { // must be square:
                $this->_ImageWidth = $maxSize;
                $this->_ImageHeight = $maxSize;
            }
        return (true);
    }

    /**
     * Get scaled image cords
     * @param int $cellWidth
     * @param bool $withSpacing
     * @param int $scale
     * @return array
     */
    public function GetCellImageDimensions($cellWidth, $withSpacing = false, $scale = 0) {
        $imgWidth = $this->GetImageWidth();
        $imgHeight = $this->GetImageHeight();

        if ($imgWidth > $cellWidth) {
            $imgScale = $imgWidth / $imgHeight;
            $imgWidth = $cellWidth;
            $imgHeight = $cellWidth / $imgScale;
        }

        if ($withSpacing) {
            if ($this->_HorizontalAlignment && is_numeric($this->_HorizontalAlignment)) {
                $imgWidth += $this->_HorizontalAlignment;
            }
            if ($this->_VerticalAlignment && is_numeric($this->_VerticalAlignment)) {
                $imgHeight += $this->_VerticalAlignment;
            }
        }

        return array(round($imgWidth, 1), round($imgHeight, 1));
    }

    // PRIVATE METHODS:
    private function _Inst_ImageDimensions()
    {
        $this->_Inst_ImageExists();
        if ($this->_ImageExists && ((int) $this->_rawImageHeight == 0 || (int) $this->_rawImageWidth == 0)) {
            $ImageDimensions = getimagesize($this->_ImagePath);
            $this->_rawImageWidth = $this->_ImageWidth = $ImageDimensions[0];
            $this->_rawImageHeight = $this->_ImageHeight = $ImageDimensions[1];
        }
    }

    private function _Inst_ImageExists()
    {
        if (is_null($this->_ImageExists)) {
            if ($this->_ImagePath && file_exists($this->_ImagePath)) {
                $this->_ImageExists = true;
            } else {
                $this->_ImageExists = false;
            }
        }
    }

    private function _Inst_HeightWidthRatio()
    {
        $this->_Inst_ImageExists();
        if ($this->_ImageExists) {
            $this->_HeightWidthRatio = 1; // if square
            if ($this->_rawImageWidth > $this->_rawImageHeight) { // landscape:
                $this->_HeightWidthRatio = $this->_rawImageHeight / $this->_rawImageWidth;
            } elseif ($this->_rawImageWidth < $this->_rawImageHeight) { // portrait:
                $this->_HeightWidthRatio = $this->_rawImageWidth / $this->_rawImageHeight;
            }
        }
    }
}
