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
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $toDelete = array_slice($files, $this->keep);

        foreach ($toDelete as $file) {
            unlink($file);
            $this->stdout("Deleted: " . basename($file) . "\n");
        }

        $this->stdout("\nRemoved " . count($toDelete) . " old backup(s), kept {$this->keep}.\n");

        return ExitCode::OK;
    }
}
