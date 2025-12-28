<?php
// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'config.php';

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    $current_url = 'datos_personales_html.php';
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <script>
            localStorage.setItem('redirect_after_login', '$current_url');
            window.location.href = 'login.php';
        </script>
    </head>
    <body></body>
    </html>";
    exit();
}

// Manejar validación AJAX
if (isset($_GET['validar'])) {
    header('Content-Type: application/json');
    $tipo = $_GET['tipo'] ?? '';
    $valor = $_GET['valor'] ?? '';
    $usuario_id = $_SESSION['usuario_id'];
    
    $existe = false;
    
    if ($tipo === 'dni') {
        $stmt = $conn->prepare("SELECT id FROM accounts WHERE dni = ? AND id != ?");
        $stmt->bind_param("si", $valor, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existe = $result->num_rows > 0;
    } elseif ($tipo === 'phone') {
        $stmt = $conn->prepare("SELECT id FROM accounts WHERE CONCAT(area_code, phone) = ? AND id != ?");
        $stmt->bind_param("si", $valor, $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existe = $result->num_rows > 0;
    }
    
    echo json_encode(['existe' => $existe]);
    exit();
}

// Incluir el archivo PHP que contiene la lógica después de verificar la sesión
include('datos_personales.php');


?>

<?php
// Asegurar que exista el array de errores (para evitar warnings)
$errores = $errores ?? [];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos Personales - Send4Less</title>
    <link rel="stylesheet" href="../css/datos_personales.css">
    <link rel="stylesheet" href="css/header.css">
    <link rel="stylesheet" href="css/footer.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">

    <link rel="icon" href="Imagenes/globo5.png" type="image/png">
    
    <style>
        .header-section {
            text-align: center;
            margin: 30px 0 40px 0;
            padding-bottom: 30px;
            border-bottom: 3px solid #42ba25;
        }

        .header-section h1 {
            color: #2c3e50;
            font-size: 2.8rem;
            font-weight: 300;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .header-section p {
            color: #6c757d;
            font-size: 1.1rem;
            font-weight: 400;
            margin: 0;
        }

        @media (max-width: 768px) {
            .header-section {
                margin: 20px 0;
                padding-bottom: 20px;
            }
            
            .header-section h1 {
                font-size: 2rem;
            }
            
            .header-section p {
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .header-section h1 {
                font-size: 1.8rem;
            }
        }
        
/* Contenedor general del campo */
.info-item {
  margin-bottom: 22px;
  position: relative;
}

/* Mensaje de error arriba del campo */
.error {
  background: rgba(255, 99, 71, 0.1);   /* rojo muy suave */
  border-left: 4px solid #e74c3c;        /* barra roja elegante */
  color: #b30000;                        /* texto rojo oscuro */
  font-size: 0.9em;
  font-weight: 500;
  padding: 6px 10px;
  border-radius: 5px;
  margin-bottom: 6px;
  transition: all 0.3s ease;
  animation: fadeIn 0.3s ease-in-out;
}

/* Animación de aparición */
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Campo con error resaltado */
.input-error {
  border: 1.5px solid #e74c3c !important;
  background-color: #fff5f5;
  transition: all 0.3s ease;
}

.input-error:focus {
  box-shadow: 0 0 6px rgba(231, 76, 60, 0.3);
}


    </style>
</head>
<body>
    <?php include 'shop-header.php'; ?>

    <div class="header-section">
        <h1>Datos Personales</h1>
        <p>Gestiona tu información personal y mantén tu perfil actualizado</p>
    </div>

    <?php if (isset($_SESSION['mensaje'])): ?>
        <div class="mensaje <?php echo $_SESSION['tipo_mensaje'] === 'exito' ? 'exito' : 'error'; ?>">
            <?php 
            echo $_SESSION['mensaje'];
            unset($_SESSION['mensaje']);
            unset($_SESSION['tipo_mensaje']);
            ?>
        </div>
    <?php endif; ?>
    
    <h1 class="user-title"><?php echo htmlspecialchars($usuario['full_name']); ?></h1>

    <div class="main-container">
        <div class="left-column">
            <h2>Información Personal</h2>

            <?php if (!empty($mensaje)): ?>
                <div class="mensaje <?php echo $datosGuardados ? 'exito' : 'error'; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
             
                <div class="info-item">
                    <label for="username">Nombre de Usuario:</label>
                   <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($usuario['username']); ?>" readonly aria-readonly="true" required>
                </div>
             
                <div class="info-item">
                    <label for="email">Correo Electrónico:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" readonly>
                </div>
                
                <div class="info-item">
    <?php if (!empty($errores['dni'])): ?>
        <p class="error"><?php echo $errores['dni']; ?></p>
    <?php endif; ?>
    <label for="dni">DNI/NIE:</label>
    <input type="text" id="dni" name="dni"
           value="<?php echo htmlspecialchars($_POST['dni'] ?? $usuario['dni']); ?>"
           <?php echo $datosGuardados ? 'readonly' : ''; ?>
           required>
    <span id="dni-error" style="color: red; font-size: 0.9rem; display: none;">Este DNI ya está registrado</span>
</div>


                
                <div class="info-item">
                    <label for="countryCode">Código de Área:</label>
                    <select id="countryCode" name="country_code" required>
                        <option value="93" <?php echo $usuario['area_code'] == '93' ? 'selected' : ''; ?>>Afganistán (+93)</option>
                        <option value="49" <?php echo $usuario['area_code'] == '49' ? 'selected' : ''; ?>>Alemania (+49)</option>
                        <option value="966" <?php echo $usuario['area_code'] == '966' ? 'selected' : ''; ?>>Arabia Saudita (+966)</option>
                        <option value="213" <?php echo $usuario['area_code'] == '213' ? 'selected' : ''; ?>>Argelia (+213)</option>
                        <option value="376" <?php echo $usuario['area_code'] == '376' ? 'selected' : ''; ?>>Andorra (+376)</option>
                        <option value="244" <?php echo $usuario['area_code'] == '244' ? 'selected' : ''; ?>>Angola (+244)</option>
                        <option value="1264" <?php echo $usuario['area_code'] == '1264' ? 'selected' : ''; ?>>Anguila (+1264)</option>
                        <option value="1268" <?php echo $usuario['area_code'] == '1268' ? 'selected' : ''; ?>>Antigua y Barbuda (+1268)</option>
                        <option value="54" <?php echo $usuario['area_code'] == '54' ? 'selected' : ''; ?>>Argentina (+54)</option>
                        <option value="374" <?php echo $usuario['area_code'] == '374' ? 'selected' : ''; ?>>Armenia (+374)</option>
                        <option value="297" <?php echo $usuario['area_code'] == '297' ? 'selected' : ''; ?>>Aruba (+297)</option>
                        <option value="61" <?php echo $usuario['area_code'] == '61' ? 'selected' : ''; ?>>Australia (+61)</option>
                        <option value="43" <?php echo $usuario['area_code'] == '43' ? 'selected' : ''; ?>>Austria (+43)</option>
                        <option value="994" <?php echo $usuario['area_code'] == '994' ? 'selected' : ''; ?>>Azerbaiyán (+994)</option>
                        <option value="973" <?php echo $usuario['area_code'] == '973' ? 'selected' : ''; ?>>Baréin (+973)</option>
                        <option value="880" <?php echo $usuario['area_code'] == '880' ? 'selected' : ''; ?>>Bangladesh (+880)</option>
                        <option value="1246" <?php echo $usuario['area_code'] == '1246' ? 'selected' : ''; ?>>Barbados (+1246)</option>
                        <option value="32" <?php echo $usuario['area_code'] == '32' ? 'selected' : ''; ?>>Bélgica (+32)</option>
                        <option value="501" <?php echo $usuario['area_code'] == '501' ? 'selected' : ''; ?>>Belice (+501)</option>
                        <option value="229" <?php echo $usuario['area_code'] == '229' ? 'selected' : ''; ?>>Benín (+229)</option>
                        <option value="1441" <?php echo $usuario['area_code'] == '1441' ? 'selected' : ''; ?>>Bermudas (+1441)</option>
                        <option value="591" <?php echo $usuario['area_code'] == '591' ? 'selected' : ''; ?>>Bolivia (+591)</option>
                        <option value="387" <?php echo $usuario['area_code'] == '387' ? 'selected' : ''; ?>>Bosnia y Herzegovina (+387)</option>
                        <option value="267" <?php echo $usuario['area_code'] == '267' ? 'selected' : ''; ?>>Botsuana (+267)</option>
                        <option value="55" <?php echo $usuario['area_code'] == '55' ? 'selected' : ''; ?>>Brasil (+55)</option>
                        <option value="246" <?php echo $usuario['area_code'] == '246' ? 'selected' : ''; ?>>Territorio Británico del Océano Índico (+246)</option>
                        <option value="673" <?php echo $usuario['area_code'] == '673' ? 'selected' : ''; ?>>Brunéi (+673)</option>
                        <option value="359" <?php echo $usuario['area_code'] == '359' ? 'selected' : ''; ?>>Bulgaria (+359)</option>
                        <option value="226" <?php echo $usuario['area_code'] == '226' ? 'selected' : ''; ?>>Burkina Faso (+226)</option>
                        <option value="257" <?php echo $usuario['area_code'] == '257' ? 'selected' : ''; ?>>Burundi (+257)</option>
                        <option value="975" <?php echo $usuario['area_code'] == '975' ? 'selected' : ''; ?>>Bután (+975)</option>
                        <option value="238" <?php echo $usuario['area_code'] == '238' ? 'selected' : ''; ?>>Cabo Verde (+238)</option>
                        <option value="855" <?php echo $usuario['area_code'] == '855' ? 'selected' : ''; ?>>Camboya (+855)</option>
                        <option value="237" <?php echo $usuario['area_code'] == '237' ? 'selected' : ''; ?>>Camerún (+237)</option>
                        <option value="1" <?php echo $usuario['area_code'] == '1' ? 'selected' : ''; ?>>Canadá (+1)</option>
                        <option value="235" <?php echo $usuario['area_code'] == '235' ? 'selected' : ''; ?>>Chad (+235)</option>
                        <option value="56" <?php echo $usuario['area_code'] == '56' ? 'selected' : ''; ?>>Chile (+56)</option>
                        <option value="86" <?php echo $usuario['area_code'] == '86' ? 'selected' : ''; ?>>China (+86)</option>
                        <option value="57" <?php echo $usuario['area_code'] == '57' ? 'selected' : ''; ?>>Colombia (+57)</option>
                        <option value="506" <?php echo $usuario['area_code'] == '506' ? 'selected' : ''; ?>>Costa Rica (+506)</option>
                        <option value="385" <?php echo $usuario['area_code'] == '385' ? 'selected' : ''; ?>>Croacia (+385)</option>
                        <option value="53" <?php echo $usuario['area_code'] == '53' ? 'selected' : ''; ?>>Cuba (+53)</option>
                        <option value="357" <?php echo $usuario['area_code'] == '357' ? 'selected' : ''; ?>>Chipre (+357)</option>
                        <option value="45" <?php echo $usuario['area_code'] == '45' ? 'selected' : ''; ?>>Dinamarca (+45)</option>
                        <option value="593" <?php echo $usuario['area_code'] == '593' ? 'selected' : ''; ?>>Ecuador (+593)</option>
                        <option value="20" <?php echo $usuario['area_code'] == '20' ? 'selected' : ''; ?>>Egipto (+20)</option>
                        <option value="503" <?php echo $usuario['area_code'] == '503' ? 'selected' : ''; ?>>El Salvador (+503)</option>
                        <option value="34" <?php echo $usuario['area_code'] == '34' ? 'selected' : ''; ?>>España (+34)</option>
                        <option value="1" <?php echo $usuario['area_code'] == '1' ? 'selected' : ''; ?>>Estados Unidos (+1)</option>
                        <option value="33" <?php echo $usuario['area_code'] == '33' ? 'selected' : ''; ?>>Francia (+33)</option>
                        <option value="30" <?php echo $usuario['area_code'] == '30' ? 'selected' : ''; ?>>Grecia (+30)</option>
                        <option value="502" <?php echo $usuario['area_code'] == '502' ? 'selected' : ''; ?>>Guatemala (+502)</option>
                        <option value="504" <?php echo $usuario['area_code'] == '504' ? 'selected' : ''; ?>>Honduras (+504)</option>
                        <option value="852" <?php echo $usuario['area_code'] == '852' ? 'selected' : ''; ?>>Hong Kong (+852)</option>
                        <option value="36" <?php echo $usuario['area_code'] == '36' ? 'selected' : ''; ?>>Hungría (+36)</option>
                        <option value="91" <?php echo $usuario['area_code'] == '91' ? 'selected' : ''; ?>>India (+91)</option>
                        <option value="62" <?php echo $usuario['area_code'] == '62' ? 'selected' : ''; ?>>Indonesia (+62)</option>
                        <option value="353" <?php echo $usuario['area_code'] == '353' ? 'selected' : ''; ?>>Irlanda (+353)</option>
                        <option value="972" <?php echo $usuario['area_code'] == '972' ? 'selected' : ''; ?>>Israel (+972)</option>
                        <option value="39" <?php echo $usuario['area_code'] == '39' ? 'selected' : ''; ?>>Italia (+39)</option>
                        <option value="81" <?php echo $usuario['area_code'] == '81' ? 'selected' : ''; ?>>Japón (+81)</option>
                        <option value="52" <?php echo $usuario['area_code'] == '52' ? 'selected' : ''; ?>>México (+52)</option>
                        <option value="31" <?php echo $usuario['area_code'] == '31' ? 'selected' : ''; ?>>Países Bajos (+31)</option>
                        <option value="507" <?php echo $usuario['area_code'] == '507' ? 'selected' : ''; ?>>Panamá (+507)</option>
                        <option value="595" <?php echo $usuario['area_code'] == '595' ? 'selected' : ''; ?>>Paraguay (+595)</option>
                        <option value="51" <?php echo $usuario['area_code'] == '51' ? 'selected' : ''; ?>>Perú (+51)</option>
                        <option value="48" <?php echo $usuario['area_code'] == '48' ? 'selected' : ''; ?>>Polonia (+48)</option>
                        <option value="351" <?php echo $usuario['area_code'] == '351' ? 'selected' : ''; ?>>Portugal (+351)</option>
                        <option value="44" <?php echo $usuario['area_code'] == '44' ? 'selected' : ''; ?>>Reino Unido (+44)</option>
                        <option value="7" <?php echo $usuario['area_code'] == '7' ? 'selected' : ''; ?>>Rusia (+7)</option>
                        <option value="46" <?php echo $usuario['area_code'] == '46' ? 'selected' : ''; ?>>Suecia (+46)</option>
                        <option value="41" <?php echo $usuario['area_code'] == '41' ? 'selected' : ''; ?>>Suiza (+41)</option>
                        <option value="90" <?php echo $usuario['area_code'] == '90' ? 'selected' : ''; ?>>Turquía (+90)</option>
                        <option value="380" <?php echo $usuario['area_code'] == '380' ? 'selected' : ''; ?>>Ucrania (+380)</option>
                        <option value="598" <?php echo $usuario['area_code'] == '598' ? 'selected' : ''; ?>>Uruguay (+598)</option>
                        <option value="58" <?php echo $usuario['area_code'] == '58' ? 'selected' : ''; ?>>Venezuela (+58)</option>
                    </select>
                </div>
                
                <div class="info-item">
    <?php if (!empty($errores['phone'])): ?>
        <p class="error"><?php echo $errores['phone']; ?></p>
    <?php endif; ?>
    <label for="phone">Número de Teléfono:</label>
    <input type="tel" id="phone" name="phone"
           value="<?php echo htmlspecialchars($_POST['phone'] ?? $usuario['phone']); ?>"
           inputmode="tel"
           required>
    <span id="phone-error" style="color: red; font-size: 0.9rem; display: none;">Este teléfono ya está registrado</span>
</div>

                
                <div class="info-item">
    <?php if (!empty($errores['nueva_direccion'])): ?>
        <p class="error"><?php echo $errores['nueva_direccion']; ?></p>
    <?php endif; ?>
    <label for="nueva_direccion">Dirección:</label>
    <input type="text" id="nueva_direccion" name="nueva_direccion"
           value="<?php echo htmlspecialchars($_POST['nueva_direccion'] ?? $usuario['direccion']); ?>"
           inputmode="text"
           title="La dirección debe tener entre 5 y 100 caracteres (letras, números, espacios, puntos, comas, # y guiones)"
           required>
</div>

                
                <div class="info-item">
    <?php if (!empty($errores['nuevo_cp'])): ?>
        <p class="error"><?php echo $errores['nuevo_cp']; ?></p>
    <?php endif; ?>
    <label for="nuevo_cp">Código Postal: 
        <span style="color: #6c757d; font-size: 0.8rem; font-weight: 400;">(Opcional)</span>
    </label>
    <input type="text" id="nuevo_cp" name="nuevo_cp"
           value="<?php echo htmlspecialchars($_POST['nuevo_cp'] ?? $usuario['cp']); ?>"
           placeholder="Ej: 28001 (opcional)"
           inputmode="numeric">
</div>


                
               <div class="info-item">
    <?php if (!empty($errores['nueva_provincia'])): ?>
        <p class="error"><?php echo $errores['nueva_provincia']; ?></p>
    <?php endif; ?>
    <label for="nueva_provincia">Ciudad, región:</label>
    <input type="text" id="nueva_provincia" name="nueva_provincia"
           value="<?php echo htmlspecialchars($_POST['nueva_provincia'] ?? $usuario['provincia']); ?>"
           inputmode="text"
           required>
</div>

                
               <div class="info-item">
    <?php if (!empty($errores['nuevo_pais'])): ?>
        <p class="error"><?php echo $errores['nuevo_pais']; ?></p>
    <?php endif; ?>
    <label for="nuevo_pais">País:</label>
    <input type="text" id="nuevo_pais" name="nuevo_pais"
           value="<?php echo htmlspecialchars($_POST['nuevo_pais'] ?? $usuario['pais']); ?>"
           inputmode="text"
           required>
</div>


                
                <button id="guardar" type="submit" name="guardar_datos">Guardar</button>
            </form>
        </div>

        <div class="right-column">
            <div class="info-box">
                <div class="icon-container">
                    <img src="../Imagenes/DATA_DIS.svg" alt="Guardar Datos" class="grid-icon" />
                </div>
                <div class="info-text">
                    <h3>Completa tus datos personales</h3>
                    <p>Una vez llenados y guardados no podrá editar el nombre, DNI ni correo electrónico, para dar seguridad y confianza.</p>
                </div>
            </div>

            <div class="info-box">
                <div class="icon-container">
                    <img src="../Imagenes/verified-user-focused-blue-check.svg" alt="Verificar identidad" class="grid-icon" />
                </div>
                <div class="info-text">
                    <h3>Verificación de identidad</h3>
                    <p>Los usuarios verificados transmiten mayor confianza, ¡verifique su identidad!</p>
                    <p><a href="verificacion_identidad.php" class="verify-link">Presione aquí.</a></p>
                </div>
            </div>

            <div class="info-box">
                <div class="icon-container">
                    <img src="../Imagenes/EYE.svg" alt="Verificar identidad" class="grid-icon" />
                </div>
                <div class="info-text">
                    <h3>¿Qué datos se van a mostrar?</h3>
                    <p>Los datos visibles para los usuarios de la página serán sólo el nombre de usuario, no se compartirá ni se hará público ningún dato personal.</p>
                </div>
            </div>
        </div>
    </div>
    <?php include 'mobile-bottom-nav.php'; ?>

    <?php include 'footer.php'; ?>

    <script>
        <?php if (!$datosGuardados): ?>
        // Validación en tiempo real del DNI
        document.getElementById('dni').addEventListener('blur', function() {
            const dni = this.value.trim();
            const errorSpan = document.getElementById('dni-error');
            const submitBtn = document.getElementById('guardar');
            
            if (dni && dni !== '<?php echo htmlspecialchars($usuario['dni']); ?>') {
                fetch('?validar=1&tipo=dni&valor=' + encodeURIComponent(dni))
                    .then(response => response.json())
                    .then(data => {
                        if (data.existe) {
                            errorSpan.style.display = 'block';
                            this.style.borderColor = 'red';
                            submitBtn.disabled = true;
                            submitBtn.style.opacity = '0.5';
                            submitBtn.style.cursor = 'not-allowed';
                        } else {
                            errorSpan.style.display = 'none';
                            this.style.borderColor = '';
                            submitBtn.disabled = false;
                            submitBtn.style.opacity = '1';
                            submitBtn.style.cursor = 'pointer';
                        }
                    })
                    .catch(error => console.error('Error'));
            }
        });

        // Validación en tiempo real del teléfono
        document.getElementById('phone').addEventListener('blur', function() {
            validatePhone();
        });

        document.getElementById('countryCode').addEventListener('change', function() {
            if (document.getElementById('phone').value.trim()) {
                validatePhone();
            }
        });

        function validatePhone() {
            const phone = document.getElementById('phone').value.trim();
            const countryCode = document.getElementById('countryCode').value;
            const errorSpan = document.getElementById('phone-error');
            const submitBtn = document.getElementById('guardar');
            const originalPhone = '<?php echo htmlspecialchars($usuario['area_code'] . $usuario['phone']); ?>';
            
            if (phone && countryCode) {
                const phoneCompleto = countryCode + phone;
                
                if (phoneCompleto !== originalPhone) {
                    fetch('?validar=1&tipo=phone&valor=' + encodeURIComponent(phoneCompleto))
                        .then(response => response.json())
                        .then(data => {
                            if (data.existe) {
                                errorSpan.style.display = 'block';
                                document.getElementById('phone').style.borderColor = 'red';
                                submitBtn.disabled = true;
                                submitBtn.style.opacity = '0.5';
                                submitBtn.style.cursor = 'not-allowed';
                            } else {
                                errorSpan.style.display = 'none';
                                document.getElementById('phone').style.borderColor = '';
                                submitBtn.disabled = false;
                                submitBtn.style.opacity = '1';
                                submitBtn.style.cursor = 'pointer';
                            }
                        })
                        .catch(error => console.error('Error'));
                }
            }
        }
        <?php endif; ?>
    </script>
</body>
</html>