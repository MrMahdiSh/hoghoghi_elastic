<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Exception\ClientResponseException;

class ImportCsvToElasticsearch extends Command
{
    protected $signature = 'import:csv-to-elasticsearch {file} {indexName}';

    protected $tables = ['ara_heyat', 'ara_heyat_takhasosi', 'moghararat', 'nazarat_mashverati', 'qazayi', 'posts', 'ara_jadid'];


    protected $description = 'Import CSV data to Elasticsearch';

    public function handle()
    {
        // Initialize Elasticsearch client
        $client = ClientBuilder::create()->build();

        // Get CSV file path from command argument
        $csvFileName = $this->argument('file');
        $indexName = $this->argument('indexName');

        // Check if the provided index name exists in the tables array
        if (!in_array($indexName, $this->tables)) {
            $this->error("Index name '$indexName' is not valid.");
            return;
        }

        // Open CSV file for reading
        $file = fopen($csvFileName, 'r');

        // Check if the index exists, if not create it
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

            $params['body'][] = $this->mapCsvRowToDocument($line, $indexName);
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
                    'properties' => $this->getMappingProperties($indexName),
                ],
            ]
        ];

        $client->indices()->create($params);
    }

    private function mapCsvRowToDocument($line, $indexName)
    {
        switch ($indexName) {
            case 'ara_heyat':
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
                    'timestamp' => $line[16],
                ];
            case 'ara_heyat_takhasosi':
                return [
                    'id' => $line[0],
                    'title' => $line[1],
                    'content' => $line[2],
                    'sublink' => $line[3],
                    'date' => $line[4],
                    'case_number' => $line[5],
                    'verdict_number' => $line[6],
                    'complainant' => $line[7],
                    'defendant' => $line[8],
                    'complaint_subject' => $line[9],
                    'question' => $line[10],
                    'answer' => $line[11],
                    'visit_number' => $line[12],
                    'status' => $line[13],
                    'created_at' => $line[14],
                    'updated_at' => $line[15],
                ];
            case 'ara_jadid':
                return [
                    'id' => $line[0],
                    'title' => $line[1],
                    'content' => $line[2],
                    'sublink' => $line[3],
                    'date' => $line[4],
                    'shnumber' => $line[5],
                    'groupof' => $line[6],
                    'marja' => $line[7],
                    'page' => $line[8],
                    'visit_number' => $line[9],
                    'status' => $line[10],
                    'created_at' => $line[11],
                    'updated_at' => $line[12],
                ];
            case 'moghararat':
                return [
                    'id' => $line[0],
                    'qID' => $line[1],
                    'title' => $line[2],
                    'content' => $line[3],
                    'sublink' => $line[4],
                    'date' => $line[5],
                    'extra' => $line[6],
                    'jeld' => $line[7],
                    'ketab' => $line[8],
                    'bakhsh' => $line[9],
                    'fasl' => $line[10],
                    'mabhath' => $line[11],
                    'madde' => $line[12],
                    'tabsareh' => $line[13],
                    'alphabet' => $line[14],
                    'category' => $line[15],
                    'visit_number' => $line[16],
                    'status' => $line[17],
                    'created_at' => $line[18],
                    'updated_at' => $line[19],
                ];
            case 'nazarat_mashverati':
                return [
                    'id' => $line[0],
                    'title' => $line[1],
                    'content' => $line[2],
                    'sublink' => $line[3],
                    'date' => $line[4],
                    'number' => $line[5],
                    'question' => $line[6],
                    'answer' => $line[7],
                    'visit_number' => $line[8],
                    'status' => $line[9],
                    'created_at' => $line[10],
                    'updated_at' => $line[11],
                ];
            case 'qazayi':
                return [
                    'id' => $line[0],
                    'title' => $line[1],
                    'content' => $line[2],
                    'sublink' => $line[3],
                    'date' => $line[4],
                    'shomare_nazarie' => $line[5],
                    'shomare_parvande' => $line[6],
                    'estelam' => $line[7],
                    'nazarie_mashverati' => $line[8],
                    'visit_number' => $line[9],
                    'status' => $line[10],
                    'created_at' => $line[11],
                    'updated_at' => $line[12],
                ];
            case 'posts':
                return [
                    'id' => $line[0],
                    'qID' => $line[1],
                    'sublink' => $line[2],
                    'title' => $line[3],
                    'content' => $line[4],
                    'date' => $line[5],
                    'extra' => $line[6],
                    'jeld' => $line[7],
                    'ketab' => $line[8],
                    'bakhsh' => $line[9],
                    'fasl' => $line[10],
                    'mabhath' => $line[11],
                    'madde' => $line[12],
                    'tabsareh' => $line[13],
                    'alphabet' => $line[14],
                    'category' => $line[15],
                    'visit_number' => $line[16],
                    'status' => $line[17],
                    'created_at' => $line[18],
                    'updated_at' => $line[19],
                ];
            default:
                return [];
        }
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

    private function getMappingProperties($indexName)
    {
        switch ($indexName) {
            case 'ara_heyat':
                return [
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
                ];
            case 'ara_heyat_takhasosi':
                return [
                    'id' => ['type' => 'long'],
                    'title' => ['type' => 'text'],
                    'content' => ['type' => 'text'],
                    'sublink' => ['type' => 'text'],
                    'date' => ['type' => 'text'],
                    'case_number' => ['type' => 'text'],
                    'verdict_number' => ['type' => 'text'],
                    'complainant' => ['type' => 'text'],
                    'defendant' => ['type' => 'text'],
                    'complaint_subject' => ['type' => 'text'],
                    'question' => ['type' => 'text'],
                    'answer' => ['type' => 'text'],
                    'visit_number' => ['type' => 'long'],
                    'status' => ['type' => 'keyword'],
                    'created_at' => ['type' => 'date'],
                    'updated_at' => ['type' => 'date'],
                ];
            case 'ara_jadid':
                return [
                    'id' => ['type' => 'long'],
                    'title' => ['type' => 'text'],
                    'content' => ['type' => 'text'],
                    'sublink' => ['type' => 'text'],
                    'date' => ['type' => 'text'],
                    'shnumber' => ['type' => 'text'],
                    'groupof' => ['type' => 'text'],
                    'marja' => ['type' => 'text'],
                    'page' => ['type' => 'integer'],
                    'visit_number' => ['type' => 'long'],
                    'status' => ['type' => 'keyword'],
                    'created_at' => ['type' => 'date'],
                    'updated_at' => ['type' => 'date'],
                ];
            case 'moghararat':
                return [
                    'id' => ['type' => 'long'],
                    'qID' => ['type' => 'long'],
                    'title' => ['type' => 'text'],
                    'content' => ['type' => 'text'],
                    'sublink' => ['type' => 'text'],
                    'date' => ['type' => 'date'],
                    'extra' => ['type' => 'text'],
                    'jeld' => ['type' => 'text'],
                    'ketab' => ['type' => 'text'],
                    'bakhsh' => ['type' => 'text'],
                    'fasl' => ['type' => 'text'],
                    'mabhath' => ['type' => 'text'],
                    'madde' => ['type' => 'text'],
                    'tabsareh' => ['type' => 'text'],
                    'alphabet' => ['type' => 'text'],
                    'category' => ['type' => 'keyword'],
                    'visit_number' => ['type' => 'long'],
                    'status' => ['type' => 'keyword'],
                    'created_at' => ['type' => 'date'],
                    'updated_at' => ['type' => 'date'],
                ];
            case 'nazarat_mashverati':
                return [
                    'id' => ['type' => 'long'],
                    'title' => ['type' => 'text'],
                    'content' => ['type' => 'text'],
                    'sublink' => ['type' => 'text'],
                    'date' => ['type' => 'text'],
                    'number' => ['type' => 'text'],
                    'question' => ['type' => 'text'],
                    'answer' => ['type' => 'text'],
                    'visit_number' => ['type' => 'long'],
                    'status' => ['type' => 'keyword'],
                    'created_at' => ['type' => 'date'],
                    'updated_at' => ['type' => 'date'],
                ];
            case 'qazayi':
                return [
                    'id' => ['type' => 'long'],
                    'title' => ['type' => 'text'],
                    'content' => ['type' => 'text'],
                    'sublink' => ['type' => 'text'],
                    'date' => ['type' => 'text'],
                    'shomare_nazarie' => ['type' => 'text'],
                    'shomare_parvande' => ['type' => 'text'],
                    'estelam' => ['type' => 'text'],
                    'nazarie_mashverati' => ['type' => 'text'],
                    'visit_number' => ['type' => 'long'],
                    'status' => ['type' => 'keyword'],
                    'created_at' => ['type' => 'date'],
                    'updated_at' => ['type' => 'date'],
                ];
            case 'posts':
                return [
                    'id' => ['type' => 'long'],
                    'qID' => ['type' => 'long'],
                    'sublink' => ['type' => 'text'],
                    'title' => ['type' => 'text'],
                    'content' => ['type' => 'text'],
                    'date' => ['type' => 'date'],
                    'extra' => ['type' => 'text'],
                    'jeld' => ['type' => 'text'],
                    'ketab' => ['type' => 'text'],
                    'bakhsh' => ['type' => 'text'],
                    'fasl' => ['type' => 'text'],
                    'mabhath' => ['type' => 'text'],
                    'madde' => ['type' => 'text'],
                    'tabsareh' => ['type' => 'text'],
                    'alphabet' => ['type' => 'text'],
                    'category' => ['type' => 'keyword'],
                    'visit_number' => ['type' => 'long'],
                    'status' => ['type' => 'keyword'],
                    'created_at' => ['type' => 'date'],
                    'updated_at' => ['type' => 'date'],
                ];
            default:
                return [];
        }
    }
}
