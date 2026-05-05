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

namespace Modules\ModuleRoutingMap\Setup;

use MikoPBX\Common\Models\PbxSettings;
use MikoPBX\Modules\Setup\PbxExtensionSetupBase;

/**
 * ModuleRoutingMap installer.
 *
 * Read-only visualization module — no database tables.
 * REST API routes are auto-discovered from Lib/RestAPI/Graph/Controller.php.
 * Overrides addToSidebar() to place the menu item in the "maintenance" group
 * instead of the default "modules" group.
 */
class PbxExtensionSetup extends PbxExtensionSetupBase
{
    public function addToSidebar(): bool
    {
        $menuSettingsKey = "AdditionalMenuItem$this->moduleUniqueID";
        $menuSettings = PbxSettings::findFirstByKey($menuSettingsKey);
        if ($menuSettings === null) {
            $menuSettings = new PbxSettings();
            $menuSettings->key = $menuSettingsKey;
        }
        $value = [
            'uniqid' => $this->moduleUniqueID,
            'group' => 'maintenance',
            'iconClass' => 'project diagram',
            'caption' => "Breadcrumb$this->moduleUniqueID",
            'showAtSidebar' => true,
        ];
        $menuSettings->value = json_encode($value);

        return $menuSettings->save();
    }
}
