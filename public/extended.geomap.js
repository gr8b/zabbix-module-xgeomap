// document.body.querySelector('.leaflet-marker-pane').addEventListener('click', e => console.log('click e:', e))

(() => {



Object.defineProperty(ZABBIX, 'Dashboard', {
	set: dashboard => {
		ZABBIX._Dashboard = dashboard;
		
		console.log('dashobard initialized', dashboard);
		
	},
	get: () => ZABBIX._Dashboard
});

})();