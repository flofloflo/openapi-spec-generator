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

        // Fix empty scopes arrays BEFORE validation: OpenAPI spec requires scopes to be objects {}, not arrays []
        // Convert empty arrays in OAuth2 flows.scopes to empty objects in the OpenAPI object structure
        $this->fixEmptyScopesArraysInObject($openapi);

        $openapi->validate();

        $storageDisk = Storage::disk(config('openapi.filesystem_disk'));

        $fileName = $serverKey.'_openapi.'.$format;

        if ($format === 'yaml') {
            $output = Yaml::dump($openapi->toArray(), 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        } elseif ($format === 'json') {
            $output = json_encode($openapi->toArray(), JSON_PRETTY_PRINT);
        }

        $storageDisk->put($fileName, $output);

        return $output;
    }

    /**
     * Fix empty scopes arrays in the OpenAPI object structure before validation.
     * Uses reflection to access and modify protected properties of OAuthFlow objects.
     *
     * @param  \GoldSpecDigital\ObjectOrientedOAS\OpenApi  $openapi
     */
    protected function fixEmptyScopesArraysInObject($openapi): void
    {
        $components = $openapi->components;
        if (! $components) {
            return;
        }

        // Use reflection to access securitySchemes property
        $componentsReflection = new \ReflectionClass($components);
        if (! $componentsReflection->hasProperty('securitySchemes')) {
            return;
        }

        $securitySchemesProperty = $componentsReflection->getProperty('securitySchemes');
        $securitySchemesProperty->setAccessible(true);
        $securitySchemes = $securitySchemesProperty->getValue($components);

        if (! is_array($securitySchemes)) {
            return;
        }

        foreach ($securitySchemes as $scheme) {
            // Use reflection to access flows property
            $schemeReflection = new \ReflectionClass($scheme);
            if (! $schemeReflection->hasProperty('flows')) {
                continue;
            }

            $flowsProperty = $schemeReflection->getProperty('flows');
            $flowsProperty->setAccessible(true);
            $flows = $flowsProperty->getValue($scheme);

            if (! is_array($flows)) {
                continue;
            }

            foreach ($flows as $flow) {
                // Use reflection to access the protected scopes property
                $flowReflection = new \ReflectionClass($flow);
                if (! $flowReflection->hasProperty('scopes')) {
                    continue;
                }

                $scopesProperty = $flowReflection->getProperty('scopes');
                $scopesProperty->setAccessible(true);
                $scopes = $scopesProperty->getValue($flow);

                // If scopes is an empty array, convert it to an empty object
                if (is_array($scopes) && empty($scopes)) {
                    $scopesProperty->setValue($flow, (object) []);
                }
            }
        }
    }
}
