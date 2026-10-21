<?php

declare(strict_types=1);

namespace Lubie\Controllers;

use Lubie\Audit\AuditLogger;
use Lubie\Http\HttpException;
use Lubie\Http\Request;
use Lubie\Http\Response;
use Lubie\Views\View;

/**
 * Role page shells (/admin, /director, /pm, /qs, /site, /client). The server checks the
 * role; another role's URL returns 403 and is audit-logged. Data come from /api/v1.
 */
final class PageController extends BaseController
{
    private const PAGES = ['admin' => 'Administrator', 'director' => 'Director', 'pm' => 'Project Manager', 'qs' => 'Quantity Surveyor', 'site' => 'Site Engineer', 'client' => 'Client'];

    public function role(Request $r): Response
    {
        $u = $this->user();
        $page = explode('/', trim($r->path, '/'))[0];
        if (!isset(self::PAGES[$page]) || '/' . $page !== $u->dashboardPath) {
            $this->c->get(AuditLogger::class)->log($u->id, 'forbidden', 'page', $r->path);
            throw HttpException::forbidden('This page is for another role. Use your own dashboard.');
        }
        return Response::html($this->c->get(View::class)->render('pages/app', ['title' => self::PAGES[$page], 'role' => $page]));
    }

    public function offline(Request $r): Response
    {
        return Response::html($this->c->get(View::class)->render('pages/offline', ['title' => 'Offline'], 'layout_public'));
    }
}
