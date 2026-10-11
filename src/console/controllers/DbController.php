<?php

namespace Noo\CraftModules\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\FileHelper;
use yii\console\ExitCode;

class DbController extends Controller
{
    /**
     * @var int Number of backups to keep. Defaults to 10.
     */
    public int $keep = 10;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        if ($actionID === 'cleanup') {
            $options[] = 'keep';
        }

        return $options;
    }

    /**
     * Removes old database backups, keeping the most recent ones.
     *
     * Example:
     * ```
     * php craft craft-modules/db/cleanup
     * php craft craft-modules/db/cleanup --keep=10
     * ```
     */
    public function actionCleanup(): int
    {
        if ($this->keep < 0) {
            $this->stderr("--keep must be 0 or greater.\n");

            return ExitCode::USAGE;
        }

        $backupPath = Craft::$app->getPath()->getDbBackupPath();

        if (!is_dir($backupPath)) {
            $this->stdout("No backup directory found.\n");

            return ExitCode::OK;
        }

        $files = FileHelper::findFiles($backupPath, [
            'only' => ['*.sql', '*.sql.zip'],
        ]);

        if (count($files) <= $this->keep) {
            $this->stdout("Nothing to clean up. Found " . count($files) . " backup(s), keeping {$this->keep}.\n");

            return ExitCode::OK;
        }

        // Sort by modification time, newest first
        $mtimes = array_map(fn ($file) => (int) @filemtime($file), array_combine($files, $files));
        arsort($mtimes);

        $toDelete = array_slice(array_keys($mtimes), $this->keep);
        $deleted = 0;
        $failed = 0;

        foreach ($toDelete as $file) {
            if (@unlink($file)) {
                $deleted++;
                $this->stdout("Deleted: " . basename($file) . "\n");
            } else {
                $failed++;
                $this->stderr("Could not delete: " . basename($file) . "\n");
            }
        }

        $this->stdout("\nRemoved {$deleted} old backup(s), kept {$this->keep}.\n");

        if ($failed > 0) {
            return ExitCode::IOERR;
        }

        return ExitCode::OK;
    }
}
