<?php

namespace LaravelJsonApi\OpenApiSpec;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;

class OpenApiGenerator
{
    /**
     * @throws \GoldSpecDigital\ObjectOrientedOAS\Exceptions\ValidationException
     */
    public function generate(string $serverKey, string $format = 'yaml'): string
    {
        $generator = new Generator($serverKey);
        $openapi = $generator->generate();

        $openapi->validate();

        $storageDisk = Storage::disk(config('openapi.filesystem_disk'));

        $fileName = $serverKey.'_openapi.'.$format;

        // Fix empty scopes arrays: OpenAPI spec requires scopes to be objects {}, not arrays []
        // Convert empty arrays in OAuth2 flows.scopes to empty objects
        $specArray = $this->fixEmptyScopesArrays($openapi->toArray());

        if ($format === 'yaml') {
            $output = Yaml::dump($specArray, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        } elseif ($format === 'json') {
            $output = json_encode($specArray, JSON_PRETTY_PRINT);
        }

        $storageDisk->put($fileName, $output);

        return $output;
    }

    /**
     * Recursively convert empty scopes arrays to empty objects in OAuth2 flows.
     * OpenAPI spec requires scopes to be objects {}, not arrays [].
     *
     * @param  array|object  $data
     * @return array|object
     */
    protected function fixEmptyScopesArrays($data)
    {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                // Check if this is a scopes field in an OAuth2 flow
                if ($key === 'scopes' && is_array($value) && empty($value)) {
                    // Convert empty array to empty object
                    $result[$key] = (object) [];
                } elseif (is_array($value) || is_object($value)) {
                    // Recursively process nested arrays/objects
                    $result[$key] = $this->fixEmptyScopesArrays($value);
                } else {
                    $result[$key] = $value;
                }
            }

            return $result;
        } elseif (is_object($data)) {
            $result = new \stdClass;
            foreach ($data as $key => $value) {
                // Check if this is a scopes field in an OAuth2 flow
                if ($key === 'scopes' && is_array($value) && empty($value)) {
                    // Convert empty array to empty object
                    $result->$key = (object) [];
                } elseif (is_array($value) || is_object($value)) {
                    // Recursively process nested arrays/objects
                    $result->$key = $this->fixEmptyScopesArrays($value);
                } else {
                    $result->$key = $value;
                }
            }

            return $result;
        }

        return $data;
    }
}
