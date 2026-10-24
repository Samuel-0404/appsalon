<?php
// Responsable: B
class ServiceController {
    public function index() {
        // Público: listado de servicios
        render('services/index', ['servicios' => []]); // TODO: Service::todos()
    }
    public function admin()  { requireAdmin(); render('services/admin/lista', ['servicios' => []]); }
    public function crear()  { requireAdmin(); /* TODO: GET form | POST validar + guardar */ render('services/admin/crear'); }
    public function editar() { requireAdmin(); /* TODO */ render('services/admin/editar'); }
    public function eliminar(){ requireAdmin(); /* TODO: solo POST + validarCsrf() */ }
}
