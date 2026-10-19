<?php
/**
 * CitasSpa — Sistema de Gestión y Agendamiento de Citas
 * Centro de belleza y spa.
 *
 * PATRÓN IMPLEMENTADO: Observer (comportamental)
 * El sujeto CitaObservable notifica a Cliente, Profesional y Recepción
 * cada vez que el estado de una cita cambia, sin acoplarlos entre sí.
 *
 * Ejecutar: php -S localhost:8080  (o copiar a htdocs de XAMPP)
 */
declare(strict_types=1);
session_start();

/* =============================================================================
 * PATRÓN OBSERVER — Interfaz del observador
 * Contrato común: el sujeto no conoce clases concretas, solo esta interfaz.
 * ========================================================================== */
interface ICitaObserver
{
    public function actualizar(string $mensaje): void;
}

/**
 * Recolector de eventos para el panel "en tiempo real".
 * Los observadores escriben aquí en lugar de enviar WhatsApp/correo reales
 * (alcance del proyecto: simulación, no integración externa).
 */
class ObserverLog
{
    public static function registrar(string $actor, string $canal, string $mensaje): void
    {
        if (!isset($_SESSION['observer_log'])) {
            $_SESSION['observer_log'] = [];
        }
        array_unshift($_SESSION['observer_log'], [
            'hora'    => date('H:i:s'),
            'fecha'   => date('d/m/Y'),
            'actor'   => $actor,
            'canal'   => $canal,
            'mensaje' => $mensaje,
        ]);
        $_SESSION['observer_log'] = array_slice($_SESSION['observer_log'], 0, 80);
    }
}

/* =============================================================================
 * PATRÓN OBSERVER — Sujeto (Observable)
 * Mantiene el estado de la cita y la lista de observadores.
 * cambiarEstado() actualiza el estado y dispara notificarObservadores().
 * ========================================================================== */
class CitaObservable
{
    public string $idCita;
    public string $cliente;
    public string $profesional;
    public string $servicio;
    public string $fecha;
    public string $hora;
    public string $estado;
    public string $telefono;
    public string $correo;

    /** @var ICitaObserver[] */
    private array $observadores = [];

    public function __construct(array $cita)
    {
        $this->idCita      = $cita['id'];
        $this->cliente     = $cita['cliente'];
        $this->profesional = $cita['profesional'];
        $this->servicio    = $cita['servicio'];
        $this->fecha       = $cita['fecha'];
        $this->hora        = $cita['hora'];
        $this->estado      = $cita['estado'];
        $this->telefono    = $cita['telefono'] ?? '';
        $this->correo      = $cita['correo'] ?? '';
    }

    public function adjuntar(ICitaObserver $observador): void
    {
        $this->observadores[] = $observador;
    }

    public function desacoplar(ICitaObserver $observador): void
    {
        $this->observadores = array_values(array_filter(
            $this->observadores,
            static fn (ICitaObserver $o) => $o !== $observador
        ));
    }

    public function notificarObservadores(): void
    {
        $mensaje = $this->construirMensaje();
        foreach ($this->observadores as $observador) {
            $observador->actualizar($mensaje);
        }
    }

    public function cambiarEstado(string $nuevoEstado, ?string $nuevaFecha = null, ?string $nuevaHora = null): void
    {
        $this->estado = $nuevoEstado;
        if ($nuevaFecha !== null && $nuevaFecha !== '') {
            $this->fecha = $nuevaFecha;
        }
        if ($nuevaHora !== null && $nuevaHora !== '') {
            $this->hora = $nuevaHora;
        }
        // Punto clave del Observer: un cambio de estado notifica a todos los suscriptores.
        $this->notificarObservadores();
    }

    private function construirMensaje(): string
    {
        $fechaFmt = date('d/m/Y', strtotime($this->fecha));
        return sprintf(
            'Cita %s · Cliente: %s · Servicio: %s · Profesional: %s · %s %s · Estado: %s',
            $this->idCita,
            $this->cliente,
            $this->servicio,
            $this->profesional,
            $fechaFmt,
            $this->hora,
            $this->estado
        );
    }
}

