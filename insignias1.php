<?php
/**
 * insignias1.php - Sistema de insignias unificado para Send4Less
 * Código para implementar insignias con efectos visuales para usuarios verificados y valoraciones
 * Versión unificada para Mi Perfil y SendVialo Shop
 */

/**
 * Obtener insignia de verificación
 */
function obtenerInsigniaVerificacion($verificado) {
    if ($verificado != 1) {
        return '';
    }

    return '
    <div class="verificacion-wrapper">
      <svg class="verificacion-insignia" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <g id="checkmark" fill="#4169E1">
          <path
            d="M15.8038 12.3135C16.4456 11.6088 17.5544 11.6088 18.1962 12.3135V12.3135C18.5206 12.6697 18.9868 12.8628 19.468 12.8403V12.8403C20.4201 12.7958 21.2042 13.5799 21.1597 14.532V14.532C21.1372 15.0132 21.3303 15.4794 21.6865 15.8038V15.8038C22.3912 16.4456 22.3912 17.5544 21.6865 18.1962V18.1962C21.3303 18.5206 21.1372 18.9868 21.1597 19.468V19.468C21.2042 20.4201 20.4201 21.2042 19.468 21.1597V21.1597C18.9868 21.1372 18.5206 21.3303 18.1962 21.6865V21.6865C17.5544 22.3912 16.4456 22.3912 15.8038 21.6865V21.6865C15.4794 21.3303 15.0132 21.1372 14.532 21.1597V21.1597C13.5799 21.2042 12.7958 20.4201 12.8403 19.468V19.468C12.8628 18.9868 12.6697 18.5206 12.3135 18.1962V18.1962C11.6088 17.5544 11.6088 16.4456 12.3135 15.8038V15.8038C12.6697 15.4794 12.8628 15.0132 12.8403 14.532V14.532C12.7958 13.5799 13.5799 12.7958 14.532 12.8403V12.8403C15.0132 12.8628 15.4794 12.6697 15.8038 12.3135Z"
            fill="#4169E1"/>
          <path
            d="M15.3636 17L16.4546 18.0909L18.6364 15.9091"
            stroke="white"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="1.5"/>
        </g>
      </svg>
    </div>';
}

/**
 * Determinar tipo de efecto según valoración
 * Ampliado para incluir diamond y más niveles
 */
function obtenerTipoEfecto($valoracion) {
    if ($valoracion >= 4.8) {
        return 'diamond';
    } elseif ($valoracion >= 4.5) {
        return 'gold';
    } elseif ($valoracion >= 4.0) {
        return 'silver';
    } elseif ($valoracion >= 3.5) {
        return 'bronze';
    } else {
        return 'basic';
    }
}

/**
 * Obtener corona de laurel según valoración (para Mi Perfil)
 */
function obtenerCoronaDeLaurel($valoracion) {
    if ($valoracion >= 5.0) {
        return '<img src="../shop/Imagenes/Golden_Wreath_3D.svg" class="laurel-crown" alt="Corona de laurel dorada">';
    }
    if ($valoracion >= 4.5) {
        return '<img src="../shop/Imagenes/insignia.svg" class="laurel-crown" alt="Corona de laurel">';
    }
    return '';
}

/**
 * Mostrar imagen con laurel - Versión original para Mi Perfil
 */
