<?php

namespace App\Http\Controllers;

use App\Services\ElasticsearchService;
use Illuminate\Http\Request;
use Elastic\Elasticsearch\ClientBuilder;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        // Get the search queries JSON from the request
        $searchQueriesJson = $request->input('search_queries');

        
        // Decode the JSON to an array
        $searchQueries = json_decode($searchQueriesJson, true);
        
        return $searchQueries;
        
        // Initialize Elasticsearch client
        $client = ClientBuilder::create()->build();

        // Initialize an array to store the Elasticsearch queries
        $elasticsearchQueries = [];

        // Process each search query
        foreach ($searchQueries as $query) {
            // Extract text and type from the query
            $queryText = $query['text'];
            $queryType = $query['type'];

            // Define the Elasticsearch query based on the type
            if ($queryType === 'and') {
                $elasticsearchQuery = [
                    'match' => [
                        'content' => $queryText,
                    ],
                ];
            } elseif ($queryType === 'not') {
                $elasticsearchQuery = [
                    'bool' => [
                        'must_not' => [
                            [
                                'match' => [
                                    'content' => $queryText,
                                ],
                            ],
                        ],
                    ],
                ];
            }

            // Add the Elasticsearch query to the array
            $elasticsearchQueries[] = $elasticsearchQuery;
        }

        // Combine all Elasticsearch queries using 'should' for 'and' queries
        // and 'must_not' for 'not' queries
        $combinedQuery = [
            'bool' => [
                'should' => $elasticsearchQueries,
            ],
        ];

        // Perform the Elasticsearch search query
        $response = $client->search([
            'index' => 'ara_heyat',
            'body' => [
                'query' => $combinedQuery,
            ],
        ]);

        // Extract and return search results
        $hits = $response['hits']['hits'];
        return response()->json($hits);
    }
}
