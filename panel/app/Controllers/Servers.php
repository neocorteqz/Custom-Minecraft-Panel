<?php
namespace App\Controllers;

use DB;

class Servers {
    public function index() {
        \require_login();
        $servers = DB::all('SELECT s.*, n.name AS node_name FROM servers s JOIN nodes n ON n.id=s.node_id ORDER BY s.id DESC');
        \view('servers/index', ['title'=>'Servers','servers'=>$servers]);
    }
    public function create() {
        \require_role('operator');
        $nodes = DB::all('SELECT * FROM nodes ORDER BY name');
        \view('servers/create', ['title'=>'Deploy Server','nodes'=>$nodes]);
    }
    public function store() {
        \check_csrf();
        $u = \require_role('operator');
        $name = trim($_POST['name'] ?? '');
        $game = $_POST['game'] ?? '';
        $node_id = (int)($_POST['node_id'] ?? 0);
        $port = (int)($_POST['port'] ?? 25565);
        $cpu = (int)($_POST['cpu_limit'] ?? 2);
        $ram = (int)($_POST['ram_mb'] ?? 2048);
        $disk = (int)($_POST['disk_gb'] ?? 10);
        if (!$name || !in_array($game, ['minecraft-java','minecraft-bedrock','cs2','rust']) || !$node_id) {
            \flash('error','Missing required fields.'); \redirect('/servers/new');
        }
        $id = DB::insert('servers', [
            'name'=>$name,'game'=>$game,'node_id'=>$node_id,'owner_id'=>$u['id'],
            'port'=>$port,'cpu_limit'=>$cpu,'ram_mb'=>$ram,'disk_gb'=>$disk,
            'status'=>'installing','version'=>'latest','players_max'=>$game==='cs2'?32:($game==='rust'?100:20),
        ]);
        DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, '[installer] Provisioning '.$game.' server "'.$name.'" on node '.$node_id, 'system']);
        DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, '[installer] Allocated CPU='.$cpu.'c RAM='.$ram.'MB DISK='.$disk.'GB PORT='.$port, 'info']);
        DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, '[installer] Downloading game files…', 'info']);
        DB::q('UPDATE servers SET status="offline" WHERE id=?', [$id]);
        \log_activity('create-server','server:'.$name);
        \flash('success','Server "'.$name.'" deployed. Ready to start.');
        \redirect('/servers/'.$id);
    }
    public function show(int $id) {
        \require_login();
        $s = DB::one('SELECT s.*, n.name AS node_name FROM servers s JOIN nodes n ON n.id=s.node_id WHERE s.id=?', [$id]);
        if (!$s) { http_response_code(404); \view('errors/404'); return; }
        \view('servers/show', ['title'=>$s['name'],'s'=>$s]);
    }
    public function action() {
        \check_csrf();
        \require_role('operator');
        $id = (int)($_POST['id'] ?? 0);
        $act = $_POST['action'] ?? '';
        $s = DB::one('SELECT * FROM servers WHERE id=?', [$id]);
        if (!$s) { \flash('error','Server not found.'); \redirect('/servers'); }
        $map = ['start'=>'starting','stop'=>'stopping','restart'=>'starting','kill'=>'offline'];
        if (!isset($map[$act])) { \flash('error','Invalid action.'); \redirect('/servers/'.$id); }
        DB::q('UPDATE servers SET status=? WHERE id=?', [$map[$act], $id]);
        DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, '[control] '.strtoupper($act).' issued by '.($_SESSION['uid']??''), 'system']);
        // simulate transition to steady state
        if ($act === 'start' || $act === 'restart') {
            DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, '[game] Loading world…', 'info']);
            DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, '[game] Server online on port '.$s['port'], 'info']);
            DB::q('UPDATE servers SET status="online", cpu_usage=?, ram_usage_mb=?, players_online=? WHERE id=?',
                [rand(10,55), (int)($s['ram_mb']*rand(30,70)/100), rand(0, (int)$s['players_max']), $id]);
        } elseif ($act === 'stop' || $act === 'kill') {
            DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, '[game] Server stopped.', 'system']);
            DB::q('UPDATE servers SET status="offline", cpu_usage=0, ram_usage_mb=0, players_online=0 WHERE id=?',[$id]);
        }
        \log_activity($act.'-server','server:'.$s['name']);
        \flash('success', ucfirst($act).' complete.');
        \redirect('/servers/'.$id);
    }
    public function delete() {
        \check_csrf();
        \require_role('admin');
        $id = (int)($_POST['id'] ?? 0);
        $s = DB::one('SELECT name FROM servers WHERE id=?', [$id]);
        DB::q('DELETE FROM servers WHERE id=?', [$id]);
        \log_activity('delete-server','server:'.($s['name']??''));
        \flash('success','Server removed.');
        \redirect('/servers');
    }
    public function apiList() {
        \require_login();
        // Simulate live drift
        $rows = DB::all('SELECT * FROM servers');
        foreach ($rows as $r) {
            if ($r['status'] === 'online') {
                $cpu = max(2, min(95, (float)$r['cpu_usage'] + rand(-8,8)));
                $ram = max(64, min((int)$r['ram_mb'], (int)$r['ram_usage_mb'] + rand(-64,64)));
                DB::q('UPDATE servers SET cpu_usage=?, ram_usage_mb=? WHERE id=?', [$cpu, $ram, $r['id']]);
            }
        }
        \json_response(DB::all('SELECT id, name, game, status, cpu_usage, ram_usage_mb, ram_mb, players_online, players_max FROM servers'));
    }
    public function apiLogs() {
        \require_login();
        $id = (int)($_GET['id'] ?? 0);
        $after = (int)($_GET['after'] ?? 0);
        $s = DB::one('SELECT * FROM servers WHERE id=?', [$id]);
        if (!$s) \json_response(['lines'=>[]]);
        // Simulate log drift if online
        if ($s['status'] === 'online' && rand(0,2) === 0) {
            $samples = [
                'minecraft-java'    => ['[Server] Saved the game','[Server] '.rand(1,20).' players online','[Server] Chunk generated at (X,Y)'],
                'minecraft-bedrock' => ['[INFO] Player connected','[INFO] Level saved','[INFO] Autosave completed'],
                'cs2'               => ['L 12:00:00: World triggered "Round_Start"','L 12:00:00: "player" connected','L 12:00:00: score '.rand(0,16).':'.rand(0,16)],
                'rust'              => ['[Rust] Net traffic: '.rand(10,300).'KB/s','[Rust] Save complete in '.rand(1,3).'s','[Rust] Player joined'],
            ];
            $lines = $samples[$s['game']] ?? ['[server] Heartbeat OK'];
            DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, $lines[array_rand($lines)], 'info']);
        }
        $logs = DB::all('SELECT id, line, level, DATE_FORMAT(created_at,"%H:%i:%s") ts FROM server_logs WHERE server_id=? AND id > ? ORDER BY id ASC LIMIT 60', [$id, $after]);
        \json_response(['lines'=>$logs]);
    }
    public function apiConsoleCmd() {
        \require_role('operator');
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        // Verify CSRF from header for JSON
        if (!hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF'] ?? '')) \json_response(['error'=>'csrf'],419);
        $id = (int)($body['id'] ?? 0);
        $cmd = trim($body['cmd'] ?? '');
        if (!$id || !$cmd) \json_response(['error'=>'invalid']);
        DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, '> '.$cmd, 'system']);
        // Fake responses
        $resp = match(true) {
            str_starts_with($cmd,'say ') => '[Server] Broadcast: '.substr($cmd,4),
            $cmd === 'list' => '[Server] There are '.rand(0,20).' players online.',
            $cmd === 'help' => '[Server] Commands: say <msg>, list, stop, save',
            $cmd === 'stop' => '[Server] Stopping…',
            $cmd === 'save' => '[Server] Saving game.',
            default => '[Server] Unknown command: '.$cmd,
        };
        DB::q('INSERT INTO server_logs (server_id, line, level) VALUES (?,?,?)', [$id, $resp, 'info']);
        \json_response(['ok'=>true]);
    }
}
