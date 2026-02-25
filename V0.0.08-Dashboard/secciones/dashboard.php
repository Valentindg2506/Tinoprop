<?php
/* Seccion: Dashboard CRM
   Descripcion: Resumen general con filtros simulados
*/

$filtro_equipo = isset($_GET['equipo']) ? $_GET['equipo'] : 'Todos';
$filtro_periodo = isset($_GET['periodo']) ? $_GET['periodo'] : 'Mes actual';
$filtro_operacion = isset($_GET['operacion']) ? $_GET['operacion'] : 'Venta y Alquiler';

$kpis = [
    ["titulo" => "Clientes activos", "valor" => "128", "detalle" => "+12 este mes"],
    ["titulo" => "Prospectos", "valor" => "46", "detalle" => "12 nuevos"],
    ["titulo" => "Propiedades venta", "valor" => "32", "detalle" => "6 destacadas"],
    ["titulo" => "Propiedades alquiler", "valor" => "18", "detalle" => "4 urgentes"],
];

$embudo = [
    ["etapa" => "Contacto", "valor" => 54, "color" => "#3498db"],
    ["etapa" => "Visita", "valor" => 32, "color" => "#1abc9c"],
    ["etapa" => "Oferta", "valor" => 18, "color" => "#f39c12"],
    ["etapa" => "Cierre", "valor" => 9, "color" => "#2ecc71"],
];

$alertas = [
    ["titulo" => "Visita pendiente", "detalle" => "Piso Ruzafa - 17:00"],
    ["titulo" => "Contrato listo", "detalle" => "Chalet Torrent"],
    ["titulo" => "Documento faltante", "detalle" => "Certificado energetico"],
];

$actividad = [
    ["hora" => "09:15", "texto" => "Nueva nota en cliente Ana Lopez"],
    ["hora" => "10:40", "texto" => "Prospecto contactado: Javier S"],
    ["hora" => "12:05", "texto" => "Propiedad publicada: Duplex Moderno"],
    ["hora" => "13:20", "texto" => "Reserva confirmada: Loft Urban"],
];
?>

<div class="encabezado_seccion">
    <h2>Dashboard CRM</h2>
    <a class="btn_nuevo_cliente" href="#">+ Nueva accion</a>
</div>

<form class="barra_filtros" method="GET">
    <input type="hidden" name="seccion" value="dashboard">

    <div class="filtro_item">
        <label for="equipo">Equipo</label>
        <select id="equipo" name="equipo">
            <option value="Todos" <?php echo $filtro_equipo === 'Todos' ? 'selected' : ''; ?>>Todos</option>
            <option value="Vendedor" <?php echo $filtro_equipo === 'Vendedor' ? 'selected' : ''; ?>>Vendedor</option>
            <option value="Comprador" <?php echo $filtro_equipo === 'Comprador' ? 'selected' : ''; ?>>Comprador</option>
        </select>
    </div>

    <div class="filtro_item">
        <label for="periodo">Periodo</label>
        <select id="periodo" name="periodo">
            <option value="Mes actual" <?php echo $filtro_periodo === 'Mes actual' ? 'selected' : ''; ?>>Mes actual</option>
            <option value="Ultimos 90 dias" <?php echo $filtro_periodo === 'Ultimos 90 dias' ? 'selected' : ''; ?>>Ultimos 90 dias</option>
            <option value="Ano" <?php echo $filtro_periodo === 'Ano' ? 'selected' : ''; ?>>Ano</option>
        </select>
    </div>

    <div class="filtro_item">
        <label for="operacion">Operacion</label>
        <select id="operacion" name="operacion">
            <option value="Venta y Alquiler" <?php echo $filtro_operacion === 'Venta y Alquiler' ? 'selected' : ''; ?>>Venta y Alquiler</option>
            <option value="Venta" <?php echo $filtro_operacion === 'Venta' ? 'selected' : ''; ?>>Solo Venta</option>
            <option value="Alquiler" <?php echo $filtro_operacion === 'Alquiler' ? 'selected' : ''; ?>>Solo Alquiler</option>
        </select>
    </div>

    <button class="btn_guardar" type="submit">Aplicar filtros</button>
</form>

<div class="dashboard_kpis">
    <?php foreach ($kpis as $kpi): ?>
        <div class="kpi_card">
            <span class="kpi_title"><?php echo $kpi['titulo']; ?></span>
            <strong class="kpi_value"><?php echo $kpi['valor']; ?></strong>
            <span class="kpi_detail"><?php echo $kpi['detalle']; ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="dashboard_grid">
    <section class="panel_panel">
        <div class="panel_header">
            <h3>Embudo comercial</h3>
            <span class="panel_hint"><?php echo $filtro_periodo; ?> · <?php echo $filtro_operacion; ?></span>
        </div>

        <div class="embudo_lista">
            <?php foreach ($embudo as $fila): ?>
                <div class="embudo_item">
                    <span class="embudo_label"><?php echo $fila['etapa']; ?></span>
                    <div class="embudo_barra">
                        <span style="width: <?php echo $fila['valor']; ?>%; background: <?php echo $fila['color']; ?>;"></span>
                    </div>
                    <span class="embudo_valor"><?php echo $fila['valor']; ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel_panel">
        <div class="panel_header">
            <h3>Alertas prioritarias</h3>
            <span class="panel_hint">Seguimiento del dia</span>
        </div>

        <ul class="lista_alertas">
            <?php foreach ($alertas as $alerta): ?>
                <li>
                    <strong><?php echo $alerta['titulo']; ?></strong>
                    <span><?php echo $alerta['detalle']; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="panel_panel">
        <div class="panel_header">
            <h3>Actividad reciente</h3>
            <span class="panel_hint">Equipo: <?php echo $filtro_equipo; ?></span>
        </div>

        <ul class="lista_actividad">
            <?php foreach ($actividad as $item): ?>
                <li>
                    <span class="actividad_hora"><?php echo $item['hora']; ?></span>
                    <span class="actividad_texto"><?php echo $item['texto']; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="panel_panel">
        <div class="panel_header">
            <h3>Propiedades destacadas</h3>
            <span class="panel_hint">Top 3 performance</span>
        </div>

        <div class="mini_cards">
            <div class="mini_card">
                <strong>Atico Luminoso</strong>
                <span>12 visitas · 3 ofertas</span>
                <a href="index.php?seccion=ver_propiedad&id=103&origen=propiedades-vendedor" class="btn_ver_mas">Ver detalle</a>
            </div>
            <div class="mini_card">
                <strong>Loft Urban</strong>
                <span>9 visitas · 1 reserva</span>
                <a href="index.php?seccion=ver_propiedad&id=301&origen=alquileres-vendedor" class="btn_ver_mas">Ver detalle</a>
            </div>
            <div class="mini_card">
                <strong>Duplex Moderno</strong>
                <span>7 visitas · 2 ofertas</span>
                <a href="index.php?seccion=ver_propiedad&id=202&origen=propiedades-comprador" class="btn_ver_mas">Ver detalle</a>
            </div>
        </div>
    </section>
</div>