function mostrarImagenConLaurel($imageUrl, $valoracion, $verificado) {
    ob_start();
    ?>
    <div class="profile-img-container">
        <?php echo obtenerCoronaDeLaurel($valoracion); ?>
        <img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="Foto de perfil" class="profile-img">
        <?php echo obtenerInsigniaVerificacion($verificado); ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Generar SVG de laurel dinámico para el Shop
 */
function generarLaurelSVG($tipo, $size = 70) {
    $colores = [
        'diamond' => ['primary' => '#e1f5fe', 'secondary' => '#81d4fa', 'glow' => '#4fc3f7'],
        'gold' => ['primary' => '#ffd700', 'secondary' => '#ffb300', 'glow' => '#ff8f00'],
        'silver' => ['primary' => '#e0e0e0', 'secondary' => '#bdbdbd', 'glow' => '#9e9e9e'],
        'bronze' => ['primary' => '#ffab91', 'secondary' => '#ff8a65', 'glow' => '#ff7043'],
        'basic' => ['primary' => '#f5f5f5', 'secondary' => '#e0e0e0', 'glow' => '#bdbdbd']
    ];
    
    $color = $colores[$tipo] ?? $colores['basic'];
    $unique_id = 'svg_' . uniqid();
    
    return "
        <svg width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 120 120\" class=\"laurel-svg laurel-{$tipo}\">
            <defs>
                <radialGradient id=\"laurelGradient{$unique_id}\" cx=\"50%\" cy=\"30%\">
                    <stop offset=\"0%\" stop-color=\"{$color['primary']}\" />
                    <stop offset=\"100%\" stop-color=\"{$color['secondary']}\" />
                </radialGradient>
                <filter id=\"glow{$unique_id}\">
                    <feGaussianBlur stdDeviation=\"2\" result=\"coloredBlur\"/>
                    <feMerge> 
                        <feMergeNode in=\"coloredBlur\"/>
                        <feMergeNode in=\"SourceGraphic\"/>
                    </feMerge>
                </filter>
            </defs>
            <g fill=\"url(#laurelGradient{$unique_id})\" stroke=\"{$color['glow']}\" stroke-width=\"0.5\" filter=\"url(#glow{$unique_id})\">
                <path d=\"M30 45 Q20 35 25 25 Q35 20 45 30 Q50 35 45 45 Q35 50 30 45\" />
                <path d=\"M90 45 Q100 35 95 25 Q85 20 75 30 Q70 35 75 45 Q85 50 90 45\" />
                <path d=\"M35 55 Q25 45 30 35 Q40 30 50 40 Q55 45 50 55 Q40 60 35 55\" />
                <path d=\"M85 55 Q95 45 90 35 Q80 30 70 40 Q65 45 70 55 Q80 60 85 55\" />
                <path d=\"M40 65 Q30 55 35 45 Q45 40 55 50 Q60 55 55 65 Q45 70 40 65\" />
                <path d=\"M80 65 Q90 55 85 45 Q75 40 65 50 Q60 55 65 65 Q75 70 80 65\" />
                <path d=\"M45 75 Q35 65 40 55 Q50 50 60 60 Q65 65 60 75 Q50 80 45 75\" />
                <path d=\"M75 75 Q85 65 80 55 Q70 50 60 60 Q55 65 60 75 Q70 80 75 75\" />
            </g>
        </svg>
    ";
}

/**
 * Mostrar imagen con laurel para SendVialo Shop (VERSIÓN ÚNICA)
 */
function mostrarImagenConLaurelShop($imageUrl, $rating, $verificado = false, $size = 70) {
    $rating = (float)$rating;
    $tipo = obtenerTipoEfecto($rating);
    $laurelSize = round($size * 1.4);
    
    // ESTILOS INLINE FUERTES
    $imgStyle = "width: {$size}px !important; height: {$size}px !important; object-fit: cover !important; border-radius: 50% !important; display: block !important; max-width: {$size}px !important; max-height: {$size}px !important; position: relative; z-index: 2;";
    $containerStyle = "position: relative; display: inline-block; line-height: 0; overflow: hidden; border-radius: 50%;";
    
    ob_start();
    ?>
    <div class="profile-image-laurel" style="<?php echo $containerStyle; ?>">
        <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
             alt="Perfil" 
             style="<?php echo $imgStyle; ?>"
             onerror="this.onerror=null; this.src='user-default.jpg';">
        <?php echo generarLaurelSVG($tipo, $laurelSize); ?>
        <?php if ($verificado): ?>
        <div class="verified-badge">
            <i class="fas fa-check"></i>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Obtener valoración unificada de un usuario desde la tabla comentarios
 */
function getUnifiedUserRating($user_id, $conexion) {
    try {
        $sql = "SELECT 
                    AVG(valoracion) as promedio_valoracion,
                    COUNT(*) as total_valoraciones
                FROM comentarios 
                WHERE usuario_id = ? AND bloqueado = 0";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$user_id]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $promedio = 0;
        $total = 0;
        
        if ($resultado && $resultado['promedio_valoracion'] !== null) {
            $promedio = round((float)$resultado['promedio_valoracion'], 1);
            $total = (int)$resultado['total_valoraciones'];
        }
        
        return [
            'average_rating' => $promedio,
            'total_ratings' => $total,
            'rating_display' => $promedio > 0 ? $promedio : 'N/A',
            'badge_type' => obtenerTipoEfecto($promedio)
        ];
        
    } catch (PDOException $e) {
        return [
            'average_rating' => 0,
            'total_ratings' => 0,
            'rating_display' => 'N/A',
            'badge_type' => 'basic'
        ];
    }
}

/**
 * Función específica para shop-actions.php
 */
function getUnifiedSellerRating($seller_id) {
    global $conexion;
    return getUnifiedUserRating($seller_id, $conexion);
}

/**
 * Obtener perfil completo del usuario con valoraciones (VERSIÓN ÚNICA)
 */
function obtenerPerfilCompletoUsuario($userId, $conexion) {
    try {
        // Obtener datos básicos del usuario
        $sql = "SELECT full_name, username, verificado, ruta_imagen, 
                       provincia, pais, created_at, estudios, trabajo, 
                       gustos, idiomas, viajes
                FROM accounts 
                WHERE id = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;
        }
        
        // Obtener valoraciones usando el sistema unificado
        $rating_sql = "SELECT 
                        AVG(valoracion) as promedio_valoracion,
                        COUNT(*) as total_valoraciones
                       FROM comentarios 
                       WHERE usuario_id = ? AND bloqueado = 0";
        $rating_stmt = $conexion->prepare($rating_sql);
        $rating_stmt->execute([$userId]);
        $rating_data = $rating_stmt->fetch(PDO::FETCH_ASSOC);
        
        $average_rating = $rating_data['promedio_valoracion'] ? round($rating_data['promedio_valoracion'], 1) : 0;
        $total_ratings = $rating_data['total_valoraciones'] ?: 0;
        $badge_type = obtenerTipoEfecto($average_rating);
        
        // Avatar URL
        $avatar_url = !empty($user['ruta_imagen']) ? 
            "mostrar_imagen.php?id=" . $userId : 
            "https://ui-avatars.com/api/?name=" . urlencode($user['full_name']) . "&background=667eea&color=fff&size=100";
        
        return [
            'id' => $userId,
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'verificado' => $user['verificado'],
            'avatar_url' => $avatar_url,
            'provincia' => $user['provincia'],
            'pais' => $user['pais'],
            'created_at' => $user['created_at'],
            'estudios' => $user['estudios'],
            'trabajo' => $user['trabajo'],
            'gustos' => $user['gustos'],
            'idiomas' => $user['idiomas'],
            'viajes' => $user['viajes'],
            'rating_data' => [
                'average_rating' => $average_rating,
                'total_ratings' => $total_ratings,
                'rating_display' => $average_rating > 0 ? $average_rating : 'N/A',
                'badge_type' => $badge_type
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Error en obtenerPerfilCompletoUsuario: " . $e->getMessage());
        return null;
    }
}

/**
 * Función para generar estrellas de valoración como texto
 */
function generarEstrellasTexto($rating) {
    $rating = (float)$rating;
    $fullStars = floor($rating);
    $hasHalfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
    
    $stars = str_repeat('★', $fullStars);
    if ($hasHalfStar) {
        $stars .= '⭐';
    }
    $stars .= str_repeat('☆', $emptyStars);
    
    return $stars;
}

/**
 * Función para obtener valoraciones de un vendedor (sistema unificado)
 */
function obtenerValoracionesVendedor($sellerId, $conexion) {
    try {
        $sql = "SELECT 
                    AVG(valoracion) as promedio_valoracion,
                    COUNT(*) as total_valoraciones,
                    COUNT(CASE WHEN valoracion = 5 THEN 1 END) as cinco_estrellas,
                    COUNT(CASE WHEN valoracion = 4 THEN 1 END) as cuatro_estrellas,
                    COUNT(CASE WHEN valoracion = 3 THEN 1 END) as tres_estrellas,
                    COUNT(CASE WHEN valoracion = 2 THEN 1 END) as dos_estrellas,
                    COUNT(CASE WHEN valoracion = 1 THEN 1 END) as una_estrella
                FROM comentarios 
                WHERE usuario_id = ? AND bloqueado = 0";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$sellerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $average_rating = $result['promedio_valoracion'] ? round($result['promedio_valoracion'], 1) : 0;
        $total_ratings = $result['total_valoraciones'] ?: 0;
        
        return [
            'average_rating' => $average_rating,
            'total_ratings' => $total_ratings,
            'rating_display' => $average_rating > 0 ? $average_rating : 'N/A',
            'badge_type' => obtenerTipoEfecto($average_rating),
            'distribution' => [
                5 => (int)($result['cinco_estrellas'] ?? 0),
                4 => (int)($result['cuatro_estrellas'] ?? 0),
                3 => (int)($result['tres_estrellas'] ?? 0),
                2 => (int)($result['dos_estrellas'] ?? 0),
                1 => (int)($result['una_estrella'] ?? 0)
            ]
        ];
        
    } catch (PDOException $e) {
        error_log("Error en obtenerValoracionesVendedor: " . $e->getMessage());
        return [
            'average_rating' => 0,
            'total_ratings' => 0,
            'rating_display' => 'N/A',
            'badge_type' => 'basic',
            'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]
        ];
    }
}

/**
 * Función para verificar si un usuario puede ser considerado "vendedor estrella"
 */
function esVendedorEstrella($userId, $conexion) {
    try {
        // Criterios para ser vendedor estrella:
        // 1. Tener al menos 10 valoraciones
        // 2. Promedio de 4.5 o superior
        // 3. Al menos 5 productos activos
        
        $valoraciones = obtenerValoracionesVendedor($userId, $conexion);
        
        if ($valoraciones['total_ratings'] < 10 || $valoraciones['average_rating'] < 4.5) {
            return false;
        }
        
        // Verificar productos activos
        $sql = "SELECT COUNT(*) FROM shop_products WHERE seller_id = ? AND active = 1";
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$userId]);
        $productos_activos = $stmt->fetchColumn();
        
        return $productos_activos >= 5;
        
    } catch (PDOException $e) {
        error_log("Error en esVendedorEstrella: " . $e->getMessage());
        return false;
    }
}

/**
 * Función para obtener el ranking de un vendedor
 */
function obtenerRankingVendedor($userId, $conexion) {
    try {
        // Obtener el ranking basado en valoración promedio y total de valoraciones
        $sql = "SELECT 
                    vendedor_id,
                    promedio_valoracion,
                    total_valoraciones,
                    (@rank := @rank + 1) as ranking
                FROM (
                    SELECT 
                        c.usuario_id as vendedor_id,
                        AVG(c.valoracion) as promedio_valoracion,
                        COUNT(c.valoracion) as total_valoraciones
                    FROM comentarios c
                    WHERE c.bloqueado = 0
                    GROUP BY c.usuario_id
                    HAVING total_valoraciones >= 3
                    ORDER BY promedio_valoracion DESC, total_valoraciones DESC
                ) as rankings
                CROSS JOIN (SELECT @rank := 0) r
                HAVING vendedor_id = ?";
        
        $stmt = $conexion->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'ranking' => (int)$result['ranking'],
                'average_rating' => round($result['promedio_valoracion'], 1),
                'total_ratings' => (int)$result['total_valoraciones']
            ];
        }
        
        return ['ranking' => null, 'average_rating' => 0, 'total_ratings' => 0];
        
    } catch (PDOException $e) {
        error_log("Error en obtenerRankingVendedor: " . $e->getMessage());
        return ['ranking' => null, 'average_rating' => 0, 'total_ratings' => 0];
    }
}

