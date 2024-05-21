this is how to run the importer

php artisan import:csv-to-elasticsearch path/to/your/csv_file.csv

# Close the index
curl -XPOST "http://localhost:9200/ara_heyat/_close"

# Apply the settings changes
curl -XPUT "http://localhost:9200/ara_heyat/_settings" -H 'Content-Type: application/json' -d '{"index":{"analysis":{"analyzer":{"persian_lowercase":{"type":"custom","tokenizer":"standard","filter":["lowercase"]}}}}}'

# Reopen the index
curl -XPOST "http://localhost:9200/ara_heyat/_open"

[{"text":"your-search-text-1","type":"and"},{"text":"your-search-text-2","type":"not"}]
