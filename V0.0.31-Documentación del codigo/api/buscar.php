<?php
/*
 * ==========================================================================
 * Archivo: api/buscar.php — Endpoint de Búsqueda Global de TinoProp
 * ==========================================================================
 *
 * ¿QUÉ HACE ESTE ARCHIVO?
 * Este archivo funciona como una "API" (un punto de acceso que el navegador
 * puede consultar) para buscar de forma simultánea en clientes, propiedades
 * y prospectos del CRM inmobiliario TinoProp.
 *
 * Cuando el usuario escribe algo en la barra de búsqueda de la aplicación,
 * el navegador (JavaScript) envía una petición HTTP aquí y recibe los
 * resultados en formato JSON (un formato que JavaScript entiende fácilmente).
 *
 * ¿CÓMO FUNCIONA PASO A PASO?
 * 1. El navegador envía una petición GET con el parámetro "q" (el texto buscado).
 *    Ejemplo: GET /api/buscar.php?q=García
 * 2. Si el texto tiene menos de 2 caracteres, devuelve un array vacío
 *    (para evitar búsquedas demasiado genéricas que sobrecarguen la BD).
 * 3. Si es válido, llama a busqueda_global() que busca coincidencias en las
 *    tablas de clientes, propiedades y prospectos.
 * 4. Devuelve los resultados como JSON para que el frontend los muestre.
 *
 * SEGURIDAD:
 * - Se requiere sesión activa (el bootstrap.php verifica que el usuario
 *   esté logueado antes de llegar aquí).
 * - Los resultados se filtran automáticamente por la inmobiliaria del
 *   usuario (multi-tenant con sql_iid()), así cada inmobiliaria solo
 *   ve sus propios datos.
 *
 * EJEMPLO DE RESPUESTA:
 *   [{"tipo": "cliente", "id": 5, "texto": "García López"}, ...]
 */

// Indicamos al navegador que la respuesta será JSON con caracteres UTF-8
// (esto es necesario para que acentos y ñ se muestren correctamente)
header('Content-Type: application/json; charset=utf-8');

// Cargamos el "bootstrap" de la aplicación: este archivo inicializa la sesión,
// conecta a la base de datos, carga funciones auxiliares y verifica que el
// usuario esté autenticado. Sin esto, nada funciona.
require_once __DIR__ . '/../inc/bootstrap.php';

// Recogemos el término de búsqueda del parámetro "q" de la URL.
// trim() elimina espacios en blanco al inicio y final del texto.
// El operador ?? (null coalescing) devuelve '' si "q" no existe en la URL.
$termino = trim($_GET['q'] ?? '');

// Validación: si el usuario escribió menos de 2 caracteres, no buscamos.
// ¿Por qué? Buscar con 1 solo carácter (ej: "a") devolvería demasiados
// resultados y haría la consulta SQL muy lenta e inútil.
if (strlen($termino) < 2) {
    echo json_encode([]); // Devolvemos un array JSON vacío: []
    exit;                 // Terminamos la ejecución del script aquí
}

// Obtenemos la conexión a la base de datos.
// $pdo es un objeto PDO (PHP Data Objects) que nos permite hacer consultas SQL
// de forma segura contra la base de datos MySQL de TinoProp.
$pdo = db();

// Ejecutamos la búsqueda global: busca el término en las tablas de clientes,
// propiedades y prospectos. El tercer parámetro (10) limita a 10 resultados
// máximo para que la respuesta sea rápida y no sature la interfaz.
$resultados = busqueda_global($pdo, $termino, 10);

// Convertimos el array PHP a formato JSON y lo enviamos al navegador.
// JSON_UNESCAPED_UNICODE evita que caracteres como "ñ" o "á" se conviertan
// en códigos raros (ej: "\u00f1"), manteniéndolos legibles.
echo json_encode($resultados, JSON_UNESCAPED_UNICODE);
