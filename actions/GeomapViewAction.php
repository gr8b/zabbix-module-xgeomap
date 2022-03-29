<?php

namespace Modules\ExtendedGeoMapWidget\Actions;

use API;
use CLink;
use CControllerResponseData;
use CControllerWidgetGeoMapView;
use CSpan;
use CTableInfo;
use CSeverityHelper;
use CWebUser;
use CRoleHelper;

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
			'selectAcknowledges' => ['userid', 'clock', 'message', 'action', 'old_severity', 'new_severity'],
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
		$allowed = [
			'ui_problems' => CWebUser::checkAccess(CRoleHelper::UI_MONITORING_PROBLEMS),
			'add_comments' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ADD_PROBLEM_COMMENTS),
			'change_severity' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CHANGE_SEVERITY),
			'acknowledge' => CWebUser::checkAccess(CRoleHelper::ACTIONS_ACKNOWLEDGE_PROBLEMS),
			'close' => CWebUser::checkAccess(CRoleHelper::ACTIONS_CLOSE_PROBLEMS)
		];
		$actions = getEventsActionsIconsData($problems, $triggers);
		$users = [];

		if ($actions['userids']) {
			$users = API::User()->get([
				'output' => ['username', 'surname', 'name'],
				'userids' => array_keys($actions['userids']),
				'preservekeys' => true
			]);
		}

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
				$can_be_closed = ($triggers[$problem['objectid']]['manual_close'] == ZBX_TRIGGER_MANUAL_CLOSE_ALLOWED && $allowed['close']);
				$cell_status = new CSpan($value_str);
				addTriggerValueStyle($cell_status, $value, $value_clock, $is_acknowledged);

				// Create acknowledge link.
				$problem_update_link = ($allowed['add_comments'] || $allowed['change_severity'] || $allowed['acknowledge']
						|| $can_be_closed)
					? (new CLink($is_acknowledged ? _('Yes') : _('No')))
						->addClass($is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
						->addClass(ZBX_STYLE_LINK_ALT)
						->onClick('acknowledgePopUp('.json_encode(['eventids' => [$problem['eventid']]]).', this);')
					: (new CSpan($is_acknowledged ? _('Yes') : _('No')))->addClass(
						$is_acknowledged ? ZBX_STYLE_GREEN : ZBX_STYLE_RED
					);

				$hostids_tables[$hostid][$problem['severity']]->addRow([
					$cell_status,
					CSeverityHelper::makeSeverityCell((int) $problem['severity'], $problem['name']),
					zbx_date2age($problem['clock']),
					$problem_update_link,
					makeEventActionsIcons($problem['eventid'], $actions['data'], $users),
				]);
			}
		}

		return $hostids_tables;
	}
}
