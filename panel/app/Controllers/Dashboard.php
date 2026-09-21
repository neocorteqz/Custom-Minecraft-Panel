<?php
namespace App\Controllers;

use DB;

class Dashboard {
    public function index() {
        \require_login();
        $servers = DB::all('SELECT s.*, n.name AS node_name FROM servers s JOIN nodes n ON n.id=s.node_id ORDER BY s.id DESC');
        $stats = [
            'total'   => count($servers),
            'online'  => count(array_filter($servers, fn($s)=>$s['status']==='online')),
            'nodes'   => (int)DB::one('SELECT COUNT(*) c FROM nodes')['c'],
            'players' => array_sum(array_map(fn($s)=>(int)$s['players_online'], $servers)),
        ];
        $activity = DB::all('SELECT a.*, u.username FROM activity_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 12');
        \view('dashboard/index', ['title'=>'Dashboard','servers'=>$servers,'stats'=>$stats,'activity'=>$activity]);
    }
}
