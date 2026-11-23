<?php
// Responsable: A  (entrega final, módulo de reportes)
// Rutas: reportes/index (HTML + Chart.js), reportes/csv (tipo=citas|servicios|dias), reportes/pdf

class ReportController {

    private const ESTADOS   = ['pendiente', 'confirmada', 'completada', 'cancelada'];
    private const MAX_DIAS  = 366;

    /** Panel de reportes con filtros, KPIs, gráficas y tablas */
    public function index() {
        requireAdmin();
        [$f, $errores] = $this->filtros();

        render('reportes/index', [
            'filtros'   => $f,
            'errores'   => $errores,
            'resumen'   => Report::resumen($f['inicio'], $f['fin']),
            'servicios' => Report::ingresosPorServicio($f['inicio'], $f['fin']),
            'porDia'    => Report::porDia($f['inicio'], $f['fin']),
            'citas'     => Report::citas($f['inicio'], $f['fin'], $f['estado']),
        ]);
    }

    /** Descarga CSV (separador ';' y BOM UTF-8 para que Excel en español lo abra bien) */
    public function csv() {
        requireAdmin();
        [$f] = $this->filtros();
        $tipo = $_GET['tipo'] ?? 'citas';

        switch ($tipo) {
            case 'servicios':
                $cab   = ['ID', 'Servicio', 'Reservas', 'Realizados', 'Ingresos realizados', 'Por cobrar'];
                $filas = array_map(fn($s) => [$s['id'], $s['nombre'], $s['reservas'], $s['realizados'],
                                              $s['ingresos_realizados'], $s['ingresos_por_cobrar']],
                                   Report::ingresosPorServicio($f['inicio'], $f['fin']));
                break;
            case 'dias':
                $cab   = ['Fecha', 'Citas', 'Ingresos realizados'];
                $filas = array_map(fn($d) => [$d['fecha'], $d['citas'], $d['ingresos']], Report::porDia($f['inicio'], $f['fin']));
                break;
            case 'citas':
                $cab   = ['ID', 'Fecha', 'Hora', 'Cliente', 'Email', 'Servicios', 'Duración (min)', 'Estado', 'Total'];
                $filas = array_map(fn($c) => [$c['id'], $c['fecha'], $c['hora'], $c['cliente'], $c['email'],
                                              $c['servicios'], $c['duracion'], $c['estado'], $c['total']],
                                   Report::citas($f['inicio'], $f['fin'], $f['estado']));
                break;
            default:
                http_response_code(400);
                exit('Tipo de reporte inválido');
        }

        $nombre = sprintf('appsalon_%s_%s_a_%s.csv', $tipo, $f['inicio'], $f['fin']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $cab, ';');
        foreach ($filas as $fila) {
            fputcsv($out, array_map([self::class, 'celdaSegura'], $fila), ';');
        }
        fclose($out);
        exit;
    }

    /** Reporte PDF con TCPDF (requiere `composer install`) */
    public function pdf() {
        requireAdmin();
        [$f] = $this->filtros();

        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload)) {
            setAlerta('danger', 'Falta TCPDF: ejecuta "composer install" en la raíz del proyecto.');
            redirect('reportes/index&' . http_build_query(['inicio' => $f['inicio'], 'fin' => $f['fin']]));
        }
        require_once $autoload;

        $resumen   = Report::resumen($f['inicio'], $f['fin']);
        $servicios = Report::ingresosPorServicio($f['inicio'], $f['fin']);
        $citas     = Report::citas($f['inicio'], $f['fin'], $f['estado']);

        $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->SetCreator('AppSalon');
        $pdf->SetAuthor('AppSalon');
        $pdf->SetTitle('Reporte AppSalon ' . $f['inicio'] . ' a ' . $f['fin']);
        $pdf->setPrintHeader(false);
        $pdf->setFooterData([0, 0, 0], [200, 200, 200]);
        $pdf->setFooterFont(['helvetica', '', 8]);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->AddPage();

        // Las vistas usan e() en todos los datos: TCPDF interpreta HTML, así que también se escapa aquí
        ob_start();
        include __DIR__ . '/../views/reportes/pdf.php';
        $html = ob_get_clean();

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output(sprintf('appsalon_reporte_%s_a_%s.pdf', $f['inicio'], $f['fin']), 'D');
        exit;
    }

    /**
     * Lee y valida los filtros GET. Por defecto: el mes en curso.
     * @return array{0: array{inicio:string, fin:string, estado:?string}, 1: array<string,string>}
     */
    private function filtros(): array {
        $errores = [];
        $hoy = new DateTimeImmutable('today');
        $f = [
            'inicio' => $hoy->modify('first day of this month')->format('Y-m-d'),
            'fin'    => $hoy->modify('last day of this month')->format('Y-m-d'),
            'estado' => null,
        ];

        foreach (['inicio', 'fin'] as $k) {
            if (!isset($_GET[$k]) || $_GET[$k] === '') { continue; }
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', (string)$_GET[$k]);
            if ($d && $d->format('Y-m-d') === $_GET[$k]) {
                $f[$k] = $_GET[$k];
            } else {
                $errores[$k] = 'Fecha inválida, se usó el valor por defecto.';
            }
        }
        if ($f['inicio'] > $f['fin']) {
            [$f['inicio'], $f['fin']] = [$f['fin'], $f['inicio']];
            $errores['rango'] = 'La fecha inicial era posterior a la final; se intercambiaron.';
        }
        $dias = (new DateTimeImmutable($f['inicio']))->diff(new DateTimeImmutable($f['fin']))->days;
        if ($dias > self::MAX_DIAS) {
            $f['fin'] = (new DateTimeImmutable($f['inicio']))->modify('+' . self::MAX_DIAS . ' days')->format('Y-m-d');
            $errores['rango'] = 'El período máximo es de ' . self::MAX_DIAS . ' días; se recortó la fecha final.';
        }

        $estado = $_GET['estado'] ?? '';
        if (in_array($estado, self::ESTADOS, true)) { $f['estado'] = $estado; }

        return [$f, $errores];
    }

    /** Neutraliza la inyección de fórmulas en CSV (OWASP): =, +, -, @, tab y CR al inicio de una celda de texto */
    private static function celdaSegura($v) {
        if (is_string($v) && $v !== '' && !is_numeric($v) && strpbrk($v[0], "=+-@\t\r") !== false) {
            return "'" . $v;
        }
        return $v;
    }
}
