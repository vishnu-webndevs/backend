<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class BackupController extends Controller
{
    private $backupPath;
    private $excludedPaths = [
        'vendor',
        'node_modules',
        '.git',
        'storage/logs',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/views',
        'storage/app/backups',
        '.next',
        '*.tar.gz',
        'website_backup_*'
    ];

    public function __construct()
    {
        // Use environment variable for backup path
        $this->backupPath = env('BACKUP_PATH', '/home/developerhiteshsaini/pr-w/backups');
        
        // Ensure backup directory exists
        if (!File::exists($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
        }
    }

    /**
     * Get list of available backups
     */
    public function index(): JsonResponse
    {
        try {
            $backups = [];
            $files = File::files($this->backupPath);
            
            foreach ($files as $file) {
                if (str_ends_with($file->getFilename(), '.tar.gz')) {
                    $backups[] = [
                        'filename' => $file->getFilename(),
                        'size' => $this->formatBytes($file->getSize()),
                        'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                        'download_url' => url("/api/admin/backups/{$file->getFilename()}/download")
                    ];
                }
            }
            
            // Sort by creation date (newest first)
            usort($backups, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            return response()->json([
                'success' => true,
                'backups' => $backups
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list backups: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve backup list'
            ], 500);
        }
    }

    /**
     * Create a new backup
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $timestamp = date('Ymd_His');
            $backupName = "website_backup_{$timestamp}";
            
            // Use system temp directory to avoid including previous backups
            $tempDir = "/tmp/{$backupName}";
            $backupFile = "{$this->backupPath}/{$backupName}.tar.gz";

            // Create temporary directory
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            File::makeDirectory($tempDir, 0755, true);

            // Copy database
            $this->copyDatabase($tempDir);

            // Copy essential backend files with exclusions
            $this->copyEssentialFiles($tempDir);

            // Copy configuration files
            $this->copyConfigFiles($tempDir);

            // Create a simple backup info file
            File::put("{$tempDir}/backup_info.txt", "Backup created on: " . date('Y-m-d H:i:s') . "\n");

            // Create tar.gz archive
            $this->createTarGz($tempDir, $backupFile);

            // Clean up temporary directory
            shell_exec("rm -rf {$tempDir}");

            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'filename' => "{$backupName}.tar.gz",
                'size' => $this->formatBytes(filesize($backupFile))
            ]);

        } catch (\Exception $e) {
            Log::error('Backup creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Backup creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download a backup file
     */
    public function download(string $filename): BinaryFileResponse|JsonResponse
    {
        try {
            $filePath = "{$this->backupPath}/{$filename}";
            
            if (!File::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            return response()->download($filePath);
        } catch (\Exception $e) {
            Log::error('Backup download failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Download failed'
            ], 500);
        }
    }

    /**
     * Delete a backup file
     */
    public function destroy(string $filename): JsonResponse
    {
        try {
            $filePath = "{$this->backupPath}/{$filename}";
            
            if (!File::exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            File::delete($filePath);

            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Backup deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete backup'
            ], 500);
        }
    }

    /**
     * Full restore from backup (code + database)
     */
    public function restore(Request $request, string $filename): JsonResponse
    {
        try {
            $backupPath = "{$this->backupPath}/{$filename}";
            
            if (!File::exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            // Create current state backup before restore
            $this->createCurrentStateBackup();

            // Extract and restore
            $extractDir = $this->extractBackup($backupPath);
            $this->restoreFiles($extractDir);
            $this->restoreDatabaseOnly($extractDir);

            // Run post-restore tasks (full stack)
            $postSummary = $this->runPostRestoreTasks(includeBackend: true, includeFrontend: true);
            
            // Cleanup
            File::deleteDirectory($extractDir);

            Log::info("Full backup restore completed: {$filename}");

            return response()->json([
                'success' => true,
                'message' => 'Full backup restored successfully',
                'post_restore' => $postSummary
            ]);

        } catch (\Exception $e) {
            Log::error("Backup restore failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Restore failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore only code files from backup
     */
    public function restoreCode(Request $request, string $filename): JsonResponse
    {
        try {
            $backupPath = "{$this->backupPath}/{$filename}";
            
            if (!File::exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            // Create current state backup before restore
            $this->createCurrentStateBackup();

            // Extract and restore only code files
            $extractDir = $this->extractBackup($backupPath);
            $this->restoreFiles($extractDir);

            // Run post-restore tasks (backend + frontend rebuild) but skip DB ops
            $postSummary = $this->runPostRestoreTasks(includeBackend: true, includeFrontend: true);
            
            // Cleanup
            File::deleteDirectory($extractDir);

            Log::info("Code-only backup restore completed: {$filename}");

            return response()->json([
                'success' => true,
                'message' => 'Code restored successfully (database preserved)',
                'post_restore' => $postSummary
            ]);

        } catch (\Exception $e) {
            Log::error("Code restore failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Code restore failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore only database from backup
     */
    public function restoreDatabase(Request $request, string $filename): JsonResponse
    {
        try {
            $backupPath = "{$this->backupPath}/{$filename}";
            
            if (!File::exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            // Create current state backup before restore
            $this->createCurrentStateBackup();

            // Extract and restore only database
            $extractDir = $this->extractBackup($backupPath);
            $this->restoreDatabaseOnly($extractDir);
            
            // Run post-restore tasks (backend only)
            $postSummary = $this->runPostRestoreTasks(includeBackend: true, includeFrontend: false);
            
            // Cleanup
            File::deleteDirectory($extractDir);

            Log::info("Database-only backup restore completed: {$filename}");

            return response()->json([
                'success' => true,
                'message' => 'Database restored successfully (code preserved)',
                'post_restore' => $postSummary
            ]);

        } catch (\Exception $e) {
            Log::error("Database restore failed: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Database restore failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a backup of current state before restore
     */
    private function createCurrentStateBackup(): void
    {
        try {
            $timestamp = date('Ymd_His');
            $backupName = "pre_restore_backup_{$timestamp}";
            $tempDir = "/tmp/{$backupName}";
            $backupFile = "{$this->backupPath}/{$backupName}.tar.gz";

            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
            File::makeDirectory($tempDir, 0755, true);

            $this->copyDatabase($tempDir);
            $this->copyEssentialFiles($tempDir);
            $this->copyConfigFiles($tempDir);

            $this->createTarGz($tempDir, $backupFile);
            shell_exec("rm -rf {$tempDir}");

            Log::info("Pre-restore backup created: {$backupName}.tar.gz");
        } catch (\Exception $e) {
            Log::warning("Failed to create pre-restore backup: " . $e->getMessage());
        }
    }

    /**
     * Extract backup archive
     */
    private function extractBackup(string $backupPath): string
    {
        $extractDir = "/tmp/restore_" . uniqid();
        File::makeDirectory($extractDir, 0755, true);

        $command = "cd " . escapeshellarg($extractDir) . " && tar -xzf " . escapeshellarg($backupPath);
        $result = shell_exec($command);

        if (!File::exists($extractDir)) {
            throw new \Exception('Failed to extract backup archive');
        }

        return $extractDir;
    }

    /**
     * Restore files from extracted backup
     */
    private function restoreFiles(string $extractDir): void
    {
        $projectRoot = env('PROJECT_ROOT', '/home/developerhiteshsaini/pr-w');
        
        // Find the backup directory structure
        $backupDirs = File::directories($extractDir);
        if (count($backupDirs) > 0) {
            $backupRoot = $backupDirs[0];
        } else {
            $backupRoot = $extractDir;
        }

        // Restore backend files
        $backendSource = "{$backupRoot}/backend";
        if (File::exists($backendSource)) {
            $backendTarget = "{$projectRoot}/backend";
            
            // Backup important files before restoration
            $preserveFiles = ['.env', 'storage/logs'];
            $tempPreserve = "/tmp/preserve_" . uniqid();
            File::makeDirectory($tempPreserve, 0755, true);
            
            foreach ($preserveFiles as $file) {
                $sourcePath = "{$backendTarget}/{$file}";
                if (File::exists($sourcePath)) {
                    $targetPath = "{$tempPreserve}/" . basename($file);
                    if (File::isDirectory($sourcePath)) {
                        File::copyDirectory($sourcePath, $targetPath);
                    } else {
                        File::copy($sourcePath, $targetPath);
                    }
                }
            }

            // Restore backend files using rsync, but preserve vendor and other critical directories
            $excludeFromDelete = [
                '--exclude=vendor',
                '--exclude=node_modules',
                '--exclude=storage/logs',
                '--exclude=bootstrap/cache'
            ];
            $excludeOptions = implode(' ', $excludeFromDelete);
            shell_exec("rsync -av --delete {$excludeOptions} {$backendSource}/ {$backendTarget}/");
            
            // Restore preserved files
            foreach ($preserveFiles as $file) {
                $sourcePath = "{$tempPreserve}/" . basename($file);
                $targetPath = "{$backendTarget}/{$file}";
                if (File::exists($sourcePath)) {
                    if (File::isDirectory($sourcePath)) {
                        File::copyDirectory($sourcePath, $targetPath);
                    } else {
                        File::copy($sourcePath, $targetPath);
                    }
                }
            }
            
            File::deleteDirectory($tempPreserve);
        }

        // Restore frontend files
        $frontendSource = "{$backupRoot}/frontend";
        if (File::exists($frontendSource)) {
            $frontendTarget = "{$projectRoot}/frontend";
            // Exclude node_modules from deletion during frontend restore
            shell_exec("rsync -av --delete --exclude=node_modules {$frontendSource}/ {$frontendTarget}/");
        }

        // Restore configuration files
        $configFiles = ['nginx_preview.totan.in.conf', 'auth_response.json', 'campaign_response.json', 'preview_watch.sql'];
        foreach ($configFiles as $file) {
            $sourcePath = "{$backupRoot}/{$file}";
            $targetPath = "{$projectRoot}/{$file}";
            if (File::exists($sourcePath)) {
                File::copy($sourcePath, $targetPath);
            }
        }
    }

    /**
     * Restore only database from extracted backup
     */
    private function restoreDatabaseOnly(string $extractDir): void
    {
        // Find the backup directory structure
        $backupDirs = File::directories($extractDir);
        if (count($backupDirs) > 0) {
            $backupRoot = $backupDirs[0];
        } else {
            $backupRoot = $extractDir;
        }

        $dbBackupPath = "{$backupRoot}/database_backup.sqlite";
        $dbPath = database_path('database.sqlite');

        if (File::exists($dbBackupPath)) {
            File::copy($dbBackupPath, $dbPath);
            Log::info("Database restored from backup");
        } else {
            throw new \Exception('Database backup file not found in archive');
        }
    }

    /**
     * Copy database file
     */
    private function copyDatabase(string $tempDir): void
    {
        $dbPath = database_path('database.sqlite');
        if (File::exists($dbPath)) {
            File::copy($dbPath, "{$tempDir}/database_backup.sqlite");
        }
    }

    /**
     * Copy configuration files
     */
    private function copyConfigFiles(string $tempDir): void
    {
        $projectRoot = env('PROJECT_ROOT', '/home/developerhiteshsaini/pr-w');
        $configFiles = [
            'nginx_preview.totan.in.conf',
            'auth_response.json',
            'campaign_response.json',
            'preview_watch.sql'
        ];

        foreach ($configFiles as $file) {
            $sourcePath = "{$projectRoot}/{$file}";
            if (File::exists($sourcePath)) {
                File::copy($sourcePath, "{$tempDir}/{$file}");
            }
        }
    }

    /**
     * Create tar.gz archive
     */
    private function createTarGz(string $sourceDir, string $outputFile): void
    {
        $command = "cd " . escapeshellarg(dirname($sourceDir)) . " && tar -czf " . 
                   escapeshellarg($outputFile) . " " . escapeshellarg(basename($sourceDir));
        
        $result = shell_exec($command);
        
        if (!File::exists($outputFile)) {
            throw new \Exception('Failed to create tar.gz archive');
        }
    }

    /**
     * Check if path should be excluded
     */
    private function shouldExclude(string $path): bool
    {
        foreach ($this->excludedPaths as $excludedPath) {
            if (str_starts_with($path, $excludedPath)) {
                return true;
            }
        }
        
        // Also exclude any backup files by pattern
        if (str_contains($path, 'website_backup_') || str_ends_with($path, '.tar.gz')) {
            return true;
        }
        
        return false;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Copy essential files using shell commands with strict exclusions
     */
    private function copyEssentialFiles(string $tempDir): void
    {
        $backendDir = "{$tempDir}/backend";
        $frontendDir = "{$tempDir}/frontend";
        
        // Create directories
        File::makeDirectory($backendDir, 0755, true);
        File::makeDirectory($frontendDir, 0755, true);
        
        // Copy backend files using rsync with comprehensive exclusions
        $backendPath = base_path();
        $backupPath = $this->backupPath;
        
        // Create exclude file for rsync
        $excludeFile = '/tmp/backup_exclude_' . uniqid();
        $excludePatterns = [
            'vendor/*',
            'node_modules/*',
            'storage/logs/*',
            'storage/framework/cache/*',
            'storage/framework/sessions/*',
            'storage/framework/views/*',
            'storage/app/backups/*',
            '.git/*',
            '*.tar.gz',
            'website_backup_*'
        ];
        
        file_put_contents($excludeFile, implode("\n", $excludePatterns));
        shell_exec("rsync -av --exclude-from={$excludeFile} {$backendPath}/ {$backendDir}/ 2>/dev/null");
        unlink($excludeFile);
        
        // Copy frontend files using rsync
        $projectRoot = env('PROJECT_ROOT', '/home/developerhiteshsaini/pr-w');
        $frontendPath = "{$projectRoot}/frontend";
        
        if (File::exists($frontendPath)) {
            $frontendExcludeFile = '/tmp/frontend_exclude_' . uniqid();
            $frontendExcludePatterns = [
                'node_modules/*',
                '.next/*',
                '.git/*',
                'dist/*',
                'build/*',
                '*.tar.gz',
                'website_backup_*'
            ];
            
            file_put_contents($frontendExcludeFile, implode("\n", $frontendExcludePatterns));
            shell_exec("rsync -av --exclude-from={$frontendExcludeFile} {$frontendPath}/ {$frontendDir}/ 2>/dev/null");
            unlink($frontendExcludeFile);
        }
    }

    /**
     * Run post-restore tasks (dependency install, build, restarts) with guards and summary
     */
    private function runPostRestoreTasks(bool $includeBackend = true, bool $includeFrontend = true): array
    {
        $projectRoot = env('PROJECT_ROOT', '/home/developerhiteshsaini/pr-w');
        $steps = [];

        $run = function(string $label, string $command, int $timeoutSec = 600) use (&$steps) {
            $start = microtime(true);
            Log::info("Post-restore: START {$label}");
            $descriptorSpec = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
            $process = proc_open($command, $descriptorSpec, $pipes, null, null);
            if (!is_resource($process)) {
                $steps[] = ['step'=>$label,'status'=>'failed','error'=>'proc_open failed'];
                Log::warning("Post-restore: FAIL {$label} (proc_open)");
                return;
            }
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $output=''; $error='';
            while (true) {
                $status = proc_get_status($process);
                $output .= stream_get_contents($pipes[1]);
                $error  .= stream_get_contents($pipes[2]);
                if (!$status['running']) break;
                if ((microtime(true)-$start) > $timeoutSec) {
                    proc_terminate($process, 9);
                    $steps[] = ['step'=>$label,'status'=>'timeout','duration_sec'=>round(microtime(true)-$start,2)];
                    Log::warning("Post-restore: TIMEOUT {$label}");
                    return;
                }
                usleep(150000);
            }
            $exitCode = proc_close($process);
            $duration = round(microtime(true)-$start,2);
            if ($exitCode === 0) {
                $steps[] = ['step'=>$label,'status'=>'ok','duration_sec'=>$duration];
                Log::info("Post-restore: OK {$label} ({$duration}s)");
            } else {
                $steps[] = ['step'=>$label,'status'=>'failed','code'=>$exitCode,'duration_sec'=>$duration];
                Log::warning("Post-restore: FAIL {$label} code={$exitCode}");
            }
        };

        if ($includeBackend) {
            $run('Composer Install', "cd {$projectRoot}/backend && composer install --no-dev --optimize-autoloader");
            $run('Laravel Optimize', "cd {$projectRoot}/backend && php artisan optimize");
            $run('Laravel Cache Clear', "cd {$projectRoot}/backend && php artisan cache:clear", 120);
        }

        if ($includeFrontend) {
            $run('NPM Install', "cd {$projectRoot}/frontend && npm install --no-audit --no-fund", 600);
            $run('Next Build', "cd {$projectRoot}/frontend && npm run build", 900);

            // Verify middleware-manifest exists
            $middlewareManifest = "{$projectRoot}/frontend/.next/server/middleware-manifest.json";
            if (!file_exists($middlewareManifest)) {
                Log::warning('Post-restore: middleware-manifest.json missing after build');
                $steps[] = ['step'=>'Verify Next build','status'=>'missing-middleware-manifest'];
            } else {
                $steps[] = ['step'=>'Verify Next build','status'=>'ok'];
            }
        }

        // Restart services
        $run('Restart Backend (PM2)', 'pm2 restart backend', 60);
        if ($includeFrontend) {
            $run('Restart Frontend (PM2)', 'pm2 restart frontend', 60);
        }

        return $steps;
    }
}
