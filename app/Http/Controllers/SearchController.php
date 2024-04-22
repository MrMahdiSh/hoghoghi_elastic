<?php

namespace App\Http\Controllers;

use App\Services\ElasticsearchService;
use Illuminate\Http\Request;
use Elastic\Elasticsearch\ClientBuilder;

class SearchController extends Controller
{
    protected $elasticsearchService;

    // public function __construct(ElasticsearchService $elasticsearchService)
    // {
    //     $this->elasticsearchService = $elasticsearchService;
    // }

    public function search(Request $request)
    {
        // Create Elasticsearch client
        $client = ClientBuilder::create()->build();
        
        // Get search query from request
        $query = $request->input('q');

        // Define Elasticsearch search parameters
        $params = [
            'index' => 'posts',
            'body' => [
                'query' => [
                    'match' => [
                        'title' => $query,
                    ],
                ],
            ],
        ];

        // Perform Elasticsearch search
        $response = $client->search($params);

        // Extract search results
        $hits = $response['hits']['hits'];

        // Return search results as JSON
        return response()->json($hits);
    }
}
