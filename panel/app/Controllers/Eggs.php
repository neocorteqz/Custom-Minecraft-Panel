<?php
namespace App\Controllers;
use DB;

class Eggs {
    public function index() {
        \require_login();
        $game = $_GET['game'] ?? 'all';
        $sql = 'SELECT * FROM eggs';
        $args = [];
        if ($game !== 'all') { $sql .= ' WHERE game = ?'; $args[] = $game; }
        $sql .= ' ORDER BY featured DESC, downloads DESC, id ASC';
        $eggs = DB::all($sql, $args);
        \view('eggs/index', ['title'=>'Egg Marketplace','eggs'=>$eggs,'filter'=>$game]);
    }
    public function show(int $id) {
        \require_login();
        $egg = DB::one('SELECT * FROM eggs WHERE id=?', [$id]);
        if (!$egg) { http_response_code(404); \view('errors/404'); return; }
        \view('eggs/show', ['title'=>$egg['name'],'egg'=>$egg]);
    }
    public function deploy(int $id) {
        \require_role('operator');
        $egg = DB::one('SELECT * FROM eggs WHERE id=?', [$id]);
        if (!$egg) { \flash('error','Egg not found.'); \redirect('/eggs'); }
        $nodes = DB::all('SELECT * FROM nodes ORDER BY name');
        \view('eggs/deploy', ['title'=>'Deploy '.$egg['name'],'egg'=>$egg,'nodes'=>$nodes]);
    }
}
