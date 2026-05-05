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

use MikoPBX\Common\Models\OutgoingRoutingTable;
use MikoPBX\Common\Models\Providers;

/**
 * Builds a graph describing outbound dial plan:
 *
 *   Virtual "Internal caller" root → Rule (pattern) → Provider
 *
 * Rules are sorted by priority ascending (same order Asterisk evaluates them).
 */
final class OutgoingGraphBuilder implements GraphBuilderInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $nodes = [];

    /** @var array<string, array<string, mixed>> */
    private array $edges = [];

    public function build(): array
    {
        $this->nodes = [];
        $this->edges = [];

        $rootId = 'caller:internal';
        $this->addNode(NodeFactory::node(
            $rootId,
            NodeFactory::TYPE_ROOT,
            [
                'label' => 'Internal caller',
                'kind' => 'source',
            ]
        ));

        $providers = $this->indexProviders();
        $rules = OutgoingRoutingTable::find(['order' => 'priority asc, id asc']);

        foreach ($rules as $rule) {
            $ruleNodeId = 'outrule:' . (int) $rule->id;
            $pattern = $this->formatPattern($rule);

            $this->addNode(NodeFactory::node(
                $ruleNodeId,
                NodeFactory::TYPE_ROUTE,
                [
                    'label' => $rule->rulename ?: $pattern,
                    'pattern' => $pattern,
                    'priority' => (int) $rule->priority,
                    'prepend' => (string) ($rule->prepend ?? ''),
                    'trim' => (int) ($rule->trimfrombegin ?? 0),
                    'href' => '/admin-cabinet/outbound-routes/modify/' . (int) $rule->id,
                ]
            ));
            // Pattern label removed: it's already shown inside the rule node's
            // meta (see RouteNode.jsx). Duplicating it on the edge just clutters
            // the canvas when a root has many children.
            $this->addEdge(NodeFactory::edge($rootId, $ruleNodeId));

            $providerUniqid = (string) ($rule->providerid ?? '');
            $providerNodeId = NodeFactory::providerId($providerUniqid !== '' ? $providerUniqid : '__none__');

            if (!isset($this->nodes[$providerNodeId])) {
                $provider = $providers[$providerUniqid] ?? null;
                $label = $provider !== null
                    ? $this->cleanLabel((string) $provider->getRepresent())
                    : ($providerUniqid !== '' ? "Provider {$providerUniqid}" : 'No provider');
                $this->addNode(NodeFactory::node(
                    $providerNodeId,
                    NodeFactory::TYPE_PROVIDER,
                    [
                        'label' => $label,
                        'kind' => $provider !== null ? strtolower((string) $provider->type) : 'missing',
                        'uniqid' => $providerUniqid,
                        'disabled' => $provider !== null && $this->isProviderDisabled($provider),
                        'href' => $this->providerHref(
                            $provider !== null ? (string) $provider->type : '',
                            $providerUniqid
                        ),
                    ]
                ));
            }

            $this->addEdge(NodeFactory::edge($ruleNodeId, $providerNodeId));
        }

        return [
            'nodes' => array_values($this->nodes),
            'edges' => array_values($this->edges),
        ];
    }

    /**
     * @return array<string, Providers>
     */
    private function indexProviders(): array
    {
        $result = [];
        foreach (Providers::find() as $provider) {
            $result[(string) $provider->uniqid] = $provider;
        }

        return $result;
    }

    private function formatPattern(OutgoingRoutingTable $rule): string
    {
        $begins = (string) ($rule->numberbeginswith ?? '');
        $rest = (int) ($rule->restnumbers ?? -1);

        if ($begins === '' && $rest < 0) {
            return 'any';
        }

        $tail = $rest < 0 ? '.' : str_repeat('X', $rest);
        return $begins . $tail;
    }

    /**
     * See IncomingGraphBuilder::cleanLabel — core getRepresent() output embeds
     * HTML icon tags that must be stripped before rendering inside a React node.
     */
    private function cleanLabel(string $value): string
    {
        $stripped = strip_tags($value);
        $decoded = html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $collapsed = preg_replace('/\s+/u', ' ', $decoded) ?? $decoded;
        // Drop any trailing parenthetical appended by core getRepresent()
        // (localised "disabled" marker in 29 languages). Disabled state is
        // surfaced via the provider node's meta + className instead.
        $withoutSuffix = preg_replace('/\s*\([^)]*\)\s*$/u', '', $collapsed) ?? $collapsed;
        return trim($withoutSuffix);
    }

    private function providerHref(string $type, string $uniqid): string
    {
        if ($uniqid === '') {
            return '/admin-cabinet/providers/index';
        }

        return match (strtoupper($type)) {
            'SIP' => '/admin-cabinet/providers/modifysip/' . $uniqid,
            'IAX' => '/admin-cabinet/providers/modifyiax/' . $uniqid,
            default => '/admin-cabinet/providers/index',
        };
    }

    /**
     * Disabled state lives on the related Sip/Iax record. See
     * IncomingGraphBuilder::isProviderDisabled for the same pattern.
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
