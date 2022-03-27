(() => {
if ('Dashboard' in ZABBIX)
	return;
ZABBIX.Dashboard = null;

// CDashboardPage.prototype.announceWidgets = (dashboard_pages) => {
// 	let widgets = [];

// 	for (const dashboard_page of dashboard_pages) {
// 		widgets = widgets.concat(Array.from(dashboard_page._widgets.keys()));
// 	}

// 	for (const widget of widgets) {
// 		widget.announceWidgets(widgets);
// 	}
// }

// const bindEvents = (dashboard) => {
// 	// console.log('bindEvents:', dashboard);
// 	if (dashboard._is_edit_mode)
// 		return;

// }


// Object.defineProperty(ZABBIX, 'Dashboard', {
// 	set: dashboard => {
// 		ZABBIX._Dashboard = dashboard;
// 		bindEvents(dashboard);
// 	},
// 	get: () => ZABBIX._Dashboard
// });

})();
