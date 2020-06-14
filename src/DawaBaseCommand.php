<?php

namespace App;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Doctrine\DBAL\Connection;

class DawaBaseCommand extends BaseCommand
{

    var $dawa;

    /**
     * Construct the class.
     */
    public function __construct(ContainerBuilder $container)
    {
        $this->dawa = $container->get('dawa');
        parent::__construct($container);
    }

}
