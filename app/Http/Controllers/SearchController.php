<?php

namespace App\Http\Controllers;

use App\Services\ElasticsearchService;
use Illuminate\Http\Request;
use Elastic\Elasticsearch\ClientBuilder;

class SearchController extends Controller
{
    /**
     * @OA\Post(
     *     path="/search",
     *     summary="Search across multiple tables",
     *     description="Searches for records across specified tables with given criteria.",
     *     operationId="searchRecords",
     *     tags={"Search"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="search_queries", type="string", description="JSON string of search queries"),
     *             @OA\Property(property="table_name", type="string", description="Table to search, optional. Defaults to all tables."),
     *             @OA\Property(property="from_date", type="string", format="date", description="Start date for the search range, optional."),
     *             @OA\Property(property="to_date", type="string", format="date", description="End date for the search range, optional."),
     *             @OA\Property(property="page", type="integer", description="Page number for pagination, optional. Defaults to 1."),
     *             @OA\Property(property="per_page", type="integer", description="Number of results per page, optional. Defaults to 10."),
     *             @OA\Property(property="exact_match", type="boolean", description="Whether to use exact match, optional. Defaults to false."),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search results successfully returned",
     *         @OA\JsonContent(
     *             @OA\Property(property="hits", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total_hits", type="integer"),
     *                 @OA\Property(property="total_pages", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid search parameters")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve search results")
     *         )
     *     )
     * )
     */

    public function search(Request $request)
    {
        // Get the search queries JSON from the request
        $searchQueriesJson = $request->input('search_queries');
        $selectedTable = $request->input('table_name');
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 10);
        $exactMatch = $request->input('exact_match', false);

        // Decode search queries JSON
        $searchQueries = json_decode($searchQueriesJson, true);

        // Default tables to search across
        $defaultTables = ['ara_heyat', 'ara_heyat_takhasosi', 'moghararat', 'nazarat_mashverati', 'qazayi', 'posts', 'ara_jadid'];

        // Define specific fields to search for each table
        $tableFields = [
            'ara_heyat' => ['content', 'shomare_dadnameh', 'title'],
            'ara_heyat_takhasosi' => ['content', 'shomare_dadnameh', 'title'],
            'moghararat' => ['content', 'title'],
            'nazarat_mashverati' => ['content', 'title', 'number'],
            'qazayi' => ['content', 'title', 'number', 'estelam', 'nazarie_mashverati', 'shomare_nazarie', 'shomare_parvande'],
            'posts' => ['content', 'title'],
            'ara_jadid' => ['content', 'title', 'shnumber'],
        ];

        // Determine tables to search
        $tablesToSearch = ($selectedTable && in_array($selectedTable, $defaultTables)) ? [$selectedTable] : $defaultTables;

        // Initialize Elasticsearch client
        $client = ClientBuilder::create()->build();

        // Initialize arrays for Elasticsearch queries
        $elasticsearchQueries = [];
        $excludeWords = [];

        // Process each search query
        if (!empty($searchQueries)) {
            foreach ($searchQueries as $query) {
                $queryText = $query['text'];
                $queryType = $query['type'];

                // Construct Elasticsearch query based on type and fields
                if ($queryType === 'and') {
                    $matchQueries = [];
                    foreach ($tablesToSearch as $table) {
                        foreach ($tableFields[$table] as $field) {
                            if ($exactMatch) {
                                // Exact match phrase with no slop
                                $matchQueries[] = ['match_phrase' => [$field => ['query' => $queryText, 'slop' => 0]]];
                            } else {
                                // Match phrase with slop for near matches
                                $matchQueries[] = ['match_phrase' => [$field => ['query' => $queryText, 'slop' => 200]]];
                            }
                        }
                    }
                    $elasticsearchQueries[] = ['bool' => ['should' => $matchQueries]];
                } elseif ($queryType === 'not') {
                    $excludeWords[] = $queryText;
                }
            }
        }

        // Construct the must_not clause to exclude documents containing the specified words
        $mustNotClause = [];
        foreach ($excludeWords as $word) {
            foreach ($tablesToSearch as $table) {
                foreach ($tableFields[$table] as $field) {
                    $mustNotClause[] = ['match' => [$field => $word]];
                }
            }
        }

        // Construct date range query
        $dateRangeField = ($selectedTable == 'ara_heyat') ? 'timestamp' : 'date';
        $dateRangeQuery = [];
        if ($fromDate || $toDate) {
            $dateRangeQuery = ['range' => []];
            if ($fromDate) {
                $dateRangeQuery['range'][$dateRangeField]['gte'] = $fromDate;
            }
            if ($toDate) {
                $dateRangeQuery['range'][$dateRangeField]['lte'] = $toDate;
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

        // Perform search query for each table with pagination
        $hits = [];
        $totalHits = 0;
        $offset = ($page - 1) * $perPage;

        foreach ($tablesToSearch as $table) {
            try {
                $response = $client->search([
                    'index' => $table,
                    'body' => [
                        'query' => $combinedQuery,
                        'from' => $offset,
                        'size' => $perPage,
                    ],
                ]);

                $hits = array_merge($hits, $response['hits']['hits']);
                $totalHits += $response['hits']['total']['value'];
            } catch (\Exception $e) {
                // Handle Elasticsearch query exception
                return response()->json(['error' => 'Failed to retrieve search results.']);
            }
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