/* =============================================================================
 * PATRÓN OBSERVER — Observadores concretos
 * Cada actor reacciona de forma distinta al mismo evento.
 * ========================================================================== */
class ProfesionalObserver implements ICitaObserver
{
    private string $nombre;

    public function __construct(string $nombre)
    {
        $this->nombre = $nombre;
    }

    public function actualizar(string $mensaje): void
    {
        ObserverLog::registrar(
            'Profesional',
            'Agenda del especialista',
            "Agenda de {$this->nombre} sincronizada. {$mensaje}"
        );
    }
}

class ClienteObserver implements ICitaObserver
{
    private string $nombre;
    private string $telefono;
    private string $correo;

    public function __construct(string $nombre, string $telefono, string $correo)
    {
        $this->nombre   = $nombre;
        $this->telefono = $telefono;
        $this->correo   = $correo;
    }

    public function actualizar(string $mensaje): void
    {
        $destino = $this->telefono !== '' ? $this->telefono : $this->correo;
        ObserverLog::registrar(
            'Cliente',
            'WhatsApp / Correo (simulado)',
            "Alerta enviada a {$this->nombre} ({$destino}). {$mensaje}"
        );
    }
}

class RecepcionObserver implements ICitaObserver
{
    public function actualizar(string $mensaje): void
    {
        ObserverLog::registrar(
            'Recepción',
            'Tablero centralizado',
            "Tablero de control actualizado. {$mensaje}"
        );
    }
}

/* =============================================================================
 * Catálogos de dominio (servicios, profesionales, horarios)
 * ========================================================================== */
function catalogoServicios(): array
{
    return [
        'Masaje relajante'     => ['duracion' => 60, 'costo' => 85000,  'cat' => 'Corporal'],
        'Masaje descontracturante' => ['duracion' => 75, 'costo' => 98000, 'cat' => 'Corporal'],
        'Tratamiento facial'   => ['duracion' => 50, 'costo' => 72000,  'cat' => 'Facial'],
        'Manicure spa'         => ['duracion' => 45, 'costo' => 38000,  'cat' => 'Uñas'],
        'Pedicure spa'         => ['duracion' => 50, 'costo' => 42000,  'cat' => 'Uñas'],
        'Depilación'           => ['duracion' => 40, 'costo' => 45000,  'cat' => 'Estética'],
        'Hidratación corporal' => ['duracion' => 60, 'costo' => 90000,  'cat' => 'Corporal'],
    ];
}

function catalogoProfesionales(): array
{
    return [
        'Laura Gómez'     => ['Masaje relajante', 'Masaje descontracturante', 'Hidratación corporal'],
        'Camila Torres'   => ['Tratamiento facial', 'Hidratación corporal'],
        'Valentina Ruiz'  => ['Manicure spa', 'Pedicure spa', 'Depilación'],
        'Andrés Molina'   => ['Masaje relajante', 'Masaje descontracturante'],
        'Sofía Herrera'   => ['Tratamiento facial', 'Manicure spa', 'Pedicure spa'],
    ];
}

function franjasHorarias(): array
{
    $horas = [];
    for ($h = 9; $h <= 18; $h++) {
        $horas[] = sprintf('%02d:00', $h);
        if ($h < 18) {
            $horas[] = sprintf('%02d:30', $h);
        }
    }
    return $horas;
}

function estadosCita(): array
{
    return ['PENDIENTE', 'CONFIRMADA', 'REPROGRAMADA', 'ATENDIDA', 'CANCELADA'];
}

