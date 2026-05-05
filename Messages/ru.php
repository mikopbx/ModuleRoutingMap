<?php

return [
    'BreadcrumbModuleRoutingMap' => 'Схема маршрутизации',
    'SubHeaderModuleRoutingMap' => 'Интерактивная визуализация входящей и исходящей маршрутизации',
    'module_routing_map' => 'Схема маршрутизации',
    'module_routing_map_description' => 'Read-only React Flow диаграмма входящих и исходящих маршрутов вызовов, построенная из текущей конфигурации MikoPBX',

    'module_routing_map_Disclaimer' => 'Схема построена только по настройкам из UI. Кастомный dialplan, хуки модулей и генерируемые контексты могут не отображаться. Для сложных конфигураций проверяйте через Asterisk CLI.',
    'module_routing_map_PageTitle' => 'Схема маршрутизации вызовов',
    'module_routing_map_PageSubtitle' => 'Автоматически строится по провайдерам, входящим/исходящим маршрутам, IVR и очередям',
    'module_routing_map_TabIncoming' => 'Входящие',
    'module_routing_map_TabOutgoing' => 'Исходящие',
    'module_routing_map_Refresh' => 'Обновить',
    'module_routing_map_StatusIdle' => 'Ожидание',
    'module_routing_map_Legend' => 'Легенда',

    'module_routing_map_NodeProvider' => 'Провайдер',
    'module_routing_map_NodeRoute' => 'Маршрут / DID',
    'module_routing_map_NodeSchedule' => 'Расписание',
    'module_routing_map_NodeIvr' => 'IVR меню',
    'module_routing_map_NodeQueue' => 'Очередь',
    'module_routing_map_NodeExtension' => 'Сотрудник',
    'module_routing_map_NodeConference' => 'Конференция',
    'module_routing_map_NodeApplication' => 'Приложение',

    'rest_tag_ModuleRoutingMapGraph' => 'Модуль "Схема маршрутизации" - Graph',
    'rest_routing_map_GetIncoming' => 'Получить граф входящей маршрутизации',
    'rest_routing_map_GetIncomingDesc' => 'Возвращает ориентированный граф входящей маршрутизации (провайдеры → маршруты → расписания → IVR/очереди/сотрудники) в виде { nodes, edges }',
    'rest_routing_map_GetOutgoing' => 'Получить граф исходящей маршрутизации',
    'rest_routing_map_GetOutgoingDesc' => 'Возвращает ориентированный граф исходящей маршрутизации (шаблоны → провайдеры) в виде { nodes, edges }',

    'rest_schema_routing_map_nodes' => 'Список узлов графа',
    'rest_schema_routing_map_edges' => 'Список рёбер графа',
];
