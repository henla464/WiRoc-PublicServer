// Application object.
var app = {};
app.ui = {};
app.loggedIn = false;
app.ui.usersDeviceList = null;
app.ui.addDeviceList = null;
app.userDeviceFn = doT.template('<div class="device-item"><span class="device-header">{{=it.name}}</span><dl class="table-display"><dt>Description</dt><dd>{{=it.description}}</dd>{{~it.MessageStats :messageStat:index}}<dt>Received {{=messageStat.messageType}}</dt><dd>{{=messageStat.noOfMessages}} ({{=messageStat.createdTime}})</dd>{{~}}</dl>{{~it.SubDevices :subDevice:index}}<div class="subdevice-item"><ul class="subdevice-item-desc-list"><li>{{=subDevice.distanceToHead}} ({{=app.ui.getSubDeviceStatusCreateDate(subDevice)}}){{ for (var i = 0; i < subDevice.SubDeviceStatuses.length; i++) { }}<li>Batt: {{=subDevice.SubDeviceStatuses[i].batteryLevel}}%</li>{{ }}}</ul></div>{{~}}<img class="flag" src="../res/flag.jpg"/></div>');
app.addDeviceListFn = doT.template('<li data-checked="{{=it.connectedToUser}}" data-deviceid="{{=it.id}}"><input type="checkbox" name="checkbox{{=it.id}}" id="checkbox{{=it.id}}" class="css-checkbox" {{? it.connectedToUser==="1" }}checked="checked"{{?}}/><label class="css-label" for="checkbox{{=it.id}}"><span class="add-device-header">{{=it.name}}</span><dl class="table-display add-device-desc"><dt>Description</dt><dd>{{=it.description}}</dd></dl></label></li>');



app.initialize = function()
{
	app.ui.usersDeviceList = $('#users-device-list');
	app.ui.addDeviceList = $('#add-device-list');
};

function onSignIn(googleUser) {
	app.loggedIn = true;
				
	var id_token = googleUser.getAuthResponse().id_token;
	var xhr = new XMLHttpRequest();
	xhr.open('POST', '/api/v1/login');
	xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	xhr.onload = function() {
		console.log('Signed in as: ' + xhr.responseText);
		$(":mobile-pagecontainer").pagecontainer( "change", "#page-users-devices", { } );
	};
	xhr.send('idtoken=' + id_token);
};

app.ui.getSubDeviceStatusCreateDate = function(device) {
	if (device.SubDeviceStatuses && device.SubDeviceStatuses.length > 0) {
		return device.SubDeviceStatuses[0].createdTime;
	}
	return 'No data';
}


app.fillDevicesWithSubDeviceStatuses = function(devices) {
	
	var allSubDevices = [].concat.apply([], devices.map((device)=>{ return device.SubDevices; }));
	var subDevicePromises = allSubDevices.map((subDevice)=>{
		return fetch("/api/v1/SubDevices/" + subDevice.id + "/SubDeviceStatuses?sort=createdTime desc&limit=1", {
			credentials: 'same-origin',
			headers: {
			  'Accept': 'application/json'
			},
			method: "GET"
		})
		.then((res)=> res.json())
		.then((subDeviceStatus)=> {
			subDevice['SubDeviceStatuses'] = subDeviceStatus;
			return subDevice;
		});
	});
	return Promise.all(subDevicePromises)
		.then((subDevicesAndStatus) => {
			return devices;
		});
};

app.fillDevicesWithMessageStats = function(devices) {
	var promises = devices.map((device)=>{
		return fetch("/api/v1/Devices/" + device.id + "/MessageStats?outputType=aggregated&sort=adapterInstance asc,messageType asc", {
				credentials: 'same-origin',
				headers: {
				  'Accept': 'application/json'
				},
				method: "GET"
			})
			.then((res)=> res.json())
			.then((messageStats)=>{
				device['MessageStats'] = messageStats;
				console.log(messageStats);
				return device;
			});
	});
	return Promise.all(promises)
};

