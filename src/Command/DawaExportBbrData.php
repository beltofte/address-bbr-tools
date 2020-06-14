<?php

namespace App\Command;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\CssSelector;
use App\DawaBaseCommand;

class DawaExportBbrData extends DawaBaseCommand
{
    protected $data = [];

    protected function configure()
    {
        $this->setName('dawa:export-bbr-data')
            ->setDescription('Export various BBR data from building and related floors.')
            ->addArgument(
                'municipality-code',
                InputArgument::REQUIRED,
                'Specify a municipality code.'
            )
            ->addArgument(
                'street-code',
                InputArgument::REQUIRED,
                'Specify a street code.'
            )
            ->addArgument(
                'house-number-from',
                InputArgument::REQUIRED,
                'Specify a house number from.'
            )
            ->addArgument(
                'house-number-to',
                InputArgument::REQUIRED,
                'Specify a house number to.'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'The zone domain.',
                'table'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outputStyle = new OutputFormatterStyle('white', 'green', ['bold']);
        $output->getFormatter()->setStyle('highlight', $outputStyle);
        $outputRows = [];

        // Get output format.
        $outputFormat = $input->getOption('format');
        if (!$this->validateOutputFormat($outputFormat)) {
            $output->writeln('<error>The specified format is not valid. Only the following values are allowed: ' . (implode(', ', array_keys($this->getOutputFormats()))) . '.</error>');
            return false;
        }

        $municipalityCode = $input->getArgument('municipality-code');
        $streetCode = $input->getArgument('street-code');
        $houseNumberFrom = $input->getArgument('house-number-from');
        $houseNumberTo = $input->getArgument('house-number-to');

        $query = [
          'kommunekode' => $municipalityCode,
          'vejkode' => $streetCode,
          'husnrfra' => $houseNumberFrom,
          'husnrtil' => $houseNumberTo
        ];
        $addressResults = $this->dawa->request('adgangsadresser', $query);

        if (!empty($addressResults)) {
            foreach ($addressResults as $address) {
                $accessAdressId = $address->id;
                $accessAdressEsre = $address->esrejendomsnr;

                // Get building.
                $buildingResults = $this->dawa->request('bbrlight/bygninger', ['adgangsadresseid' => $accessAdressId]);

                if (!empty($buildingResults)) {
                    // Finding the correct building in the result.
                    foreach ($buildingResults as $buildingResult) {
                        if ($buildingResult->Bygningsnr == "1") {
                            break;
                        }
                    }
                    $buildingId = $buildingResult->Bygning_id;

                    // Get floors.
                    $floorResults = $this->dawa->request('bbrlight/etager', ['bygningsid' => $buildingId]);

                    if (!empty($floorResults)) {
                        $totalBasementArea = 0;
                        foreach ($floorResults as $floorResult) {
                            if ($floorResult->Etagebetegn == 'KL') {
                                $totalBasementArea = $floorResult->SamletAreal;
                                break;
                            }
                        }

                        // Get land.
                        $landResult = $this->dawa->request(
                            "jordstykker/{$address->ejerlav->kode}/{$address->matrikelnr}"
                        );

                        // Get owner data.
                        $ownerData = $this->getOwnerData($municipalityCode, $accessAdressEsre);

                        $this->setData(
                            $accessAdressId,
                            $address->vejstykke->navn,
                            $address->husnr,
                            $buildingResult->OPFOERELSE_AAR,
                            $buildingResult->BYG_BOLIG_ARL_SAML,
                            $totalBasementArea,
                            $landResult->registreretareal,
                            $ownerData['deed_date'] ? $ownerData['deed_date'] : '',
                            $ownerData['sales_price'] ? $ownerData['sales_price'] : 0,
                            $ownerData['sales_date'] ? $ownerData['sales_date'] : ''
                        );
                    }
                }
            }
        }

        // Output
        $headers = ['Street Name', 'House No', 'Construction Year', 'Total Living Area', 'Total Basement Area', 'Total Ground Area', 'Deed Date', 'Sales Price', 'Sales Date'];
        $this->renderOutput($output, $outputFormat, $headers, $this->getData());
    }

    protected function setData(
        string $accessAdressId,
        string $streetName,
        string $houseNo,
        int $constructionYear,
        int $totalLivingArea,
        int $tobalBasementArea,
        int $totalGroundArea,
        string $deedDate,
        int $salesPrice,
        string $salesDate)
    {
        $this->data[$accessAdressId] = [
            'street_name' => $streetName,
            'house_no' => $houseNo,
            'construction_year' => $constructionYear,
            'total_living_area' => $totalLivingArea,
            'tobal_basement_area' => $tobalBasementArea,
            'total_ground_area' => $totalGroundArea,
            'deed_date' => $deedDate,
            'sales_price' => $salesPrice,
            'sales_date' => $salesDate
        ];
    }

    protected function getData(string $accessAdressId = '')
    {
        if (!empty($accessAdressId)) {
            return isset($this->data[$accessAdressId]) ? $this->data[$accessAdressId] : false;
        }
        else {
            return $this->data;
        }
    }

    protected function getOwnerData(string $municipalityCode, string $esreNumber)
    {
        $returnData = [
            'deed_date' => '',
            'sales_date' => '',
            'sales_price' => 0,
        ];

        $htmlData = file_get_contents("https://boligejer.dk/ejendomsdata/0/10/0/{$esreNumber}%7C{$municipalityCode}");

        if (!empty($htmlData)) {
            // Deed date.
            if (strpos($htmlData, 'Skødedato') !== false) {
                $crawler = new Crawler($htmlData);
                $parentTag = $crawler->filter('p a[aria-controls="collapse3256"]')->parents();
                $textContent = $parentTag->text();

                // Extract deed date from text content.
                if (preg_match('/\d{2}-\d{2}-\d{4}/', $textContent,$matches)) {
                    $returnData['deed_date'] = $matches[0];
                }
            }

            // Sales date and price.
            if (strpos($htmlData, 'Salgspris') !== false) {
                $crawler = new Crawler($htmlData);
                $salesPrice = $crawler->filter('div[class="ejedomsdataopslag-main-info"] div[class="col-xs-12 col-sm-7"] p')->text();
                $salesPrice = str_replace('.', '', $salesPrice);

                $salesDate = $crawler->filter('div[class="ejedomsdataopslag-main-info"] div[class="col-xs-12 col-sm-7"] h3')->text();
                $salesDate = str_replace('Salgspris ', '', $salesDate);

                $returnData['sales_date'] = $salesDate;
                $returnData['sales_price'] = $salesPrice;
            }
        }

        return $returnData;
    }
}
