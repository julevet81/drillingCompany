<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$yamlPath = dirname(__DIR__) . '/openapi.yaml';
$jsonPath = dirname(__DIR__) . '/openapi.json';

$data = Yaml::parseFile($yamlPath);
file_put_contents(
    $jsonPath,
    json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

echo "Written {$jsonPath}\n";
