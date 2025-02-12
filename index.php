<?php
error_reporting(E_ALL);

// Use the composer autoloader to load dependencies.
require_once __DIR__ . '/vendor/autoload.php'; 

/**
 * This is an example of a front controller for a flat file PHP site. Using a
 * Static list provides security against URL injection by default. See README.md
 * for more examples.
 */
# [START gae_simple_front_controller]
switch (@parse_url($_SERVER['REQUEST_URI'])['path']) { 
    case '/':
        require __DIR__ . '/homepage.php';
        break; 
    case '/test.php':
        require __DIR__ . '/test.php';
        break;  
    case '/src/ajax.php':
        require __DIR__ . '/src/ajax.php';
        break; 
    case '/src/config.php':
        require __DIR__ . '/src/config.php';
        break; 
    case '/src/init.php':
        require __DIR__ . '/src/init.php';
        break; 
    case '/src/Handler/Main':
        require __DIR__ . '/src/handler/Main.php';
        break;  
    case '/src/Handler/Main.php':
        require __DIR__ . '/src/handler/Main.php';
        break;   
    case '/src/Modules/Ahrefs.php':
        require __DIR__ . '/src/modules/Ahrefs.php';
        break;  
    case '/src/Modules/Pagespeed.php':
        require __DIR__ . '/src/modules/Pagespeed.php';
        break;  
    case '/src/Modules/SearchConsole.php':
        require __DIR__ . '/src/modules/SearchConsole.php';
        break;  
    case '/src/Modules/SearchIndex.php':
        require __DIR__ . '/src/modules/SearchIndex.php';
        break;  
    case '/src/Modules/Spreadsheet.php':
        require __DIR__ . '/src/modules/Spreadsheet.php';
        break;  
    case '/src/Modules/Wappalyzer.php':
        require __DIR__ . '/src/modules/Wappalyzer.php';
        break;   
    case '/src/Utils/Helper':
        require __DIR__ . '/src/utils/Helper.php';
        break; 
    case '/src/Utils/Helper.php':
        require __DIR__ . '/src/utils/Helper.php';
        break;    
    case '/src/Utils/LogTrait':
        require __DIR__ . '/src/utils/LogTrait.php';
        break;    
    case '/src/Utils/LogTrait.php':
        require __DIR__ . '/src/utils/LogTrait.php';
        break; 
    case '/logs/ajax_temp.log':
        require __DIR__ . '/logs/ajax_temp.json';
        break; 
    case '/logs/ajax_batch.json':
        require __DIR__ . '/logs/ajax_batch.json';
        break; 
    case '/logs/cron_batch.json':
        require __DIR__ . '/logs/cron_batch.json';
        break; 
    case '/logs/cron_temp.json':
        require __DIR__ . '/logs/cron_temp.json';
        break; 
    default:
        http_response_code(404);
        exit('Not Found');
}
# [END gae_simple_front_controller]