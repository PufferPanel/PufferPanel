<?php

namespace PufferPanel\Http\Controllers\Server;

use Log;
use Debugbar;
use PufferPanel\Models\Server;
use PufferPanel\Models\Node;
use PufferPanel\Http\Helpers;

use PufferPanel\Exceptions\DisplayException;
use PufferPanel\Http\Controllers\Scales\FileController;
use PufferPanel\Http\Controllers\Controller;
use Illuminate\Http\Request;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AjaxController extends Controller
{

    /**
     * @var array
     */
    protected $files = [];

    /**
     * @var array
     */
    protected $folders = [];

    /**
     * @var string
     */
    protected $directory;

    /**
     * Returns true or false depending on the power status of the requested server.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string $uuid
     * @return \Illuminate\Contracts\View\View
     */
    public function getStatus(Request $request, $uuid)
    {

        $server = Server::getByUUID($uuid);
        $client = Node::guzzleRequest($server->node);

        try {

            $res = $client->request('GET', '/server', [
                'headers' => Server::getGuzzleHeaders($uuid)
            ]);

            if($res->getStatusCode() === 200) {

                $json = json_decode($res->getBody());

                if (isset($json->status) && $json->status === 1) {
                    return 'true';
                }

            }

        } catch (RequestException $e) {
            Debugbar::error($e->getMessage());
            Log::notice('An exception was raised while attempting to contact a Scales instance to get server status information.', [
                'exception' => $e->getMessage(),
                'path' => $request->path()
            ]);
        }

        return 'false';
    }

    /**
     * Returns a listing of files in a given directory for a server.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  string $uuid`
     * @return \Illuminate\Contracts\View\View
     */
    public function postDirectoryList(Request $request, $uuid)
    {

        $server = Server::getByUUID($uuid);
        $this->directory = '/' . trim(urldecode($request->input('directory', '/')), '/');
        $this->authorize('list-files', $server);

        $prevDir = [
            'header' => ($this->directory !== '/') ? $this->directory : ''
        ];
        if ($this->directory !== '/') {
            $prevDir['first'] = true;
        }

        // Determine if we should show back links in the file browser.
        // This code is strange, and could probably be rewritten much better.
        $goBack = explode('/', rtrim($this->directory, '/'));
        if (isset($goBack[2]) && !empty($goBack[2])) {
            $prevDir['show'] = true;
            $prevDir['link'] = '/' . trim(str_replace(end($goBack), '', $this->directory), '/');
            $prevDir['link_show'] = trim($prevDir['link'], '/');
        }

        $controller = new FileController($uuid);

        try {
            $directoryContents = $controller->returnDirectoryListing($this->directory);
        } catch (\Exception $e) {

            Debugbar::addException($e);
            $exception = 'An error occured while attempting to load the requested directory, please try again.';

            if ($e instanceof DisplayException) {
                $exception = $e->getMessage();
            }

            return response($exception, 500);

        }

        return view('server.files.list', [
            'server' => $server,
            'files' => $directoryContents->files,
            'folders' => $directoryContents->folders,
            'extensions' => Helpers::editableFiles(),
            'directory' => $prevDir
        ]);

    }

    /**
     * Handles a POST request to save a file.
     *
     * @param  Request $request
     * @param  string  $uuid
     * @return \Illuminate\Http\Response
     */
    public function postSaveFile(Request $request, $uuid)
    {

        $server = Server::getByUUID($uuid);
        $this->authorize('save-files', $server);

        $controller = new FileController($uuid);

        try {
            $controller->saveFileContents($request->input('file'), $request->input('contents'));
            return response(null, 204);
        } catch (\Exception $e) {

            Debugbar::addException($e);
            $exception = 'An error occured while attempting to save that file, please try again.';

            if ($e instanceof DisplayException) {
                $exception = $e->getMessage();
            }

            return response($exception, 500);

        }

    }

}
