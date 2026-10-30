<?php
// Responsable: B

class ServiceController {

    /**
     * Publico: Estado de servicio
     */
    public function index() {
        $servicios = Service::todos();
        render('services/index', ['servicios' => $servicios]);
    }

    /**
     * Vista de administración: listado con botones de crear, editar y eliminar
     */
    public function admin() {
        requireAdmin();
        $servicios = Service::todos();
        render('services/admin/lista', ['servicios' => $servicios]);
    }

    /**
     * Crear un nuevo servicio
     */
    public function crear() {
        requireAdmin();

        $errores = [];
        $servicio = [
            'nombre'      => '',
            'precio'      => '',
            'descripcion' => '',
            'duracion'    => 60
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $servicio['nombre']      = trim($_POST['nombre'] ?? '');
            $servicio['precio']      = trim($_POST['precio'] ?? '');
            $servicio['descripcion'] = trim($_POST['descripcion'] ?? '');
            $servicio['duracion']    = trim($_POST['duracion'] ?? 60);

            // Validaciones del servidor
            if (empty($servicio['nombre'])) {
                $errores['nombre'] = 'El nombre del servicio es obligatorio.';
            }

            if (!is_numeric($servicio['precio']) || (float)$servicio['precio'] <= 0) {
                $errores['precio'] = 'El precio debe ser un número válido mayor a 0.';
            }

            if (empty($errores)) {
                $resultado = Service::crear($servicio);
                if ($resultado) {
                    setAlerta('success', 'Servicio creado exitosamente.');
                    redirect('servicios/admin');
                } else {
                    setAlerta('danger', 'Hubo un error al guardar el servicio en la base de datos.');
                }
            }
        }

        render('services/admin/crear', [
            'servicio' => $servicio,
            'errores'  => $errores
        ]);
    }

    /**
     * Editar un servicio existente
     */
    public function editar() {
        requireAdmin();

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            setAlerta('danger', 'Identificador de servicio no válido.');
            redirect('servicios/admin');
        }

        $servicio = Service::buscar($id);
        if (!$servicio) {
            setAlerta('danger', 'El servicio solicitado no existe.');
            redirect('servicios/admin');
        }

        $errores = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $servicio['nombre']      = trim($_POST['nombre'] ?? '');
            $servicio['precio']      = trim($_POST['precio'] ?? '');
            $servicio['descripcion'] = trim($_POST['descripcion'] ?? '');
            $servicio['duracion']    = trim($_POST['duracion'] ?? 60);

            // Validaciones del servidor
            if (empty($servicio['nombre'])) {
                $errores['nombre'] = 'El nombre del servicio es obligatorio.';
            }

            if (!is_numeric($servicio['precio']) || (float)$servicio['precio'] <= 0) {
                $errores['precio'] = 'El precio debe ser un número válido mayor a 0.';
            }

            if (empty($errores)) {
                $resultado = Service::actualizar($id, $servicio);
                if ($resultado) {
                    setAlerta('success', 'Servicio actualizado correctamente.');
                    redirect('servicios/admin');
                } else {
                    setAlerta('danger', 'Error al actualizar el servicio.');
                }
            }
        }

        render('services/admin/editar', [
            'servicio' => $servicio,
            'errores'  => $errores
        ]);
    }

    /**
     * Eliminar un servicio
     */
    public function eliminar() {
        requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            validarCsrf();

            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id) {
                $resultado = Service::eliminar($id);
                if ($resultado) {
                    setAlerta('success', 'Servicio eliminado correctamente.');
                } else {
                    setAlerta('danger', 'No se pudo eliminar el servicio.');
                }
            } else {
                setAlerta('danger', 'ID inválido para eliminar.');
            }
        }

        redirect('servicios/admin');
    }
}

