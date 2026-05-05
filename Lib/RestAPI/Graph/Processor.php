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

use MikoPBX\PBXCoreREST\Lib\PBXApiResult;
use Modules\ModuleRoutingMap\Lib\RestAPI\Graph\Actions\GetIncomingGraphAction;
use Modules\ModuleRoutingMap\Lib\RestAPI\Graph\Actions\GetOutgoingGraphAction;
use Phalcon\Di\Injectable;

/**
 * Routes Graph resource requests to action classes.
 */
class Processor extends Injectable
{
    /**
     * @param array<string, mixed> $request
     */
    public static function callBack(array $request): PBXApiResult
    {
        $result = new PBXApiResult();
        $result->processor = __METHOD__;
        $action = (string) ($request['action'] ?? '');

        $result = match ($action) {
            'getIncoming' => GetIncomingGraphAction::main(),
            'getOutgoing' => GetOutgoingGraphAction::main(),
            default => self::unknownAction($result, $action),
        };

        $result->function = $action;
        return $result;
    }

    private static function unknownAction(PBXApiResult $result, string $action): PBXApiResult
    {
        $result->success = false;
        $result->messages['error'][] = "Unknown action - {$action} in " . self::class;
        return $result;
    }
}
