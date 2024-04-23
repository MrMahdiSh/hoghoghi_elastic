<?php

namespace App\Http\Controllers;

use App\Services\ElasticsearchService;
use Illuminate\Http\Request;
use Elastic\Elasticsearch\ClientBuilder;

class SearchController extends Controller
{
    public function search($text)
    {
        // Initialize Elasticsearch client
        $client = ClientBuilder::create()->build();

        // Define the search query
        $elasticsearchQuery = [
            'multi_match' => [
                'query' => $text,
                'fields' => ['content^3', 'title^2'], // Boosting title field over content
                'analyzer' => 'persian_lowercase', // Use the custom analyzer
            ],
        ];

        // Perform the search query
        $response = $client->search([
            'index' => 'ara_heyat', // Specify the index to search
            'body' => [
                'query' => $elasticsearchQuery
            ]
        ]);

        // Extract and return search results
        $hits = $response['hits']['hits'];
        return response()->json($hits);
    }
}
