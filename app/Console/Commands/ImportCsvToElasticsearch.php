<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;

class ImportCsvToElasticsearch extends Command
{
    protected $signature = 'import:csv-to-elasticsearch {file}';

    protected $description = 'Import CSV data to Elasticsearch';

    public function handle()
    {
        // Initialize Elasticsearch client
        $client = ClientBuilder::create()->build();

        // Get CSV file path from command argument
        $csvFileName = $this->argument('file');

        // Open CSV file for reading
        $file = fopen($csvFileName, 'r');

        // Check if the index exists, if not create it
        $indexName = 'ara_heyat';
        if (!$client->indices()->exists(['index' => $indexName])) {
            $this->createIndex($client, $indexName);
        }

        // Prepare bulk insert parameters
        $batchSize = 100; // Adjust batch size as needed
        $params = ['body' => []];
        $count = 0;

        // Read CSV file line by line and construct bulk insert parameters
        while (($line = fgetcsv($file)) !== FALSE) {
            $params['body'][] = [
                'index' => [
                    '_index' => $indexName,
                ],
            ];

            $params['body'][] = $this->mapCsvRowToDocument($line);
            $count++;

            // Insert batch when batch size reached
            if ($count % $batchSize === 0) {
                $this->bulkInsert($client, $params);
                $params = ['body' => []];
            }
        }

        // Insert remaining documents
        if (!empty($params['body'])) {
            $this->bulkInsert($client, $params);
        }

        fclose($file);

        $this->info('CSV data imported to Elasticsearch successfully!');
    }

    // Helper method to create Elasticsearch index with mapping
    private function createIndex($client, $indexName)
    {
        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'analysis' => [
                        'analyzer' => [
                            'persian_lowercase' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'long'],
                        'title' => ['type' => 'text'],
                        'content' => ['type' => 'text'],
                        'sublink' => ['type' => 'text'],
                        'shomare_dadnameh' => ['type' => 'text'],
                        'date' => ['type' => 'text'],
                        'parvandeh' => ['type' => 'text'],
                        'marja' => ['type' => 'text'],
                        'shaki' => ['type' => 'text'],
                        'gardesh' => ['type' => 'text'],
                        'rayheiat' => ['type' => 'text'],
                        'mozo' => ['type' => 'text'],
                        'visit_number' => ['type' => 'long'],
                        'status' => ['type' => 'keyword'],
                        'created_at' => ['type' => 'date'],
                        'updated_at' => ['type' => 'date'],
                    ]
                ]
            ]
        ];

        $client->indices()->create($params);
    }

    // Helper method to map CSV row to Elasticsearch document
    private function mapCsvRowToDocument($line)
    {
        return [
            'id' => $line[0],
            'title' => $line[1],
            'content' => $line[2],
            'sublink' => $line[3],
            'shomare_dadnameh' => $line[4],
            'date' => $line[5],
            'parvandeh' => $line[6],
            'marja' => $line[7],
            'shaki' => $line[8],
            'gardesh' => $line[9],
            'rayheiat' => $line[10],
            'mozo' => $line[11],
            'visit_number' => $line[12],
            'status' => $line[13],
            'created_at' => $line[14],
            'updated_at' => $line[15],
        ];
    }

    // Helper method to perform bulk insert with retry mechanism
    private function bulkInsert($client, $params)
    {
        $maxAttempts = 3;
        $attempt = 1;

        while ($attempt <= $maxAttempts) {
            try {
                $client->bulk($params);
                return;
            } catch (ClientResponseException $e) {
                $this->error("Bulk insert failed on attempt $attempt: " . $e->getMessage());
                $attempt++;
                usleep(500000); // Wait for 0.5 seconds before retrying
            }
        }

        throw new \Exception("Bulk insert failed after $maxAttempts attempts.");
    }
}
