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

namespace Modules\ModuleRoutingMap\Lib\GraphBuilder;

use MikoPBX\Common\Models\CallQueueMembers;
use MikoPBX\Common\Models\CallQueues;
use MikoPBX\Common\Models\ConferenceRooms;
use MikoPBX\Common\Models\DialplanApplications;
use MikoPBX\Common\Models\Extensions;
use MikoPBX\Common\Models\ExternalPhones;
use MikoPBX\Common\Models\IncomingRoutingTable;
use MikoPBX\Common\Models\IvrMenu;
use MikoPBX\Common\Models\IvrMenuActions;
use MikoPBX\Common\Models\OutWorkTimes;
use MikoPBX\Common\Models\OutWorkTimesRouts;
use MikoPBX\Common\Models\Providers;
use MikoPBX\Common\Models\SoundFiles;

/**
 * Builds a graph representing the inbound call path:
 *
 *   Provider → Incoming route (DID) → Time condition? → Target (IVR/Queue/Extension/…)
 *
 * The builder walks relational data without recursion on the PHP stack for IVR
 * and queue redirects; instead a visited-set over node IDs guards against cycles.
 * Every edge is annotated with a reason label so the React Flow frontend can
 * colour branches (work vs non-work, queue empty fallback, timeout, etc.).
 */
