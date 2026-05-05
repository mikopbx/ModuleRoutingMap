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

use MikoPBX\AdminCabinet\Providers\AssetProvider;

/**
 * Web UI controller for the routing map module.
 *
 * Renders a page with two tabs (incoming / outgoing) that host the React Flow
 * visualizer. Assets are registered explicitly on the footer JS collection:
 *
 *   1. react-flow.bundle.js (pre-bundled React + @xyflow/react + app entry).
 *   2. module-routing-map-index.js (Babel-transpiled glue code: fetch + mount).
 *
 * The React bundle exposes a single global — window.MikoRoutingMap — with a
 * mount(el, graph, options) function invoked by the glue code once data arrives.
 */
class ModuleRoutingMapController extends ModuleRoutingMapBaseController
{
    public function indexAction(): void
    {
        $headerCSS = $this->assets->collection(AssetProvider::HEADER_CSS);
        $headerCSS->addCss('css/cache/' . $this->moduleUniqueID . '/module-routing-map.css', true);

        $footerJS = $this->assets->collection(AssetProvider::FOOTER_JS);
        $footerJS->addJs(
            'js/cache/' . $this->moduleUniqueID . '/vendor/react-flow.bundle.js',
            true
        );
        $footerJS->addJs(
            'js/cache/' . $this->moduleUniqueID . '/module-routing-map-index.js',
            true
        );

        $this->view->moduleUniqueID = $this->moduleUniqueID;
        $this->view->incomingEndpoint = '/pbxcore/api/v3/module-routing-map/graph:getIncoming';
        $this->view->outgoingEndpoint = '/pbxcore/api/v3/module-routing-map/graph:getOutgoing';

        $this->view->pick('Modules/' . $this->moduleUniqueID . '/ModuleRoutingMap/index');
    }
}
