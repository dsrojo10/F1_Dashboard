function toggleHistoricalControls() {
    const isHistorical = $('#dataTypeSelect').val() === 'historical_data';
    $('#historicalControls').toggle(isHistorical);
    if (isHistorical) {
        clearInterval(updateInterval);
        loadHistoricalSessions();
    } else {
        updateInterval = setInterval(loadData, 15000);
    }
}

function loadHistoricalSessions() {
    $.ajax({
        url: 'api_proxy.php',
        method: 'GET',
        data: {
            endpoint: 'sessions'
        },
        success: function(response) {
            const sessions = JSON.parse(response);
            const select = $('#historicalSessionSelect');
            select.empty();
            
            // Agrupar sesiones por evento y ordenar por fecha
            const groupedSessions = {};
            sessions.forEach(session => {
                const meetingKey = session.meeting_key;
                if (!groupedSessions[meetingKey]) {
                    groupedSessions[meetingKey] = {
                        meeting_name: session.meeting_name,
                        date_start: session.date_start,
                        sessions: []
                    };
                }
                groupedSessions[meetingKey].sessions.push(session);
            });
            
            // Convertir a array y ordenar por fecha descendente
            const sortedEvents = Object.values(groupedSessions).sort((a, b) => 
                new Date(b.date_start) - new Date(a.date_start)
            );
            
            // Crear grupos de opciones por evento
            sortedEvents.forEach(event => {
                const optgroup = $(`<optgroup label="${event.meeting_name}">`);
                
                // Ordenar sesiones dentro del evento
                event.sessions.sort((a, b) => new Date(a.date_start) - new Date(b.date_start));
                
                event.sessions.forEach(session => {
                    const date = new Date(session.date_start);
                    const formattedDate = date.toLocaleDateString();
                    const formattedTime = date.toLocaleTimeString();
                    optgroup.append(`
                        <option value="${session.session_key}">
                            ${session.session_name} (${formattedDate} ${formattedTime})
                        </option>
                    `);
                });
                
                select.append(optgroup);
            });
        },
        error: function() {
            $('#historicalSessionSelect').html(
                '<option value="">Error al cargar sesiones</option>'
            );
        }
    });
}

function loadHistoricalData() {
    const sessionKey = $('#historicalSessionSelect').val();
    const dataType = $('#dataTypeHistorical').val();
    
    if (!sessionKey) {
        alert('Por favor, selecciona una sesión');
        return;
    }
    
    $('#loading').css('width', '30%');
    $('#dataContainer').html(`
        <div class="col-12 text-center">
            <div class="spinner-border text-danger" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
            <p>Cargando datos históricos...</p>
        </div>
    `);
    
    $.ajax({
        url: 'api_proxy.php',
        method: 'GET',
        data: {
            endpoint: dataType,
            params: `session_key=${sessionKey}`
        },
        success: function(response) {
            const data = JSON.parse(response);
            if (data.length === 0) {
                $('#dataContainer').html('<div class="alert alert-info">No hay datos disponibles para esta sesión.</div>');
                $('#loading').css('width', '0%');
                return;
            }
            
            // Obtener información de la sesión
            $.ajax({
                url: 'api_proxy.php',
                method: 'GET',
                data: {
                    endpoint: 'sessions',
                    params: `session_key=${sessionKey}`
                },
                success: function(sessionResponse) {
                    const sessionInfo = JSON.parse(sessionResponse)[0];
                    displayHistoricalData([{
                        session: sessionInfo,
                        data: data
                    }], dataType);
                },
                error: function() {
                    displayHistoricalData([{
                        session: { 
                            meeting_name: 'Sesión',
                            session_name: 'Desconocida'
                        },
                        data: data
                    }], dataType);
                }
            });
        },
        error: function() {
            $('#dataContainer').html('<div class="alert alert-danger">Error al cargar los datos históricos.</div>');
            $('#loading').css('width', '0%');
        }
    });
}

