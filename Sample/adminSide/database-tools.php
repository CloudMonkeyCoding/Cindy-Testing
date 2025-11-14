<?php
require_once __DIR__ . '/includes/require_super_admin.php';
require_once __DIR__ . '/../PHP/db_connect.php';

$activePage = 'database-tools';
$pageTitle = "Database Tools - Cindy's Bakeshop";
$downloadError = '';
$restoreMessage = '';
$restoreError = '';

$pdoReady = $pdo instanceof PDO;

if ($pdoReady && isset($_GET['action']) && $_GET['action'] === 'download') {
    try {
        if (!function_exists('generateDatabaseBackupSql')) {
            function generateDatabaseBackupSql(PDO $pdo): string
            {
                $pdo->exec('SET SESSION sql_quote_show_create = 1');
                $tablesStmt = $pdo->query('SHOW TABLES');
                $tables = $tablesStmt ? $tablesStmt->fetchAll(PDO::FETCH_COLUMN) : [];

                $lines = [];
                $lines[] = '-- Cindy\'s Bakeshop database backup';
                $lines[] = '-- Generated on ' . date('Y-m-d H:i:s');
                $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';

                foreach ($tables as $table) {
                    if (!is_string($table) || $table === '') {
                        continue;
                    }

                    $quotedTable = '`' . str_replace('`', '``', $table) . '`';
                    $lines[] = "\n-- ------------------------------------------------------";
                    $lines[] = "-- Table structure for $quotedTable";
                    $lines[] = "DROP TABLE IF EXISTS $quotedTable;";

                    $createStmt = $pdo->query("SHOW CREATE TABLE $quotedTable");
                    $createRow = $createStmt ? $createStmt->fetch(PDO::FETCH_ASSOC) : null;
                    if ($createRow) {
                        $createSql = $createRow['Create Table'] ?? $createRow['Create View'] ?? '';
                        if ($createSql !== '') {
                            $lines[] = $createSql . ';';
                        }
                    }

                    $dataStmt = $pdo->query("SELECT * FROM $quotedTable");
                    if ($dataStmt) {
                        while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                            $values = [];
                            foreach ($row as $value) {
                                if ($value === null) {
                                    $values[] = 'NULL';
                                } elseif (is_numeric($value) && !preg_match('/^0[0-9]/', (string) $value)) {
                                    $values[] = (string) $value;
                                } else {
                                    $values[] = $pdo->quote((string) $value);
                                }
                            }

                            $lines[] = 'INSERT INTO ' . $quotedTable . ' VALUES (' . implode(', ', $values) . ');';
                        }
                    }
                }

                $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
                $lines[] = '';

                return implode("\n", $lines);
            }
        }

        $sqlDump = generateDatabaseBackupSql($pdo);
        $fileName = 'cindys_bakeshop_backup_' . date('Ymd_His') . '.sql';

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . strlen($sqlDump));
        echo $sqlDump;
        exit;
    } catch (Throwable $e) {
        $downloadError = 'Failed to generate backup: ' . htmlspecialchars($e->getMessage());
    }
} elseif (isset($_GET['action']) && $_GET['action'] === 'download') {
    $downloadError = 'Database connection is not available. Please try again later.';
}

