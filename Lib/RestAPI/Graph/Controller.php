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

use MikoPBX\PBXCoreREST\Attributes\ApiDataSchema;
use MikoPBX\PBXCoreREST\Attributes\ApiOperation;
use MikoPBX\PBXCoreREST\Attributes\ApiResource;
use MikoPBX\PBXCoreREST\Attributes\ApiResponse;
use MikoPBX\PBXCoreREST\Attributes\HttpMapping;
use MikoPBX\PBXCoreREST\Attributes\ResourceSecurity;
use MikoPBX\PBXCoreREST\Attributes\SecurityType;
use MikoPBX\PBXCoreREST\Controllers\BaseRestController;

/**
 * REST controller that exposes two read-only routing graph endpoints.
 *
 *   GET /pbxcore/api/v3/module-routing-map/graph:incoming
 *   GET /pbxcore/api/v3/module-routing-map/graph:outgoing
 *
 * Both endpoints return a { nodes, edges } payload consumable by React Flow.
 */
#[ApiResource(
    path: '/pbxcore/api/v3/module-routing-map/graph',
    tags: ['Module Routing Map - Graph'],
    description: 'Routing visualization graph (nodes and edges) for incoming and outgoing call paths',
    processor: Processor::class
)]
#[HttpMapping(
    mapping: [
        'GET' => ['getIncoming', 'getOutgoing'],
    ],
    resourceLevelMethods: [],
    collectionLevelMethods: ['getIncoming', 'getOutgoing'],
    customMethods: ['getIncoming', 'getOutgoing'],
    idPattern: '[^/:]+'
)]
#[ResourceSecurity(
    'module-routing-map-graph',
    requirements: [SecurityType::LOCALHOST, SecurityType::BEARER_TOKEN]
)]
class Controller extends BaseRestController
{
    protected string $processorClass = Processor::class;

    /**
     * @route GET /pbxcore/api/v3/module-routing-map/graph:getIncoming
     */
    #[ApiDataSchema(schemaClass: DataStructure::class, type: 'detail')]
    #[ApiOperation(
        summary: 'rest_routing_map_GetIncoming',
        description: 'rest_routing_map_GetIncomingDesc',
        operationId: 'getIncomingRoutingGraph'
    )]
    #[ApiResponse(200, 'rest_response_200_record')]
    #[ApiResponse(401, 'rest_response_401')]
    #[ApiResponse(500, 'rest_response_500')]
    public function getIncoming(): void
    {
    }

    /**
     * @route GET /pbxcore/api/v3/module-routing-map/graph:getOutgoing
     */
    #[ApiDataSchema(schemaClass: DataStructure::class, type: 'detail')]
    #[ApiOperation(
        summary: 'rest_routing_map_GetOutgoing',
        description: 'rest_routing_map_GetOutgoingDesc',
        operationId: 'getOutgoingRoutingGraph'
    )]
    #[ApiResponse(200, 'rest_response_200_record')]
    #[ApiResponse(401, 'rest_response_401')]
    #[ApiResponse(500, 'rest_response_500')]
    public function getOutgoing(): void
    {
    }
}
