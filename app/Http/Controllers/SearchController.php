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

        // Get the selected table from the request
        $selectedTable = $request->input('table_name');

        // Define the default tables to search across
        $defaultTables = ['ara_heyat', 'ara_heyat_takhasosi', 'moghararat', 'nazarat_mashverati', 'qazayi', 'posts', 'ara_jadid'];

        // If a specific table is selected, use only that table
        $tablesToSearch = ($selectedTable && in_array($selectedTable, $defaultTables)) ? [$selectedTable] : $defaultTables;

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

        // Perform the Elasticsearch search query for each selected table
        $hits = [];
        foreach ($tablesToSearch as $table) {
            $response = $client->search([
                'index' => $table,
                'body' => [
                    'query' => $combinedQuery,
                ],
            ]);

            // Extract and merge search results
            $hits = array_merge($hits, $response['hits']['hits']);
        }

        // Return combined search results
        return response()->json($hits);
    }
}