if (!function_exists('splitSqlStatements')) {
    function splitSqlStatements(string $sql): array
    {
        $sql = ltrim($sql, "\xEF\xBB\xBF");
        $statements = [];
        $current = '';
        $length = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';
            $prev = $i > 0 ? $sql[$i - 1] : '';

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                    $current .= "\n";
                }
                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }
                continue;
            }

            if (!$inSingle && !$inDouble && !$inBacktick) {
                if ($char === '-' && $next === '-') {
                    $nextNext = $i + 2 < $length ? $sql[$i + 2] : '';
                    if ($nextNext === ' ' || $nextNext === "\t" || $nextNext === "\r" || $nextNext === "\n") {
                        $inLineComment = true;
                        $i++;
                        continue;
                    }
                }

                if ($char === '#') {
                    $inLineComment = true;
                    continue;
                }

                if ($char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $i++;
                    continue;
                }
            }

            if ($char === "'" && !$inDouble && !$inBacktick) {
                if ($inSingle) {
                    if ($prev !== '\\' && $next !== "'") {
                        $inSingle = false;
                    } elseif ($next === "'") {
                        $current .= "''";
                        $i++;
                        continue;
                    }
                } else {
                    $inSingle = true;
                }
            } elseif ($char === '"' && !$inSingle && !$inBacktick) {
                if ($inDouble) {
                    if ($prev !== '\\' && $next !== '"') {
                        $inDouble = false;
                    } elseif ($next === '"') {
                        $current .= '""';
                        $i++;
                        continue;
                    }
                } else {
                    $inDouble = true;
                }
            } elseif ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $trimmed = trim($current);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore'])) {
    if (!$pdoReady) {
        $restoreError = 'Database connection is not available. Please try again later.';
    } else {
        $upload = $_FILES['sql_file'] ?? null;
        if (!$upload || !isset($upload['error']) || $upload['error'] !== UPLOAD_ERR_OK) {
            $restoreError = 'Please upload a valid SQL backup file.';
        } elseif (($upload['size'] ?? 0) <= 0) {
            $restoreError = 'The uploaded file is empty.';
        } else {
            $sqlContent = file_get_contents($upload['tmp_name']);
            if ($sqlContent === false) {
                $restoreError = 'Unable to read the uploaded file. Please try again.';
            } else {
                $statements = splitSqlStatements($sqlContent);
                if (empty($statements)) {
                    $restoreError = 'The uploaded file does not contain any SQL statements.';
                } else {
                    $transactionActive = false;

                    try {
                        $transactionActive = $pdo->beginTransaction() && $pdo->inTransaction();
                    } catch (Throwable $transactionError) {
                        $transactionActive = false;
                    }

                    try {
                        foreach ($statements as $statement) {
                            $pdo->exec($statement);
                            if ($transactionActive && !$pdo->inTransaction()) {
                                $transactionActive = false;
                            }
                        }

                        if ($transactionActive && $pdo->inTransaction()) {
                            try {
                                $pdo->commit();
                            } catch (Throwable $commitError) {
                                $restoreError = 'Database restored but failed to finalize transaction: ' . htmlspecialchars($commitError->getMessage());
                            }
                        }

                        if (!$restoreError) {
                            $restoreMessage = 'Database restored successfully.';
                        }
                    } catch (Throwable $e) {
                        if ($transactionActive && $pdo->inTransaction()) {
                            try {
                                $pdo->rollBack();
                            } catch (Throwable $rollbackError) {
                                // Swallow rollback errors and surface the original exception message.
                            }
                        }

                        if (!$restoreError) {
                            $restoreError = 'Failed to restore database: ' . htmlspecialchars($e->getMessage());
                        }
                    }
                }
            }
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main">
  <div class="header">
    <h1>Database Tools</h1>
    <a href="profile.php" class="user-info">
      <span><?= htmlspecialchars($adminSession['name']); ?></span>
      <img src="<?= htmlspecialchars($adminSession['avatar_url']); ?>" alt="<?= htmlspecialchars($adminSession['name']); ?> avatar">
    </a>
  </div>

  <div class="card" style="display:flex;flex-direction:column;gap:16px;max-width:680px;">
    <?php if ($downloadError): ?>
      <div class="alert alert-danger" style="background:#fdecea;color:#c0392b;padding:12px 16px;border-radius:8px;">
        <?= $downloadError; ?>
      </div>
    <?php endif; ?>
    <?php if ($restoreMessage): ?>
      <div class="alert alert-success" style="background:#eaf7ee;color:#1e8449;padding:12px 16px;border-radius:8px;">
        <?= htmlspecialchars($restoreMessage); ?>
      </div>
    <?php endif; ?>
    <?php if ($restoreError): ?>
      <div class="alert alert-danger" style="background:#fdecea;color:#c0392b;padding:12px 16px;border-radius:8px;">
        <?= $restoreError; ?>
      </div>
    <?php endif; ?>

    <section>
      <h2 style="margin-bottom:8px;">Download Backup</h2>
      <p style="margin-bottom:12px;">Download a full SQL backup of the current Cindy&#39;s Bakeshop database.</p>
      <a class="btn btn-primary" href="?action=download" style="display:inline-flex;align-items:center;gap:8px;">
        <i class="fa-solid fa-download"></i>
        Download SQL Backup
      </a>
    </section>

    <hr style="border:none;border-top:1px solid #e0e0e0;">

    <section>
      <h2 style="margin-bottom:8px;">Restore Database</h2>
      <p style="margin-bottom:12px;">Upload a previously generated SQL backup to restore the database. This will overwrite existing data.</p>
      <form method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:12px;">
        <div class="form-group" style="display:flex;flex-direction:column;gap:4px;">
          <label for="sql_file">SQL Backup File</label>
          <input type="file" name="sql_file" id="sql_file" accept=".sql" required>
        </div>
        <button type="submit" name="restore" class="btn btn-danger" style="align-self:flex-start;display:inline-flex;align-items:center;gap:8px;">
          <i class="fa-solid fa-upload"></i>
          Restore from Backup
        </button>
      </form>
    </section>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
