<?php
namespace App\Command;

use App\Entity\Tblproductdata;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportCsvCommand extends Command
{
    private $em;
    private $exchangeRate;
    private $incorrectProducts = [];
    private $discontinued;
    private $processCount = 0;
    private $successCount = 0;
    private $skippedCount = 0;

    protected static $defaultName = 'import:csv';

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();

        $this->em = $em;
        $this->exchangeRate = 1.313775;
    }

    protected function configure()
    {
        // setting basic information about the command
        $this
            ->setDescription('Add filtered products from a csv file into the database.')
            ->setHelp('This command allows you to add products into the database from a filtered csv file...')
            ->addArgument('test', InputArgument::OPTIONAL, 'Test mode')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Attempting to import products...');

        $csvFileReader = Reader::createFromPath('%kernel.root_dir%/../src/Data/stock.csv');
        $results = $csvFileReader->fetchAssoc();

        if($input->getArgument('test') === 'test'){
            $output->writeln('Running test mode...');
            $output->writeln('');
            $output->writeln('List of products that weren\'t added:');
        }
        
        // loop which iterates through csv file rows
        foreach($results as $row){
            if($input->getArgument('test') === 'test'){
                $output->writeln($row['product_code'].' '.$row['product_name']);
            }

            $this->discontinued = $row['discontinued'] === 'yes' ? new \DateTime('now') : null;

            $product = $this->em->getRepository(Tblproductdata::class)
                ->findOneBy([
                    'strproductcode' => $row['product_code']
                ])
            ;

            if ( null === $product && $this->costIsHighEnough($row) && $this->stockIsHighEnough($row) ) {
                $product = (new Tblproductdata())
                    ->setStrproductname($row['product_name'])
                    ->setStrproductdesc($row['product_desc'])
                    ->setStrproductcode($row['product_code'])
                    ->setIntproductstock($row['stock'])
                    ->setDecproductcost($row['cost'])
                    ->setDtmadded(new \DateTime('now'))
                    ->setDtmdiscontinued($this->discontinued)
                    ->setStmtimestamp(new \DateTime('now'))
                ;
            
                $this->em->persist($product);

                // checks if argument for test mode was passed, if true prevents from adding products to database
                if($input->getArgument('test') !== 'test'){
                    // writes data to database
                    $this->em->flush();

                    // adds one to succesfully added items
                    $this->successCount++;
                }
            } else {
                $this->saveIncorrectProducts($row);
            }

            // adds one to skipped items
            $this->processCount++;
        }
        

        if($input->getArgument('test') !== 'test') {
            // lists out products which weren't added to the database
            if(count($this->incorrectProducts) > 0){
                $output->writeln('List of products that weren\'t added:');
                foreach($this->incorrectProducts as $incorrectProduct){
                    $output->writeln($incorrectProduct['product_code'].' '.$incorrectProduct['product_name']);
                }
            }
        }

        $output->writeln('');
        $output->writeln($this->processCount.' items were processed');
        $output->writeln($this->skippedCount.' items were skipped');
        $output->writeln($this->successCount.' items were successfully added');
        $io->success('Products have been imported');
    }

    private function costIsHighEnough($row)
    {
       return (is_numeric($row['cost']) && ( ($row['cost'] * $this->exchangeRate) > 5 ) && ($row['cost'] < 1000)) ? true : false;
    }

    private function stockIsHighEnough($row)
    {
        return (is_numeric($row['stock']) && ($row['stock'] > 10)) ? true : false;
    }

    private function saveIncorrectProducts($row){
        array_push($this->incorrectProducts, $row);
        $this->skippedCount++;
    }
}