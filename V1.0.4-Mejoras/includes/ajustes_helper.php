<?php
/**
 * Funciones auxiliares para ajustes de usuario
 */

/**
 * Obtener un ajuste del usuario actual
 */
function getUserSetting($clave, $default = null) {
    $db = getDB();
    $stmt = $db->prepare("SELECT valor FROM usuario_ajustes WHERE usuario_id = ? AND clave = ?");
    $stmt->execute([currentUserId(), $clave]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : $default;
}

/**
 * Guardar un ajuste del usuario actual
 */
function setUserSetting($clave, $valor) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO usuario_ajustes (usuario_id, clave, valor) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    $stmt->execute([currentUserId(), $clave, $valor]);
}

/**
 * Obtener todos los ajustes del usuario actual
 */
function getUserSettings() {
    $db = getDB();
    $stmt = $db->prepare("SELECT clave, valor FROM usuario_ajustes WHERE usuario_id = ?");
    $stmt->execute([currentUserId()]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
