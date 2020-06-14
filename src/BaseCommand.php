<?php

namespace App;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Doctrine\DBAL\Connection;

class BaseCommand extends Command
{

    var $container;

    /**
     * Construct the class.
     */
    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
        parent::__construct();
    }

    /**
     * Convert a string into camel-case without spaces.
     * Used in dynamic function names.
     *
     * @param string $string
     *   String to be converted.
     *
     * @return string
     *   The converted string.
     */
    public function createDynamicFunctionName(string $string)
    {
        return str_replace(' ', '', ucwords($string));
    }

    /**
     * Get a list of allowed output formats.
     *
     * @return array
     */
    public function getOutputFormats()
    {
        return [
            'table' => 'Table',
            'tab' => 'Tab-separated',
            'ssv' => 'Semicolon-separated',
            'csv' => 'Comma-separated'
        ];
    }

    /**
     * Validate the output format.
     *
     * @param string $format
     *   Format to be validated.
     *
     * @return bool
     */
    public function validateOutputFormat(string $format)
    {
        $formats = $this->getOutputFormats();
        return isset($formats[$format]) ? TRUE : FALSE;
    }

    /**
     * Render output to console.
     *
     * @param OutputInterface $output
     *   The output interface.
     * @param string $format
     *   The output format in use.
     * @param array $headers
     *   Array with header columns.
     * @param $rows
     *   Array with rows.
     */
    public function renderOutput(OutputInterface $output, string $format, array $headers, array $rows)
    {
        $renderCallback = 'renderOutputFormat' . $this->createDynamicFunctionName($format);
        $this->{$renderCallback}($output, $headers, $rows);
    }

    /**
     * Render callback: Tab-separated
     *
     * @param OutputInterface $output
     *   The output interface.
     * @param array $headers
     *   Array with header columns.
     * @param $rows
     *   Array with rows.
     */
    public function renderOutputFormatTab(OutputInterface $output, array $headers, array $rows)
    {
        $output->writeln(implode("\t", $headers));
        foreach ($rows as $row) {
            $output->writeln(implode("\t", $row));
        }
    }

    /**
     * Render callback: Semicolon-separated
     *
     * @param OutputInterface $output
     *   The output interface.
     * @param array $headers
     *   Array with header columns.
     * @param $rows
     *   Array with rows.
     */
    public function renderOutputFormatSsv(OutputInterface $output, array $headers, array $rows)
    {
        $output->writeln(implode(";", $headers));
        foreach ($rows as $row) {
            $output->writeln(implode(";", $row));
        }
    }

    /**
     * Render callback: Com,ma-separated
     *
     * @param OutputInterface $output
     *   The output interface.
     * @param array $headers
     *   Array with header columns.
     * @param $rows
     *   Array with rows.
     */
    public function renderOutputFormatCsv(OutputInterface $output, array $headers, array $rows)
    {
        $output->writeln(implode(",", $headers));
        foreach ($rows as $row) {
            $output->writeln(implode(",", $row));
        }
    }

    /**
     * Render callback: Table
     *
     * @param OutputInterface $output
     *   The output interface.
     * @param array $headers
     *   Array with header columns.
     * @param $rows
     *   Array with rows.
     */
    public function renderOutputFormatTable(OutputInterface $output, array $headers, array $rows)
    {
        $table = new Table($output);
        $table
            ->setHeaders($headers)
            ->setRows($rows)
        ;
        $table->render();
    }

}
