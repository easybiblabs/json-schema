<?php

require __DIR__.'/../vendor/autoload.php';

$retriever = new JsonSchema\Uri\UriRetriever;
$schema = $retriever->retrieve('file://'. __DIR__.'/schema.json');
$data = json_decode(file_get_contents(__DIR__.'/data.json'));

// If you use $ref or if you are unsure, resolve those references here
// This modifies the $schema object
$refResolver = new JsonSchema\RefResolver($retriever);
$refResolver->resolve($schema, 'file://' . __DIR__);

$start = microtime(true);

$count = 500;

for ($i = 0; $i < $count; $i++)
{
    $tmpSchema = clone $schema;

    // Validate
    $validator = new JsonSchema\Validator();
    $validator->check($data, $tmpSchema);

    if (!$validator->isValid()) {
        echo "JSON does not validate. Violations:\n";
        foreach ($validator->getErrors() as $error) {
            echo sprintf("[%s] %s\n", $error['property'], $error['message']);
        }
    }
}
$total = microtime(true)-$start;
$avg = ($total / $count) * 1000.0;

echo "Took $total seconds\n";
echo "That's an average of $avg ms\n";