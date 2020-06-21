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

class DawaExportBbrVacationHouseData extends DawaBaseCommand
{
    protected $data = [];

    protected function configure()
    {
        $this->setName('dawa:export-bbr-vacation-house-data')
            ->setDescription('Export various BBR vacation house data from building and related floors.')
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
            'husnrtil' => $houseNumberTo,
            'struktur' => 'flad'
        ];
        $addressResults = $this->dawa->request('adgangsadresser', $query);

        if (!empty($addressResults)) {
            foreach ($addressResults as $address) {
                $accessAdressId = $address->id;
                $accessAdressEsre = $address->esrejendomsnr;

                // Get building.
                $buildingResults = $this->dawa->request('bbrlight/bygninger', ['adgangsadresseid' => $accessAdressId]);

                if (!empty($buildingResults)) {
                    $primaryBuilding = null;
                    $annexeBuilding = null;
                    $carportBuilding = null;

                    foreach ($buildingResults as $buildingResult) {
                        switch ($buildingResult->BYG_ANVEND_KODE) {
                            case 510:
                                $primaryBuilding = $buildingResult;
                                break;
                            case 910:
                            case 920:
                                $carportBuilding = $buildingResult;
                                break;
                            case 585:
                            case 930:
                                $annexeBuilding = $buildingResult;
                                break;
                            default:
                                var_dump($buildingResult->BYG_ANVEND_KODE);
                        }
                    }

                    if (!empty($primaryBuilding)) {
                        $buildingId = $primaryBuilding->Bygning_id;

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
                                "jordstykker/{$address->ejerlavkode}/{$address->matrikelnr}"
                            );

                            // Get owner data.
                            $ownerData = $this->getOwnerData($municipalityCode, $accessAdressEsre);

                            $this->setData(
                                $accessAdressId,
                                $address->vejnavn,
                                $address->husnr,
                                $primaryBuilding->BYG_ANVEND_KODE,
                                $primaryBuilding->OPFOERELSE_AAR,
                                $primaryBuilding->OMBYG_AAR,
                                $primaryBuilding->BYG_BOLIG_ARL_SAML,
                                $totalBasementArea,
                                $landResult->registreretareal,
                                $annexeBuilding ? $annexeBuilding->BYG_BEBYG_ARL : null,
                                $annexeBuilding ? $annexeBuilding->OPFOERELSE_AAR : null,
                                $annexeBuilding ? $annexeBuilding->OMBYG_AAR : null,
                                $carportBuilding ? $carportBuilding->BYG_BEBYG_ARL : null,
                                $carportBuilding ? $carportBuilding->OPFOERELSE_AAR : null,
                                $carportBuilding ? $carportBuilding->OMBYG_AAR : null,
                                $ownerData['deed_date'] ? $ownerData['deed_date'] : '',
                                $ownerData['sales_price'] ? $ownerData['sales_price'] : 0,
                                $ownerData['sales_date'] ? $ownerData['sales_date'] : '',
                                $address->matrikelnr
                            );
                        }
                    }
                }
            }
        }

        // Output
        $headers = [
            'Street Name',
            'House No',
            'Building Use Code',
            'Construction Year',
            'Rebuilt Year',
            'Total Living Area',
            'Total Basement Area',
            'Total Ground Area',
            'Annexe Area',
            'Annexe Construction Year',
            'Annexe Rebuilt Year',
            'Carport Area',
            'Carport Construction Year',
            'Carport Rebuilt Year',
            'Deed Date',
            'Sales Price',
            'Sales Date',
            'Cadastral Number'
        ];
        $this->renderOutput($output, $outputFormat, $headers, $this->getData());
    }

    protected function setData(
        string $accessAdressId,
        string $streetName,
        string $houseNo,
        int $buildingType,
        int $constructionYear,
        int $rebuiltYear = null,
        int $totalLivingArea = 0,
        int $tobalBasementArea = 0,
        int $totalGroundArea = 0,
        int $annexeArea = null,
        int $annexeConstructionYear = null,
        int $annexeRebuiltYear = null,
        int $carportArea = null,
        int $carportConstructionYear = null,
        int $carportRebuiltYear = null,
        string $deedDate = '',
        int $salesPrice = 0,
        string $salesDate = '',
        string $cadastralNumber = '')
    {
        $this->data[$accessAdressId] = [
            'street_name' => $streetName,
            'house_no' => $houseNo,
            'building_type' => $buildingType,
            'construction_year' => $constructionYear,
            'rebuilt_year' => $rebuiltYear,
            'total_living_area' => $totalLivingArea,
            'tobal_basement_area' => $tobalBasementArea,
            'total_ground_area' => $totalGroundArea,
            'annexe_area' => $annexeArea,
            'annexe_construction_year' => $annexeConstructionYear,
            'annexe_rebuilt_year' => $annexeRebuiltYear,
            'carport_area' => $carportArea,
            'carport_construction_year' => $carportConstructionYear,
            'carport_rebuilt_year' => $carportRebuiltYear,
            'deed_date' => $deedDate,
            'sales_price' => $salesPrice,
            'sales_date' => $salesDate,
            'cadastral_number' => $cadastralNumber
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
                preg_match('/aria-controls="collapse(.*?)256"/', $htmlData, $matches);
                $filterId = 'collapse3256';
                if (!empty($matches[1])) {
                    $filterId = "collapse{$matches[1]}256";
                }

                $crawler = new Crawler($htmlData);
                try {
                    $parentTag = $crawler->filter('p a[aria-controls="' . $filterId . '"]')->parents();
                    $textContent = $parentTag->text();

                    // Extract deed date from text content.
                    if (preg_match('/\d{2}-\d{2}-\d{4}/', $textContent,$matches)) {
                        $returnData['deed_date'] = $matches[0];
                    }
                }
                catch (\Exception $exception) {
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