function displayHistoricalData(allData, dataType) {
    const container = $('#dataContainer');
    container.empty();
    
    if (allData.length === 0) {
        container.html('<div class="alert alert-info">No se encontraron datos para los criterios seleccionados.</div>');
        $('#loading').css('width', '0%');
        return;
    }
    
    allData.forEach(sessionData => {
        const sessionTitle = `${sessionData.session.meeting_name} - ${sessionData.session.session_name}`;
        const sessionDiv = $(`
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">${sessionTitle}</h5>
                    <small class="text-muted">Fecha: ${new Date(sessionData.session.date_start).toLocaleString()}</small>
                </div>
                <div class="card-body">
                    <div class="session-data-container"></div>
                </div>
            </div>
        `);
        
        const dataContainer = sessionDiv.find('.session-data-container');
        
        switch(dataType) {
            case 'car_data':
                displayHistoricalCarData(sessionData.data, dataContainer);
                break;
            case 'lap_times':
                displayHistoricalLapTimes(sessionData.data, dataContainer);
                break;
            case 'position_data':
                displayHistoricalPositions(sessionData.data, dataContainer);
                break;
        }
        
        container.append(sessionDiv);
    });
    
    $('#loading').css('width', '100%');
    setTimeout(() => $('#loading').css('width', '0%'), 300);
    $('#lastUpdate').text(new Date().toLocaleTimeString());
}

// Funciones específicas para mostrar cada tipo de dato histórico
function displayHistoricalCarData(data, container) {
    // Crear un canvas para la gráfica
    const chartCanvas = $('<canvas>').addClass('chart-container');
    container.append(chartCanvas);
    
    // Agrupar datos por piloto
    const driverData = {};
    data.forEach(item => {
        if (!driverData[item.driver_number]) {
            driverData[item.driver_number] = [];
        }
        driverData[item.driver_number].push(item);
    });
    
    // Preparar datos para Chart.js
    const datasets = Object.entries(driverData).map(([driver, data]) => ({
        label: `Piloto #${driver}`,
        data: data.map(d => ({
            x: new Date(d.date),
            y: d.speed
        })),
        fill: false,
        borderWidth: 1
    }));
    
    // Crear gráfica
    new Chart(chartCanvas[0], {
        type: 'line',
        data: {
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    type: 'time',
                    time: {
                        unit: 'minute'
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Velocidad (km/h)'
                    }
                }
            }
        }
    });
}

function displayHistoricalLapTimes(data, container) {
    // Ordenar vueltas por piloto y número de vuelta
    data.sort((a, b) => {
        if (a.driver_number === b.driver_number) {
            return a.lap_number - b.lap_number;
        }
        return a.driver_number - b.driver_number;
    });
    
    const table = $(`
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Piloto</th>
                        <th>Vuelta</th>
                        <th>Tiempo</th>
                        <th>Sector 1</th>
                        <th>Sector 2</th>
                        <th>Sector 3</th>
                        <th>Hora</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
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
                <td>${new Date(lap.date).toLocaleTimeString()}</td>
            </tr>
        `);
    });
    
    container.append(table);
}

function displayHistoricalPositions(data, container) {
    const lastPositions = {};
    data.forEach(pos => {
        lastPositions[pos.driver_number] = pos;
    });
    
    const table = $(`
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Posición</th>
                        <th>Piloto</th>
                        <th>Última Vuelta</th>
                        <th>Intervalo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    `);
    
    const tbody = table.find('tbody');
    
    Object.values(lastPositions)
        .sort((a, b) => a.position - b.position)
        .forEach(pos => {
            tbody.append(`
                <tr>
                    <td>${pos.position}</td>
                    <td>${pos.driver_number}</td>
                    <td>${pos.last_lap_time ? (pos.last_lap_time / 1000).toFixed(3) : '-'}</td>
                    <td>${pos.interval ? pos.interval.toFixed(3) : '-'}</td>
                    <td>${pos.status || '-'}</td>
                </tr>
            `);
        });
    
    container.append(table);
}

// Event listeners para la funcionalidad histórica
$(document).ready(function() {
    $('#loadHistoricalData').click(loadHistoricalData);
});