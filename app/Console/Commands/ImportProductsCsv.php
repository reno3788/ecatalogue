<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ProductSyncService;

class ImportProductsCsv extends Command
{
    protected $signature = 'eproc:import-products {file : Path to the CSV file}';
    protected $description = 'Import products from a CSV file';

    protected ProductSyncService $productSyncService;

    public function __construct(ProductSyncService $productSyncService)
    {
        parent::__construct();
        $this->productSyncService = $productSyncService;
    }

    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return Command::FAILURE;
        }

        $this->info("Parsing CSV file: {$file}");

        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
            if (!$header) {
                $this->error("Invalid or empty CSV.");
                return Command::FAILURE;
            }

            $products = [];
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Map row to header keys
                $data = array_combine($header, $row);
                $products[] = $data;
            }
            fclose($handle);

            $this->info("Found " . count($products) . " products. Starting sync...");

            $results = $this->productSyncService->syncBatch($products);

            $this->info("Sync completed!");
            $this->info("Success: {$results['success']}");
            $this->info("Failed: {$results['failed']}");

            if ($results['failed'] > 0) {
                $this->error("Errors:");
                foreach ($results['errors'] as $error) {
                    $this->line(" - $error");
                }
            }

            return Command::SUCCESS;
        }

        $this->error("Could not open file.");
        return Command::FAILURE;
    }
}
