<?php
/**
 * Sistema de Backup de Base de Datos
 * Compatible con Hostinger (sin acceso CLI mysqldump)
 * Genera backup SQL puro usando PHP + PDO
 */

/**
 * Generar backup completo de la base de datos
 */
function generarBackup() {
    $db = getDB();

    $backupDir = BACKUP_DIR;
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_His') . '.sql';
    $filepath = $backupDir . $filename;

    $output = "-- Tinoprop Backup\n";
    $output .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
    $output .= "-- Base de datos: " . DB_NAME . "\n";
    $output .= "-- Version: " . APP_VERSION . "\n";
    $output .= "-- -------------------------------------------\n\n";
    $output .= "SET NAMES utf8mb4;\n";
    $output .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    // Obtener todas las tablas
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // CREATE TABLE
        $createStmt = $db->query("SHOW CREATE TABLE `$table`")->fetch();
        $output .= "-- Tabla: $table\n";
        $output .= "DROP TABLE IF EXISTS `$table`;\n";
        $output .= $createStmt['Create Table'] . ";\n\n";

        // INSERT datos
        $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
            $colStr = '`' . implode('`, `', $columns) . '`';

            // Insertar en lotes de 100
            $chunks = array_chunk($rows, 100);
            foreach ($chunks as $chunk) {
                $output .= "INSERT INTO `$table` ($colStr) VALUES\n";
                $values = [];
                foreach ($chunk as $row) {
                    $vals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $db->quote($val);
                        }
                    }
                    $values[] = '(' . implode(', ', $vals) . ')';
                }
                $output .= implode(",\n", $values) . ";\n\n";
            }
        }
    }

    $output .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $output .= "-- Fin del backup\n";

    // Guardar archivo
    if (file_put_contents($filepath, $output, LOCK_EX) !== false) {
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath),
        ];
    }

    return ['error' => 'No se pudo escribir el archivo de backup'];
}

/**
 * Listar backups existentes
 */
function listarBackups() {
    $backupDir = BACKUP_DIR;
    if (!is_dir($backupDir)) return [];

    $files = glob($backupDir . 'backup_*.sql');
    $backups = [];

    foreach ($files as $file) {
        $backups[] = [
            'filename' => basename($file),
            'filepath' => $file,
            'size' => filesize($file),
            'date' => filemtime($file),
        ];
    }

    // Ordenar por fecha descendente
    usort($backups, function($a, $b) { return $b['date'] - $a['date']; });

    return $backups;
}

/**
 * Eliminar backup antiguo
 */
function eliminarBackup($filename) {
    $filepath = BACKUP_DIR . basename($filename); // basename() para seguridad
    if (file_exists($filepath) && strpos($filepath, BACKUP_DIR) === 0) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Descargar backup
 */
function descargarBackup($filename) {
    $filepath = BACKUP_DIR . basename($filename);
    if (!file_exists($filepath) || strpos($filepath, BACKUP_DIR) !== 0) {
        return false;
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Pragma: no-cache');
    readfile($filepath);
    exit;
}

/**
 * Limpiar backups antiguos (mantener los ultimos N)
 */
function limpiarBackupsAntiguos($mantener = 10) {
    $backups = listarBackups();
    $eliminados = 0;

    if (count($backups) > $mantener) {
        $aEliminar = array_slice($backups, $mantener);
        foreach ($aEliminar as $backup) {
            if (eliminarBackup($backup['filename'])) {
                $eliminados++;
            }
        }
    }

    return $eliminados;
}
