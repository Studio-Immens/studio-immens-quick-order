<?php
namespace StudioImmens\QuickOrder\PhpQrCode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * PHP QR Code encoder
 *
 * Config file, feel free to modify
 */
     
    define('SIQO_QR_CACHEABLE', false);                                                              // use cache - more disk reads but less CPU power, masks and format templates are stored there
    define('SIQO_QR_CACHE_DIR', dirname(__FILE__).DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR);  // used when SIQO_QR_CACHEABLE === true
    define('SIQO_QR_LOG_DIR', false);                                                                // disable error logs
    
    define('SIQO_QR_FIND_BEST_MASK', true);                                                          // if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
    define('SIQO_QR_FIND_FROM_RANDOM', false);                                                       // if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
    define('SIQO_QR_DEFAULT_MASK', 2);                                                               // when SIQO_QR_FIND_BEST_MASK === false
                                                  
    define('SIQO_QR_PNG_MAXIMUM_SIZE',  1024);                                                       // maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images
                                                  