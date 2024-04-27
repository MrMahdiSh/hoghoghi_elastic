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

        // Initialize Elasticsearch client
        $client = ClientBuilder::create()->build();

        // Initialize an array to store the Elasticsearch queries
        $elasticsearchQueries = [];

        // Initialize an array to store words to exclude
        $excludeWords = [];

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
                // Extract words to exclude from the "not" query
                $excludeWords[] = $queryText;
            }

            // Add the Elasticsearch query to the array
            $elasticsearchQueries[] = $elasticsearchQuery ?? null;
        }

        // Construct the must_not clause to exclude documents containing the specified words
        $mustNotClause = [];
        foreach ($excludeWords as $word) {
            $mustNotClause[] = ['match' => ['content' => $word]];
        }

        // Combine all Elasticsearch queries using 'should' for 'and' queries
        $combinedQuery = [
            'bool' => [
                'should' => $elasticsearchQueries,
                'must_not' => $mustNotClause,
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
