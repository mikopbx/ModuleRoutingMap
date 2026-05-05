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

/**
 * Shared helpers to produce nodes and edges in a canonical shape the React Flow
 * frontend can consume directly.
 *
 * Node types map 1:1 to custom React Flow node components in react-app/src/nodes/.
 * Keeping the type vocabulary frozen here prevents frontend/backend drift.
 */
final class NodeFactory
{
    public const string TYPE_ROOT = 'root';
    public const string TYPE_PROVIDER = 'provider';
    public const string TYPE_DISPATCHER = 'dispatcher';
    public const string TYPE_ROUTE = 'route';
    public const string TYPE_SCHEDULE = 'schedule';
    public const string TYPE_IVR = 'ivr';
    public const string TYPE_QUEUE = 'queue';
    public const string TYPE_EXTENSION = 'extension';
    public const string TYPE_CONFERENCE = 'conference';
    public const string TYPE_APPLICATION = 'application';
    public const string TYPE_VOICEMAIL = 'voicemail';
    public const string TYPE_EXTERNAL = 'external';
    public const string TYPE_UNKNOWN = 'unknown';

    /**
     * Build a node descriptor.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function node(string $id, string $type, array $data): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'data' => $data,
        ];
    }

    /**
     * Build an edge descriptor.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public static function edge(
        string $source,
        string $target,
        ?string $label = null,
        array $data = []
    ): array {
        $edge = [
            'id' => "e-{$source}-{$target}",
            'source' => $source,
            'target' => $target,
        ];
        if ($label !== null && $label !== '') {
            $edge['label'] = $label;
        }
        if ($data !== []) {
            $edge['data'] = $data;
        }

        return $edge;
    }

    public static function providerId(string $uniqid): string
    {
        return 'provider:' . $uniqid;
    }

    public static function dispatcherId(string $providerUniqid): string
    {
        return 'dispatcher:' . $providerUniqid;
    }

    public static function routeId(int|string $id): string
    {
        return 'route:' . (string) $id;
    }

    public static function scheduleId(int|string $id): string
    {
        return 'schedule:' . (string) $id;
    }

    public static function ivrId(string $uniqid): string
    {
        return 'ivr:' . $uniqid;
    }

    public static function queueId(string $uniqid): string
    {
        return 'queue:' . $uniqid;
    }

    public static function extensionId(string $number): string
    {
        return 'ext:' . $number;
    }

    public static function conferenceId(string $number): string
    {
        return 'conf:' . $number;
    }

    public static function applicationId(string $number): string
    {
        return 'app:' . $number;
    }

    public static function unknownId(string $extension): string
    {
        return 'unknown:' . $extension;
    }
}
