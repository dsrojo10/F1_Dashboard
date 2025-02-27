<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenF1 Datos en Tiempo Real</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css">
    <!-- DatePicker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            margin-bottom: 20px;
        }
        .historical-controls {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        optgroup {
            font-weight: bold;
            color: #e10600;
        }
        optgroup option {
            font-weight: normal;
            color: #212529;
            padding-left: 10px;
        }
        .historical-controls select {
            max-height: 400px;
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

        <!-- API Status Button -->
        <div class="col-12 text-center mt-2 mb-4">
            <button class="btn btn-outline-danger btn-sm" id="checkApiStatusBtn">
                <i class="bi bi-activity"></i> Verificar estado de la API
            </button>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="sessionSelect">Seleccionar Sesión:</label>
                    <select class="form-control" id="sessionSelect">
                        <option value="latest">Última Sesión</option>
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
                        <option value="historical_data">Datos Históricos</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Historical Controls -->
        <div id="historicalControls" class="historical-controls" style="display: none;">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="historicalSessionSelect">Seleccionar Sesión:</label>
                        <select class="form-control" id="historicalSessionSelect">
                            <option value="">Cargando sesiones...</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="dataTypeHistorical">Tipo de Datos:</label>
                        <select class="form-control" id="dataTypeHistorical">
                            <option value="car_data">Datos del Coche</option>
                            <option value="lap_times">Tiempos por Vuelta</option>
                            <option value="position_data">Posiciones</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 mt-3 text-center">
                    <button class="btn btn-primary" id="loadHistoricalData">
                        <i class="bi bi-search"></i> Cargar Datos Históricos
                    </button>
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

    <!-- API Status Modal -->
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="js/main.js"></script>
    <script src="js/historical.js"></script>
</body>
</html>