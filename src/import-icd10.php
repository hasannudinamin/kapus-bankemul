
<?php

// Enforce execution only via Command Line Interface (CLI)
if (php_sapi_name() !== 'cli') {
    die("Error: This script must be run from the command line.\n");
}

$txtFilePath = 'icd10cm-order-2027.txt';
$dbFilePath  = '/var/www/db/ira.sqlite';

if (!file_exists($txtFilePath)) {
    die("Error: Source file '{$txtFilePath}' not found in this directory.\n");
}

try {
    $pdo = new PDO("sqlite:" . $dbFilePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Creating schema and indexes...\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS icd10cm (
            code TEXT PRIMARY KEY,
            name TEXT
        );
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_icd10cm_code ON icd10cm(code);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_icd10cm_name ON icd10cm(name);");

    $fileHandle = fopen($txtFilePath, 'r');
    if (!$fileHandle) {
        throw new Exception("Failed to open text file for reading.");
    }

    $stmt = $pdo->prepare("INSERT OR IGNORE INTO icd10cm (code, name) VALUES (:code, :name)");

    $pdo->beginTransaction();
    
    echo "Processing and importing codes...\n";
    $rowCount = 0;

    while (($line = fgets($fileHandle)) !== false) {
        if (trim($line) === '') {
            continue;
        }

        // 1. Extract alphanumeric code (Position 7-13: 0-indexed 6, length 7)
        $rawCode = trim(substr($line, 6, 7));
        
        // 2. Extract LONG Description (Position 78 onwards: 0-indexed 77)
        // This drops the short description entirely and stops the duplication.
        // $name = trim(substr($line, 77));

        // 2. Extract Short Description
        $name = trim(substr($line, 16, 60));

        // Format code layout (e.g. "A01" stays "A01", "A010" becomes "A01.0")
        if (strlen($rawCode) > 3) {
            $code = substr($rawCode, 0, 3) . '.' . substr($rawCode, 3);
        } else {
            $code = $rawCode;
        }

        $stmt->execute([
            ':code' => $code,
            ':name' => $name
        ]);

        $rowCount++;
    }

    fclose($fileHandle);
    $pdo->commit();

    echo "Success! Imported {$rowCount} clean records into '{$dbFilePath}'.\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Execution Failed: " . $e->getMessage() . "\n";
    exit(1);
}
