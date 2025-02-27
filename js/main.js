// Variable para controlar el intervalo de actualización
let updateInterval;
let currentSessionKey = 'latest';
let currentDataType = 'car_data';

$(document).ready(function() {
    // Iniciar carga de datos
    loadData();
    
    // Configurar actualización automática cada 15 segundos
    updateInterval = setInterval(loadData, 15000);
    
    // Manejadores de eventos para cambios en los selectores
    $('#sessionSelect').change(function() {
        currentSessionKey = $(this).val();
        loadData();
    });
    
    $('#dataTypeSelect').change(function() {
        currentDataType = $(this).val();
        $('#dataTitle').text($('#dataTypeSelect option:selected').text());
        toggleHistoricalControls();
        if (currentDataType !== 'historical_data') {
            loadData();
        }
    });
    
    // Cargar las sesiones disponibles
    loadSessions();
    
    // Inicializar Flatpickr para el selector de fecha
    flatpickr("#dateSelect", {
        maxDate: "today",
        dateFormat: "Y-m-d"
    });
    
    // Event listeners para el estado de la API
    $('#checkApiStatusBtn').click(function() {
        $('#apiStatusModal').modal('show');
        checkApiStatus();
    });
    
    $('#refreshApiStatus').click(checkApiStatus);
});

function loadSessions() {
    $.ajax({
        url: 'api_proxy.php',
        method: 'GET',
        data: {
            endpoint: 'sessions',
            params: ''
        },
        success: function(data) {
            const sessions = JSON.parse(data);
            const sessionSelect = $('#sessionSelect');
            
            sessionSelect.empty();
            sessionSelect.append('<option value="latest">Última Sesión</option>');
            
            sessions.forEach(function(session) {
                const sessionName = `${session.meeting_name} - ${session.session_name} (${new Date(session.date_start).toLocaleDateString()})`;
                sessionSelect.append(`<option value="${session.session_key}">${sessionName}</option>`);
            });
        }
    });
}

function loadData() {
    if (currentDataType === 'historical_data') {
        return;
    }

    $('#loading').css('width', '30%');
    
    let params = '';
    if (currentSessionKey !== 'latest') {
        params = `session_key=${currentSessionKey}`;
    }
    
    $.ajax({
        url: 'api_proxy.php',
        method: 'GET',
        data: {
            endpoint: currentDataType,
            params: params
        },
        success: function(data) {
            $('#loading').css('width', '70%');
            displayData(JSON.parse(data));
            $('#lastUpdate').text(new Date().toLocaleTimeString());
            $('#loading').css('width', '100%');
            setTimeout(() => $('#loading').css('width', '0%'), 300);
        },
        error: function(error) {
            console.error("Error al cargar datos:", error);
            $('#dataContainer').html('<div class="col-12"><div class="alert alert-danger">Error al cargar los datos. Intente de nuevo más tarde.</div></div>');
            $('#loading').css('width', '0%');
        }
    });
}

function displayData(data) {
    const container = $('#dataContainer');
    container.empty();
    
    if (!data || data.length === 0) {
        container.html('<div class="col-12"><div class="alert alert-info">No hay datos disponibles para mostrar.</div></div>');
        return;
    }
    
    switch(currentDataType) {
        case 'car_data':
            displayCarData(data, container);
            break;
        case 'lap_times':
            displayLapTimes(data, container);
            break;
        case 'driver_list':
            displayDrivers(data, container);
            break;
    }
}

function displayCarData(data, container) {
    const driverData = {};
    data.forEach(item => {
        if (!driverData[item.driver_number]) {
            driverData[item.driver_number] = [];
        }
        driverData[item.driver_number].push(item);
    });
    
    Object.keys(driverData).forEach(driverNumber => {
        const latestData = driverData[driverNumber][driverData[driverNumber].length - 1];
        
        container.append(`
            <div class="col-md-4 mb-3">
                <div class="card driver-card">
                    <div class="card-header">
                        Piloto #${latestData.driver_number}
                    </div>
                    <div class="card-body">
                        <p><strong>Velocidad:</strong> ${latestData.speed} km/h</p>
                        <p><strong>RPM:</strong> ${latestData.rpm}</p>
                        <p><strong>Marcha:</strong> ${latestData.n_gear}</p>
                        <p><strong>Acelerador:</strong> ${latestData.throttle}%</p>
                        <p><strong>Freno:</strong> ${latestData.brake}%</p>
                        <p><strong>DRS:</strong> ${latestData.drs === 1 ? 'Activado' : 'Desactivado'}</p>
                        <p class="text-muted">Hora: ${new Date(latestData.date).toLocaleTimeString()}</p>
                    </div>
                </div>
            </div>
        `);
    });
}

