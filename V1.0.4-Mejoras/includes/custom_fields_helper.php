<?php
/**
 * Helper functions for custom fields
 */

function getCustomFields($entidad, $soloActivos = true) {
    $db = getDB();
    $sql = "SELECT * FROM custom_fields WHERE entidad = ?";
    if ($soloActivos) $sql .= " AND activo = 1";
    $sql .= " ORDER BY orden ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$entidad]);
    return $stmt->fetchAll();
}

function getCustomFieldValues($entidadId, $entidad = 'cliente') {
    $db = getDB();
    $stmt = $db->prepare("SELECT cf.slug, cfv.valor, cf.nombre, cf.tipo
        FROM custom_field_values cfv
        JOIN custom_fields cf ON cfv.field_id = cf.id
        WHERE cfv.entidad_id = ? AND cf.entidad = ? AND cf.activo = 1
        ORDER BY cf.orden");
    $stmt->execute([$entidadId, $entidad]);
    $values = [];
    foreach ($stmt->fetchAll() as $row) {
        $values[$row['slug']] = $row;
    }
    return $values;
}

function saveCustomFieldValues($entidadId, $entidad, $postData) {
    $db = getDB();
    $fields = getCustomFields($entidad);
    $stmt = $db->prepare("INSERT INTO custom_field_values (field_id, entidad_id, valor)
        VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    foreach ($fields as $field) {
        $key = 'cf_' . $field['slug'];
        $valor = $postData[$key] ?? '';
        if ($field['tipo'] === 'checkbox') {
            $valor = isset($postData[$key]) ? '1' : '0';
        }
        $stmt->execute([$field['id'], $entidadId, $valor]);
    }
}

function renderCustomFieldsForm($entidad, $values = []) {
    $fields = getCustomFields($entidad);
    if (empty($fields)) return;
    echo '<hr><h6 class="text-muted mb-3"><i class="bi bi-ui-checks-grid"></i> Campos Personalizados</h6>';
    echo '<div class="row g-3">';
    foreach ($fields as $field) {
        $name = 'cf_' . $field['slug'];
        $val = $values[$field['slug']]['valor'] ?? '';
        $req = $field['obligatorio'] ? 'required' : '';
        echo '<div class="col-md-6 mb-2">';
        echo '<label class="form-label">' . htmlspecialchars($field['nombre']);
        if ($field['obligatorio']) echo ' <span class="text-danger">*</span>';
        echo '</label>';
        switch ($field['tipo']) {
            case 'textarea':
                echo '<textarea name="' . $name . '" class="form-control" rows="2" ' . $req . '>' . htmlspecialchars($val) . '</textarea>';
                break;
            case 'select':
                echo '<select name="' . $name . '" class="form-select" ' . $req . '>';
                echo '<option value="">-- Seleccionar --</option>';
                foreach (explode(',', $field['opciones']) as $opt) {
                    $opt = trim($opt);
                    $sel = $val === $opt ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($opt) . '" ' . $sel . '>' . htmlspecialchars($opt) . '</option>';
                }
                echo '</select>';
                break;
            case 'checkbox':
                echo '<div class="form-check mt-1">';
                echo '<input type="checkbox" name="' . $name . '" class="form-check-input" value="1" ' . ($val === '1' ? 'checked' : '') . '>';
                echo '<label class="form-check-label">Si</label></div>';
                break;
            case 'numero':
                echo '<input type="number" name="' . $name . '" class="form-control" value="' . htmlspecialchars($val) . '" ' . $req . ' step="any">';
                break;
            case 'fecha':
                echo '<input type="date" name="' . $name . '" class="form-control" value="' . htmlspecialchars($val) . '" ' . $req . '>';
                break;
            case 'email':
                echo '<input type="email" name="' . $name . '" class="form-control" value="' . htmlspecialchars($val) . '" ' . $req . '>';
                break;
            case 'telefono':
                echo '<input type="tel" name="' . $name . '" class="form-control" value="' . htmlspecialchars($val) . '" ' . $req . '>';
                break;
            default:
                echo '<input type="text" name="' . $name . '" class="form-control" value="' . htmlspecialchars($val) . '" ' . $req . '>';
                break;
        }
        echo '</div>';
    }
    echo '</div>';
}

function renderCustomFieldsView($entidadId, $entidad = 'cliente') {
    $values = getCustomFieldValues($entidadId, $entidad);
    if (empty($values)) return;
    echo '<hr><h6 class="text-muted mb-3"><i class="bi bi-ui-checks-grid"></i> Campos Personalizados</h6>';
    echo '<div class="row g-2">';
    foreach ($values as $slug => $data) {
        if ($data['valor'] === '' || $data['valor'] === null) continue;
        $display = htmlspecialchars($data['valor']);
        if ($data['tipo'] === 'checkbox') $display = $data['valor'] === '1' ? 'Si' : 'No';
        echo '<div class="col-md-6 mb-2">';
        echo '<small class="text-muted d-block">' . htmlspecialchars($data['nombre']) . '</small>';
        echo '<span>' . $display . '</span>';
        echo '</div>';
    }
    echo '</div>';
}
