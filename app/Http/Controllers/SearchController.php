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
            'ara_heyat' => ['content'],
            'ara_heyat_takhasosi' => ['content'],
            'moghararat' => ['content'],
            'nazarat_mashverati' => ['content'],
            'qazayi' => ['content'],
            'posts' => ['content'],
            'ara_jadid' => ['content'],
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