function displayLapTimes(data, container) {
    const table = $(`
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Piloto</th>
                    <th>Vuelta</th>
                    <th>Tiempo</th>
                    <th>Sector 1</th>
                    <th>Sector 2</th>
                    <th>Sector 3</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    `);
    
    const tbody = table.find('tbody');
    
    data.forEach(lap => {
        const formatTime = (ms) => {
            if (!ms) return "-";
            const minutes = Math.floor(ms / 60000);
            const seconds = ((ms % 60000) / 1000).toFixed(3);
            return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
        };
        
        tbody.append(`
            <tr>
                <td>${lap.driver_number}</td>
                <td>${lap.lap_number}</td>
                <td>${formatTime(lap.lap_time)}</td>
                <td>${formatTime(lap.sector_1_time)}</td>
                <td>${formatTime(lap.sector_2_time)}</td>
                <td>${formatTime(lap.sector_3_time)}</td>
            </tr>
        `);
    });
    
    container.append(table);
}

function displayDrivers(data, container) {
    const table = $(`
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Nombre</th>
                    <th>Abreviatura</th>
                    <th>Equipo</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    `);
    
    const tbody = table.find('tbody');
    data.sort((a, b) => a.driver_number - b.driver_number);
    
    data.forEach(driver => {
        tbody.append(`
            <tr>
                <td>${driver.driver_number}</td>
                <td>${driver.full_name || '-'}</td>
                <td>${driver.short_name || '-'}</td>
                <td>${driver.team_name || '-'}</td>
            </tr>
        `);
    });
    
    container.append(table);
}

function checkApiStatus() {
    $('#apiStatusContent').html(`
        <div class="text-center">
            <div class="spinner-border text-danger" role="status">
                <span class="visually-hidden">Verificando...</span>
            </div>
            <p>Verificando estado de la API...</p>
        </div>
    `);
    
    $.ajax({
        url: 'api_proxy.php',
        method: 'GET',
        data: {
            endpoint: 'check_status'
        },
        success: function(response) {
            const data = typeof response === 'string' ? JSON.parse(response) : response;
            let statusHtml = '';
            
            if (data.api_available) {
                statusHtml += `
                    <div class="alert alert-success">
                        <strong>✓ La API está disponible</strong>
                    </div>
                `;
                
                if (data.active_session) {
                    statusHtml += `
                        <div class="alert alert-info">
                            <strong>✓ Hay una sesión activa:</strong><br>
                            ${data.latest_session.meeting_name} - ${data.latest_session.session_name}
                        </div>
                    `;
                    
                    if (data.latest_data) {
                        const dataTime = new Date(data.data_timestamp);
                        const timeDiff = Math.floor((new Date() - dataTime) / 1000 / 60);
                        
                        if (data.data_is_recent) {
                            statusHtml += `
                                <div class="alert alert-success">
                                    <strong>✓ Se están recibiendo datos en tiempo real</strong><br>
                                    Último dato recibido hace ${timeDiff} minutos
                                </div>
                            `;
                        } else {
                            statusHtml += `
                                <div class="alert alert-warning">
                                    <strong>⚠ Los datos no son recientes</strong><br>
                                    Último dato recibido hace ${timeDiff} minutos
                                </div>
                            `;
                        }
                    } else {
                        statusHtml += `
                            <div class="alert alert-warning">
                                <strong>⚠ No se han encontrado datos para esta sesión</strong>
                            </div>
                        `;
                    }
                } else {
                    statusHtml += `
                        <div class="alert alert-warning">
                            <strong>⚠ No hay una sesión activa en este momento</strong>
                        </div>
                    `;
                }
            } else {
                statusHtml += `
                    <div class="alert alert-danger">
                        <strong>✗ La API no está respondiendo</strong><br>
                        Por favor, intente más tarde
                    </div>
                `;
            }
            
            $('#apiStatusContent').html(statusHtml);
        },
        error: function() {
            $('#apiStatusContent').html(`
                <div class="alert alert-danger">
                    <strong>Error al verificar estado</strong><br>
                    No se pudo conectar con el servidor
                </div>
            `);
        }
    });
}