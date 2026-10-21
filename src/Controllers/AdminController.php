<?php

declare(strict_types=1);

namespace Lubie\Controllers;

use Lubie\Audit\AuditLogger;
use Lubie\Http\HttpException;
use Lubie\Http\Request;
use Lubie\Http\Response;
use Lubie\Repositories\SettingsRepository;
use Lubie\Repositories\UserRepository;
use Lubie\Services\AuditQueryService;
use Lubie\Services\AuthService;
use Lubie\Services\BackupService;
use Lubie\Services\UserService;
use Lubie\Sms\SmsGateway;

/** Administrator functions: users, settings, audit, backups (FR-05, FR-25, FR-36). */
final class AdminController extends BaseController
{
    private const EDITABLE = ['refresh_interval_seconds', 'session_idle_minutes', 'lockout_threshold', 'lockout_minutes', 'password_min_length',
        'sms_enabled', 'sms_daily_cap_per_user', 'sms_monthly_cap_total', 'offline_enabled'];

    public function users(Request $r): Response
    {
        return $this->ok($this->c->get(UserService::class)->list($r->queryParam('role')));
    }

    public function roles(Request $r): Response
    {
        return $this->ok($this->c->get(UserRepository::class)->roles());
    }

    public function createUser(Request $r): Response
    {
        return $this->created($this->c->get(UserService::class)->create($this->user(), $r->body));
    }

    public function updateUser(Request $r): Response
    {
        $this->c->get(UserService::class)->update($this->user(), $r->intParam('id'), $r->body);
        return Response::noContent();
    }

    public function resetPassword(Request $r): Response
    {
        $this->c->get(AuthService::class)->adminReset($this->user()->id, $r->intParam('id'), (string) $r->input('temporary_password', ''));
        return Response::noContent();
    }

    public function unlock(Request $r): Response
    {
        $this->c->get(UserService::class)->unlock($this->user(), $r->intParam('id'));
        return Response::noContent();
    }

    public function settings(Request $r): Response
    {
        return $this->ok(['settings' => $this->c->get(SettingsRepository::class)->allSettings(), 'editable' => self::EDITABLE,
            'sms_provider' => $this->c->get(SmsGateway::class)->name()]);
    }

    public function updateSetting(Request $r): Response
    {
        $key = $r->param('key');
        $value = (string) $r->input('value', '');
        if (!in_array($key, self::EDITABLE, true)) {
            throw HttpException::notFound('Unknown setting.');
        }
        $isBool = in_array($key, ['sms_enabled', 'offline_enabled'], true);
        if (($isBool && !in_array($value, ['0', '1'], true)) || (!$isBool && (!ctype_digit($value) || (int) $value > 100000))) {
            throw HttpException::validation(['value' => $isBool ? 'Choose on or off.' : 'Enter a whole number.']);
        }
        if ($key === 'refresh_interval_seconds' && (int) $value < 15) {
            throw HttpException::validation(['value' => 'Use 15 seconds or more to save mobile data.']);
        }
        $repo = $this->c->get(SettingsRepository::class);
        $old = $repo->get($key);
        $repo->update($key, $value, $this->user()->id);
        $this->c->get(AuditLogger::class)->log($this->user()->id, 'settings_change', 'system_setting', $key, ['value' => $old], ['value' => $value]);
        return Response::noContent();
    }

    public function testSms(Request $r): Response
    {
        $phone = (string) $r->input('phone', '');
        if (!preg_match('/^\+233\d{9}$/', $phone)) {
            throw HttpException::validation(['phone' => 'Enter a +233 number.']);
        }
        $res = $this->c->get(SmsGateway::class)->send($phone, 'Lubie dashboard test message.');
        $this->c->get(AuditLogger::class)->log($this->user()->id, 'sms_test', 'sms', null, null, ['provider' => $this->c->get(SmsGateway::class)->name(), 'ok' => $res['ok']]);
        return $this->ok(['provider' => $this->c->get(SmsGateway::class)->name(), 'accepted' => $res['ok'], 'error' => $res['error'],
            'note' => $this->c->get(SmsGateway::class)->name() === 'sandbox' ? 'Sandbox: nothing was sent to a phone.' : 'Check the phone to confirm delivery.']);
    }

    public function audit(Request $r): Response
    {
        [$page, $per] = $this->pageArgs($r);
        return Response::json($this->c->get(AuditQueryService::class)->search($r->query, $page, $per));
    }

    public function backups(Request $r): Response
    {
        return $this->ok($this->c->get(BackupService::class)->log());
    }

    public function createBackup(Request $r): Response
    {
        $b = $this->c->get(BackupService::class)->create($this->user()->id, 'manual');
        return Response::stream(static function () use ($b) {
            echo $b['bytes'];
        }, 'application/octet-stream', $b['file'])->withHeader('X-Backup-SHA256', $b['sha256']);
    }

    /** External scheduler endpoint: Bearer token, no session, no CSRF (nothing a browser can trigger). */
    public function scheduledBackup(Request $r): Response
    {
        $svc = $this->c->get(BackupService::class);
        $svc->authoriseScheduler($r->header('authorization'));
        $b = $svc->create(null, 'scheduled');
        return Response::stream(static function () use ($b) {
            echo $b['bytes'];
        }, 'application/octet-stream', $b['file'])->withHeader('X-Backup-SHA256', $b['sha256']);
    }
}
