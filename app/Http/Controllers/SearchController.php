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

        // Get the from and to dates from the request
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        // Get the page number and items per page from the request
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);

        // Calculate the offset based on the page number and items per page
        $offset = ($page - 1) * $perPage;

        // Get the exact match option from the request
        $exactMatch = $request->input('exact_match', false);

        // Initialize Elasticsearch client
        $client = ClientBuilder::create()->build();

        // Calculate the offset based on the page number and items per page
        $offset = ($page - 1) * $perPage;

        // Initialize an array to store the Elasticsearch queries
        $elasticsearchQueries = [];

        // Initialize an array to store words to exclude
        $excludeWords = [];

        // Process each search query
        if (!empty($searchQueries)) {

            foreach ($searchQueries as $query) {
                // Extract text and type from the query
                $queryText = $query['text'];
                $queryType = $query['type'];

                // Define the Elasticsearch query based on the type and exact match option
                if ($exactMatch && $queryType === 'and') {
                    $elasticsearchQuery = [
                        'term' => [
                            'content' => $queryText,
                        ],
                    ];
                } elseif ($queryType === 'and') {
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
        }

        // Construct the must_not clause to exclude documents containing the specified words
        $mustNotClause = [];
        foreach ($excludeWords as $word) {
            $mustNotClause[] = ['match' => ['content' => $word]];
        }

        // Initialize the date range query
        $dateRangeQuery = [];
        if ($fromDate && $toDate) {
            if ($selectedTable == 'ara_heyat') {
                $dateRangeQuery = [
                    'range' => [
                        'timestamp' => [
                            'gte' => $fromDate,
                            'lte' => $toDate,
                        ],
                    ],
                ];
            } else {
                $dateRangeQuery = [
                    'range' => [
                        'date' => [
                            'gte' => $fromDate,
                            'lte' => $toDate,
                        ],
                    ],
                ];
            }
        } elseif ($fromDate) {
            if ($selectedTable == 'ara_heyat') {
                $dateRangeQuery = [
                    'range' => [
                        'timestamp' => [
                            'gte' => $fromDate,
                        ],
                    ],
                ];
            } else {
                $dateRangeQuery = [
                    'range' => [
                        'date' => [
                            'gte' => $fromDate,
                        ],
                    ],
                ];
            }
        } elseif ($toDate) {
            if ($selectedTable == 'ara_heyat') {
                $dateRangeQuery = [
                    'range' => [
                        'timestamp' => [
                            'lte' => $toDate,
                        ],
                    ],
                ];
            } else {
                $dateRangeQuery = [
                    'range' => [
                        'date' => [
                            'lte' => $toDate,
                        ],
                    ],
                ];
            }
        }

        // Combine all Elasticsearch queries using 'should' for 'and' queries
        $combinedQuery = [
            'bool' => [
                'should' => $elasticsearchQueries,
                'must_not' => $mustNotClause,
            ],
        ];

        // Add the date range query to the combined query
        if (!empty($dateRangeQuery)) {
            $combinedQuery['bool']['filter'] = $dateRangeQuery;
        }

        // Perform the Elasticsearch search query for each selected table with pagination
        $hits = [];
        $totalHits = 0;
        foreach ($tablesToSearch as $table) {
            $response = $client->search([
                'index' => $table,
                'body' => [
                    'query' => $combinedQuery,
                    'from' => $offset, // Offset for pagination
                    'size' => $perPage, // Number of items per page
                ],
            ]);

            // Extract and merge search results
            $hits = array_merge($hits, $response['hits']['hits']);
            $totalHits += $response['hits']['total']['value'];
        }
        // Calculate pagination details
        $totalPages = ceil($totalHits / $perPage);

        // Return combined search results
        return response()->json([
            'hits' => $hits,
            'pagination' => [
                'total_hits' => $totalHits,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }
}
