<?php

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

$container = new ContainerBuilder();

$container->register('dawa', 'App\dawa')
    ->setPublic(true)
    ->setArguments([]);

// List of available console commands.
$commands = [
    'dawa.exportbbrdata' => 'App\Command\DawaExportBbrData',
    'dawa.exportbbrvacationhousedata' => 'App\Command\DawaExportBbrVacationHouseData',
    'helloworld' => 'App\Command\HelloWorld',
];

// Register console commands in container.
foreach ($commands as $name => $class) {
    $container->register('command.'.$name, $class)
        ->addTag('console.command')
        ->setAutowired(false)
        ->setAutoconfigured(false)
        ->setPublic(true)
        ->setArguments([
            '$container' => new Reference('service_container'),
        ]);
}

// Compile container.
$container->compile();