/**
 * Función para formatear la moneda según el tipo
 */
function formatearMoneda($amount, $currency) {
    $symbols = [
        'EUR' => '€',
        'USD' => '$',
        'BOB' => 'Bs',
        'BRL' => 'R$',
        'ARS' => '$',
        'COP' => '$',
        'MXN' => '$',
        'PEN' => 'S/'
    ];
    
    $symbol = $symbols[$currency] ?? '€';
    $value = number_format((float)$amount, 2);
    
    // Para USD y otros dólares, símbolo antes
    if (in_array($currency, ['USD', 'ARS', 'COP', 'MXN'])) {
        return $symbol . $value;
    }
    
    // Para Euro y otros, símbolo después
    return $value . $symbol;
}

/**
 * Generar estrellas HTML
 */
function generarEstrellas($rating, $mostrar_numero = true) {
    $estrellas = [];
    $estrellas_llenas = floor($rating);
    $media_estrella = ($rating - $estrellas_llenas) >= 0.5;
    
    for ($i = 0; $i < 5; $i++) {
        if ($i < $estrellas_llenas) {
            $estrellas[] = '<i class="fas fa-star"></i>';
        } elseif ($i === $estrellas_llenas && $media_estrella) {
            $estrellas[] = '<i class="fas fa-star-half-alt"></i>';
        } else {
            $estrellas[] = '<i class="far fa-star"></i>';
        }
    }
    
    $tipo = obtenerTipoEfecto($rating);
    $estrellas_html = '<span class="rating-stars">' . implode('', $estrellas) . '</span>';
    $numero_html = $mostrar_numero ? "<span class=\"rating-value {$tipo}-text\">{$rating}</span>" : '';
    
    return $estrellas_html . $numero_html;
}

