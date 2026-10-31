<?php
declare(strict_types=1);

namespace Stillroom\Admin;

use Stillroom\Audit;
use Stillroom\Auth;
use Stillroom\Backup;
use Stillroom\Collections;
use Stillroom\Db;
use Stillroom\Env;
use Stillroom\Http;
use Stillroom\Media;
use Stillroom\Migrator;
use Stillroom\Scenes;
use Stillroom\Settings;
use Stillroom\Taxonomy;
use Stillroom\Tracks;

/** /admin — server-rendered admin panel. Every POST is CSRF-checked; every page checks the session and the role. */
final class Controller
{
    public static function handle(string $path): void
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
        if (!Migrator::installed()) {
            Http::redirect('/install');
        }
        $sub = substr($path, strlen('/admin')) ?: '/';

        if ($sub === '/login') {
            self::login();
            return;
        }
        $user = Auth::user();
        if (!$user) {
            Http::redirect('/admin/login?next=' . rawurlencode($path));
        }
        if (Http::isPost()) {
            Auth::checkCsrf();
        }

        $routes = [
            '#^/$#' => 'dashboard',
            '#^/logout$#' => 'logout',
            '#^/tracks$#' => 'tracks',
            '#^/tracks/new$#' => 'trackEdit',
            '#^/tracks/(\d+)$#' => 'trackEdit',
            '#^/tracks/reorder$#' => 'tracksReorder',
            '#^/collections$#' => 'collections',
            '#^/collections/new$#' => 'collectionEdit',
            '#^/collections/(\d+)$#' => 'collectionEdit',
            '#^/categories$#' => 'categories',
            '#^/tags$#' => 'tags',
            '#^/home$#' => 'home',
            '#^/site$#' => 'site',
            '#^/users$#' => 'users',
            '#^/account$#' => 'account',
            '#^/backups$#' => 'backups',
            '#^/backups/download/(stillroom-[0-9-]+\.zip)$#' => 'backupDownload',
        ];
        foreach ($routes as $re => $method) {
            if (preg_match($re, $sub, $m)) {
                self::$method($m[1] ?? null);
                return;
            }
        }
        http_response_code(404);
        View::render('message', ['title' => 'Not found', 'text' => 'This admin page does not exist.']);
    }

    // ------------------------------------------------------------------ auth

    private static function login(): void
    {
        $error = '';
        $next = Http::query('next', '/admin');
        if (!str_starts_with($next, '/admin')) {
            $next = '/admin';
        }
        if (Http::isPost()) {
            Auth::checkCsrf();
            $error = Auth::attempt(Http::post('email'), (string) ($_POST['password'] ?? '')) ?? '';
            if ($error === '') {
                Http::redirect($next);
            }
        } elseif (Auth::user()) {
            Http::redirect('/admin');
        }
        View::render('login', ['error' => $error, 'next' => $next], false);
    }

    private static function logout(): void
    {
        if (Http::isPost()) {
            Audit::log('logout', 'user', (string) (Auth::user()['id'] ?? ''));
            Auth::logout();
        }
        Http::redirect('/admin/login');
    }

    private static function requireAdmin(): void
    {
        if (!Auth::can('admin')) {
            http_response_code(403);
            View::render('message', ['title' => 'Admins only', 'text' => 'Ask an admin to change this.']);
            exit;
        }
    }

    // ------------------------------------------------------------------ dashboard

    private static function dashboard(): void
    {
        $counts = [];
        foreach (Db::all('SELECT status, COUNT(*) AS n FROM tracks GROUP BY status') as $r) {
            $counts[$r['status']] = (int) $r['n'];
        }
        View::render('dashboard', [
            'counts' => $counts,
            'changes' => (int) Db::val('SELECT COUNT(*) FROM tracks WHERE draft IS NOT NULL'),
            'collections' => (int) Db::val('SELECT COUNT(*) FROM collections'),
            'mediaSize' => (int) Db::val('SELECT COALESCE(SUM(size),0) FROM media'),
            'settingsDrafts' => array_filter(Settings::NAMES, [Settings::class, 'hasDraft']),
            'recent' => Audit::recent(12),
            'limits' => ['upload' => ini_get('upload_max_filesize'), 'post' => ini_get('post_max_size'), 'audio' => Env::int('MAX_AUDIO_MB', 60)],
        ]);
    }

    // ------------------------------------------------------------------ tracks

    private static function tracks(): void
    {
        $q = Http::query('q');
        $status = Http::query('status');
        $cat = (int) Http::query('category', '0');
        View::render('tracks', [
            'tracks' => Tracks::adminList($q, $status, $cat),
            'categories' => Taxonomy::categories(),
            'q' => $q, 'status' => $status, 'category' => $cat,
        ]);
    }

    private static function tracksReorder(): void
    {
        if (!Http::isPost()) {
            Http::redirect('/admin/tracks');
        }
        Tracks::reorder(array_map('intval', Http::postList('ids')));
        View::flash('Order saved. The new order is live.');
        Http::redirect('/admin/tracks');
    }

    private static function trackEdit(?string $idStr): void
    {
        $id = $idStr ? (int) $idStr : null;
        $row = $id ? Tracks::find($id) : null;
        if ($id && !$row) {
            http_response_code(404);
            View::render('message', ['title' => 'Track not found', 'text' => '']);
            return;
        }
        $data = $row ? Tracks::editable($row) : [
            'title' => '', 'slug' => '', 'subtitle' => '', 'details' => '', 'description' => '', 'category_id' => null,
            'scene' => 'stress-relief', 'engine' => null, 'audio_media_id' => null, 'cover_media_id' => null, 'duration' => null,
            'featured' => 0, 'seo_title' => '', 'seo_description' => '', 'tags' => [],
        ];
        $errors = [];
        $problems = [];

        if (Http::isPost()) {
            $action = Http::post('action', 'save');
            if ($row && $action === 'delete') {
                if (Http::post('confirm_delete') !== 'yes') {
                    View::flash('Tick “Yes, delete this track” to confirm.', 'error');
                    Http::redirect('/admin/tracks/' . $id);
                }
                Tracks::delete($id);
                View::flash('Track deleted.');
                Http::redirect('/admin/tracks');
            }
            if ($row && $action === 'unpublish') {
                Tracks::unpublish($id);
                View::flash('Unpublished. The track is hidden from the site and the app.');
                Http::redirect('/admin/tracks/' . $id);
            }
            if ($row && $action === 'discard') {
                Tracks::discardDraft($id);
                View::flash('Unpublished changes were discarded.');
                Http::redirect('/admin/tracks/' . $id);
            }

            $in = $_POST;
            $in['tags'] = array_merge(Http::postList('tags'), Taxonomy::ensureTags(Http::post('new_tags')));
            $in['audio_media_id'] = !empty($_POST['remove_audio']) ? null : ($data['audio_media_id'] ?? null);
            $in['cover_media_id'] = !empty($_POST['remove_cover']) ? null : ($data['cover_media_id'] ?? null);
            $uid = (int) Auth::user()['id'];
            $audio = Media::store($_FILES['audio'] ?? null, 'audio', $uid, (int) Http::post('duration_ms'));
            if (isset($audio['error'])) {
                $errors['audio'] = $audio['error'];
            } elseif ($audio) {
                $in['audio_media_id'] = $audio['id'];
                $in['duration'] = (int) Http::post('duration_ms');
            }
            $cover = Media::store($_FILES['cover'] ?? null, 'image', $uid);
            if (isset($cover['error'])) {
                $errors['cover'] = $cover['error'];
            } elseif ($cover) {
                $in['cover_media_id'] = $cover['id'];
            }
            if (!$audio && !empty($_POST['remove_audio'])) {
                $in['duration'] = null;
            } elseif (!$audio) {
                $in['duration'] = $data['duration'];
            }

            [$d, $verr] = Tracks::validate($in, $id);
            $errors += $verr;
            if (!$errors) {
                if (!$row) {
                    $id = Tracks::create($d, $uid);
                } else {
                    Tracks::save($id, $d);
                }
                if ($action === 'publish') {
                    $problems = Tracks::publish($id);
                    if (!$problems) {
                        View::flash('Published. It is live on the site and in the app now.');
                        Http::redirect('/admin/tracks/' . $id);
                    }
                } elseif ($action === 'preview') {
                    Http::redirect('/admin/tracks/' . $id . '?open_preview=1');
                } else {
                    View::flash(($row && $row['status'] === 'published') ? 'Saved as a draft. The live version is unchanged until you publish.' : 'Saved as a draft.');
                    Http::redirect('/admin/tracks/' . $id);
                }
                $row = Tracks::find($id);
            }
            $data = array_merge($data, $d ?? []);
        }

        View::render('track_edit', [
            'row' => $row, 'data' => $data, 'errors' => $errors, 'problems' => $problems,
            'categories' => Taxonomy::categories(), 'tags' => Taxonomy::tags(),
            'audio' => Media::find($data['audio_media_id'] ? (int) $data['audio_media_id'] : null),
            'cover' => Media::find($data['cover_media_id'] ? (int) $data['cover_media_id'] : null),
            'openPreview' => Http::query('open_preview') === '1' && $row,
        ]);
    }

    // ------------------------------------------------------------------ collections

    private static function collections(): void
    {
        if (Http::isPost() && Http::post('action') === 'reorder') {
            Collections::reorder(array_map('intval', Http::postList('ids')));
            View::flash('Order saved.');
            Http::redirect('/admin/collections');
        }
        View::render('collections', ['collections' => Collections::all()]);
    }

    private static function collectionEdit(?string $idStr): void
    {
        $id = $idStr ? (int) $idStr : null;
        $row = $id ? Collections::find($id) : null;
        if ($id && !$row) {
            http_response_code(404);
            View::render('message', ['title' => 'Collection not found', 'text' => '']);
            return;
        }
        $data = $row ? Collections::editable($row) : ['title' => '', 'slug' => '', 'description' => '', 'cover_media_id' => null, 'tracks' => []];
        $errors = [];
        $problems = [];
        if (Http::isPost()) {
            $action = Http::post('action', 'save');
            if ($row && $action === 'delete') {
                if (Http::post('confirm_delete') !== 'yes') {
                    View::flash('Tick “Yes, delete” to confirm.', 'error');
                    Http::redirect('/admin/collections/' . $id);
                }
                Collections::delete($id);
                View::flash('Collection deleted.');
                Http::redirect('/admin/collections');
            }
            if ($row && $action === 'unpublish') {
                Collections::unpublish($id);
                View::flash('Unpublished.');
                Http::redirect('/admin/collections/' . $id);
            }
            if ($row && $action === 'discard') {
                Collections::discardDraft($id);
                View::flash('Changes discarded.');
                Http::redirect('/admin/collections/' . $id);
            }
            $in = $_POST;
            $in['tracks'] = Http::postList('tracks');
            $in['cover_media_id'] = !empty($_POST['remove_cover']) ? null : $data['cover_media_id'];
            $cover = Media::store($_FILES['cover'] ?? null, 'image', (int) Auth::user()['id']);
            if (isset($cover['error'])) {
                $errors['cover'] = $cover['error'];
            } elseif ($cover) {
                $in['cover_media_id'] = $cover['id'];
            }
            [$d, $verr] = Collections::validate($in, $id);
            $errors += $verr;
            if (!$errors) {
                $row ? Collections::save($id, $d) : ($id = Collections::create($d));
                if ($action === 'publish') {
                    $problems = Collections::publish($id);
                    if (!$problems) {
                        View::flash('Published.');
                        Http::redirect('/admin/collections/' . $id);
                    }
                } elseif ($action === 'preview') {
                    Http::redirect('/admin/collections/' . $id . '?open_preview=1');
                } else {
                    View::flash('Saved as a draft.');
                    Http::redirect('/admin/collections/' . $id);
                }
                $row = Collections::find($id);
            }
            $data = array_merge($data, $d ?? []);
        }
        View::render('collection_edit', [
            'row' => $row, 'data' => $data, 'errors' => $errors, 'problems' => $problems,
            'tracks' => Tracks::adminList(),
            'cover' => Media::find($data['cover_media_id'] ? (int) $data['cover_media_id'] : null),
            'openPreview' => Http::query('open_preview') === '1' && $row,
        ]);
    }

    // ------------------------------------------------------------------ taxonomy

    private static function categories(): void
    {
        $errors = [];
        if (Http::isPost()) {
            $action = Http::post('action');
            $id = (int) Http::post('id');
            if ($action === 'reorder') {
                Taxonomy::reorderCategories(array_map('intval', Http::postList('ids')));
                View::flash('Order saved.');
                Http::redirect('/admin/categories');
            }
            if ($action === 'delete' && $id) {
                $err = Taxonomy::deleteCategory($id);
                View::flash($err ?: 'Category deleted.', $err ? 'error' : 'ok');
                Http::redirect('/admin/categories');
            }
            [$d, $errors] = Taxonomy::validateCategory($_POST, $id ?: null);
            if (!$errors) {
                Taxonomy::saveCategory($id ?: null, $d);
                View::flash('Category saved. Changes are live.');
                Http::redirect('/admin/categories');
            }
        }
        View::render('categories', ['categories' => Taxonomy::categories(), 'errors' => $errors, 'editId' => (int) Http::post('id')]);
    }

    private static function tags(): void
    {
        if (Http::isPost()) {
            $action = Http::post('action');
            $id = (int) Http::post('id');
            if ($action === 'add') {
                Taxonomy::ensureTags(Http::post('titles'));
                View::flash('Tags added.');
            } elseif ($action === 'rename' && $id) {
                Taxonomy::renameTag($id, Http::post('title'));
                View::flash('Tag renamed.');
            } elseif ($action === 'delete' && $id) {
                Taxonomy::deleteTag($id);
                View::flash('Tag deleted.');
            }
            Http::redirect('/admin/tags');
        }
        View::render('tags', ['tags' => Taxonomy::tags()]);
    }

    // ------------------------------------------------------------------ home + site settings

    private static function settingsActions(string $name, string $back): bool
    {
        $action = Http::post('action', 'save');
        if ($action === 'discard') {
            Settings::discard($name);
            View::flash('Draft discarded.');
            Http::redirect($back);
        }
        return in_array($action, ['save', 'preview', 'publish'], true);
    }

    private static function settingsFinish(string $name, string $back, string $previewUrl): never
    {
        $action = Http::post('action', 'save');
        if ($action === 'publish') {
            Settings::publish($name);
            View::flash('Published. The live site uses the new version now.');
            Http::redirect($back);
        }
        if ($action === 'preview') {
            Http::redirect($back . '?open_preview=' . rawurlencode($previewUrl));
        }
        View::flash('Saved as a draft. Visitors still see the published version.');
        Http::redirect($back);
    }

    private static function home(): void
    {
        if (Http::isPost() && self::settingsActions('home', '/admin/home')) {
            $sections = [];
            $types = $_POST['section_type'] ?? [];
            $refs = $_POST['section_ref'] ?? [];
            $titles = $_POST['section_title'] ?? [];
            foreach ((array) $types as $i => $type) {
                $type = (string) $type;
                if (!in_array($type, ['collection', 'categories', 'new', 'featured'], true)) {
                    continue;
                }
                $s = ['type' => $type];
                if ($type === 'collection') {
                    $s['slug'] = (string) ($refs[$i] ?? '');
                    if ($s['slug'] === '') {
                        continue;
                    }
                }
                $t = trim((string) ($titles[$i] ?? ''));
                if ($t !== '') {
                    $s['title'] = mb_substr($t, 0, 80);
                }
                $sections[] = $s;
            }
            Settings::saveDraft('home', [
                'hero_eyebrow' => mb_substr(Http::post('hero_eyebrow'), 0, 80),
                'hero_title' => mb_substr(Http::post('hero_title'), 0, 120),
                'hero_subtitle' => mb_substr(Http::post('hero_subtitle'), 0, 300),
                'cta_label' => mb_substr(Http::post('cta_label'), 0, 40),
                'cta_track' => preg_match('/^[a-z0-9-]{1,80}$/', Http::post('cta_track')) ? Http::post('cta_track') : '',
                'sections' => $sections,
            ]);
            self::settingsFinish('home', '/admin/home', '/?preview=1');
        }
        View::render('home', [
            'home' => Settings::get('home', true), 'hasDraft' => Settings::hasDraft('home'),
            'collections' => Collections::all(), 'tracks' => Tracks::adminList(),
            'openPreview' => self::safePreview(Http::query('open_preview')),
        ]);
    }

    private static function site(): void
    {
        self::requireAdmin();
        $errors = [];
        $current = Settings::get('site', true);
        if (Http::isPost() && self::settingsActions('site', '/admin/site')) {
            $links = [];
            foreach ((array) ($_POST['link_label'] ?? []) as $i => $label) {
                $url = trim((string) ($_POST['link_url'][$i] ?? ''));
                $label = trim((string) $label);
                if ($label === '' && $url === '') {
                    continue;
                }
                if (!preg_match('#^(https?://|mailto:)#i', $url)) {
                    $errors['links'] = 'Links must start with https://, http:// or mailto:';
                    continue;
                }
                $links[] = ['label' => mb_substr($label, 0, 40), 'url' => mb_substr($url, 0, 300)];
            }
            $uid = (int) Auth::user()['id'];
            $logoId = !empty($_POST['remove_logo']) ? null : ($current['logo_media_id'] ?? null);
            $logo = Media::store($_FILES['logo'] ?? null, 'logo', $uid);
            if (isset($logo['error'])) {
                $errors['logo'] = $logo['error'];
            } elseif ($logo) {
                $logoId = $logo['id'];
            }
            $ogId = !empty($_POST['remove_og']) ? null : ($current['seo']['image_media_id'] ?? null);
            $og = Media::store($_FILES['og_image'] ?? null, 'image', $uid);
            if (isset($og['error'])) {
                $errors['og_image'] = $og['error'];
            } elseif ($og) {
                $ogId = $og['id'];
            }
            $name = mb_substr(Http::post('name'), 0, 60);
            if ($name === '') {
                $errors['name'] = 'Site name is required.';
            }
            if (!$errors) {
                Settings::saveDraft('site', [
                    'name' => $name,
                    'tagline' => mb_substr(Http::post('tagline'), 0, 140),
                    'logo_media_id' => $logoId,
                    'links' => $links,
                    'footer' => mb_substr(Http::post('footer'), 0, 300),
                    'about' => mb_substr(Http::post('about'), 0, 3000),
                    'seo' => [
                        'title' => mb_substr(Http::post('seo_title'), 0, 120),
                        'description' => mb_substr(Http::post('seo_description'), 0, 300),
                        'image_media_id' => $ogId,
                    ],
                ]);
                self::settingsFinish('site', '/admin/site', '/?preview=1');
            }
        }
        View::render('site', [
            'site' => $current, 'hasDraft' => Settings::hasDraft('site'), 'errors' => $errors,
            'logo' => Media::find(!empty($current['logo_media_id']) ? (int) $current['logo_media_id'] : null),
            'og' => Media::find(!empty($current['seo']['image_media_id']) ? (int) $current['seo']['image_media_id'] : null),
            'openPreview' => self::safePreview(Http::query('open_preview')),
        ]);
    }

    private static function safePreview(string $url): string
    {
        return preg_match('#^/[a-z0-9/_-]*\?preview=1$#', $url) ? $url : '';
    }

    // ------------------------------------------------------------------ users

    private static function users(): void
    {
        self::requireAdmin();
        $error = '';
        if (Http::isPost()) {
            $action = Http::post('action');
            $id = (int) Http::post('id');
            $me = (int) Auth::user()['id'];
            try {
                if ($action === 'add') {
                    Auth::createUser(Http::post('email'), (string) ($_POST['password'] ?? ''), Http::post('role'), Http::post('name'));
                    Audit::log('create', 'user', '', Http::post('email'));
                    View::flash('User added. Share the password with them privately.');
                } elseif ($action === 'toggle' && $id && $id !== $me) {
                    Db::exec('UPDATE users SET active = 1 - active WHERE id = ?', [$id]);
                    Audit::log('toggle', 'user', (string) $id);
                    View::flash('User updated.');
                } elseif ($action === 'role' && $id && $id !== $me && in_array(Http::post('role'), Auth::ROLES, true)) {
                    Db::update('users', ['role' => Http::post('role')], 'id = ?', [$id]);
                    Audit::log('role', 'user', (string) $id, Http::post('role'));
                    View::flash('Role changed.');
                } elseif ($action === 'password' && $id) {
                    $p = (string) ($_POST['password'] ?? '');
                    if ($err = Auth::validatePassword($p)) {
                        throw new \InvalidArgumentException($err);
                    }
                    Db::update('users', ['password_hash' => Auth::hash($p)], 'id = ?', [$id]);
                    Audit::log('password', 'user', (string) $id);
                    View::flash('Password changed.');
                }
                Http::redirect('/admin/users');
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
        View::render('users', ['users' => Db::all('SELECT id, email, name, role, active, last_login_at FROM users ORDER BY id'), 'error' => $error]);
    }

    private static function account(): void
    {
        $error = '';
        $u = Auth::user();
        if (Http::isPost()) {
            $row = Db::one('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
            $new = (string) ($_POST['new_password'] ?? '');
            if (!password_verify((string) ($_POST['current_password'] ?? ''), $row['password_hash'])) {
                $error = 'The current password is wrong.';
            } elseif ($err = Auth::validatePassword($new)) {
                $error = $err;
            } else {
                Db::update('users', ['password_hash' => Auth::hash($new)], 'id = ?', [$u['id']]);
                Audit::log('password', 'user', (string) $u['id']);
                View::flash('Password changed.');
                Http::redirect('/admin/account');
            }
        }
        View::render('account', ['user' => $u, 'error' => $error]);
    }

    // ------------------------------------------------------------------ backups

    private static function backups(): void
    {
        self::requireAdmin();
        if (Http::isPost() && Http::post('action') === 'migrate') {
            @set_time_limit(300);
            $f = Backup::create(false); // safety copy of the database first
            $applied = Migrator::run();
            Audit::log('migrate', 'system', '', implode(',', $applied));
            View::flash($applied ? 'Database updated (' . implode(', ', $applied) . '). Safety backup: ' . basename($f) : 'Database is already up to date.');
            Http::redirect('/admin/backups');
        }
        if (Http::isPost() && Http::post('action') === 'create') {
            @set_time_limit(300);
            $f = Backup::create(Http::post('media') !== 'no');
            Audit::log('backup', 'system', '', basename($f));
            View::flash('Backup created: ' . basename($f));
            Http::redirect('/admin/backups');
        }
        View::render('backups', ['backups' => Backup::list(), 'root' => APP_ROOT, 'pending' => Migrator::pending()]);
    }

    private static function backupDownload(?string $name): void
    {
        self::requireAdmin();
        $p = Backup::path((string) $name);
        if (!$p) {
            http_response_code(404);
            exit('Not found');
        }
        Audit::log('download', 'backup', '', (string) $name);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($p) . '"');
        header('Content-Length: ' . filesize($p));
        readfile($p);
        exit;
    }

    public static function scenes(): array
    {
        return Scenes::SCENES;
    }
}
