<?php

declare(strict_types=1);

/*
 * MikoPBX - free phone system for small business
 * Copyright © 2017-2026 Alexey Portnov and Nikolay Beketov
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace Modules\ModuleRoutingMap\Lib\RestAPI\Graph;

use MikoPBX\PBXCoreREST\Lib\Common\AbstractDataStructure;
use MikoPBX\PBXCoreREST\Lib\Common\OpenApiSchemaProvider;

/**
 * OpenAPI schema provider for the routing graph endpoints.
 *
 * The graph payload is a pair of arrays: nodes (typed vertices) and edges
 * (typed connections). Keeping field definitions in one place keeps the
 * generated OpenAPI spec in sync with the React Flow frontend contract.
 */
class DataStructure extends AbstractDataStructure implements OpenApiSchemaProvider
{
    public static function getListItemSchema(): array
    {
        return self::getDetailSchema();
    }

    public static function getDetailSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'nodes' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/components/schemas/RoutingMapNode'],
                ],
                'edges' => [
                    'type' => 'array',
                    'items' => ['$ref' => '#/components/schemas/RoutingMapEdge'],
                ],
            ],
        ];
    }

    public static function getRelatedSchemas(): array
    {
        return [
            'RoutingMapNode' => [
                'type' => 'object',
                'required' => ['id', 'type', 'data'],
                'properties' => [
                    'id' => ['type' => 'string', 'example' => 'route:42'],
                    'type' => [
                        'type' => 'string',
                        'enum' => [
                            'root', 'provider', 'route', 'schedule', 'ivr',
                            'queue', 'extension', 'conference', 'application',
                            'voicemail', 'external', 'unknown',
                        ],
                    ],
                    'data' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                        'example' => ['label' => 'DID 74951234567', 'did' => '74951234567'],
                    ],
                ],
            ],
            'RoutingMapEdge' => [
                'type' => 'object',
                'required' => ['id', 'source', 'target'],
                'properties' => [
                    'id' => ['type' => 'string', 'example' => 'e-route:42-ivr:main'],
                    'source' => ['type' => 'string', 'example' => 'route:42'],
                    'target' => ['type' => 'string', 'example' => 'ivr:main'],
                    'label' => ['type' => 'string', 'example' => 'Work'],
                    'data' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                    ],
                ],
            ],
        ];
    }

    public static function getParameterDefinitions(): array
    {
        return [
            'request' => [],
            'response' => [
                'nodes' => [
                    'type' => 'array',
                    'description' => 'rest_schema_routing_map_nodes',
                    'readOnly' => true,
                ],
                'edges' => [
                    'type' => 'array',
                    'description' => 'rest_schema_routing_map_edges',
                    'readOnly' => true,
                ],
            ],
            'related' => self::getRelatedSchemas(),
        ];
    }
}
