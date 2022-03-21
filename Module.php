<?php

namespace Modules\ExtendedGeoMapWidget;

use CController;
use Core\CModule as ModuleBase;


class Module extends ModuleBase {

	public function onBeforeAction(CController $action): void {
		if ($action->getAction() !== 'dashboard.view') {
			return;
		}

		// TODO: load via document.write('<script src="..."></script>)
		zbx_add_post_js(file_get_contents(__DIR__.'/public/extended.geomap.js'));
	}
}
