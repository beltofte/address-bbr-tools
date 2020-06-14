<?php

namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use App\BaseCommand;

class HelloWorld extends BaseCommand
{
    protected function configure()
    {
        $this->setName('helloworld')
            ->setDescription('Outputs a hello world message...');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('Hello world....');
    }

}
