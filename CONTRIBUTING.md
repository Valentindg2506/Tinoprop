# Guía de Contribución

## Aviso importante — Proyecto Propietario

**TinoProp es un proyecto propietario.** El código fuente está publicado con fines de **evaluación académica** por el tribunal del Ciclo Formativo de Grado Superior en DAM (curso 2025/2026). Consulta el archivo [LICENSE](./LICENSE) para más detalles.

Esto significa que **no se aceptan contribuciones externas de código** (pull requests de terceros serán cerrados).

---

## ¿Puedo participar de alguna forma?

Aunque no se aceptan contribuciones de código, sí son bienvenidos:

### Reportar errores (Issues)

Si encuentras un bug o comportamiento inesperado:

1. Abre un [Issue](../../issues/new/choose)
2. Usa la plantilla **Bug Report**
3. Describe el problema con el mayor detalle posible (pasos para reproducirlo, comportamiento esperado vs. obtenido, capturas de pantalla si aplica)

### Sugerencias de mejora

Si tienes una idea o propuesta de mejora:

1. Abre un [Issue](../../issues/new/choose)
2. Usa la plantilla **Feature Request**
3. Explica el caso de uso y por qué sería útil

### Preguntas técnicas

Para preguntas sobre la arquitectura, tecnologías o decisiones de diseño, puedes abrir un Issue con el prefijo `[Pregunta]` en el título.

---

## Entorno de desarrollo (para el autor)

### Requisitos
- PHP 8.2+
- MySQL 8.0+ / MariaDB 10.6+
- Apache 2.4 con `mod_rewrite` y `mod_headers`

### Configuración local
```bash
# Clonar el repositorio
git clone https://github.com/Valentindg2506/Tinoprop.git
cd Tinoprop

# Copiar la versión actual y configurar
cd "V1.0.3-Arreglo de errores"
cp .env.example .env
# Editar .env con los datos de tu entorno local

# Crear directorios necesarios
mkdir -p storage/uploads storage/documentacion logs
```

### Convenciones de commit

```
tipo: descripción breve en imperativo

feat:     nueva funcionalidad
fix:      corrección de error
docs:     cambios en documentación
refactor: refactorización sin cambio de funcionalidad
style:    cambios de formato/estilo
chore:    tareas de mantenimiento
```

---

**Valentín Antonio De Gennaro** — Valencia, España — 2025/2026
