<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2017
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace Controller;


use FastD\Http\Response;
use FastD\Http\ServerRequest;
use Register\Node;

/**
 * Class ServicesController
 * @package Controller
 */
class ServicesController
{
    /**
     * @param ServerRequest $request
     * @return Response
     * @throws \FastD\Packet\Exceptions\PacketException
     */
    public function collections(ServerRequest $request)
    {
        $services = node()->collection();

        return json($services);
    }

    /**
     * @param ServerRequest $request
     * @return Response
     * @throws \Exception
     */
    public function query(ServerRequest $request)
    {
        $node = $request->getAttribute('name');

        $node = node()->get($node);

        return json($node);
    }

    /**
     * @param ServerRequest $request
     * @return Response
     * @throws \FastD\Packet\Exceptions\PacketException
     */
    public function node(ServerRequest $request)
    {
        $nodeInfo = $request->getParsedBody();

        node()->add($nodeInfo['name'], $nodeInfo);

        return json($nodeInfo, Response::HTTP_CREATED);
    }
}