app.fillDevicesWithSubDevices = function(devices) {
	var promises = devices.map((device)=>{
		return fetch("/api/v1/Devices/" + device.id + "/SubDevices?sort=distanceToHead", {
				credentials: 'same-origin',
				headers: {
				  'Accept': 'application/json'
				},
				method: "GET"
			})
			.then((res)=> res.json())
			.then((subDevices)=>{
				device['SubDevices'] = subDevices;
				return device;
			});
	});
	return Promise.all(promises)
};

app.getUsersDevices = function(devices) {
	return fetch("/api/v1/Devices?sort=name asc&limitToUser=true",
		{
			credentials: 'same-origin',
			headers: {
			  'Accept': 'application/json'
			},
			method: "GET"
		})
		.then(function(res) { 
			return res.json();
		});
};

app.getDevices = function() {
	return fetch("/api/v1/Devices",
		{
			credentials: 'same-origin',
			headers: {
			  'Accept': 'application/json'
			},
			method: "GET"
		})
		.then(function(res) { 
			return res.json();
		});
};
      

app.loadUserDevices = function() {
	if (app.loggedIn) {
		app.getUsersDevices()
		.then((devices)=>{
			return app.fillDevicesWithSubDevices(devices);
		})
		.then((devices)=>{
			return app.fillDevicesWithMessageStats(devices);
		})
		.then((devicesWithSubDevices) => {
			return app.fillDevicesWithSubDeviceStatuses(devicesWithSubDevices);
		})
		.then((devicesWithSubDevices) => {
			app.ui.usersDeviceList.empty();
			$.each(devicesWithSubDevices, function (index, device) {
				var userDevice = app.userDeviceFn(device);
				app.ui.usersDeviceList.append(userDevice);
			})
		})
		.catch(function(res){ console.log(res) });
	}
};

app.loadDevices = function() {
	if (app.loggedIn) {
		app.getDevices()
		.then((devices) => {
			app.ui.addDeviceList.empty();
			$.each(devices, function (index, device) {
				var addDeviceItem = app.addDeviceListFn(device);
				app.ui.addDeviceList.append(addDeviceItem);
			})
		})
		.catch(function(res){ console.log(res) });
	}
};

app.addDeviceToUser = function(deviceId) {
	var userDevice = {
		deviceId: deviceId
	};
	console.log(JSON.stringify( userDevice ));
	fetch("/api/v1/UserDevices", {
		credentials: 'same-origin',
		headers: {
		  'Accept': 'application/json'
		},
		method: "POST",
		body: JSON.stringify( userDevice )
	})
	.catch(function(res){ console.log(res) });
};

app.removeDeviceFromUser = function(deviceId) {
	fetch("/api/v1/Devices/" + deviceId + "/UserDevices", {
		credentials: 'same-origin',
		headers: {
		  'Accept': 'application/json'
		},
		method: "DELETE"
	})
	.catch(function(res){ console.log(res) });
	//.then(function(res){ return res.json(); })
	//.then(function(data){ alert( JSON.stringify( data ) ) });
};

app.addDevicesToUser = function() {
	
	if (app.loggedIn) {
		app.ui.addDeviceList.children().each(function (index) {
			var isChecked = $(this).children("input").first().prop("checked") ? 1 : 0;
			console.log("isChecked: " + isChecked);
			var wasChecked = parseInt($(this).data("checked"));
			console.log("wasChecked: " + wasChecked);
			if (wasChecked !== isChecked) {
				var deviceId = $(this).data("deviceid");
				if (isChecked === 1) {
					app.addDeviceToUser(deviceId);
				} else {
					app.removeDeviceFromUser(deviceId);
				}
			}
		});
		$(":mobile-pagecontainer").pagecontainer( "change", "#page-users-devices", { } );
	}
};


