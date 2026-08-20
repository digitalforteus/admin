<?php

use App\Mcp\Servers\AppServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('project', AppServer::class);
