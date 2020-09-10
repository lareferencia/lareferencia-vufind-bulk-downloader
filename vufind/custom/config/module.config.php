<?php
namespace custom\Module\Configuration;

$config = [  	
    'controllers' => [
        'factories' => [
			'custom\Controller\BulkExportController' => 'VuFind\Controller\AbstractBaseFactory',
        ],
        'aliases' => [
			'BulkExport' => 'custom\Controller\BulkExportController',
			'bulkexport' => 'custom\Controller\BulkExportController',
        ],
    ],  
];

// Define static routes -- Controller/Action strings
$staticRoutes = [
	'BulkExport/Home', 
	'BulkExport/CSV',
	'BulkExport/Download',
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addStaticRoutes($config, $staticRoutes);

return $config;
