<?php

namespace Modules\ExtendedGeoMapWidget\Actions;

use API;
use CLink;
use CControllerResponseData;
use CControllerWidgetGeoMapView;
use CSpan;
use CTableInfo;
use CSeverityHelper;

class GeomapViewAction extends CControllerWidgetGeoMapView {

	protected function doAction() {
		parent::doAction();

		/** @var CControllerResponseData $data */
		$data = $this->getResponse();
		$data = $data->getData();
		$hostids_problems = $this->getHostsProblemsTables($data['hosts']);

		foreach ($data['hosts'] as &$host) {
			foreach ($host['properties']['problems'] as $severity => &$problem) {
				$problem = (string) (new CLink($problem))
					->addClass(ZBX_STYLE_LINK_ACTION)
					->setHint($hostids_problems[$host['properties']['hostid']][$severity]);
			}
		}
		unset($problem);

		$this->setResponse(new CControllerResponseData($data));
	}

	/**
	 * Return problems table for desired
	 */
	protected function getHostsProblemsTables(array $hosts) {
		$hostids_problems = [];

		foreach ($hosts as $host) {
			$hostids_problems[$host['properties']['hostid']] = [];
		}

		if (!$hostids_problems) {
			return [];
		}

		$problems = API::Problem()->get([
			'output' => ['eventid', 'r_eventid', 'objectid', 'clock', 'ns', 'name', 'acknowledged', 'severity'],
			'selectAcknowledges' => ['action'],
			'hostids' => array_keys($hostids_problems),
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER,
			'suppressed' => false,
			'sortfield' => ['eventid'],
			'sortorder' => ZBX_SORT_DOWN,
			'preservekeys' => true
		]);

		if (!$problems) {
			return [];
		}

		$triggers = API::Trigger()->get([
			'output' => ['priority', 'manual_close'],
			'selectGroups' => ['groupid'],
			'selectHosts' => ['hostid'],
			'triggerids' => array_unique(array_column($problems, 'objectid')),
			'monitored' => true,
			'skipDependent' => true,
			'preservekeys' => true
		]);

		foreach ($problems as $problem) {
			$hosts = $triggers[$problem['objectid']]['hosts'];

			foreach ($hosts as $host) {
				$hostids_problems[$host['hostid']][] = $problem;
			}
		}

		$hostids_tables = [];

		foreach ($hostids_problems as $hostid => $problems) {
			if (!array_key_exists($hostid, $hostids_tables)) {
				$hostids_tables[$hostid] = [];
			}

			foreach ($problems as $problem) {
				if (!array_key_exists($problem['severity'], $hostids_tables[$hostid])) {
					$hostids_tables[$hostid][$problem['severity']] = (new CTableInfo())->setHeader([
						_('Status'),
						_('Problem'),
						_('Duration'),
						_('Ack'),
						_('Actions'),
					]);
				}
				if ($problem['r_eventid'] != 0) {
					$value = TRIGGER_VALUE_FALSE;
					$value_str = _('RESOLVED');
					$value_clock = $problem['r_clock'];
				}
				else {
					$in_closing = false;

					foreach ($problem['acknowledges'] as $acknowledge) {
						if (($acknowledge['action'] & ZBX_PROBLEM_UPDATE_CLOSE) == ZBX_PROBLEM_UPDATE_CLOSE) {
							$in_closing = true;
							break;
						}
					}

					$value = $in_closing ? TRIGGER_VALUE_FALSE : TRIGGER_VALUE_TRUE;
					$value_str = $in_closing ? _('CLOSING') : _('PROBLEM');
					$value_clock = $in_closing ? time() : $problem['clock'];
				}

				$is_acknowledged = ($problem['acknowledged'] == EVENT_ACKNOWLEDGED);
				$cell_status = new CSpan($value_str);
				addTriggerValueStyle($cell_status, $value, $value_clock, $is_acknowledged);

				$hostids_tables[$hostid][$problem['severity']]->addRow([
					$cell_status,
					CSeverityHelper::makeSeverityCell((int) $problem['severity'], $problem['name']),
					zbx_date2age($problem['clock']),
					'',
					''
				]);
			}
		}

		return $hostids_tables;
	}
}
