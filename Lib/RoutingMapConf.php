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

namespace Modules\ModuleRoutingMap\Lib;

use MikoPBX\Modules\Config\ConfigClass;

/**
 * Config class for ModuleRoutingMap.
 *
 * No custom hooks needed:
 * - Menu item is registered via Setup/PbxExtensionSetup::addToSidebar().
 * - REST API controllers are auto-discovered by ControllerDiscovery from Lib/RestAPI/.
 * - Admin UI assets are registered by the web controller's indexAction().
 */
class RoutingMapConf extends ConfigClass
{
}