function semillaInicial(): void
{
    if (!isset($_SESSION['citas'])) {
        $_SESSION['citas'] = [
            [
                'id'          => 'CITA-1001',
                'cliente'     => 'María Pérez',
                'telefono'    => '3001112233',
                'correo'      => 'maria.perez@correo.com',
                'servicio'    => 'Masaje relajante',
                'profesional' => 'Laura Gómez',
                'fecha'       => date('Y-m-d', strtotime('+1 day')),
                'hora'        => '10:00',
                'duracion'    => 60,
                'estado'      => 'CONFIRMADA',
                'creada'      => date('Y-m-d H:i:s'),
            ],
            [
                'id'          => 'CITA-1002',
                'cliente'     => 'Carlos Restrepo',
                'telefono'    => '3105556677',
                'correo'      => 'carlos.r@correo.com',
                'servicio'    => 'Manicure spa',
                'profesional' => 'Valentina Ruiz',
                'fecha'       => date('Y-m-d'),
                'hora'        => '15:30',
                'duracion'    => 45,
                'estado'      => 'PENDIENTE',
                'creada'      => date('Y-m-d H:i:s'),
            ],
        ];
    }
    if (!isset($_SESSION['observer_log'])) {
        $_SESSION['observer_log'] = [];
    }
    if (!isset($_SESSION['secuencia'])) {
        $_SESSION['secuencia'] = 1003;
    }
}

function minutos(string $hora): int
{
    [$h, $m] = array_map('intval', explode(':', $hora));
    return $h * 60 + $m;
}

function hayCruceHorario(array $citas, string $profesional, string $fecha, string $hora, int $duracion, ?string $exceptoId = null): bool
{
    $inicio = minutos($hora);
    $fin    = $inicio + $duracion;
    foreach ($citas as $cita) {
        if ($exceptoId !== null && $cita['id'] === $exceptoId) {
            continue;
        }
        if ($cita['profesional'] !== $profesional || $cita['fecha'] !== $fecha) {
            continue;
        }
        if (in_array($cita['estado'], ['CANCELADA', 'ATENDIDA'], true)) {
            continue;
        }
        $iniB = minutos($cita['hora']);
        $finB = $iniB + (int) $cita['duracion'];
        if ($inicio < $finB && $iniB < $fin) {
            return true;
        }
    }
    return false;
}

/**
 * Enlaza los 3 observadores al sujeto y dispara el cambio de estado.
 * Aquí se ve el “uno a muchos” del patrón Observer.
 */
function dispararObserver(array &$cita, string $nuevoEstado, ?string $nuevaFecha = null, ?string $nuevaHora = null): void
{
    $sujeto = new CitaObservable($cita);
    $sujeto->adjuntar(new ClienteObserver($cita['cliente'], $cita['telefono'], $cita['correo']));
    $sujeto->adjuntar(new ProfesionalObserver($cita['profesional']));
    $sujeto->adjuntar(new RecepcionObserver());
    $sujeto->cambiarEstado($nuevoEstado, $nuevaFecha, $nuevaHora);

    $cita['estado'] = $sujeto->estado;
    $cita['fecha']  = $sujeto->fecha;
    $cita['hora']   = $sujeto->hora;
}

function flash(string $tipo, string $texto): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function tomarFlash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

semillaInicial();

$servicios     = catalogoServicios();
$profesionales = catalogoProfesionales();
$errores       = [];

