# Almacén Cecyte 11

Sistema sencillo para el control de entradas y salidas de productos, con inventario actualizado automáticamente, historial de movimientos y recibo de salidas.

## Requisitos

- XAMPP (Apache + MySQL + PHP 7.4+)
- Navegador web

## Instalación

1. **Base de datos**

   - Abre **phpMyAdmin** (http://localhost/phpmyadmin) o la consola de MySQL.
   - Ejecuta el contenido de `database/schema.sql`.
   - Se creará la base `sistema_almacen`, tablas (usuarios, productos, entradas, detalle_entradas, salidas, detalle_salidas) y usuario inicial.

2. **Configuración**

   - Si usas otro usuario/contraseña de MySQL, edita `config/database.php` (DB_USER, DB_PASS).

3. **Acceso**

   - Abre **http://localhost/sistema-almacen/**
   - Inicia sesión con usuario **admin** y contraseña **password** (cámbiala en producción).

## Estructura de tablas

| Tabla              | Uso                                           |
| ------------------ | --------------------------------------------- |
| `usuarios`         | Login (usuario, clave)                        |
| `productos`        | Catálogo de productos                         |
| `entradas`         | Cabecera de cada entrada (fecha, responsable) |
| `detalle_entradas` | Líneas: producto, cantidad                    |
| `salidas`          | Cabecera de cada salida (fecha, receptor)     |
| `detalle_salidas`  | Líneas: producto, cantidad                    |

El inventario se calcula como suma de entradas menos suma de salidas por producto.

## Funcionalidad

- **Login:** acceso con usuario y contraseña.
- **Dashboard:** total de artículos, entradas/salidas, actividad semanal y transacciones recientes.
- **Transacciones:** listado con búsqueda y filtros (todas / entradas / salidas).
- **Nueva entrada:** fecha, responsable y detalle (producto, cantidad). Referencia PE-AÑO-NNN.
- **Nueva salida:** fecha, nombre del receptor y detalle. Al guardar se genera el **recibo**. Referencia PS-AÑO-NNN.
- **Productos:** listado con stock actual y alta de nuevos productos.

## Recibo de salida

Cada salida genera un recibo con: nombre del receptor, fecha, lista de artículos y cantidades. Se puede imprimir desde la pantalla del recibo.

## Migración (si tenías versión anterior con zonas)

Si ya tenías la base de datos con zonas, ejecuta `database/migrar_quitar_zonas.sql` y ajusta los nombres de las claves foráneas si tu MySQL los generó de otra forma.
