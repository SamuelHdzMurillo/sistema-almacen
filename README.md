# Sistema de Almacén

Sistema sencillo para el control de entradas y salidas de productos, con inventario actualizado automáticamente, historial de movimientos y recibo de salidas.

## Requisitos

- XAMPP (Apache + MySQL + PHP 7.4+)
- Navegador web

## Instalación

1. **Base de datos (un solo paso)**

   Ejecuta **una vez** `database/schema.sql`. Incluye tablas, catálogos, almacenes, auditoría, datos iniciales y usuario admin. No hace falta correr los `migrar_*.sql` en una instalación nueva.

   - **phpMyAdmin:** http://localhost/phpmyadmin → Importar → `database/schema.sql`
   - **Consola (XAMPP):** `database\install.bat` o:
     ```bat
     c:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
     ```

   Credenciales por defecto: base `sistema_almacen`, usuario **admin**, contraseña **password**.

2. **Configuración**

   - Si usas otro usuario/contraseña de MySQL, edita `config/database.php` (DB_USER, DB_PASS).

3. **Acceso**

   - Abre **http://localhost/sistema-almacen/**
   - Inicia sesión con usuario **admin** y contraseña **password** (cámbiala en producción).

4. **Actualizar una base antigua**

   Si ya tenías `sistema_almacen` de una versión anterior, revisa `database/LEEME-MIGRACIONES.txt` y ejecuta solo los scripts que te falten.

## Estructura de tablas

| Tabla                        | Uso                                              |
| ---------------------------- | ------------------------------------------------ |
| `almacenes`                  | Almacenes del sistema                            |
| `usuarios`                   | Login (usuario, clave, almacén)                  |
| `productos`                  | Catálogo de productos                            |
| `catalogo_*`                 | Proveedores, receptores, planteles, etc.         |
| `entradas` / `detalle_entradas` | Entradas y líneas (factura, docs, estado línea) |
| `salidas` / `detalle_salidas`   | Salidas y líneas (recibo firmado adjunto)      |
| `transaccion_modificaciones` | Historial de ediciones con razón                 |
| `db_audit`                     | Auditoría automática (triggers)                  |

El inventario se calcula como suma de entradas activas menos suma de salidas por producto.

## Funcionalidad

- **Login:** acceso con usuario y contraseña.
- **Dashboard:** total de artículos, entradas/salidas, actividad semanal y transacciones recientes.
- **Transacciones:** listado con búsqueda y filtros (todas / entradas / salidas).
- **Nueva entrada:** fecha, responsable y detalle (producto, cantidad). Referencia PE-AÑO-NNN.
- **Nueva salida:** fecha, nombre del receptor y detalle. Al guardar se genera el **recibo**. Referencia PS-AÑO-NNN.
- **Productos:** listado con stock actual y alta de nuevos productos.

## Recibo de salida

Cada salida genera un recibo con: nombre del receptor, fecha, lista de artículos y cantidades. Se puede imprimir desde la pantalla del recibo.

