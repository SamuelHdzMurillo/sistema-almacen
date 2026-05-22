@echo off
REM Instalacion de base de datos - Sistema de Almacen (XAMPP)
set MYSQL=c:\xampp\mysql\bin\mysql.exe
if not exist "%MYSQL%" (
  echo No se encontro MySQL en %MYSQL%
  echo Ajusta la ruta o ejecuta database\schema.sql en phpMyAdmin.
  pause
  exit /b 1
)
echo Ejecutando database\schema.sql ...
"%MYSQL%" -u root < "%~dp0schema.sql"
if errorlevel 1 (
  echo Error al importar el esquema.
  pause
  exit /b 1
)
echo Listo. Base: sistema_almacen | Usuario: admin | Clave: password
pause
