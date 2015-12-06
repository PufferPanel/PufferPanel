<?php

namespace PufferPanel\Http\Controllers\Admin;

use Debugbar;
use PufferPanel\Models\Server;
use PufferPanel\Models\Node;

use PufferPanel\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ServersController extends Controller
{

    public function getIndex(Request $request)
    {
        return view('admin.servers.index', [
            'servers' => Server::select('servers.*', 'nodes.name as a_nodeName', 'users.email as a_ownerEmail')
                ->join('nodes', 'servers.node', '=', 'nodes.id')
                ->join('users', 'servers.owner', '=', 'users.id')
                ->paginate(20),
        ]);
    }

    public function getNew(Request $request)
    {
        //
    }

    public function getView(Request $request, $id)
    {
        //
    }

}
