<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Traits\GrandstreamTrait;

class IPController extends Controller
{
    use GrandstreamTrait;

    public function index()
    {
        $localExtensions = Extension::with('department')->get()->keyBy('extension');
        $monitorList = [];

        if ($this->testConnection()) {
            $accounts = $this->fetchLiveAccounts();
            
            foreach ($accounts as $account) {
                $ext = $account['extension'];
                $local = $localExtensions->get($ext);

                $monitorList[] = [
                    'extension' => $ext,
                    'name' => $local?->fullname ?? $account['fullname'] ?? 'Desconocido',
                    'department' => $local?->department?->name ?? '-',
                    'ip' => $this->parseAddress($account['addr'] ?? null),
                    'status' => $account['status'],
                ];
            }
        }

        return view('monitor.index', compact('monitorList'));
    }

    private function fetchLiveAccounts(): array
    {
        $response = $this->connectApi('listAccount', [
            'options' => 'extension,status,addr,fullname',
            'item_num' => 1000,
            'sidx' => 'extension',
            'sord' => 'asc'
        ]);

        return $response['response']['account'] 
            ?? $response['response']['body']['account'] 
            ?? [];
    }

    private function parseAddress(?string $addr): string
    {
        return ($addr && $addr !== '-') ? $addr : '---';
    }
}
