<?php
namespace Doba\Rpc;

class UtilRpc
{
    public function __construct() {
    }

    public function ping($params) {
        return 'pong';
    }
}