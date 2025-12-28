/**
 * shop-verificacion-qr-producto.js
 * Manejo de escaneo QR y actualización de estados de entrega para productos
 */

let currentScanner = null;

/**
 * Abrir escáner de QR
 */
function openQRScanner(deliveryId, expectedQrId) {
    Swal.fire({
        title: 'Escanear Código QR',
        html: `
            <div id="qr-reader-modal" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
            <div style="margin-top: 20px;">
                <button id="btn-start-camera" class="swal2-confirm swal2-styled" style="margin: 5px;">
                    <i class="fas fa-camera"></i> Activar Cámara
                </button>
                <button id="btn-select-file" class="swal2-confirm swal2-styled" style="margin: 5px; background-color: #6c757d;">
                    <i class="fas fa-image"></i> Seleccionar Imagen
                </button>
            </div>
            <input type="file" id="qr-file-input" accept="image/*" style="display: none;">
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Cerrar',
        width: '600px',
        didOpen: () => {
            // Botón de cámara
            document.getElementById('btn-start-camera').addEventListener('click', () => {
                startCameraScanner(deliveryId, expectedQrId);
            });

            // Botón de archivo
            document.getElementById('btn-select-file').addEventListener('click', () => {
                document.getElementById('qr-file-input').click();
            });

            // Input de archivo
            document.getElementById('qr-file-input').addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    scanQRFromFile(file, deliveryId, expectedQrId);
                }
            });
        },
        willClose: () => {
            if (currentScanner) {
                currentScanner.stop().catch(err => console.error('Error stopping scanner:', err));
                currentScanner = null;
            }
        }
    });
}

/**
 * Iniciar escáner con cámara
 */
function startCameraScanner(deliveryId, expectedQrId) {
    if (currentScanner) {
        return;
    }

    currentScanner = new Html5Qrcode("qr-reader-modal");

    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    };

    currentScanner.start(
        { facingMode: "environment" },
        config,
        (decodedText, decodedResult) => {
            console.log('QR Code detected:', decodedText);
            processQRCode(decodedText, deliveryId, expectedQrId);
        },
        (errorMessage) => {
            // Error de escaneo (normal cuando no hay QR visible)
        }
    ).catch(err => {
        console.error('Error starting camera:', err);
        Swal.fire({
            icon: 'error',
            title: 'Error al acceder a la cámara',
            text: 'No se pudo acceder a la cámara. Intenta seleccionar una imagen en su lugar.',
            confirmButtonText: 'Entendido'
        });
    });
}

/**
 * Escanear QR desde archivo
 */
function scanQRFromFile(file, deliveryId, expectedQrId) {
    if (!currentScanner) {
        currentScanner = new Html5Qrcode("qr-reader-modal");
    }

    currentScanner.scanFile(file, true)
        .then(decodedText => {
            console.log('QR Code from file:', decodedText);
            processQRCode(decodedText, deliveryId, expectedQrId);
        })
        .catch(err => {
            console.error('Error scanning file:', err);
            Swal.fire({
                icon: 'error',
                title: 'QR no detectado',
                text: 'No se pudo leer el código QR de la imagen. Asegúrate de que la imagen sea clara.',
                confirmButtonText: 'Intentar de nuevo'
            });
        });
}

/**
 * Procesar código QR escaneado
 */
function processQRCode(qrCodeData, deliveryId, expectedQrId) {
    // Detener scanner
    if (currentScanner) {
        currentScanner.stop().catch(err => console.error('Error stopping scanner:', err));
        currentScanner = null;
    }

    // Cerrar modal de escaneo
    Swal.close();

    // Validar que el QR coincida
    if (qrCodeData !== expectedQrId) {
        Swal.fire({
            icon: 'error',
            title: 'QR Incorrecto',
            text: 'Este código QR no corresponde a esta entrega.',
            confirmButtonText: 'Entendido'
        });
        return;
    }

    // QR válido - procesar entrega
    Swal.fire({
        title: 'Procesando...',
        text: 'Confirmando entrega y liberando pago',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Enviar al servidor para procesar
    fetch('shop-process-qr-producto.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            delivery_id: deliveryId,
            qr_code: qrCodeData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Entrega Confirmada!',
                html: `
                    <p>La entrega ha sido confirmada exitosamente.</p>
                    <p><strong>Pago liberado:</strong> ${data.payment_released ? 'Sí' : 'No aplicable'}</p>
                    ${data.payment_released && data.amount ? `<p><strong>Monto:</strong> ${data.amount} ${data.currency}</p>` : ''}
                `,
                confirmButtonText: 'Entendido'
            }).then(() => {
                // Recargar página para mostrar estado actualizado
                location.reload();
            });
        } else {
            throw new Error(data.message || 'Error al procesar la entrega');
        }
    })
    .catch(error => {
        console.error('Error processing QR:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: error.message || 'Hubo un error al procesar el código QR',
            confirmButtonText: 'Entendido'
        });
    });
}

/**
 * Actualizar estado de entrega (para vendedores)
 */
function updateDeliveryState(deliveryId, newState) {
    Swal.fire({
        title: '¿Actualizar estado?',
        text: `¿Confirmas que deseas marcar esta entrega como "${getStateName(newState)}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, actualizar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#42ba25'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Actualizando...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('shop-update-delivery-state-producto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    delivery_id: deliveryId,
                    new_state: newState
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Actualizado!',
                        text: `El estado ha sido actualizado a "${getStateName(newState)}"`,
                        confirmButtonText: 'Entendido'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    throw new Error(data.message || 'Error al actualizar el estado');
                }
            })
            .catch(error => {
                console.error('Error updating state:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'Hubo un error al actualizar el estado',
                    confirmButtonText: 'Entendido'
                });
            });
        }
    });
}

/**
 * Obtener nombre legible del estado
 */
function getStateName(state) {
    const stateNames = {
        'pending': 'Pendiente',
        'in_transit': 'En Tránsito',
        'at_destination': 'En Destino',
        'delivered': 'Entregado'
    };
    return stateNames[state] || state;
}
