<?php

/**
 * (c) Joffrey Demetz <joffrey.demetz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JDZ\Favicon;

use Exception;

/**
 * Png favicon generator
 * 
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Png
{
  /**
   * Flag to tell if the required functions exist.
   * 
   * @var boolean
   */
  protected $valid;

  /**
   * The source file
   * 
   * @var string
   */
  protected $source;

  /**
   * The image resource
   * 
   * @var resource
   */
  protected $image;


  /**
   * Check the dependencies
   * 
   * In case composer is not used to check dependencies
   * 
   * @throws Exception
   */
  public static function checkDependencies()
  {
    $required_functions = [
      'getimagesize',
      'imagecreatefrompng',
      'imagecreatefromgif',
      'imagecreatefromjpeg',
      'imagecreatetruecolor',
      'imagesx',
      'imagesy',
      'imagecopyresampled',
      'imagesavealpha',
      'imagepng',
      'imagealphablending',
      'imagefill',
      'imagecolorallocatealpha',
    ];

    foreach ($required_functions as $function) {
      if (!function_exists($function)) {
        throw new Exception('$function function does not exist, which is part of the GD library.');
      }
    }
  }

  /**
   * Constructor 
   * 
   * @param   string    $source     Path to the source image file
   * @throws  GeneratorException
   */
  public function __construct($source)
  {
    $this->source = $source;
    $this->valid  = false;

    $this->valid  = true;

    $image_info = getimagesize($this->source);
    $type = $image_info[2];

    if ($type === IMAGETYPE_JPEG) {
      $this->image = imagecreatefromjpeg($this->source);
    } elseif ($type === IMAGETYPE_GIF) {
      $this->image = imagecreatefromgif($this->source);
    } elseif ($type === IMAGETYPE_PNG) {
      $this->image = imagecreatefrompng($this->source);
    }
  }

  /**
   * Resize image to square
   *
   * @param   string    $file     Path to the destination file
   * @param   int       $size     The destination size
   * @return  boolean
   */
  public function square($file, $size)
  {
    if ($this->valid === false) {
      return false;
    }

    $this->scale($size, $size);
    imagepng($this->image, $file);
    return true;
  }

  /**
   * Create a ms tile
   *
   * @param   string    $file           Path to the destination file
   * @param   string    $background     The background color (hex)
   * @param   int       $width          The tile width
   * @param   int       $height         The tile height
   * @return  boolean
   */
  public function tile($file, $background, $width, $height, $padding)
  {
    if ($this->valid === false) {
      return false;
    }

    // Create an image with the specified width and height
    $background_image = imagecreatetruecolor($width, $height);

    // Convert the background color from hexadecimal to RGB
    $background_color = $this->hexToRGB($background);

    // Allocate the background color on the image
    $background_color = imagecolorallocate($background_image, $background_color['r'], $background_color['g'], $background_color['b']);

    // Fill the image with the background color
    imagefill($background_image, 0, 0, $background_color);

    // Get the user image resource
    $user_image = $this->image;
    $user_width = imagesx($user_image);
    $user_height = imagesy($user_image);

    // Calculate the aspect ratio of the user image
    $user_aspect_ratio = $user_width / $user_height;

    // Calculate the aspect ratio of the desired tile
    $tile_aspect_ratio = $width / $height;

    // Calculate the new width and height of the user image to fit within the tile with padding
    if ($user_aspect_ratio > $tile_aspect_ratio) {
      $new_user_width = $width - 2 * $padding;
      $new_user_height = $new_user_width / $user_aspect_ratio;
    } else {
      $new_user_height = $height - 2 * $padding;
      $new_user_width = $new_user_height * $user_aspect_ratio;
    }

    // Calculate the padding to center the user image on the background image with padding
    $x = floor(($width - $new_user_width) / 2);
    $y = floor(($height - $new_user_height) / 2);

    // Copy the user image onto the background image with the new size and padding
    imagecopyresampled($background_image, $user_image, $x, $y, 0, 0, $new_user_width, $new_user_height, $user_width, $user_height);

    // Save the final MS tile
    imagepng($background_image, $file);

    // Free the memory used by the images
    imagedestroy($background_image);

    return true;
  }


  // Helper function to convert a hexadecimal color to RGB
  private function hexToRGB($hex)
  {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
      list($r, $g, $b) = sscanf($hex, "%1s%1s%1s");
      $r = hexdec("$r$r");
      $g = hexdec("$g$g");
      $b = hexdec("$b$b");
    } elseif (strlen($hex) === 6) {
      list($r, $g, $b) = sscanf($hex, "%2s%2s%2s");
      $r = hexdec($r);
      $g = hexdec($g);
      $b = hexdec($b);
    } else {
      throw new Exception("Invalid hex color format");
    }

    return ['r' => $r, 'g' => $g, 'b' => $b];
  }

  /**
   * Scale image
   *
   * @param   int       $width          The destination width
   * @param   int       $height         The destination height
   * @return  void
   */
  protected function scale($width, $height)
  {
    $w = $this->getWidth();
    $h = $this->getHeight();

    if ($w > $h) {
      $ratio = $width / $w;
      $h = $h * $ratio;
      $this->resize($width, $h);
    } elseif ($w < $h) {
      $ratio = $height / $h;
      $w = $w * $ratio;
      $this->resize($w, $height);
    } else {
      $this->resize($width, $height);
    }
  }

  /**
   * Resize image
   *
   * Stores the image resource in $this->image
   * 
   * @param   int       $width          The destination width
   * @param   int       $height         The destination height
   * @return  void
   */
  protected function resize($width, $height)
  {
    imagesavealpha($this->image, true);
    $im = imagecreatetruecolor($width, $height);

    $background = imagecolorallocatealpha($im, 255, 255, 255, 127);
    imagecolortransparent($im, $background);
    imagealphablending($im, false);
    imagesavealpha($im, true);

    imagecopyresampled($im, $this->image, 0, 0, 0, 0, $width, $height, $this->getWidth(), $this->getHeight());
    $this->image = $im;
  }

  /**
   * Get the image resource width
   *
   * @return  int   The image width
   */
  protected function getWidth()
  {
    return imagesx($this->image);
  }

  /**
   * Get the image resource height
   *
   * @return  int   The image height
   */
  protected function getHeight()
  {
    return imagesy($this->image);
  }
}