/**
 * Incluir estilos - Versión ampliada para ambos sistemas
 */
function incluirEstilosInsignias() {
    ?>
    <style>
        /* === ESTILOS ORIGINALES PARA MI PERFIL === */
        .profile-img-container {
            position: relative;
            display: inline-block;
        }

        .profile-img {
            position: relative;
            z-index: 2;
            border-radius: 50%;
            object-fit: cover;
            width: 50px;
            height: 50px;
        }

        .laurel-crown {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 130%;
            height: 130%;
            z-index: 1;
        }

        .verificacion-wrapper {
            position: absolute;
            bottom: -17px;
            right: -16px;
            z-index: 6;
        }

        .verificacion-insignia {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 50px;
            height: 50px;
            z-index: 5;
        }

        /* === ESTILOS PARA SENDVIALO SHOP === */
        .profile-image-laurel {
            position: relative;
            display: inline-block;
            margin: 0 auto;
        }
        
        .profile-image-laurel img {
            border-radius: 50%;
            display: block;
            position: relative;
            z-index: 2;
            object-fit: cover;
        }
        
        .profile-image-laurel .laurel-svg {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1;
            pointer-events: none;
        }
        
        .verified-badge {
            position: absolute;
            bottom: 5px;
            right: 5px;
            z-index: 3;
            background: #4CAF50;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .verified-badge i {
            color: white;
            font-size: 10px;
        }
        
        .verified-icon {
            color: #4CAF50;
            font-size: 14px;
            margin-left: 4px;
        }
        
        .rating-stars {
            color: #ffd700;
            margin-right: 5px;
        }
        
        .rating-value {
            font-weight: 600;
            margin-right: 5px;
        }

        /* === COLORES UNIFICADOS === */
        .diamond-text {
            color: #e1f5fe !important;
            text-shadow: 0 0 8px #81d4fa;
        }

        .gold-text {
            color: #FFD700 !important;
            text-shadow: 0 0 6px #ffb300;
        }

        .silver-text {
            color: #C0C0C0 !important;
            text-shadow: 0 0 4px #9e9e9e;
        }

        .bronze-text {
            color: #CD7F32 !important;
            text-shadow: 0 0 4px #8d6e63;
        }

        .basic-text {
            color: #888888 !important;
        }

        /* === ANIMACIONES PARA SHOP === */
        @keyframes glow-diamond {
            0%, 100% { filter: drop-shadow(0 0 3px #e1f5fe); }
            50% { filter: drop-shadow(0 0 8px #81d4fa); }
        }
        
        @keyframes glow-gold {
            0%, 100% { filter: drop-shadow(0 0 3px #ffd700); }
            50% { filter: drop-shadow(0 0 6px #ffb300); }
        }
        
        @keyframes glow-silver {
            0%, 100% { filter: drop-shadow(0 0 2px #c0c0c0); }
            50% { filter: drop-shadow(0 0 4px #9e9e9e); }
        }
        
        @keyframes glow-bronze {
            0%, 100% { filter: drop-shadow(0 0 2px #cd7f32); }
            50% { filter: drop-shadow(0 0 4px #8d6e63); }
        }
        
        .laurel-diamond { animation: glow-diamond 3s ease-in-out infinite; }
        .laurel-gold { animation: glow-gold 3s ease-in-out infinite; }
        .laurel-silver { animation: glow-silver 3s ease-in-out infinite; }
        .laurel-bronze { animation: glow-bronze 3s ease-in-out infinite; }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .verified-badge {
                width: 16px;
                height: 16px;
            }
            
            .verified-badge i {
                font-size: 8px;
            }
        }
    </style>
    <?php
}

?>