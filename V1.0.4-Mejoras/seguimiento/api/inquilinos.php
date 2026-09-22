<?php
require_once '_db.php';
casAuth();

$db     = casDB();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $rows = $db->query('SELECT * FROM inquilinos ORDER BY id DESC')->fetchAll();
        foreach ($rows as &$r) {
            $r['historial']  = json_decode($r['historial']  ?? '[]', true) ?: [];
            $r['estudia']    = (bool)($r['estudia']    ?? 0);
            $r['trabajaFijo']= (bool)($r['trabajaFijo'] ?? 0);
        }
        casOk($rows);

    } elseif ($method === 'POST') {
        $b = casBody();
        if (empty($b['id'])) { casErr('Falta id'); exit; }

        $hist = isset($b['historial']) ? json_encode($b['historial'], JSON_UNESCAPED_UNICODE) : '[]';
        $sql  = 'INSERT INTO inquilinos
                   (id,n,t,email,oc,trab,bud,ent,dur,de,ac,mas,fum,cal,notas,estado,fecha,propId,
                    seguimiento,segHora,historial,edad,estudia,trabajaFijo,nacionalidad,calManual,sexo,notaProx)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   n=VALUES(n),t=VALUES(t),email=VALUES(email),oc=VALUES(oc),trab=VALUES(trab),
                   bud=VALUES(bud),ent=VALUES(ent),dur=VALUES(dur),de=VALUES(de),ac=VALUES(ac),
                   mas=VALUES(mas),fum=VALUES(fum),cal=VALUES(cal),notas=VALUES(notas),
                   estado=VALUES(estado),fecha=VALUES(fecha),propId=VALUES(propId),
                   seguimiento=VALUES(seguimiento),segHora=VALUES(segHora),historial=VALUES(historial),
                   edad=VALUES(edad),estudia=VALUES(estudia),trabajaFijo=VALUES(trabajaFijo),
                   nacionalidad=VALUES(nacionalidad),calManual=VALUES(calManual),sexo=VALUES(sexo),
                   notaProx=VALUES(notaProx)';
        $db->prepare($sql)->execute([
            $b['id'], $b['n'] ?? '', $b['t'] ?? '', $b['email'] ?? '',
            $b['oc'] ?? '', $b['trab'] ?? '', $b['bud'] ?? '', $b['ent'] ?? '',
            $b['dur'] ?? '', $b['de'] ?? '', $b['ac'] ?? '', $b['mas'] ?? '',
            $b['fum'] ?? '', $b['cal'] ?? '', $b['notas'] ?? '', $b['estado'] ?? '',
            $b['fecha'] ?? '', $b['propId'] ?? null, $b['seguimiento'] ?? '',
            $b['segHora'] ?? '', $hist,
            $b['edad'] ?? null, (int)($b['estudia'] ?? 0), (int)($b['trabajaFijo'] ?? 0),
            $b['nacionalidad'] ?? null, $b['calManual'] ?? null, $b['sexo'] ?? null,
            $b['notaProx'] ?? null
        ]);
        casOk(['id' => $b['id']]);

    } elseif ($method === 'DELETE') {
        $id = $_GET['id'] ?? null;
        if (!$id) { casErr('Falta id'); exit; }
        $db->prepare('DELETE FROM inquilinos WHERE id = ?')->execute([$id]);
        casOk(['deleted' => $id]);

    } else {
        casErr('Método no permitido', 405);
    }
} catch (Exception $e) {
    casErr('Error interno: ' . $e->getMessage(), 500);
}
