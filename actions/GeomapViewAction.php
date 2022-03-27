<?php

namespace Modules\ExtendedGeoMapWidget\Actions;

use CLink;
use CControllerResponseData;
use CControllerWidgetGeoMapView;

class GeomapViewAction extends CControllerWidgetGeoMapView {

	protected function doAction() {
		parent::doAction();

		/** @var CControllerResponseData $data */
		$data = $this->getResponse();
		$data = $data->getData();

		foreach ($data['hosts'] as &$host) {
			foreach ($host['properties']['problems'] as &$problem) {
				$problem = (string) (new CLink($problem))->addClass(ZBX_STYLE_LINK_ACTION);
			}
		}
		unset($problem);

		$this->setResponse(new CControllerResponseData($data));
	}
}
