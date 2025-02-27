<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenF1 Datos en Tiempo Real</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <style>
        body {
            padding-top: 20px;
            background-color: #f8f9fa;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #e10600;
            color: white;
            font-weight: bold;
        }
        .refresh-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .driver-card {
            transition: all 0.3s ease;
        }
        .driver-card:hover {
            transform: translateY(-5px);
        }
        #loading {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background-color: #e10600;
            z-index: 9999;
            width: 0%;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div id="loading"></div>
    <div class="container">
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h1>OpenF1 - Datos en Tiempo Real</h1>
                <p class="text-muted">Información actualizada de Fórmula 1</p>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="sessionSelect">Seleccionar Sesión:</label>
                    <select class="form-control" id="sessionSelect">
                        <option value="latest">Última Sesión</option>
                        <!-- Sesiones se cargarán dinámicamente -->
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="dataTypeSelect">Tipo de Datos:</label>
                    <select class="form-control" id="dataTypeSelect">
                        <option value="car_data">Datos del Coche</option>
                        <option value="lap_times">Tiempos de Vuelta</option>
                        <option value="driver_list">Lista de Pilotos</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span id="dataTitle">Datos del Coche</span>
                        <span class="refresh-time">Última actualización: <span id="lastUpdate">-</span></span>
                    </div>
                    <div class="card-body">
                        <div id="dataContainer" class="row">
                            <!-- Los datos se cargarán aquí -->
                            <div class="col-12 text-center">
                                <div class="spinner-border text-danger" role="status">
                                    <span class="visually-hidden">Cargando...</span>
                                </div>
                                <p>Cargando datos...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
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
                loadData();
            });
            
            // Cargar las sesiones disponibles
            loadSessions();
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
                    
                    // Mantener la opción "Última Sesión"
                    sessionSelect.empty();
                    sessionSelect.append('<option value="latest">Última Sesión</option>');
                    
                    // Agregar todas las sesiones disponibles
                    sessions.forEach(function(session) {
                        const sessionName = `${session.meeting_name} - ${session.session_name} (${new Date(session.date_start).toLocaleDateString()})`;
                        sessionSelect.append(`<option value="${session.session_key}">${sessionName}</option>`);
                    });
                }
            });
        }
        
        function loadData() {
            // Mostrar indicador de carga
            $('#loading').css('width', '30%');
            
            // Construir parámetros según el tipo de datos
            let params = '';
            if (currentSessionKey !== 'latest') {
                params = `session_key=${currentSessionKey}`;
            }
            
            // Realizar la solicitud al proxy
            $.ajax({
                url: 'api_proxy.php',
                method: 'GET',
                data: {
                    endpoint: currentDataType,
                    params: params
                },
                success: function(data) {
                    // Avanzar la barra de carga
                    $('#loading').css('width', '70%');
                    
                    // Procesar y mostrar los datos
                    displayData(JSON.parse(data));
                    
                    // Actualizar la hora de última actualización
                    $('#lastUpdate').text(new Date().toLocaleTimeString());
                    
                    // Completar la barra de carga
                    $('#loading').css('width', '100%');
                    setTimeout(() => $('#loading').css('width', '0%'), 300);
                },
                error: function(error) {
                    console.error("Error al cargar datos:", error);
                    $('#dataContainer').html('<div class="col-12"><div class="alert alert-danger">Error al cargar los datos. Intente de nuevo más tarde.</div></div>');
                    
                    // Resetear la barra de carga
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
            
            // Mostrar datos según el tipo seleccionado
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
                default:
                    // Mostrar datos genéricos
                    container.html('<pre class="bg-light p-3">' + JSON.stringify(data, null, 2) + '</pre>');
            }
        }
        
        function displayCarData(data, container) {
            // Agrupar datos por piloto
            const driverData = {};
            data.forEach(item => {
                if (!driverData[item.driver_number]) {
                    driverData[item.driver_number] = [];
                }
                driverData[item.driver_number].push(item);
            });
            
            // Mostrar el último dato de cada piloto
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
            // Crear tabla de tiempos
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
                    <tbody id="lap-data">
                    </tbody>
                </table>
            `);
            
            container.append(table);
            const tbody = $('#lap-data');
            
            // Ordenar por piloto y vuelta
            data.sort((a, b) => {
                if (a.driver_number === b.driver_number) {
                    return b.lap_number - a.lap_number;
                }
                return a.driver_number - b.driver_number;
            });
            
            // Agregar filas a la tabla
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
        }
        
        function displayDrivers(data, container) {
            // Crear tabla de pilotos
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
                    <tbody id="driver-data">
                    </tbody>
                </table>
            `);
            
            container.append(table);
            const tbody = $('#driver-data');
            
            // Ordenar por número de piloto
            data.sort((a, b) => a.driver_number - b.driver_number);
            
            // Agregar filas a la tabla
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
        }
    </script>
    <!-- Modal para estado de la API -->
<div class="modal fade" id="apiStatusModal" tabindex="-1" aria-labelledby="apiStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="apiStatusModalLabel">Estado de la API OpenF1</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="apiStatusContent">
                <div class="text-center">
                    <div class="spinner-border text-danger" role="status">
                        <span class="visually-hidden">Verificando...</span>
                    </div>
                    <p>Verificando estado de la API...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="refreshApiStatus">Actualizar estado</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Añadir botón de estado en la parte superior
    $('.container .row:first').append(`
        <div class="col-12 text-center mt-2">
            <button class="btn btn-outline-danger btn-sm" id="checkApiStatusBtn">
                <i class="bi bi-activity"></i> Verificar estado de la API
            </button>
        </div>
    `);
    
    // Función para verificar el estado de la API
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
            success: function(data) {
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
                            const timeDiff = Math.floor((new Date() - dataTime) / 1000 / 60); // diferencia en minutos
                            
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
                            
                            // Mostrar información del último dato
                            statusHtml += `
                                <div class="card mt-3">
                                    <div class="card-header bg-light">Último dato recibido</div>
                                    <div class="card-body">
                                        <p><strong>Fecha:</strong> ${dataTime.toLocaleString()}</p>
                                        <p><strong>Piloto:</strong> #${data.latest_data.driver_number}</p>
                                        <p><strong>Velocidad:</strong> ${data.latest_data.speed} km/h</p>
                                    </div>
                                </div>
                            `;
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
            error: function(error) {
                $('#apiStatusContent').html(`
                    <div class="alert alert-danger">
                        <strong>Error al verificar estado</strong><br>
                        No se pudo conectar con el servidor
                    </div>
                `);
            }
        });
    }
    
    // Eventos para verificar estado
    $(document).ready(function() {
        $('#checkApiStatusBtn').click(function() {
            $('#apiStatusModal').modal('show');
            checkApiStatus();
        });
        
        $('#refreshApiStatus').click(function() {
            checkApiStatus();
        });
    });
</script>
</body>
</html>