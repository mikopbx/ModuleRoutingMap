<?php

return [
    'BreadcrumbModuleRoutingMap' => 'Routing Map',
    'SubHeaderModuleRoutingMap' => 'Interactive visualization of incoming and outgoing call routing',
    'module_routing_map' => 'Routing Map',
    'module_routing_map_description' => 'Read-only React Flow diagram of incoming and outgoing call paths built from current MikoPBX configuration',

    'module_routing_map_Disclaimer' => 'This diagram is built from UI configuration only. Custom dialplan files, module hooks and generated contexts may not appear. For complex setups verify via Asterisk CLI.',
    'module_routing_map_PageTitle' => 'Call routing map',
    'module_routing_map_PageSubtitle' => 'Auto-generated from providers, incoming/outgoing routes, IVRs and queues',
    'module_routing_map_TabIncoming' => 'Incoming',
    'module_routing_map_TabOutgoing' => 'Outgoing',
    'module_routing_map_Refresh' => 'Refresh',
    'module_routing_map_StatusIdle' => 'Idle',
    'module_routing_map_Legend' => 'Legend',

    'module_routing_map_NodeProvider' => 'Provider',
    'module_routing_map_NodeRoute' => 'Route / DID',
    'module_routing_map_NodeSchedule' => 'Time condition',
    'module_routing_map_NodeIvr' => 'IVR menu',
    'module_routing_map_NodeQueue' => 'Call queue',
    'module_routing_map_NodeExtension' => 'Extension',
    'module_routing_map_NodeConference' => 'Conference',
    'module_routing_map_NodeApplication' => 'Application',

    'rest_tag_ModuleRoutingMapGraph' => 'Module Routing Map - Graph',
    'rest_routing_map_GetIncoming' => 'Get incoming routing graph',
    'rest_routing_map_GetIncomingDesc' => 'Returns the directed graph of the incoming call routing (providers → routes → time conditions → IVR/queues/extensions) as { nodes, edges }',
    'rest_routing_map_GetOutgoing' => 'Get outgoing routing graph',
    'rest_routing_map_GetOutgoingDesc' => 'Returns the directed graph of outbound routing (patterns → providers) as { nodes, edges }',

    'rest_schema_routing_map_nodes' => 'List of graph nodes',
    'rest_schema_routing_map_edges' => 'List of graph edges',
];