final class IncomingGraphBuilder implements GraphBuilderInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $nodes = [];

    /** @var array<string, array<string, mixed>> */
    private array $edges = [];

    /** @var array<string, true> Nodes whose children are already expanded. */
    private array $expanded = [];

    /**
     * Time conditions that apply to every incoming route regardless of the
     * per-route junction table (m_OutWorkTimesRouts). In core dialplan these
     * are conditions with `allowRestriction = '0'` — the default. See
     * `ExtensionsOutWorkTimeConf::generate()` for the authoritative logic:
     * Asterisk `Gosub`s every condition on every incoming call and lets the
     * first matching one take over with either `extension` or `playmessage`.
     *
     * @var list<OutWorkTimes>
     */
    private array $globalConditions = [];

    public function build(): array
    {
        $this->nodes = [];
        $this->edges = [];
        $this->expanded = [];
        $this->globalConditions = $this->loadGlobalConditions();

        // External caller synthetic root — symmetric to outgoing's
        // `caller:internal`. Gives every provider a visible upstream anchor so
        // the incoming graph reads top-down the same way the outgoing one does.
        $externalRootId = 'caller:external';
        $this->addNode(NodeFactory::node(
            $externalRootId,
            NodeFactory::TYPE_ROOT,
            [
                'label' => 'External caller',
                'kind' => 'source',
            ]
        ));

        // Create a single "Global time policy" pivot if any global conditions
        // exist. Every provider wires into it exactly once. Without this pivot
        // we'd emit provider_count × global_condition_count edges and spam the
        // visualization; with it each provider contributes a single edge.
        $pivotNodeId = $this->globalConditions !== []
            ? $this->createGlobalSchedulePivot()
            : null;

        $providers = Providers::find(['order' => 'type, uniqid']);
        $routesByProvider = $this->groupRoutesByProvider();

        foreach ($providers as $provider) {
            $providerNodeId = NodeFactory::providerId((string) $provider->uniqid);
            $this->addNode(NodeFactory::node(
                $providerNodeId,
                NodeFactory::TYPE_PROVIDER,
                [
                    'label' => $this->cleanLabel((string) $provider->getRepresent()),
                    'kind' => strtolower((string) $provider->type),
                    'uniqid' => (string) $provider->uniqid,
                    'disabled' => $this->isProviderDisabled($provider),
                    'href' => $this->providerHref((string) $provider->type, (string) $provider->uniqid),
                ]
            ));
            $this->addEdge(NodeFactory::edge($externalRootId, $providerNodeId));

            if ($pivotNodeId !== null) {
                $this->addEdge(NodeFactory::edge(
                    $providerNodeId,
                    $pivotNodeId,
                    null,
                    ['branch' => 'gate']
                ));
            }

            $uniqid = (string) $provider->uniqid;
            $routes = $routesByProvider[$uniqid] ?? [];
            $this->attachRoutesToProvider($providerNodeId, $uniqid, $routes);
        }

        // Default catch-all routes (priority 9999 or without a provider)
        if (isset($routesByProvider['__default__'])) {
            $defaultRootId = 'provider:__default__';
            $this->addNode(NodeFactory::node(
                $defaultRootId,
                NodeFactory::TYPE_PROVIDER,
                [
                    'label' => 'Default route',
                    'kind' => 'default',
                    'uniqid' => '',
                    'href' => '/admin-cabinet/incoming-routes/index',
                ]
            ));
            $this->addEdge(NodeFactory::edge($externalRootId, $defaultRootId));

            if ($pivotNodeId !== null) {
                $this->addEdge(NodeFactory::edge(
                    $defaultRootId,
                    $pivotNodeId,
                    null,
                    ['branch' => 'gate']
                ));
            }
            $defaultRoutes = $routesByProvider['__default__'];
            $this->attachRoutesToProvider($defaultRootId, '__default__', $defaultRoutes);
        }

        return [
            'nodes' => array_values($this->nodes),
            'edges' => array_values($this->edges),
        ];
    }

    /**
     * Wires a provider's incoming routes onto the graph. Inserts a priority
     * dispatcher only when there's more than one route — with a single rule
     * there's nothing to "dispatch" and the dispatcher node is pure visual
     * noise (empty pass-through). The dispatcher carries the sequential
     * evaluation semantics (ascending priority, first DID match wins), which
     * only becomes informative once at least two rules compete.
     *
     * @param list<IncomingRoutingTable> $routes
     */
    private function attachRoutesToProvider(string $providerNodeId, string $providerUniqid, array $routes): void
    {
        if ($routes === []) {
            return;
        }
        if (count($routes) === 1) {
            // Single route: attach directly, no dispatcher, no priority label.
            $this->appendRouteBranch($providerNodeId, $routes[0]);
            return;
        }

        $dispatcherNodeId = $this->createDispatcher($providerNodeId, $providerUniqid, $routes);
        foreach ($routes as $route) {
            $this->appendRouteBranch($dispatcherNodeId, $route, (string) $route->priority);
        }
    }

    /**
     * Creates a "Priority dispatcher" node between a provider and its incoming
     * routes. Dispatcher carries the evaluation semantics (routes are scanned
     * sequentially by ascending priority, first DID match wins) — which the
     * flat provider → route fan-out used to hide.
     *
     * @param list<IncomingRoutingTable> $routes
     */
    private function createDispatcher(string $providerNodeId, string $providerUniqid, array $routes): string
    {
        $dispatcherNodeId = NodeFactory::dispatcherId($providerUniqid);

        $priorities = array_map(static fn($r) => (int) $r->priority, $routes);
        $min = $priorities === [] ? 0 : min($priorities);
        $max = $priorities === [] ? 0 : max($priorities);

        $this->addNode(NodeFactory::node(
            $dispatcherNodeId,
            NodeFactory::TYPE_DISPATCHER,
            [
                'label' => 'Priority dispatcher',
                'rulesCount' => count($routes),
                'priorityRange' => [$min, $max],
                'providerUniqid' => $providerUniqid,
            ]
        ));
        $this->addEdge(NodeFactory::edge($providerNodeId, $dispatcherNodeId));

        return $dispatcherNodeId;
    }

    /**
     * Creates a single "Global time policy" pivot node plus all of its schedule
     * children and their non-work actions. The pivot is attached later once per
     * provider (by the caller) — we only build the self-contained schedules
     * subgraph here.
     *
     * @return string The pivot node ID providers should connect to.
     */
    private function createGlobalSchedulePivot(): string
    {
        $pivotNodeId = 'schedule-pivot:global';

        $ruleSummaries = [];
        foreach ($this->globalConditions as $condition) {
            $ruleSummaries[] = $this->scheduleLabel($condition);
        }

        $this->addNode(NodeFactory::node(
            $pivotNodeId,
            NodeFactory::TYPE_SCHEDULE,
            [
                'label' => 'Global time policy',
                'kind' => 'pivot',
                'description' => sprintf(
                    '%d rule%s applied before every route',
                    count($this->globalConditions),
                    count($this->globalConditions) === 1 ? '' : 's'
                ),
                'scope' => 'global',
                'rules' => $ruleSummaries,
                'href' => '/admin-cabinet/off-work-times/index',
            ]
        ));

        foreach ($this->globalConditions as $condition) {
            $this->attachGlobalSchedule($pivotNodeId, $condition);
        }

        return $pivotNodeId;
    }

    /**
     * Attaches a single global schedule (and its non-work sink) underneath the
     * pivot. Mirrors attachScheduleBranch() but is only used for the pivot
     * wiring — per-route conditions use the inline variant.
     */
    private function attachGlobalSchedule(string $pivotNodeId, OutWorkTimes $condition): void
    {
        $scheduleNodeId = NodeFactory::scheduleId((int) $condition->id);
        if (!isset($this->nodes[$scheduleNodeId])) {
            $this->addNode(NodeFactory::node(
                $scheduleNodeId,
                NodeFactory::TYPE_SCHEDULE,
                [
                    'label' => $this->scheduleLabel($condition),
                    'description' => (string) ($condition->description ?? ''),
                    'priority' => (int) $condition->priority,
                    'scope' => 'global',
                    'href' => '/admin-cabinet/off-work-times/modify/' . (int) $condition->id,
                ]
            ));
        }
        $this->addEdge(NodeFactory::edge($pivotNodeId, $scheduleNodeId));

        $action = (string) ($condition->action ?? '');
        if ($action === 'playmessage' || $action === '') {
            $this->attachPlaybackSink(
                $scheduleNodeId,
                (string) ($condition->audio_message_id ?? ''),
                ['label' => 'Non-work', 'branch' => 'nonwork']
            );
            return;
        }

        $this->attachTarget(
            $scheduleNodeId,
            (string) ($condition->extension ?? ''),
            ['label' => 'Non-work', 'branch' => 'nonwork']
        );
    }

    /**
     * @return array<string, list<IncomingRoutingTable>>
     */
    private function groupRoutesByProvider(): array
    {
        $routes = IncomingRoutingTable::find(['order' => 'priority asc, id asc']);
        $grouped = [];
        foreach ($routes as $route) {
            $key = (string) ($route->provider ?? '');
            if ($key === '' || $key === 'none' || (int) $route->priority === 9999) {
                $grouped['__default__'][] = $route;
                continue;
            }
            $grouped[$key][] = $route;
        }

        return $grouped;
    }

    private function appendRouteBranch(
        string $parentNodeId,
        IncomingRoutingTable $route,
        ?string $edgeLabel = null
    ): void {
        $routeNodeId = NodeFactory::routeId((int) $route->id);
        $did = (string) ($route->number ?? '');
        $label = $did !== '' ? "DID {$did}" : ($route->rulename ?: 'Any');

        $this->addNode(NodeFactory::node(
            $routeNodeId,
            NodeFactory::TYPE_ROUTE,
            [
                'label' => $label,
                'did' => $did,
                'rulename' => (string) ($route->rulename ?? ''),
                'priority' => (int) $route->priority,
                'timeout' => (int) $route->timeout,
                'href' => '/admin-cabinet/incoming-routes/modify/' . (int) $route->id,
            ]
        ));
        $this->addEdge(NodeFactory::edge($parentNodeId, $routeNodeId, $edgeLabel));

        // Global conditions (allowRestriction='0') are attached via the shared
        // pivot node wired in build() — we only handle per-route-linked rules
        // (allowRestriction='1') here. These are rare in practice but when
        // present they override the route's default flow exactly like the
        // globals do.
        $perRouteConditions = $this->collectLinkedConditions($route);

        if ($perRouteConditions === []) {
            $this->attachTarget($routeNodeId, (string) ($route->extension ?? ''), null);
            return;
        }

        foreach ($perRouteConditions as $condition) {
            $this->attachScheduleBranch($routeNodeId, $condition);
        }

        // Work branch: normal route target, reached when no time condition matches.
        $this->attachTarget(
            $routeNodeId,
            (string) ($route->extension ?? ''),
            ['label' => 'Work', 'branch' => 'work']
        );
    }

    /**
     * Attaches a schedule node and its "non-work" action (playback or redirect)
     * to the given parent. Schedule node dedup means the same global condition
     * referenced by multiple routes produces a single node with multiple
     * incoming edges.
     */
    private function attachScheduleBranch(string $parentNodeId, OutWorkTimes $condition): void
    {
        $scheduleNodeId = NodeFactory::scheduleId((int) $condition->id);
        $created = !isset($this->nodes[$scheduleNodeId]);
        if ($created) {
            $this->addNode(NodeFactory::node(
                $scheduleNodeId,
                NodeFactory::TYPE_SCHEDULE,
                [
                    'label' => $this->scheduleLabel($condition),
                    'description' => (string) ($condition->description ?? ''),
                    'priority' => (int) $condition->priority,
                    'scope' => ((string) $condition->allowRestriction === '1') ? 'per-route' : 'global',
                    'href' => '/admin-cabinet/off-work-times/modify/' . (int) $condition->id,
                ]
            ));
        }
        $this->addEdge(NodeFactory::edge($parentNodeId, $scheduleNodeId));

        if (!$created) {
            // Playback/target branch was already attached the first time we
            // created this schedule — no need to re-attach duplicates.
            return;
        }

        $action = (string) ($condition->action ?? '');
        if ($action === 'playmessage' || $action === '') {
            $this->attachPlaybackSink(
                $scheduleNodeId,
                (string) ($condition->audio_message_id ?? ''),
                ['label' => 'Non-work', 'branch' => 'nonwork']
            );
            return;
        }

        // action === 'extension' — non-work redirect to some extension.
        $this->attachTarget(
            $scheduleNodeId,
            (string) ($condition->extension ?? ''),
            ['label' => 'Non-work', 'branch' => 'nonwork']
        );
    }

    /**
     * Loads time conditions that apply globally (allowRestriction='0').
     * Ordered by priority so the visualization mirrors the evaluation order
     * Asterisk uses when cascading `Gosub`s through every rule.
     *
     * @return list<OutWorkTimes>
     */
    private function loadGlobalConditions(): array
    {
        $records = OutWorkTimes::find([
            'conditions' => "allowRestriction = '0' OR allowRestriction IS NULL",
            'order' => 'priority, id',
        ]);
        $out = [];
        foreach ($records as $record) {
            $out[] = $record;
        }
        return $out;
    }

    /**
     * @return list<OutWorkTimes>
     */
    private function collectLinkedConditions(IncomingRoutingTable $route): array
    {
        $links = OutWorkTimesRouts::find([
            'conditions' => 'routId = :rid:',
            'bind' => ['rid' => (int) $route->id],
        ]);

        $branches = [];
        foreach ($links as $link) {
            $condition = OutWorkTimes::findFirstById((int) $link->timeConditionId);
            if ($condition === null) {
                continue;
            }
            // Skip conditions already included as globals to avoid duplicate
            // edges when a rule is both global and linked.
            if ((string) $condition->allowRestriction !== '1') {
                continue;
            }
            $branches[] = $condition;
        }

        return $branches;
    }

    /**
     * Creates a "Play audio then hangup" leaf node representing the
     * `action=playmessage` branch of a time condition. Looks up the sound
     * file name from SoundFiles for a useful label.
     *
     * @param array{label?: string, branch?: string}|null $edgeMeta
     */
    private function attachPlaybackSink(
        string $parentNodeId,
        string $audioMessageId,
        ?array $edgeMeta
    ): void {
        $nodeId = 'playback:' . ($audioMessageId !== '' ? $audioMessageId : 'silent');
        if (!isset($this->nodes[$nodeId])) {
            $soundLabel = 'Play message';
            if ($audioMessageId !== '') {
                $sound = SoundFiles::findFirstById((int) $audioMessageId);
                if ($sound !== null) {
                    $name = (string) ($sound->name ?? '');
                    $soundLabel = $name !== '' ? "Play: {$name}" : $soundLabel;
                }
            }

            $this->addNode(NodeFactory::node(
                $nodeId,
                NodeFactory::TYPE_VOICEMAIL,
                [
                    'label' => $soundLabel,
                    'kind' => 'playback',
                    'audio_message_id' => $audioMessageId,
                    'href' => $audioMessageId !== ''
                        ? '/admin-cabinet/sound-files/modify/' . (int) $audioMessageId
                        : '/admin-cabinet/sound-files/index',
                ]
            ));
        }

        $label = $edgeMeta['label'] ?? null;
        $data = $edgeMeta !== null ? array_diff_key($edgeMeta, ['label' => null]) : [];
        $this->addEdge(NodeFactory::edge($parentNodeId, $nodeId, $label, $data));
    }

    /**
     * Resolves an extension number to a concrete target node and wires the edge.
     *
     * @param array{label?: string, branch?: string}|null $edgeMeta
     */
    private function attachTarget(string $sourceNodeId, string $extensionNumber, ?array $edgeMeta): void
    {
        if ($extensionNumber === '') {
            return;
        }

        [$targetNodeId, $builtHere] = $this->resolveExtensionNode($extensionNumber);
        if ($targetNodeId === null) {
            return;
        }

        $label = $edgeMeta['label'] ?? null;
        $data = $edgeMeta !== null ? array_diff_key($edgeMeta, ['label' => null]) : [];
        $this->addEdge(NodeFactory::edge($sourceNodeId, $targetNodeId, $label, $data));

        if ($builtHere) {
            $this->expandTarget($targetNodeId, $extensionNumber);
        }
    }

    /**
     * @return array{0: string|null, 1: bool} Target node ID and whether it was just created (so we should expand it).
     */
    private function resolveExtensionNode(string $extensionNumber): array
    {
        $extension = Extensions::findFirst([
            'conditions' => 'number = :num:',
            'bind' => ['num' => $extensionNumber],
        ]);

        if ($extension === null) {
            // Unresolved target: render as explicit "unknown" node so the
            // branch is visible in the graph rather than silently dropped.
            $nodeId = NodeFactory::unknownId($extensionNumber);
            if (!isset($this->nodes[$nodeId])) {
                $this->addNode(NodeFactory::node(
                    $nodeId,
                    NodeFactory::TYPE_UNKNOWN,
                    ['label' => "Unknown: {$extensionNumber}"]
                ));
            }
            return [$nodeId, false];
        }

        $type = (string) $extension->type;
        return match ($type) {
            Extensions::TYPE_IVR_MENU => $this->buildIvrNode($extensionNumber),
            Extensions::TYPE_QUEUE => $this->buildQueueNode($extensionNumber),
            Extensions::TYPE_CONFERENCE => $this->buildConferenceNode($extensionNumber, $extension),
            Extensions::TYPE_MODULES => $this->buildApplicationNode($extensionNumber, $extension),
            Extensions::TYPE_SIP => $this->buildSipExtensionNode($extensionNumber, $extension),
            Extensions::TYPE_EXTERNAL => $this->buildExternalNode($extensionNumber, $extension),
            Extensions::TYPE_SYSTEM => $this->buildSystemSinkNode($extensionNumber, $extension),
            default => $this->buildGenericExtensionNode($extensionNumber, $extension),
        };
    }

    /**
     * Renders system sinks (hangup, busy, voicemail, did2user) — extensions
     * with `type='SYSTEM'`. These used to be hardcoded as a magic string list;
     * resolving via the Extensions table matches how core stores them.
     *
     * @return array{0: string, 1: bool}
     */
    private function buildSystemSinkNode(string $extensionNumber, Extensions $extension): array
    {
        $nodeId = 'system:' . $extensionNumber;
        if (!isset($this->nodes[$nodeId])) {
            $labels = [
                'hangup' => 'Hangup',
                'busy' => 'Busy',
                'voicemail' => 'Voicemail',
                'did2user' => 'DID → user',
            ];
            $label = $labels[$extensionNumber] ?? ($extension->callerid ?: ucfirst($extensionNumber));

            $this->addNode(NodeFactory::node(
                $nodeId,
                NodeFactory::TYPE_APPLICATION,
                [
                    'label' => $label,
                    'extension' => $extensionNumber,
                    'kind' => 'system',
                ]
            ));
        }

        return [$nodeId, false];
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function buildIvrNode(string $extensionNumber): array
    {
        $ivr = IvrMenu::findFirst([
            'conditions' => 'extension = :ext:',
            'bind' => ['ext' => $extensionNumber],
        ]);
        if ($ivr === null) {
            $nodeId = NodeFactory::unknownId($extensionNumber);
            $this->addNode(NodeFactory::node(
                $nodeId,
                NodeFactory::TYPE_UNKNOWN,
                ['label' => "Missing IVR: {$extensionNumber}"]
            ));
            return [$nodeId, false];
        }

        $nodeId = NodeFactory::ivrId((string) $ivr->uniqid);
        $created = !isset($this->nodes[$nodeId]);
        if ($created) {
            $this->addNode(NodeFactory::node(
                $nodeId,
                NodeFactory::TYPE_IVR,
                [
                    'label' => $ivr->name ?: "IVR {$extensionNumber}",
                    'extension' => $extensionNumber,
                    'timeout' => (int) $ivr->timeout,
                    // Embed the digit→target map directly in the node so the
                    // frontend can render an inline keypad-style summary. The
                    // edges to the same targets are still emitted by
                    // expandIvr(), so the full graph path remains visible.
                    'options' => $this->loadIvrOptions((string) $ivr->uniqid),
                    'timeoutTarget' => $this->describeIvrTarget((string) ($ivr->timeout_extension ?? '')),
                    'href' => '/admin-cabinet/ivr-menu/modify/' . (string) $ivr->uniqid,
                ]
            ));
        }

        return [$nodeId, $created];
    }

    /**
     * Builds a compact summary of IVR digit options for inline display. Each
     * entry pairs the DTMF digit with a short label describing its target
     * (resolved via Extensions like the full graph traversal does, but without
     * adding any nodes).
     *
     * @return list<array{digit: string, extension: string, label: string, kind: string}>
     */
    private function loadIvrOptions(string $ivrUniqid): array
    {
        $actions = IvrMenuActions::find([
            'conditions' => 'ivr_menu_id = :uid:',
            'bind' => ['uid' => $ivrUniqid],
            'order' => 'digits',
        ]);

        $out = [];
        foreach ($actions as $action) {
            $target = $this->describeIvrTarget((string) $action->extension);
            $out[] = [
                'digit' => (string) $action->digits,
                'extension' => (string) $action->extension,
                'label' => $target['label'],
                'kind' => $target['kind'],
            ];
        }
        return $out;
    }

    /**
     * Produces a label + kind pair describing where an IVR-option extension
     * leads. Used to decorate the inline keypad without allocating graph
     * nodes. Falls back to "ext N" when the target is unknown.
     *
     * @return array{label: string, kind: string}
     */
    private function describeIvrTarget(string $extensionNumber): array
    {
        if ($extensionNumber === '') {
            return ['label' => '', 'kind' => ''];
        }

        $extension = Extensions::findFirst([
            'conditions' => 'number = :num:',
            'bind' => ['num' => $extensionNumber],
        ]);
        if ($extension === null) {
            return ['label' => "ext {$extensionNumber}", 'kind' => 'unknown'];
        }

        $type = (string) $extension->type;
        $callerId = (string) ($extension->callerid ?? '');

        $label = match ($type) {
            Extensions::TYPE_QUEUE => $this->lookupQueueName($extensionNumber) ?? ($callerId ?: "queue {$extensionNumber}"),
            Extensions::TYPE_IVR_MENU => $this->lookupIvrName($extensionNumber) ?? ($callerId ?: "IVR {$extensionNumber}"),
            Extensions::TYPE_SYSTEM => match ($extensionNumber) {
                'hangup' => 'Hangup',
                'busy' => 'Busy',
                'voicemail' => 'Voicemail',
                'did2user' => 'DID → user',
                default => $callerId ?: ucfirst($extensionNumber),
            },
            default => $callerId ?: "ext {$extensionNumber}",
        };

        $kind = match ($type) {
            Extensions::TYPE_QUEUE => 'queue',
            Extensions::TYPE_IVR_MENU => 'ivr',
            Extensions::TYPE_CONFERENCE => 'conference',
            Extensions::TYPE_EXTERNAL => 'external',
            Extensions::TYPE_SYSTEM => 'system',
            default => 'extension',
        };

        return ['label' => $label, 'kind' => $kind];
    }

    private function lookupQueueName(string $extensionNumber): ?string
    {
        $queue = CallQueues::findFirst([
            'conditions' => 'extension = :ext:',
            'bind' => ['ext' => $extensionNumber],
        ]);
        return $queue !== null ? ($queue->name ?: null) : null;
    }

    private function lookupIvrName(string $extensionNumber): ?string
    {
        $ivr = IvrMenu::findFirst([
            'conditions' => 'extension = :ext:',
            'bind' => ['ext' => $extensionNumber],
        ]);
        return $ivr !== null ? ($ivr->name ?: null) : null;
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function buildQueueNode(string $extensionNumber): array
    {
        $queue = CallQueues::findFirst([
            'conditions' => 'extension = :ext:',
            'bind' => ['ext' => $extensionNumber],
        ]);
        if ($queue === null) {
            $nodeId = NodeFactory::unknownId($extensionNumber);
            $this->addNode(NodeFactory::node(
                $nodeId,
                NodeFactory::TYPE_UNKNOWN,
                ['label' => "Missing queue: {$extensionNumber}"]
            ));
            return [$nodeId, false];
        }

        $memberCount = CallQueueMembers::count([
            'conditions' => 'queue = :uid:',
            'bind' => ['uid' => (string) $queue->uniqid],
        ]);

        $nodeId = NodeFactory::queueId((string) $queue->uniqid);
        $created = !isset($this->nodes[$nodeId]);
        if ($created) {
            $this->addNode(NodeFactory::node(
                $nodeId,
                NodeFactory::TYPE_QUEUE,
                [
                    'label' => $queue->name ?: "Queue {$extensionNumber}",
                    'extension' => $extensionNumber,
                    'strategy' => (string) $queue->strategy,
                    'members' => (int) $memberCount,
                    'href' => '/admin-cabinet/call-queues/modify/' . (string) $queue->uniqid,
                ]
            ));
        }

        return [$nodeId, $created];
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function buildConferenceNode(string $extensionNumber, Extensions $extension): array
    {
        $room = ConferenceRooms::findFirst([
            'conditions' => 'extension = :ext:',
            'bind' => ['ext' => $extensionNumber],
        ]);

        $nodeId = NodeFactory::conferenceId($extensionNumber);
        $created = !isset($this->nodes[$nodeId]);
        if ($created) {
            $this->addNode(NodeFactory::node(
                $nodeId,
                NodeFactory::TYPE_CONFERENCE,
                [
                    'label' => $room?->name ?: ($extension->callerid ?: "Conference {$extensionNumber}"),
                    'extension' => $extensionNumber,
                    'href' => $room !== null
                        ? '/admin-cabinet/conference-rooms/modify/' . (int) $room->id
                        : '/admin-cabinet/conference-rooms/index',
                ]
            ));
        }

        return [$nodeId, $created];
    }

    /**
     * Resolves a `type='MODULES'` extension. These are registered by core
     * modules (Smart IVR, Autoprovision, etc.) — they do NOT appear in
     * `m_DialplanApplications`; their handlers are injected into the dialplan
     * via module hooks. We can't introspect the handler, but we can at least
     * show the module's user-facing caller ID with an indicator that this is
     * a module-provided extension.
     *
     * @return array{0: string, 1: bool}
     */
    private function buildApplicationNode(string $extensionNumber, Extensions $extension): array
    {
        $app = DialplanApplications::findFirst([
            'conditions' => 'extension = :ext:',
            'bind' => ['ext' => $extensionNumber],
        ]);

        $nodeId = NodeFactory::applicationId($extensionNumber);
        $created = !isset($this->nodes[$nodeId]);
        if ($created) {
            // type=MODULES falls here when DialplanApplications has no matching
            // row. Distinguish the two visually via `kind` so the React node
            // renders a module icon instead of a generic code icon.
            $kind = $app !== null ? 'dialplan' : 'module';
            $label = $app?->name
                ?: ($extension->callerid ?: "Module {$extensionNumber}");
            $href = $app !== null
                ? '/admin-cabinet/dialplan-applications/modify/' . (int) $app->id
                : '/admin-cabinet/pbx-extension-modules/index';

            $this->addNode(NodeFactory::node(
                $nodeId,
                NodeFactory::TYPE_APPLICATION,
                [
                    'label' => $label,
                    'extension' => $extensionNumber,
                    'kind' => $kind,
                    'href' => $href,
                ]
            ));
        }

        return [$nodeId, $created];
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function buildSipExtensionNode(string $extensionNumber, Extensions $extension): array
    {
        $nodeId = NodeFactory::extensionId($extensionNumber);
        $created = !isset($this->nodes[$nodeId]);
        if ($created) {
            $this->addNode(NodeFactory::node(
                $nodeId,
                NodeFactory::TYPE_EXTENSION,
                [
                    'label' => $extension->callerid ?: "Extension {$extensionNumber}",
                    'extension' => $extensionNumber,
                    'href' => $extension->userid !== null
                        ? '/admin-cabinet/extensions/modify/' . (int) $extension->userid
                        : '/admin-cabinet/extensions/index',
                ]
            ));
        }

        return [$nodeId, $created];
    }

    /**
     * Builds a node for EXTERNAL extensions (mobile forwarding targets).
     * Looks up the actual dial string from ExternalPhones so the graph shows
     * the real target number, not just the internal placeholder.
     *
     * @return array{0: string, 1: bool}
     */
    private function buildExternalNode(string $extensionNumber, Extensions $extension): array
    {
        $nodeId = 'external:' . $extensionNumber;
        $created = !isset($this->nodes[$nodeId]);
        if ($created) {
            $external = ExternalPhones::findFirst([
                'conditions' => 'extension = :ext:',
                'bind' => ['ext' => $extensionNumber],
            ]);
            $dialString = $external !== null ? (string) ($external->dialstring ?? '') : '';

            $this->addNode(NodeFactory::node(
                $nodeId,
                NodeFactory::TYPE_EXTERNAL,
                [
                    'label' => $extension->callerid ?: "External {$extensionNumber}",
                    'extension' => $extensionNumber,
                    'dialstring' => $dialString,
                ]
            ));
        }

        return [$nodeId, $created];
    }

    /**
     * @return array{0: string, 1: bool}
     */
    private function buildGenericExtensionNode(string $extensionNumber, Extensions $extension): array
    {
        $nodeId = NodeFactory::extensionId($extensionNumber);
        $created = !isset($this->nodes[$nodeId]);
        if ($created) {
            $this->addNode(NodeFactory::node(
                $nodeId,
                NodeFactory::TYPE_EXTENSION,
                [
                    'label' => $extension->callerid ?: "Extension {$extensionNumber}",
                    'extension' => $extensionNumber,
                    'kind' => strtolower((string) $extension->type),
                ]
            ));
        }

        return [$nodeId, $created];
    }

    /**
     * Expands children of just-created IVR / queue nodes.
     */
    private function expandTarget(string $nodeId, string $extensionNumber): void
    {
        if (isset($this->expanded[$nodeId])) {
            return;
        }
        $this->expanded[$nodeId] = true;

        $node = $this->nodes[$nodeId];
        $type = (string) $node['type'];

        if ($type === NodeFactory::TYPE_IVR) {
            $this->expandIvr($nodeId, $extensionNumber);
            return;
        }

        if ($type === NodeFactory::TYPE_QUEUE) {
            $this->expandQueue($nodeId, $extensionNumber);
        }
    }

    private function expandIvr(string $ivrNodeId, string $extensionNumber): void
    {
        $ivr = IvrMenu::findFirst([
            'conditions' => 'extension = :ext:',
            'bind' => ['ext' => $extensionNumber],
        ]);
        if ($ivr === null) {
            return;
        }

        $actions = IvrMenuActions::find([
            'conditions' => 'ivr_menu_id = :uid:',
            'bind' => ['uid' => (string) $ivr->uniqid],
            'order' => 'digits',
        ]);
        foreach ($actions as $action) {
            // Digit label is rendered inline inside the IVR node itself (see
            // `options` in IvrNode), so we keep the edge for path continuity
            // but drop the duplicated digit on the edge chip.
            $this->attachTarget(
                $ivrNodeId,
                (string) $action->extension,
                null
            );
        }

        if (!empty($ivr->timeout_extension)) {
            $this->attachTarget(
                $ivrNodeId,
                (string) $ivr->timeout_extension,
                ['label' => 'Timeout', 'branch' => 'timeout']
            );
        }
    }

    private function expandQueue(string $queueNodeId, string $extensionNumber): void
    {
        $queue = CallQueues::findFirst([
            'conditions' => 'extension = :ext:',
            'bind' => ['ext' => $extensionNumber],
        ]);
        if ($queue === null) {
            return;
        }

        if (!empty($queue->redirect_to_extension_if_empty)) {
            $this->attachTarget(
                $queueNodeId,
                (string) $queue->redirect_to_extension_if_empty,
                ['label' => 'If empty', 'branch' => 'empty']
            );
        }
        if (!empty($queue->redirect_to_extension_if_unanswered)) {
            $this->attachTarget(
                $queueNodeId,
                (string) $queue->redirect_to_extension_if_unanswered,
                ['label' => 'If unanswered', 'branch' => 'unanswered']
            );
        }
        if (!empty($queue->timeout_extension)) {
            $this->attachTarget(
                $queueNodeId,
                (string) $queue->timeout_extension,
                ['label' => 'Timeout', 'branch' => 'timeout']
            );
        }
        if (!empty($queue->redirect_to_extension_if_repeat_exceeded)) {
            $this->attachTarget(
                $queueNodeId,
                (string) $queue->redirect_to_extension_if_repeat_exceeded,
                ['label' => 'Repeat exceeded', 'branch' => 'repeat']
            );
        }
    }

    private function scheduleLabel(OutWorkTimes $condition): string
    {
        if (!empty($condition->description)) {
            return (string) $condition->description;
        }
        $parts = [];
        if (!empty($condition->time_from) || !empty($condition->time_to)) {
            $parts[] = trim(((string) $condition->time_from) . '-' . ((string) $condition->time_to), '-');
        }
        if (!empty($condition->weekday_from) || !empty($condition->weekday_to)) {
            $parts[] = 'wd:' . ((string) $condition->weekday_from) . '-' . ((string) $condition->weekday_to);
        }

        return $parts === [] ? 'Time condition' : implode(' ', $parts);
    }

    /**
     * Strips HTML from model::getRepresent() output. Core models embed Semantic
     * UI icon tags (e.g. `<i class="server icon"></i> SIP: Provider name`) which
     * are great for Volt dropdowns but show up as raw markup when rendered as
     * text inside a React Flow node label. `strip_tags` + whitespace collapse
     * gives us a clean label while preserving any original text content.
     */
    private function cleanLabel(string $value): string
    {
        $stripped = strip_tags($value);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $collapsed = preg_replace('/\s+/u', ' ', $decoded) ?? $decoded;
        // Drop any trailing parenthetical core models append in getRepresent()
        // (e.g. "(disabled)" in English, "(выключен)" in Russian, and the 28
        // other localised variants). Disabled state is surfaced explicitly via
        // the provider node's meta + className; keeping the suffix would
        // duplicate it in every supported language.
        $withoutSuffix = preg_replace('/\s*\([^)]*\)\s*$/u', '', $collapsed) ?? $collapsed;
        return trim($withoutSuffix);
    }

    private function providerHref(string $type, string $uniqid): string
    {
        return match (strtoupper($type)) {
            'SIP' => '/admin-cabinet/providers/modifysip/' . $uniqid,
            'IAX' => '/admin-cabinet/providers/modifyiax/' . $uniqid,
            default => '/admin-cabinet/providers/index',
        };
    }

    /**
     * Resolves the `disabled` flag for a provider. The flag itself lives on the
     * underlying Sip/Iax record (via the hasOne relation on Providers), not on
     * the join row — accessing $provider->Sip / $provider->Iax follows that
     * relation. Providers whose child record is missing are considered enabled
     * rather than disabled so they don't get hidden by the collapse-by-default
     * frontend behaviour.
     */
    private function isProviderDisabled(Providers $provider): bool
    {
        $type = strtoupper((string) $provider->type);
        if ($type === 'SIP') {
            $sip = $provider->Sip;
            return $sip !== null && (string) $sip->disabled === '1';
        }
        if ($type === 'IAX') {
            $iax = $provider->Iax;
            return $iax !== null && (string) $iax->disabled === '1';
        }
        return false;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function addNode(array $node): void
    {
        $id = (string) $node['id'];
        if (!isset($this->nodes[$id])) {
            $this->nodes[$id] = $node;
        }
    }

    /**
     * @param array<string, mixed> $edge
     */
    private function addEdge(array $edge): void
    {
        $id = (string) $edge['id'];
        if (!isset($this->edges[$id])) {
            $this->edges[$id] = $edge;
        }
    }
}
