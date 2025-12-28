<?php
// datos_personales.php

session_start();
include 'config.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$mensaje = '';

// Obtener datos actuales del usuario
try {
    $stmt = $conexion->prepare("SELECT * FROM accounts WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new Exception("Usuario no encontrado.");
    }

    // Verificar si los datos ya fueron completados
    $datosGuardados = ($usuario['datos_completados'] == '1');
} catch (Exception $e) {
    die("Error al obtener datos: " . $e->getMessage());
}

// Manejar envío del formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['guardar_datos'])) {
    $country_code = trim($_POST['country_code']);
    $phone        = trim($_POST['phone']);
    $direccion    = trim($_POST['nueva_direccion']);
    $cp           = trim($_POST['nuevo_cp']); // Ya no obligatorio
    $provincia    = trim($_POST['nueva_provincia']);
    $pais         = trim($_POST['nuevo_pais']);
    
    // Solo procesar username y dni si no están guardados
    $username = $datosGuardados ? $usuario['username'] : trim($_POST['username']);
    $dni      = $datosGuardados ? $usuario['dni'] : trim($_POST['dni']);

 $errores = [];

if (!$datosGuardados) {
    if (empty($username)) {
        $errores['username'] = "El nombre de usuario es obligatorio.";
    } elseif (!preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username)) {
        $errores['username'] = "El nombre de usuario debe tener entre 3 y 20 caracteres y solo puede contener letras, números, guiones y guiones bajos.";
    }
}

if (!$datosGuardados) {
    if (empty($dni)) {
        $errores['dni'] = "El DNI es obligatorio.";
    } else {
        $stmt = $conexion->prepare("SELECT id FROM accounts WHERE dni = :dni AND id != :id");
        $stmt->bindParam(':dni', $dni);
        $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetch()) {
            $errores['dni'] = "El DNI ya está registrado.";
        }
    }
}

if (empty($country_code)) {
    $errores['country_code'] = "El código de país es obligatorio.";
} elseif (!preg_match('/^\d{1,4}$/', $country_code)) {
    $errores['country_code'] = "El código de país debe contener solo números y tener entre 1 y 4 dígitos.";
}

if (empty($phone)) {
    $errores['phone'] = "El número de teléfono es obligatorio.";
} elseif (!preg_match('/^\d{7,15}$/', $phone)) {
    $errores['phone'] = "El número de teléfono debe contener solo números y tener entre 7 y 15 dígitos.";
}

if (empty($direccion)) {
    $errores['nueva_direccion'] = "La dirección es obligatoria.";
} elseif (!preg_match('/^[a-zA-Z0-9\s.,#-]{5,100}$/', $direccion)) {
    $errores['nueva_direccion'] = "La dirección debe tener entre 5 y 100 caracteres y solo puede contener letras, números, espacios y los caracteres ., #-";
}

if (!empty($cp) && !preg_match('/^\d{4,10}$/', $cp)) {
    $errores['nuevo_cp'] = "El código postal debe contener solo números y tener entre 4 y 10 dígitos.";
}

if (empty($provincia)) {
    $errores['nueva_provincia'] = "La provincia es obligatoria.";
} elseif (!preg_match('/^[a-zA-Z\s]{2,50}$/', $provincia)) {
    $errores['nueva_provincia'] = "La provincia debe contener solo letras y tener entre 2 y 50 caracteres.";
}

if (empty($pais)) {
    $errores['nuevo_pais'] = "El país es obligatorio.";
}


// 👉 A partir de aquí añadimos el bloque final
if (empty($errores)) {
    try {
        $conexion->beginTransaction();

        $sql = "UPDATE accounts SET 
                    username = :username,
                    dni = :dni,
                    area_code = :country_code,
                    phone = :phone,
                    direccion = :direccion,
                    cp = :cp,
                    provincia = :provincia,
                    pais = :pais,
                    datos_completados = 1 
                WHERE id = :id";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([
            ':username'     => $username,
            ':dni'          => $dni,
            ':country_code' => $country_code,
            ':phone'        => $phone,
            ':direccion'    => $direccion,
            ':cp'           => $cp,
            ':provincia'    => $provincia,
            ':pais'         => $pais,
            ':id'           => $usuario_id
        ]);

        $conexion->commit();

        $_SESSION['mensaje'] = "Datos guardados exitosamente ✓";
        $_SESSION['tipo_mensaje'] = "exito";
        header("Location: shop_datos_personales_html.php");
        exit();
    } catch (Exception $e) {
        $conexion->rollBack();
        $_SESSION['mensaje'] = "Error: " . $e->getMessage();
        $_SESSION['tipo_mensaje'] = "error";
        header("Location: shop_datos_personales_html.php");
        exit();
    }
} else {
    // 🔴 Si hay errores, mostramos los mensajes sin recargar la página
    $mensaje = "Por favor corrige los errores indicados.";
    $datosGuardados = false;
}
 
}

// Obtener los datos actuales del usuario (esto se realiza después del POST para reflejar los cambios)
try {
    $stmt = $conexion->prepare("SELECT * FROM accounts WHERE id = :id");
    $stmt->bindParam(':id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        throw new Exception("Usuario no encontrado.");
    }
} catch (Exception $e) {
    die("Error al obtener los datos del usuario: " . $e->getMessage());
}
?>