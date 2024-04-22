<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elastic\Elasticsearch\ClientBuilder;

class ImportCsvToElasticsearch extends Command
{
    protected $signature = 'import:csv-to-elasticsearch {file}';

    protected $description = 'Import CSV data to Elasticsearch';

    public function handle()
    {
        $hosts = [
            'your_elasticsearch_host:9200', // Change this to your Elasticsearch host
        ];

        $client = ClientBuilder::create()->setHosts($hosts)->build();

        $csvFileName = $this->argument('file');
        $file = fopen($csvFileName, 'r');

        $params = ['body' => []];

        while (($line = fgetcsv($file)) !== FALSE) {
            $params['body'][] = [
                'index' => [
                    '_index' => 'your_index_name',
                    '_type' => '_doc',
                ],
            ];

            // Adjust field names as per your CSV columns
            $params['body'][] = [
                'field1' => $line[0],
                'field2' => $line[1],
                // Add more fields as needed
            ];

            // Limit the bulk size to improve performance
            if (count($params['body']) >= 1000) {
                $client->bulk($params);
                $params = ['body' => []];
            }
        }

        // Insert any remaining documents
        if (!empty($params['body'])) {
            $client->bulk($params);
        }

        fclose($file);

        $this->info('CSV data imported to Elasticsearch successfully!');
    }
}