/* --- Acciones HTTP --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agendar') {
        $cliente     = trim((string) ($_POST['cliente'] ?? ''));
        $telefono    = trim((string) ($_POST['telefono'] ?? ''));
        $correo      = trim((string) ($_POST['correo'] ?? ''));
        $servicio    = (string) ($_POST['servicio'] ?? '');
        $profesional = (string) ($_POST['profesional'] ?? '');
        $fecha       = (string) ($_POST['fecha'] ?? '');
        $hora        = (string) ($_POST['hora'] ?? '');

        if ($cliente === '' || $telefono === '' || $correo === '') {
            $errores[] = 'Complete los datos de contacto del cliente (nombre, teléfono y correo).';
        }
        if (!isset($servicios[$servicio])) {
            $errores[] = 'Seleccione un servicio válido.';
        }
        if (!isset($profesionales[$profesional])) {
            $errores[] = 'Seleccione un profesional válido.';
        } elseif (isset($servicios[$servicio]) && !in_array($servicio, $profesionales[$profesional], true)) {
            $errores[] = "{$profesional} no está capacitado(a) para el servicio «{$servicio}».";
        }
        if ($fecha === '' || strtotime($fecha) === false) {
            $errores[] = 'Indique una fecha válida.';
        } elseif ($fecha < date('Y-m-d')) {
            $errores[] = 'No es posible agendar en una fecha anterior a hoy.';
        }
        if (!in_array($hora, franjasHorarias(), true)) {
            $errores[] = 'Seleccione una franja horaria del centro (9:00 a 18:00).';
        }

        $duracion = isset($servicios[$servicio]) ? (int) $servicios[$servicio]['duracion'] : 0;
        if ($errores === [] && hayCruceHorario($_SESSION['citas'], $profesional, $fecha, $hora, $duracion)) {
            $errores[] = 'Horario ocupado: ese profesional ya tiene una cita en la franja seleccionada. Elija otra hora o fecha.';
        }

        if ($errores === []) {
            $id = 'CITA-' . $_SESSION['secuencia']++;
            $nueva = [
                'id'          => $id,
                'cliente'     => $cliente,
                'telefono'    => $telefono,
                'correo'      => $correo,
                'servicio'    => $servicio,
                'profesional' => $profesional,
                'fecha'       => $fecha,
                'hora'        => $hora,
                'duracion'    => $duracion,
                'estado'      => 'PENDIENTE',
                'creada'      => date('Y-m-d H:i:s'),
            ];
            dispararObserver($nueva, 'PENDIENTE');
            $_SESSION['citas'][] = $nueva;
            flash('ok', "Reserva {$id} registrada. Agenda actualizada y observadores notificados.");
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
    }

    if ($accion === 'estado') {
        $id          = (string) ($_POST['id_cita'] ?? '');
        $nuevoEstado = strtoupper((string) ($_POST['nuevo_estado'] ?? ''));
        $nuevaFecha  = trim((string) ($_POST['nueva_fecha'] ?? ''));
        $nuevaHora   = trim((string) ($_POST['nueva_hora'] ?? ''));

        if (!in_array($nuevoEstado, estadosCita(), true) || $nuevoEstado === 'PENDIENTE') {
            $errores[] = 'Estado no permitido.';
        }

        $idx = null;
        foreach ($_SESSION['citas'] as $i => $c) {
            if ($c['id'] === $id) {
                $idx = $i;
                break;
            }
        }
        if ($idx === null) {
            $errores[] = 'No se encontró la cita seleccionada.';
        } else {
            $cita = $_SESSION['citas'][$idx];
            if (in_array($cita['estado'], ['ATENDIDA', 'CANCELADA'], true)) {
                $errores[] = 'Una cita atendida o cancelada no puede cambiar de estado.';
            }
            if ($nuevoEstado === 'REPROGRAMADA') {
                if ($nuevaFecha === '' || $nuevaHora === '') {
                    $errores[] = 'Para reprogramar indique la nueva fecha y hora.';
                } elseif ($nuevaFecha < date('Y-m-d')) {
                    $errores[] = 'La nueva fecha no puede ser anterior a hoy.';
                } elseif (hayCruceHorario($_SESSION['citas'], $cita['profesional'], $nuevaFecha, $nuevaHora, (int) $cita['duracion'], $cita['id'])) {
                    $errores[] = 'La nueva franja ya está ocupada para ese profesional.';
                }
            } else {
                $nuevaFecha = null;
                $nuevaHora  = null;
            }
        }

        if ($errores === [] && $idx !== null) {
            dispararObserver($_SESSION['citas'][$idx], $nuevoEstado, $nuevaFecha, $nuevaHora);
            flash('ok', "Cita {$id} pasó a {$nuevoEstado}. Cliente, profesional y recepción fueron notificados.");
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
    }

    if ($accion === 'limpiar_log') {
        $_SESSION['observer_log'] = [];
        flash('ok', 'Panel de observadores reiniciado.');
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    if ($accion === 'reiniciar') {
        unset($_SESSION['citas'], $_SESSION['observer_log'], $_SESSION['secuencia'], $_SESSION['flash']);
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
}

$flash = tomarFlash();
$citas = $_SESSION['citas'];
usort($citas, static function (array $a, array $b): int {
    $cmp = strcmp($b['fecha'] . $b['hora'], $a['fecha'] . $a['hora']);
    return $cmp !== 0 ? $cmp : strcmp($b['id'], $a['id']);
});
$log = $_SESSION['observer_log'] ?? [];
$mapProfesionalesJson = json_encode($profesionales, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CitasSpa · Gestión y agendamiento</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --spa: #6b2d5c;
            --spa-deep: #4a1d40;
            --spa-soft: #c9a0bb;
            --blush: #f4e6ef;
            --cream: #fbf7f4;
            --ink: #2c2430;
            --muted: #6f6474;
            --line: #eadfe6;
            --ok: #2f7d5a;
            --ok-bg: #e4f4ec;
            --warn: #b7791f;
            --warn-bg: #f8eed8;
            --info: #3d5a80;
            --info-bg: #e6eef6;
            --pend: #7a5c2e;
            --pend-bg: #f3ead8;
            --danger: #a33b4a;
            --danger-bg: #f8e4e7;
            --card: #ffffff;
            --shadow: 0 18px 50px rgba(107, 45, 92, 0.10);
            --radius: 18px;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: Outfit, system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(1200px 500px at 10% -10%, #ead3e2 0%, transparent 55%),
                radial-gradient(900px 400px at 100% 0%, #e8dcc8 0%, transparent 45%),
                var(--cream);
            min-height: 100vh;
        }
        .wrap { width: min(1180px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 64px; }
        header.hero {
            display: flex; flex-wrap: wrap; gap: 16px;
            justify-content: space-between; align-items: flex-end;
            margin-bottom: 28px;
        }
        .brand { display: flex; gap: 14px; align-items: center; }
        .logo {
            width: 52px; height: 52px; border-radius: 16px;
            background: linear-gradient(145deg, var(--spa), #9b4d82);
            color: #fff; display: grid; place-items: center;
            font-family: "Cormorant Garamond", serif; font-size: 28px; font-weight: 700;
            box-shadow: 0 10px 24px rgba(107, 45, 92, .28);
        }
        h1 {
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            margin: 0; letter-spacing: .02em;
        }
        .tag { margin: 2px 0 0; color: var(--muted); font-size: .92rem; }
        .chip {
            background: #fff; border: 1px solid var(--line); color: var(--spa);
            padding: 8px 12px; border-radius: 999px; font-size: .78rem; font-weight: 500;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        @media (min-width: 960px) {
            .grid { grid-template-columns: 1.05fr .95fr; align-items: start; }
        }
        .card {
            background: var(--card);
            border: 1px solid rgba(107, 45, 92, .08);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 22px;
        }
        .card h2 {
            font-family: "Cormorant Garamond", serif;
            margin: 0 0 6px;
            font-size: 1.55rem;
        }
        .lead { color: var(--muted); margin: 0 0 18px; font-size: .92rem; }
        form .row { display: grid; gap: 12px; }
        @media (min-width: 640px) {
            form .row.two { grid-template-columns: 1fr 1fr; }
            form .row.three { grid-template-columns: 1fr 1fr 1fr; }
        }
        label { display: block; font-size: .78rem; font-weight: 600; color: var(--spa-deep); margin-bottom: 6px; letter-spacing: .04em; text-transform: uppercase; }
        input, select {
            width: 100%;
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 12px;
            padding: 11px 12px;
            font: inherit;
            color: var(--ink);
            outline: none;
            transition: border .15s, box-shadow .15s;
        }
        input:focus, select:focus {
            border-color: var(--spa-soft);
            box-shadow: 0 0 0 4px rgba(107, 45, 92, .12);
        }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        button, .btn {
            appearance: none; border: 0; cursor: pointer;
            border-radius: 12px; padding: 11px 16px;
            font: 600 .9rem Outfit, sans-serif;
            transition: transform .12s, filter .12s, background .12s;
        }
        button:hover, .btn:hover { transform: translateY(-1px); filter: brightness(1.05); }
        button:active, .btn:active { transform: translateY(0); }
        .btn-primary { background: var(--spa); color: #fff; }
        .btn-ghost { background: var(--blush); color: var(--spa); }
        .btn-ok { background: var(--ok); color: #fff; }
        .btn-warn { background: #c4892a; color: #fff; }
        .btn-info { background: var(--info); color: #fff; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-tiny { padding: 7px 10px; font-size: .75rem; border-radius: 9px; }
        .alert {
            border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; font-size: .9rem;
        }
        .alert.ok { background: var(--ok-bg); color: var(--ok); }
        .alert.err { background: var(--danger-bg); color: var(--danger); }
        .log {
            max-height: 520px; overflow: auto;
            display: flex; flex-direction: column; gap: 10px;
        }
        .event {
            border: 1px solid var(--line);
            border-left: 4px solid var(--spa);
            border-radius: 12px;
            padding: 10px 12px;
            background: #fffafd;
            animation: in .35s ease;
        }
        .event.Cliente { border-left-color: var(--info); background: #f7fbff; }
        .event.Profesional { border-left-color: var(--spa); }
        .event.Recepción { border-left-color: var(--ok); background: #f6fbf8; }
        .event header { display: flex; justify-content: space-between; gap: 8px; font-size: .75rem; color: var(--muted); }
        .event strong { color: var(--ink); font-size: .82rem; }
        .event p { margin: 6px 0 0; font-size: .84rem; line-height: 1.45; }
        @keyframes in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        .empty { color: var(--muted); font-size: .9rem; padding: 18px 8px; text-align: center; }
        .table-wrap { overflow: auto; margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; min-width: 780px; }
        th {
            text-align: left; font-size: .72rem; letter-spacing: .06em;
            text-transform: uppercase; color: var(--muted); padding: 10px 8px;
            border-bottom: 1px solid var(--line);
        }
        td { padding: 12px 8px; border-bottom: 1px solid #f1eaef; font-size: .88rem; vertical-align: middle; }
        tr:hover td { background: #fdf8fb; }
        .badge {
            display: inline-block; border-radius: 999px;
            padding: 4px 10px; font-size: .72rem; font-weight: 600; letter-spacing: .03em;
        }
        .PENDIENTE { background: var(--pend-bg); color: var(--pend); }
        .CONFIRMADA { background: var(--ok-bg); color: var(--ok); }
        .REPROGRAMADA { background: var(--warn-bg); color: var(--warn); }
        .ATENDIDA { background: var(--info-bg); color: var(--info); }
        .CANCELADA { background: var(--danger-bg); color: var(--danger); }
        .toolbar { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center; }
        footer.note {
            margin-top: 28px; color: var(--muted); font-size: .8rem; text-align: center;
        }
        .hint { font-size: .78rem; color: var(--muted); margin: 4px 0 0; }
        .estado-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: end; }
        .estado-bar .grow { flex: 1 1 140px; }
        kbd { font-family: inherit; background: var(--blush); padding: 1px 6px; border-radius: 6px; color: var(--spa); }
    </style>
</head>
<body>
<div class="wrap">
    <header class="hero">
        <div class="brand">
            <div class="logo">C</div>
            <div>
                <h1>CitasSpa</h1>
                <p class="tag">Gestión y agendamiento · patrón Observer</p>
            </div>
        </div>
        <span class="chip">Belleza · Spa · Bienestar</span>
    </header>

    <?php if ($flash): ?>
        <div class="alert <?= $flash['tipo'] === 'ok' ? 'ok' : 'err' ?>"><?= e($flash['texto']) ?></div>
    <?php endif; ?>
    <?php if ($errores): ?>
        <div class="alert err">
            <?php foreach ($errores as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <section class="card">
            <h2>Agendar cita</h2>
            <p class="lead">Registre cliente, servicio, profesional y horario. Al confirmar, el sujeto notifica a los tres observadores.</p>
            <form method="post" id="form-agendar">
                <input type="hidden" name="accion" value="agendar">
                <div class="row two">
                    <div>
                        <label for="cliente">Cliente</label>
                        <input id="cliente" name="cliente" required placeholder="Nombre completo" value="<?= e($_POST['cliente'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="telefono">Teléfono / WhatsApp</label>
                        <input id="telefono" name="telefono" required placeholder="3000000000" value="<?= e($_POST['telefono'] ?? '') ?>">
                    </div>
                </div>
                <div class="row" style="margin-top:12px">
                    <div>
                        <label for="correo">Correo</label>
                        <input id="correo" name="correo" type="email" required placeholder="correo@cliente.com" value="<?= e($_POST['correo'] ?? '') ?>">
                    </div>
                </div>
                <div class="row two" style="margin-top:12px">
                    <div>
                        <label for="servicio">Servicio</label>
                        <select id="servicio" name="servicio" required>
                            <option value="">Seleccione…</option>
                            <?php foreach ($servicios as $nombre => $meta): ?>
                                <option value="<?= e($nombre) ?>" <?= (($_POST['servicio'] ?? '') === $nombre) ? 'selected' : '' ?>>
                                    <?= e($nombre) ?> · <?= (int) $meta['duracion'] ?> min · $<?= number_format((int) $meta['costo'], 0, ',', '.') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="profesional">Profesional</label>
                        <select id="profesional" name="profesional" required>
                            <option value="">Primero elija un servicio</option>
                        </select>
                        <p class="hint">Solo se listan especialistas capacitados para el servicio.</p>
                    </div>
                </div>
                <div class="row two" style="margin-top:12px">
                    <div>
                        <label for="fecha">Fecha</label>
                        <input id="fecha" name="fecha" type="date" required min="<?= date('Y-m-d') ?>" value="<?= e($_POST['fecha'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="hora">Hora</label>
                        <select id="hora" name="hora" required>
                            <option value="">Seleccione…</option>
                            <?php foreach (franjasHorarias() as $franja): ?>
                                <option value="<?= e($franja) ?>" <?= (($_POST['hora'] ?? '') === $franja) ? 'selected' : '' ?>><?= e($franja) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="actions">
                    <button class="btn-primary" type="submit">Registrar reserva</button>
                    <button class="btn-ghost" type="reset">Limpiar</button>
                </div>
            </form>
        </section>

        <section class="card">
            <div class="toolbar">
                <div>
                    <h2>Panel Observer</h2>
                    <p class="lead">Mensajes emitidos por Cliente, Profesional y Recepción al cambiar el estado.</p>
                </div>
                <form method="post">
                    <input type="hidden" name="accion" value="limpiar_log">
                    <button class="btn-ghost btn-tiny" type="submit">Vaciar log</button>
                </form>
            </div>
            <div class="log" id="observer-log">
                <?php if (!$log): ?>
                    <p class="empty">Aún no hay notificaciones. Agende una cita o cambie un estado para ver el patrón en acción.</p>
                <?php endif; ?>
                <?php foreach ($log as $evento): ?>
                    <article class="event <?= e($evento['actor']) ?>">
                        <header>
                            <strong><?= e($evento['actor']) ?></strong>
                            <span><?= e($evento['canal']) ?> · <?= e($evento['fecha']) ?> <?= e($evento['hora']) ?></span>
                        </header>
                        <p><?= e($evento['mensaje']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <section class="card" style="margin-top:20px">
        <div class="toolbar">
            <div>
                <h2>Historial de citas</h2>
                <p class="lead">Seleccione una cita y aplique un estado. <kbd>REPROGRAMADA</kbd> pide nueva fecha y hora.</p>
            </div>
            <form method="post" onsubmit="return confirm('¿Reiniciar datos de demostración?');">
                <input type="hidden" name="accion" value="reiniciar">
                <button class="btn-ghost btn-tiny" type="submit">Reiniciar demo</button>
            </form>
        </div>

        <form method="post" class="estado-bar" style="margin: 8px 0 16px">
            <input type="hidden" name="accion" value="estado">
            <div class="grow">
                <label for="id_cita">Cita</label>
                <select id="id_cita" name="id_cita" required>
                    <option value="">Seleccione…</option>
                    <?php foreach ($citas as $c): ?>
                        <option value="<?= e($c['id']) ?>"><?= e($c['id'] . ' · ' . $c['cliente'] . ' · ' . $c['estado']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grow">
                <label for="nuevo_estado">Nuevo estado</label>
                <select id="nuevo_estado" name="nuevo_estado" required>
                    <option value="CONFIRMADA">CONFIRMADA</option>
                    <option value="REPROGRAMADA">REPROGRAMADA</option>
                    <option value="ATENDIDA">ATENDIDA</option>
                    <option value="CANCELADA">CANCELADA</option>
                </select>
            </div>
            <div class="grow" id="box-reprog" hidden>
                <label for="nueva_fecha">Nueva fecha</label>
                <input id="nueva_fecha" name="nueva_fecha" type="date" min="<?= date('Y-m-d') ?>">
            </div>
            <div class="grow" id="box-reprog-hora" hidden>
                <label for="nueva_hora">Nueva hora</label>
                <select id="nueva_hora" name="nueva_hora">
                    <option value="">Seleccione…</option>
                    <?php foreach (franjasHorarias() as $franja): ?>
                        <option value="<?= e($franja) ?>"><?= e($franja) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn-primary" type="submit">Aplicar estado</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Servicio</th>
                    <th>Profesional</th>
                    <th>Fecha / hora</th>
                    <th>Estado</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($citas as $c): ?>
                    <tr>
                        <td><strong><?= e($c['id']) ?></strong></td>
                        <td>
                            <?= e($c['cliente']) ?><br>
                            <span class="hint"><?= e($c['telefono']) ?></span>
                        </td>
                        <td><?= e($c['servicio']) ?><br><span class="hint"><?= (int) $c['duracion'] ?> min</span></td>
                        <td><?= e($c['profesional']) ?></td>
                        <td><?= e(date('d/m/Y', strtotime($c['fecha']))) ?> · <?= e($c['hora']) ?></td>
                        <td><span class="badge <?= e($c['estado']) ?>"><?= e($c['estado']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <footer class="note">
        Observer: <em>CitaObservable</em> (sujeto) + <em>ClienteObserver</em>, <em>ProfesionalObserver</em> y <em>RecepcionObserver</em>.
        Los canales WhatsApp/correo se simulan en el log, según el alcance del proyecto.
    </footer>
</div>
<script>
    const mapa = <?= $mapProfesionalesJson ?>;
    const selServicio = document.getElementById('servicio');
    const selProf = document.getElementById('profesional');
    const seleccionado = <?= json_encode($_POST['profesional'] ?? '', JSON_UNESCAPED_UNICODE) ?>;

    function pintarProfesionales() {
        const servicio = selServicio.value;
        selProf.innerHTML = '';
        const base = document.createElement('option');
        base.value = '';
        base.textContent = servicio ? 'Seleccione especialista…' : 'Primero elija un servicio';
        selProf.appendChild(base);
        if (!servicio) return;
        Object.keys(mapa).forEach((nombre) => {
            if (mapa[nombre].indexOf(servicio) === -1) return;
            const opt = document.createElement('option');
            opt.value = nombre;
            opt.textContent = nombre;
            if (nombre === seleccionado) opt.selected = true;
            selProf.appendChild(opt);
        });
    }
    selServicio.addEventListener('change', pintarProfesionales);
    pintarProfesionales();

    const estado = document.getElementById('nuevo_estado');
    const boxF = document.getElementById('box-reprog');
    const boxH = document.getElementById('box-reprog-hora');
    function toggleReprog() {
        const show = estado.value === 'REPROGRAMADA';
        boxF.hidden = !show;
        boxH.hidden = !show;
        document.getElementById('nueva_fecha').required = show;
        document.getElementById('nueva_hora').required = show;
    }
    estado.addEventListener('change', toggleReprog);
    toggleReprog();
</script>
</body>
</html>
