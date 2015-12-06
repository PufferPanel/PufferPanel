<?php

namespace PufferPanel\Http\Controllers\Server;

use Auth;
use PufferPanel\Models\Server;
use PufferPanel\Models\Node;
use PufferPanel\Models\Download;
use Debugbar;
use Uuid;
use Alert;

use PufferPanel\Exceptions\DisplayException;
use PufferPanel\Http\Controllers\Scales\FileController;
use PufferPanel\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ServerController extends Controller
{

    /**
     * Renders server index page for specified server.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function getIndex(Request $request)
    {
        $server = Server::getByUUID($request->route()->server);
        return view('server.index', [
            'server' => $server,
            'node' => Node::find($server->node)
        ]);
    }

    /**
     * Renders file overview page.
     *
     * @param  Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function getFiles(Request $request)
    {

        $server = Server::getByUUID($request->route()->server);
        $this->authorize('list-files', $server);

        return view('server.files.index', [
            'server' => $server,
            'node' => Node::find($server->node)
        ]);
    }

    /**
     * Renders add file page.
     *
     * @param  Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function getAddFile(Request $request)
    {

        $server = Server::getByUUID($request->route()->server);
        $this->authorize('add-files', $server);

        return view('server.files.add', [
            'server' => $server,
            'node' => Node::find($server->node),
            'directory' => (in_array($request->get('dir'), [null, '/', ''])) ? '' : trim($request->get('dir'), '/') . '/'
        ]);
    }

    /**
     * Renders edit file page for a given file.
     *
     * @param  Request $request
     * @param  string  $uuid
     * @param  string  $file
     * @return \Illuminate\Contracts\View\View
     */
    public function getEditFile(Request $request, $uuid, $file)
    {

        $server = Server::getByUUID($uuid);
        $this->authorize('edit-files', $server);

        $fileInfo = (object) pathinfo($file);
        $controller = new FileController($uuid);

        try {
            $fileContent = $controller->returnFileContents($file);
        } catch (\Exception $e) {

            Debugbar::addException($e);
            $exception = 'An error occured while attempting to load the requested file for editing, please try again.';

            if ($e instanceof DisplayException) {
                $exception = $e->getMessage();
            }

            Alert::danger($exception)->flash();
            return redirect()->route('files.index', $uuid);

        }

        return view('server.files.edit', [
            'server' => $server,
            'node' => Node::find($server->node),
            'file' => $file,
            'contents' => $fileContent->contents,
            'directory' => (in_array($fileInfo->dirname, ['.', './', '/'])) ? '/' : trim($fileInfo->dirname, '/') . '/',
            'extension' => $fileInfo->extension
        ]);

    }

    /**
     * Handles downloading a file for the user.
     *
     * @param  Request $request
     * @param  string  $uuid
     * @param  string  $file
     * @return \Illuminate\Contracts\View\View
     */
    public function getDownloadFile(Request $request, $uuid, $file)
    {

        $server = Server::getByUUID($uuid);
        $node = Node::find($server->node);

        $this->authorize('download-files', $server);

        $download = new Download;

        $download->token = Uuid::generate(4);
        $download->server = $server->id;
        $download->path = str_replace('../', '', $file);

        $download->save();

        return redirect('https://' . $node->fqdn . ':' . $node->daemonListen . '/server/download/' . $download->token);

    }

}
