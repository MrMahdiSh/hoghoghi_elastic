<?php

namespace App\Services;

use Elastic\Elasticsearch\ClientBuilder;

class ElasticsearchService
{
    protected $client;

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            ->setHosts(config('database.connections.elasticsearch.hosts'))
            ->build();
    }

    public function search($query)
    {
        // Define Elasticsearch search parameters
        $params = [
            'index' => 'your_index_name', // Replace with your Elasticsearch index name
            'body' => [
                'query' => [
                    'match' => [
                        'your_searchable_field' => $query, // Replace with the field you want to search
                    ],
                ],
            ],
        ];

        // Perform Elasticsearch search
        $response = $this->client->search($params);

        // Extract and return search results
        return $response['hits']['hits'];
    }

    // You can implement other Elasticsearch operations here as needed
}
