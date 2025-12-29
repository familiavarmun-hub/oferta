<?php
// shop-confirmacion-pago.php - Confirmación de pago completado
session_start();
require_once '../config.php';
require_once 'insignias1.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../login.php');
    exit;
}

$proposal_id = (int)($_GET['proposal_id'] ?? 0);
$payment_id = (int)($_GET['payment_id'] ?? 0);
$user_id = $_SESSION['usuario_id'];

if ($proposal_id <= 0) {
    echo '<div class="alert alert-danger">ID inválido</div>';
    exit;
}

// Cargar datos completos
$sql = "SELECT p.*, r.*,
               p.id as proposal_id, r.id as request_id,
               COALESCE(trav.full_name, trav.username, 'Viajero') as traveler_name,
               COALESCE(req.full_name, req.username, 'Solicitante') as requester_name,
               trav.id as traveler_user_id, trav.verificado as traveler_verified,
               pay.estado as payment_status, pay.charge_id, pay.monto_total,
               pay.amount_to_transporter, pay.amount_to_company,
               pay.codigo_unico, pay.created_at as payment_date,
               p.proposed_currency as moneda
        FROM shop_request_proposals p
        JOIN shop_requests r ON r.id = p.request_id
        LEFT JOIN accounts trav ON trav.id = p.traveler_id
        LEFT JOIN accounts req ON req.id = r.user_id
        LEFT JOIN payments_in_custody pay ON pay.id = :payment_id
        WHERE p.id = :proposal_id AND r.user_id = :user_id";

$stmt = $conexion->prepare($sql);
$stmt->execute([':proposal_id' => $proposal_id, ':payment_id' => $payment_id, ':user_id' => $user_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    echo '<div class="alert alert-danger">Datos no encontrados</div>';
    exit;
}

// Obtener rating del viajero
$sql_rating = "SELECT AVG(rating) as rating, COUNT(*) as total
               FROM shop_seller_ratings WHERE seller_id = :seller_id";
$stmt_rating = $conexion->prepare($sql_rating);
$stmt_rating->execute([':seller_id' => $data['traveler_user_id']]);
$rating_data = $stmt_rating->fetch(PDO::FETCH_ASSOC);
$rating = $rating_data ? round($rating_data['rating'], 1) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pago Confirmado - SendVialo Shop</title>
  <link rel="stylesheet" href="../css/estilos.css">
  <link rel="icon" href="../Imagenes/globo5.png" type="image/png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary-color: #2196F3;
      --success-color: #41ba0d;
      --warning-color: #FF9800;
    }

    * { box-sizing: border-box; }

    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(180deg, #F7FAFC 0%, #FFFFFF 100%);
      min-height: 100vh;
      padding: 40px 20px;
    }

    .confirmation-container {
      max-width: 800px;
      margin: 0 auto;
    }

    .success-icon {
      text-align: center;
      margin-bottom: 30px;
    }

    .success-icon i {
      font-size: 5rem;
      color: var(--success-color);
      animation: scaleIn 0.5s ease-out;
    }

    @keyframes scaleIn {
      from { transform: scale(0); }
      to { transform: scale(1); }
    }

    .card-modern {
      background: #fff;
      border-radius: 16px;
      padding: 40px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      margin-bottom: 30px;
    }

    .confirmation-header {
      text-align: center;
      margin-bottom: 40px;
    }

    .confirmation-header h1 {
      font-size: 2.2rem;
      font-weight: 700;
      color: var(--success-color);
      margin-bottom: 10px;
    }

    .confirmation-header p {
      font-size: 1.1rem;
      color: #666;
    }

    .info-section {
      margin-bottom: 30px;
      padding-bottom: 30px;
      border-bottom: 2px solid #f0f0f0;
    }

    .info-section:last-child {
      border-bottom: none;
      padding-bottom: 0;
      margin-bottom: 0;
    }

    .info-section h2 {
      font-size: 1.4rem;
      font-weight: 700;
      margin-bottom: 20px;
      color: var(--primary-color);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      font-size: 1rem;
    }

    .info-row strong {
      color: #333;
    }

    .info-row span {
      color: #666;
      text-align: right;
    }

    .highlight-box {
      background: linear-gradient(135deg, #E3F2FD 0%, #E8F5E9 100%);
      padding: 20px;
      border-radius: 12px;
      border-left: 4px solid var(--success-color);
      margin: 20px 0;
    }

    .highlight-box h3 {
      font-size: 1.1rem;
      font-weight: 700;
      margin-bottom: 10px;
      color: var(--success-color);
    }

    .traveler-info {
      display: flex;
      align-items: center;
      gap: 20px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 12px;
      margin-top: 15px;
    }

    .traveler-avatar {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      border: 3px solid var(--primary-color);
    }

    .traveler-details h3 {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 5px;
    }

    .rating-stars {
      color: #ffd700;
    }

    .action-buttons {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
      margin-top: 30px;
    }

    .btn-action {
      padding: 16px;
      border: none;
      border-radius: 12px;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      text-align: center;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn-primary {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--success-color) 100%);
      color: #fff;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(33, 150, 243, 0.4);
      color: #fff;
    }

    .btn-secondary {
      background: #f0f0f0;
      color: #333;
    }

    .btn-secondary:hover {
      background: #e0e0e0;
    }

    @media (max-width: 768px) {
      .action-buttons {
        grid-template-columns: 1fr;
      }

      .card-modern {
        padding: 25px;
      }
    }
  </style>
</head>
<body>
  <?php include 'header2.php'; ?>

  <div class="confirmation-container">
   

    <div class="card-modern">
      <div class="confirmation-header">
           <div class="success-icon">
      <i class="bi bi-check-circle-fill"></i>
    </div>
        <h1>¡Pago Confirmado!</h1>
        
        <p>Tu propuesta ha sido aceptada exitosamente</p>
      </div>

    <div class="card-modern" style="margin-bottom: 20px;">
<a href="shop-chat-list.php?proposal_id=<?php echo $proposal_id; ?>" class="btn btn-outline-success w-100">
            <i class="bi bi-chat-dots"></i> Chat con el viajero
    </a>
</div>

 
  </div>
</body>
</html>