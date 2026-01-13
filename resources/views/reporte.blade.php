<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Gestión de Llamadas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        .badge { font-weight: 500; letter-spacing: 0.5px; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        
        /* Tarjetas de Resumen */
        .card-resumen { border-left: 5px solid #ddd; transition: transform 0.2s; }
        .card-resumen:hover { transform: translateY(-3px); }
        .borde-azul { border-left-color: #0d6efd; }
        .borde-info { border-left-color: #0dcaf0; }
        .borde-verde { border-left-color: #198754; }
        
        .texto-precio { font-size: 1.8rem; font-weight: 800; color: #198754; }
        .cursor-pointer { cursor: pointer; }
        
        /* Animación para el botón de carga */
        .spin { animation: spin 1s infinite linear; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>
</head>
<body class="bg-light">

<div class="container my-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-primary fw-bold"><i class="bi bi-telephone-inbound-fill me-2"></i>Dashboard de Control</h3>
            <span class="text-muted small">Generado: {{ date('d/m/Y H:i') }}</span>
        </div>
        
        <a href="{{ route('cdr.sync') }}" class="btn btn-warning fw-bold shadow-sm" 
           onclick="this.innerHTML='<i class=\'bi bi-arrow-repeat spin\'></i> Buscando...'; this.classList.add('disabled');">
            <i class="bi bi-cloud-arrow-down-fill"></i> Sincronizar Ahora
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 card-resumen borde-azul h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold">Total Llamadas</h6>
                        <h2 class="mb-0 fw-bold text-dark">{{ number_format($totalLlamadas) }}</h2>
                    </div>
                    <div class="fs-1 text-primary opacity-25"><i class="bi bi-list-ul"></i></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 card-resumen borde-info h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold">Tiempo Facturable</h6>
                        <h2 class="mb-0 fw-bold text-dark">{{ number_format($minutosFacturables ?? 0) }} <small class="fs-6 text-muted">min</small></h2>
                    </div>
                    <div class="fs-1 text-info opacity-25"><i class="bi bi-stopwatch"></i></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-0 card-resumen borde-verde h-100 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold">Total a Cobrar</h6>
                        <div class="texto-precio">${{ number_format($totalPagar, 0, ',', '.') }}</div>
                        <small class="text-muted" style="font-size: 0.8rem;">(Tarifa: ${{ $tarifa }}/min)</small>
                    </div>
                    <div class="fs-1 text-success opacity-25"><i class="bi bi-cash-stack"></i></div>
                </div>
            </div>
        </div>
    </div>
    
    @if(isset($labels) && count($labels) > 0)
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-white py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-bar-chart-fill me-1"></i> Tendencia Diaria</h6>
        </div>
        <div class="card-body">
            <div style="height: 250px; width: 100%;">
                <canvas id="graficoLlamadas"></canvas>
            </div>
        </div>
    </div>
    @endif

    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-dark text-white">
            <i class="bi bi-funnel-fill me-1"></i> Filtros de Búsqueda
        </div>
        <div class="card-body bg-white">
            <form action="{{ url('/') }}" method="GET" class="row g-3 align-items-end">
                
                <div class="col-md-2">
                    <label class="form-label fw-bold small">Desde:</label>
                    <input type="date" class="form-control" name="fecha_inicio" value="{{ $fechaInicio }}">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label fw-bold small">Hasta:</label>
                    <input type="date" class="form-control" name="fecha_fin" value="{{ $fechaFin }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small">Anexo / Origen:</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-telephone"></i></span>
                        <input type="text" class="form-control" name="anexo" value="{{ $anexo }}" placeholder="Ej: 3002">
                    </div>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold small text-success">Valor Minuto:</label>
                    <div class="input-group">
                        <span class="input-group-text text-success fw-bold">$</span>
                        <input type="number" class="form-control border-success" name="tarifa" 
                               value="{{ $tarifa ?? 50 }}" min="0">
                    </div>
                </div>
                
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1 fw-bold">
                        <i class="bi bi-calculator"></i> Calcular
                    </button>
                    
                    <button type="submit" 
                            formaction="{{ route('cdr.pdf') }}" 
                            formtarget="_blank" 
                            class="btn btn-danger" 
                            title="Descargar PDF">
                        <i class="bi bi-file-earmark-pdf-fill"></i>
                    </button>
                    <a href="{{ route('calls.export', request()->all()) }}" class="btn btn-success ms-2">
                        <i class="bi bi-file-earmark-excel"></i> Excel
                    </a>

                    <a href="{{ url('/') }}" class="btn btn-outline-secondary" title="Limpiar">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-dark">Detalle de Registros</h6>
            <span class="badge bg-light text-dark border">Viendo {{ $llamadas->count() }} de {{ $llamadas->total() }}</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th class="text-center py-3">Hora</th>
                            <th class="py-3">Origen / Nombre</th>
                            <th class="text-center py-3">Destino</th>
                            <th class="text-center py-3">Duración</th>
                            <th class="text-center py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($llamadas as $cdr)
                            <tr>
                                <td class="text-center">
                                    <div class="fw-bold text-dark">{{ date('H:i:s', strtotime($cdr->start_time)) }}</div>
                                    <small class="text-muted" style="font-size: 0.75rem;">{{ date('d/m/Y', strtotime($cdr->start_time)) }}</small>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="fw-bold fs-5 me-2 text-dark">{{ $cdr->source }}</span>
                                        
                                        @if($cdr->extension && $cdr->extension->fullname)
                                            <span class="text-primary small fst-italic me-2">
                                                <i class="bi bi-person-fill"></i> {{ $cdr->extension->fullname }}
                                            </span>
                                        @endif

                                        <button class="btn btn-sm btn-light text-secondary border-0 py-0 px-1 ms-1" 
                                                onclick="editarNombre('{{ $cdr->source }}', '{{ $cdr->extension->fullname ?? '' }}')"
                                                title="Editar nombre localmente">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                    </div>
                                    
                                    {{-- Usamos ?? '' para decirle: "Si no existe la extensión, asume que el nombre es vacío" --}}
                                    @if($cdr->caller_name && $cdr->caller_name != $cdr->source && $cdr->caller_name != ($cdr->extension->fullname ?? ''))
                                        <div class="small text-muted fst-italic">
                                            <i class="bi bi-telephone-forward"></i> ID Central: {{ $cdr->caller_name }}
                                        </div>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-3 py-2 font-monospace">
                                        {{ $cdr->destination }}
                                    </span>
                                </td>

                                <td class="text-center fw-bold {{ $cdr->billsec > 0 ? 'text-dark' : 'text-muted' }}">
                                    {{ $cdr->billsec }}s
                                </td>

                                <td class="text-center">
                                    @if($cdr->disposition == 'ANSWERED')
                                        <span class="badge bg-success bg-opacity-75 w-75">Contestada</span>
                                    @elseif($cdr->disposition == 'NO ANSWER')
                                        <span class="badge bg-danger bg-opacity-75 w-75">No Contestan</span>
                                    @elseif($cdr->disposition == 'BUSY')
                                        <span class="badge bg-warning text-dark w-75">Ocupado</span>
                                    @elseif($cdr->disposition == 'FAILED')
                                        <span class="badge bg-secondary w-75">Fallida</span>
                                    @else
                                        <span class="badge bg-secondary w-75">{{ $cdr->disposition }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted opacity-50">
                                        <i class="bi bi-inbox fs-1"></i>
                                        <p class="mt-2">No se encontraron llamadas con estos filtros.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card-footer bg-white d-flex justify-content-center py-3">
             {{ $llamadas->appends(request()->input())->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarNombre" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <form action="{{ route('extension.update') }}" method="POST">
        @csrf
        <div class="modal-header bg-primary text-white py-2">
            <h6 class="modal-title fw-bold"><i class="bi bi-pencil-fill me-1"></i> Asignar Nombre</h6>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="extension_id" id="modalExtensionId">
            
            <div class="mb-3">
                <label class="form-label small text-muted text-uppercase fw-bold">Anexo</label>
                <input type="text" class="form-control bg-light fw-bold" id="modalExtensionDisplay" readonly>
            </div>
            
            <div class="mb-3">
                <label class="form-label small text-muted text-uppercase fw-bold">Nombre Persona/Depto</label>
                <input type="text" class="form-control" name="fullname" id="modalFullname" placeholder="Ej: Gerencia" required autofocus>
            </div>
        </div>
        <div class="modal-footer p-1">
            <button type="submit" class="btn btn-primary w-100">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // 1. Lógica del Modal
    function editarNombre(extension, nombreActual) {
        document.getElementById('modalExtensionId').value = extension;
        document.getElementById('modalExtensionDisplay').value = extension;
        document.getElementById('modalFullname').value = nombreActual;
        
        var myModal = new bootstrap.Modal(document.getElementById('modalEditarNombre'));
        myModal.show();
    }

    // 2. Lógica del Gráfico (Si hay datos)
    @if(isset($labels) && isset($data))
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('graficoLlamadas');
        if(ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: @json($labels),
                    datasets: [{
                        label: 'Llamadas',
                        data: @json($data),
                        backgroundColor: 'rgba(13, 110, 253, 0.7)',
                        borderColor: 'rgba(13, 110, 253, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }
    });
    @endif
</script>

</body>
</html>