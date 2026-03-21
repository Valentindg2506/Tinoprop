<div class="content-wrapper">
    <div class="header">
        <h2>Gestión de Propiedades</h2>
        <button class="btn-primary">+ Nueva Propiedad</button>
    </div>

    <div class="table-container">
        <table class="crm-table">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Tipo</th>
                    <th>Localización</th>
                    <th>Precio</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>Apartamento Céntrico</td>
                    <td>Piso</td>
                    <td>Valencia</td>
                    <td>185.000 €</td>
                    <td>Disponible</td>
                    <td>
                        <button class="btn-secondary">Ver más ➜</button>
                    </td>
                </tr>

                <tr>
                    <td>Chalet con Jardín</td>
                    <td>Chalet</td>
                    <td>Torrent</td>
                    <td>320.000 €</td>
                    <td>Reservado</td>
                    <td>
                        <button class="btn-secondary">Ver más ➜</button>
                    </td>
                </tr>

                <tr>
                    <td>Local Comercial</td>
                    <td>Local</td>
                    <td>Paterna</td>
                    <td>1.200 €/mes</td>
                    <td>Alquiler</td>
                    <td>
                        <button class="btn-secondary">Ver más ➜</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<style>
/* ===== Estructura ===== */

.content-wrapper {
    padding: 30px;
    background: #f5f6fa;
    min-height: 100vh;
    font-family: "Segoe UI", sans-serif;
}

/* ===== Cabecera ===== */

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header h2 {
    font-size: 24px;
    color: #333;
    margin: 0;
}

/* ===== Botones ===== */

.btn-primary {
    background: #28a745;
    color: #fff;
    padding: 10px 18px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: 0.2s ease;
}

.btn-primary:hover {
    background: #218838;
}

.btn-secondary {
    background: #e9f3ff;
    color: #007bff;
    padding: 8px 14px;
    border: 1px solid #cfe2ff;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: 0.2s ease;
}

.btn-secondary:hover {
    background: #d7e9ff;
}

/* ===== Tabla CRM ===== */

.table-container {
    background: #fff;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 7px rgba(0,0,0,0.07);
}

.crm-table {
    width: 100%;
    border-collapse: collapse;
}

.crm-table thead {
    background: #2d3e50;
    color: #fff;
}

.crm-table thead th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
}

.crm-table tbody tr {
    border-bottom: 1px solid #e4e4e4;
}

.crm-table tbody td {
    padding: 12px;
    font-size: 14px;
    color: #444;
}

.crm-table tbody tr:hover {
    background: #f0f7ff;
}
</style>
