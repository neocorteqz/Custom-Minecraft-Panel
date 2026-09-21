<?php
namespace App\Controllers;
use DB;

class Backups {
    private function server(int $id): array {
        $s = DB::one('SELECT * FROM servers WHERE id=?', [$id]);
        if (!$s) { http_response_code(404); \view('errors/404'); exit; }
        return $s;
    }
    public function index(int $id) {
        \require_login();
        $s = $this->server($id);
        $sched = DB::one('SELECT * FROM backup_schedules WHERE server_id=?', [$id]) ?: [
            'interval_minutes'=>1440,'retention'=>7,'storage'=>'local','enabled'=>0,
            's3_bucket'=>'','s3_endpoint'=>'','s3_access_key'=>'','s3_secret_key'=>'',
            'last_run'=>null,
        ];
        $backups = DB::all('SELECT * FROM backups WHERE server_id=? ORDER BY id DESC LIMIT 30', [$id]);
        \view('backups/index', ['title'=>'Backups — '.$s['name'],'s'=>$s,'sched'=>$sched,'backups'=>$backups]);
    }

    public function saveSchedule(int $id) {
        \check_csrf(); \require_role('operator');
        $s = $this->server($id);
        $data = [
            'server_id' => $id,
            'interval_minutes' => max(5, (int)($_POST['interval_minutes'] ?? 1440)),
            'retention' => max(1, (int)($_POST['retention'] ?? 7)),
            'storage' => ($_POST['storage'] ?? 'local') === 's3' ? 's3' : 'local',
            's3_bucket' => trim($_POST['s3_bucket'] ?? ''),
            's3_endpoint' => trim($_POST['s3_endpoint'] ?? ''),
            's3_access_key' => trim($_POST['s3_access_key'] ?? ''),
            's3_secret_key' => trim($_POST['s3_secret_key'] ?? ''),
            'enabled' => isset($_POST['enabled']) ? 1 : 0,
        ];
        DB::q('INSERT INTO backup_schedules (server_id, interval_minutes, retention, storage, s3_bucket, s3_endpoint, s3_access_key, s3_secret_key, enabled)
               VALUES (?,?,?,?,?,?,?,?,?)
               ON DUPLICATE KEY UPDATE interval_minutes=VALUES(interval_minutes), retention=VALUES(retention),
               storage=VALUES(storage), s3_bucket=VALUES(s3_bucket), s3_endpoint=VALUES(s3_endpoint),
               s3_access_key=VALUES(s3_access_key), s3_secret_key=VALUES(s3_secret_key), enabled=VALUES(enabled)',
            [$id, $data['interval_minutes'], $data['retention'], $data['storage'],
             $data['s3_bucket'], $data['s3_endpoint'], $data['s3_access_key'], $data['s3_secret_key'], $data['enabled']]);
        \log_activity('save-backup-schedule','server:'.$s['name']);
        \flash('success','Backup schedule saved.');
        \redirect("/servers/$id/backups");
    }

    public function runNow(int $id) {
        \check_csrf(); \require_role('operator');
        $s = $this->server($id);
        $wd = $s['work_dir'] ?: "/var/lib/apexnode/servers/$id";
        if (!is_dir($wd)) @mkdir($wd, 0755, true);
        $out_dir = "/var/lib/apexnode/backups/$id";
        @mkdir($out_dir, 0755, true);
        $stamp = date('Ymd-His');
        $name = "backup-{$stamp}.tar.gz";
        $out = "$out_dir/$name";
        $bid = DB::insert('backups', ['server_id'=>$id,'name'=>$name,'path'=>$out,'status'=>'running','storage'=>'local']);
        DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, "[backup] Manual snapshot → $name", 'system']);
        $cmd = sprintf('tar -czf %s -C %s . 2>&1', escapeshellarg($out), escapeshellarg($wd));
        exec($cmd, $lines, $rc);
        if ($rc !== 0) {
            DB::q('UPDATE backups SET status="failed", error=?, completed_at=NOW() WHERE id=?', [substr(implode(' ',$lines),0,900), $bid]);
            DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, "[backup] FAILED", 'error']);
            \flash('error','Backup failed: '.substr(implode(' ',$lines),0,200));
        } else {
            DB::q('UPDATE backups SET status="completed", size_bytes=?, completed_at=NOW() WHERE id=?', [filesize($out) ?: 0, $bid]);
            DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, "[backup] Completed", 'system']);
            \log_activity('run-backup','server:'.$s['name'],$name);
            \flash('success','Backup created.');
        }
        \redirect("/servers/$id/backups");
    }

    public function restore(int $id) {
        \check_csrf(); \require_role('operator');
        $s = $this->server($id);
        $bid = (int)($_POST['backup_id'] ?? 0);
        $b = DB::one('SELECT * FROM backups WHERE id=? AND server_id=?', [$bid, $id]);
        if (!$b || !file_exists($b['path'])) { \flash('error','Backup missing on disk.'); \redirect("/servers/$id/backups"); }
        $wd = $s['work_dir'] ?: "/var/lib/apexnode/servers/$id";
        // Wipe existing contents
        if (is_dir($wd)) {
            exec('rm -rf '.escapeshellarg($wd.'/'));
        }
        @mkdir($wd, 0755, true);
        exec(sprintf('tar -xzf %s -C %s 2>&1', escapeshellarg($b['path']), escapeshellarg($wd)), $out, $rc);
        if ($rc === 0) {
            DB::q('UPDATE backups SET status="restored" WHERE id=?', [$bid]);
            DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, "[backup] Restored from ".$b['name'], 'system']);
            \log_activity('restore-backup','server:'.$s['name'],$b['name']);
            \flash('success','Restored — restart the server to use the new files.');
        } else {
            \flash('error','Restore failed: '.substr(implode(' ',$out),0,200));
        }
        \redirect("/servers/$id/backups");
    }

    public function delete(int $id) {
        \check_csrf(); \require_role('operator');
        $s = $this->server($id);
        $bid = (int)($_POST['backup_id'] ?? 0);
        $b = DB::one('SELECT * FROM backups WHERE id=? AND server_id=?', [$bid, $id]);
        if ($b) {
            if ($b['path'] && file_exists($b['path'])) @unlink($b['path']);
            DB::q('DELETE FROM backups WHERE id=?', [$bid]);
        }
        \flash('success','Backup deleted.');
        \redirect("/servers/$id/backups");
    }

    public function download(int $id) {
        \require_login();
        $bid = (int)($_GET['backup_id'] ?? 0);
        $b = DB::one('SELECT * FROM backups WHERE id=? AND server_id=?', [$bid, $id]);
        if (!$b || !file_exists($b['path'])) { http_response_code(404); echo 'Not found'; return; }
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="'.basename($b['path']).'"');
        readfile($b['path']);
    }
}
