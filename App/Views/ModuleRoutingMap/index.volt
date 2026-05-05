{# Routing Map visualization page. The standard module header (title +
   subtitle + version + enable toggle) is rendered by the admin cabinet layout,
   so the view only owns the tabs + canvases + legend. #}
<div class="ui segment routing-map-page">
    <div class="ui small warning message routing-map-disclaimer">
        <i class="info circle icon"></i>
        {{ t._('module_routing_map_Disclaimer') }}
    </div>

    <div class="ui top attached tabular menu" id="routing-map-tabs">
        <a class="active item" data-tab="incoming">
            <i class="sign in icon"></i>
            {{ t._('module_routing_map_TabIncoming') }}
        </a>
        <a class="item" data-tab="outgoing">
            <i class="sign out icon"></i>
            {{ t._('module_routing_map_TabOutgoing') }}
        </a>
    </div>

    <div class="ui bottom attached active tab segment routing-map-tab" data-tab="incoming">
        <div class="routing-map-toolbar">
            <button class="ui small basic button" id="routing-map-refresh-incoming">
                <i class="sync icon"></i>
                {{ t._('module_routing_map_Refresh') }}
            </button>
            <div class="ui small label routing-map-status" id="routing-map-status-incoming">
                {{ t._('module_routing_map_StatusIdle') }}
            </div>
        </div>
        <div class="routing-map-canvas" id="routing-map-incoming"
             data-endpoint="{{ incomingEndpoint }}"
             data-direction="incoming">
            <div class="ui active centered inline loader"></div>
        </div>
    </div>

    <div class="ui bottom attached tab segment routing-map-tab" data-tab="outgoing">
        <div class="routing-map-toolbar">
            <button class="ui small basic button" id="routing-map-refresh-outgoing">
                <i class="sync icon"></i>
                {{ t._('module_routing_map_Refresh') }}
            </button>
            <div class="ui small label routing-map-status" id="routing-map-status-outgoing">
                {{ t._('module_routing_map_StatusIdle') }}
            </div>
        </div>
        <div class="routing-map-canvas" id="routing-map-outgoing"
             data-endpoint="{{ outgoingEndpoint }}"
             data-direction="outgoing">
            <div class="ui active centered inline loader"></div>
        </div>
    </div>

    <div class="ui segment routing-map-legend">
        <h4 class="ui header">{{ t._('module_routing_map_Legend') }}</h4>
        <div class="routing-map-legend-items">
            <span class="routing-map-legend-item" data-node-type="provider">
                <i class="server icon"></i> {{ t._('module_routing_map_NodeProvider') }}
            </span>
            <span class="routing-map-legend-item" data-node-type="route">
                <i class="sign in icon"></i> {{ t._('module_routing_map_NodeRoute') }}
            </span>
            <span class="routing-map-legend-item" data-node-type="schedule">
                <i class="calendar times outline icon"></i> {{ t._('module_routing_map_NodeSchedule') }}
            </span>
            <span class="routing-map-legend-item" data-node-type="ivr">
                <i class="sitemap icon"></i> {{ t._('module_routing_map_NodeIvr') }}
            </span>
            <span class="routing-map-legend-item" data-node-type="queue">
                <i class="users icon"></i> {{ t._('module_routing_map_NodeQueue') }}
            </span>
            <span class="routing-map-legend-item" data-node-type="extension">
                <i class="user icon"></i> {{ t._('module_routing_map_NodeExtension') }}
            </span>
            <span class="routing-map-legend-item" data-node-type="conference">
                <i class="comments icon"></i> {{ t._('module_routing_map_NodeConference') }}
            </span>
            <span class="routing-map-legend-item" data-node-type="application">
                <i class="code icon"></i> {{ t._('module_routing_map_NodeApplication') }}
            </span>
        </div>
    </div>
</div>
