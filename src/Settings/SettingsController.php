<?php

declare(strict_types=1);

namespace FileRoll\Settings;

use FileRoll\Core\Request;
use FileRoll\Core\Response;
use FileRoll\Core\Container;
use FileRoll\User\UserController;

class SettingsController
{
    public function profile(Request $request, Response $response): Response
    {
        $controller = new UserController();
        return $controller->profile($request, $response);
    }

    public function update(Request $request, Response $response): Response
    {
        $controller = new UserController();
        return $controller->update($request, $response);
    }

    public function changePassword(Request $request, Response $response): Response
    {
        $controller = new UserController();
        return $controller->changePassword($request, $response);
    }
}
