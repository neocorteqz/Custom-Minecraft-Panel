<?php
namespace App\Controllers;
use DB;

class Jobs {
    public function index() {
        \require_login();
        $jobs = DB::all('SELECT j.*, s.name AS server_name
                         FROM jobs j
                         LEFT JOIN servers s ON s.id = j.target_id AND j.target_kind="server"
                         ORDER BY j.id DESC LIMIT 100');
        \view('jobs/index', ['title'=>'Background Jobs','jobs'=>$jobs]);
    }
    public function apiShow(int $id) {
        \require_login();
        $j = DB::one('SELECT * FROM jobs WHERE id=?', [$id]);
        if (!$j) \json_response(['ok'=>false,'error'=>'not found'], 404);
        // Cast for JS convenience
        $j['progress'] = (int)$j['progress'];
        $j['total']    = (int)$j['total'];
        $j['pct'] = $j['total'] > 0 ? min(100, (int)round(100 * $j['progress'] / $j['total'])) : 0;
        \json_response($j);
    }
    public function apiForServer(int $sid) {
        \require_login();
        $rows = DB::all('SELECT id, kind, status, progress, total, message, created_at, completed_at
                         FROM jobs WHERE target_kind="server" AND target_id=? ORDER BY id DESC LIMIT 10', [$sid]);
        foreach ($rows as &$r) {
            $r['pct'] = $r['total'] > 0 ? min(100, (int)round(100 * $r['progress'] / $r['total'])) : 0;
        }
        \json_response($rows);
    }
}
