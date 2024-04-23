this is how to run the importer

php artisan import:csv-to-elasticsearch path/to/your/csv_file.csv

curl -XPUT "http://localhost:9200/ara_heyat/_settings" -H "Content-Type: application/json" -d "{\"index\":{\"analysis\":{\"analyzer\":{\"persian_lowercase\":{\"type\":\"custom\",\"tokenizer\":\"standard\",\"filter\":[\"lowercase\"]}}}}}"



[{"text":"your-search-text-1","type":"and"},{"text":"your-search-text-2","type":"not"}]