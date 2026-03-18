<?php
/**
 * PDOStatement personalizado para auditar todas las queries.
 *
 * Se usa vía `PDO::ATTR_STATEMENT_CLASS` en `config/database.php`.
 */

class LoggedPDOStatement extends PDOStatement {
    protected function __construct(...$args) {
        // PDO internamente pasa argumentos al construir el statement; no necesitamos
        // inicializar nada aquí, pero debemos aceptar los argumentos para que no falle.
    }

    public function execute($input_parameters = null): bool {
        $start = microtime(true);
        $sql = (string) ($this->queryString ?? '');
        $op = $this->detectOp($sql);
        $requestId = $GLOBALS['APP_REQUEST_ID'] ?? null;
        $userId = $GLOBALS['APP_USER_ID'] ?? null;
        $userName = $GLOBALS['APP_USER_NAME'] ?? null;
        $ruta = $GLOBALS['APP_REQUEST_CTX']['ruta'] ?? null;
        $method = $GLOBALS['APP_REQUEST_CTX']['method'] ?? null;
        $table = $this->extractTable($sql);

        $paramsSafe = $this->normalizeParamsForLog($input_parameters);
        $summary = $this->buildPrettySummary($op, $table, $paramsSafe, $userName, $userId, $method, $ruta);

        try {
            $result = parent::execute($input_parameters);
            $durationMs = (microtime(true) - $start) * 1000;
            $this->writeLog([
                'timestamp' => date('c'),
                'request_id' => $requestId,
                'usuario_id' => $userId,
                'usuario_nombre' => $userName,
                'duration_ms' => round($durationMs, 3),
                'op' => $op,
                'write' => $this->isWriteOp($op),
                'tabla' => $table,
                'sql' => $sql,
                'params_raw' => $paramsSafe,
                'resumen' => $summary,
                'ok' => true,
            ]);
            $this->writePrettyLine([
                'timestamp' => date('c'),
                'request_id' => $requestId,
                'usuario_nombre' => $userName,
                'usuario_id' => $userId,
                'op' => $op,
                'tabla' => $table,
                'resumen' => $summary,
            ]);
            return $result;
        } catch (Throwable $e) {
            $durationMs = (microtime(true) - $start) * 1000;
            $this->writeLog([
                'timestamp' => date('c'),
                'request_id' => $requestId,
                'usuario_id' => $userId,
                'usuario_nombre' => $userName,
                'duration_ms' => round($durationMs, 3),
                'op' => $op,
                'write' => $this->isWriteOp($op),
                'tabla' => $table,
                'sql' => $sql,
                'params_raw' => $paramsSafe,
                'resumen' => $summary,
                'ok' => false,
                'error_class' => get_class($e),
                'error_code' => $e->getCode(),
                // No incluimos el mensaje para evitar más fuga de info.
            ]);
            throw $e;
        }
    }

    private function extractTable(string $sql): ?string {
        $s = trim($sql);
        if (preg_match('/\bINSERT\s+INTO\s+([`"]?[A-Za-z0-9_]+[`"]?)/i', $s, $m)) {
            return trim($m[1], '`"');
        }
        if (preg_match('/^\s*UPDATE\s+([`"]?[A-Za-z0-9_]+[`"]?)/i', $s, $m)) {
            return trim($m[1], '`"');
        }
        if (preg_match('/\bDELETE\s+FROM\s+([`"]?[A-Za-z0-9_]+[`"]?)/i', $s, $m)) {
            return trim($m[1], '`"');
        }
        return null;
    }

    private function buildPrettySummary(string $op, ?string $table, $paramsSafe, $userName, $userId, $method, $ruta): string {
        $u = '';
        if ($userName !== null && $userName !== '') {
            $u = "usuario={$userName}";
        } else {
            $u = "usuario_id={$userId}";
        }
        $m = $method !== null ? $method : '';
        $r = $ruta !== null ? $ruta : '';
        $t = $table !== null ? $table : 'tabla?';

        $paramsShort = $this->paramsToShortString($paramsSafe);
        return trim("{$u} {$m} {$r} -> {$op} {$t} params={$paramsShort}");
    }

    private function paramsToShortString($paramsSafe): string {
        if ($paramsSafe === null) {
            return '[]';
        }
        $encoded = @json_encode($paramsSafe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = (string)$paramsSafe;
        }
        // Evitar logs gigantescos si hay arrays grandes.
        $max = 500;
        if (strlen($encoded) > $max) {
            $encoded = substr($encoded, 0, $max) . '...';
        }
        return $encoded;
    }

    private function detectOp(string $sql): string {
        $s = ltrim($sql);
        if (preg_match('/^(INSERT|UPDATE|DELETE|REPLACE)\b/i', $s, $m)) {
            return strtoupper($m[1]);
        }
        if (preg_match('/^SELECT\b/i', $s)) {
            return 'SELECT';
        }
        if (preg_match('/^WITH\b/i', $s)) {
            return 'WITH';
        }
        return 'OTHER';
    }

    private function isWriteOp(string $op): bool {
        return in_array($op, ['INSERT', 'UPDATE', 'DELETE', 'REPLACE'], true);
    }

    private function normalizeParamsForLog($input_parameters) {
        // params puede venir como array u otro tipo según cómo se invoque execute().
        if ($input_parameters === null) {
            return null;
        }
        $encoded = @json_encode($input_parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return [
                '_non_json' => true,
                'value' => var_export($input_parameters, true),
            ];
        }
        $decoded = json_decode($encoded, true);
        if ($decoded === null && strtolower(trim((string) $encoded)) !== 'null') {
            // Si decode falla, guardamos el JSON como string.
            return [
                '_params_json' => $encoded,
            ];
        }
        return $decoded;
    }

    private function writeLog(array $entry): void {
        try {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            $logFile = $logDir . '/sql.log';
            $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($line === false) {
                return;
            }
            @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // Logging nunca debe romper el sistema.
        }
    }

    private function writePrettyLine(array $entry): void {
        try {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            $logFile = $logDir . '/sql_pretty.log';
            // Una línea humana por ejecución.
            $line = sprintf(
                "%s request_id=%s %s",
                $entry['timestamp'] ?? '',
                $entry['request_id'] ?? '',
                $entry['resumen'] ?? ''
            );
            @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // No romper sistema si falla.
        }
    }
}

