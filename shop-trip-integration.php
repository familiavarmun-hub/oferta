<?php
// shop-trip-integration.php — Utilidades para shop_trip_info
// (Usado si quieres guardar preferencias por viaje)
function getTripShopInfo(PDO $db, int $tripId): ?array {
  $st = $db->prepare("SELECT * FROM shop_trip_info WHERE trip_id=:t");
  $st->execute([':t'=>$tripId]);
  $r = $st->fetch(PDO::FETCH_ASSOC);
  return $r ?: null;
}

function upsertTripShopInfo(PDO $db, int $tripId, array $data): bool {
  $st = $db->prepare("INSERT INTO shop_trip_info (trip_id, enabled, categories, available_space, budget, currency, notes)
                      VALUES (:t,:en,:cat,:space,:bud,:cur,:n)
                      ON DUPLICATE KEY UPDATE enabled=VALUES(enabled), categories=VALUES(categories),
                        available_space=VALUES(available_space), budget=VALUES(budget),
                        currency=VALUES(currency), notes=VALUES(notes)");
  return $st->execute([
    ':t'=>$tripId,
    ':en'=> !empty($data['enabled'])?1:0,
    ':cat'=> !empty($data['categories']) ? json_encode($data['categories']) : null,
    ':space'=> $data['available_space'] ?? null,
    ':bud'=> $data['budget'] ?? null,
    ':cur'=> $data['currency'] ?? 'EUR',
    ':n'=> $data['notes'] ?? null
  ]);
}
