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

namespace Modules\ModuleRoutingMap\App\Controllers;

use MikoPBX\AdminCabinet\Controllers\BaseController;
use MikoPBX\Modules\PbxExtensionUtils;

class ModuleRoutingMapBaseController extends BaseController
{
    protected string $moduleUniqueID = 'ModuleRoutingMap';
    protected string $moduleDir = '';

    public function initialize(): void
    {
        if ($this->request->isAjax() === false) {
            $this->moduleDir = PbxExtensionUtils::getModuleDir($this->moduleUniqueID);
            $this->view->logoImagePath = "{$this->url->get()}assets/img/cache/{$this->moduleUniqueID}/logo.svg";
        }
        parent::initialize();
    }
}
