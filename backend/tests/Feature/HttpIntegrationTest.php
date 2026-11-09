<?php

namespace Tests\Feature;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class HttpIntegrationTest extends TestCase
{
    public function test_real_http_session_csrf_persistence_and_frontend_assets(): void
    {
        $database = storage_path('framework/testing-http-'.Str::uuid().'.sqlite');
        touch($database);
        $port = random_int(18000, 24000);
        $environment = [
            'APP_ENV' => 'local', 'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
            'SESSION_DRIVER' => 'database', 'CACHE_STORE' => 'database',
            'SESSION_SECURE_COOKIE' => 'false', 'SESSION_DOMAIN' => '',
        ];
        $server = null;

        try {
            $migration = new Process([PHP_BINARY, 'artisan', 'migrate', '--seed', '--force', '--no-interaction'], base_path(), $environment);
            $migration->setTimeout(60);
            $migration->mustRun();
            config(['database.connections.http_smoke' => ['driver' => 'sqlite', 'database' => $database, 'foreign_key_constraints' => true]]);
            $roleId = DB::connection('http_smoke')->table('roles')->where('name', 'Red Cross Administrator')->value('id');
            $user = User::factory()->make(['role_id' => $roleId, 'active' => true]);
            $user->setConnection('http_smoke')->save();
            $email = $user->email;

            $server = new Process([PHP_BINARY, '-S', '127.0.0.1:'.$port, base_path('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], public_path(), $environment);
            $server->disableOutput();
            $server->start();
            $jar = new CookieJar;
            $client = new Client(['base_uri' => 'http://127.0.0.1:'.$port, 'cookies' => $jar, 'http_errors' => false, 'timeout' => 5, 'headers' => ['Accept' => 'application/json']]);

            $ready = false;
            for ($attempt = 0; $attempt < 30; $attempt++) {
                try {
                    $ready = $client->get('/api/v1/health')->getStatusCode() === 200;
                } catch (ConnectException) {
                    usleep(100000);
                }

                if ($ready) {
                    break;
                }
            }

            $this->assertTrue($ready, 'Isolated HTTP test server did not become ready.');
            $this->assertSame(401, $client->get('/api/v1/me')->getStatusCode());
            $this->assertSame(419, $client->post('/api/v1/auth/login', ['json' => ['email' => $email, 'password' => 'password']])->getStatusCode());
            $this->assertSame(204, $client->get('/sanctum/csrf-cookie')->getStatusCode());
            $token = fn (): string => rawurldecode($jar->getCookieByName('XSRF-TOKEN')->getValue());
            $signedIn = $client->post('/api/v1/auth/login', ['headers' => ['X-XSRF-TOKEN' => $token()], 'json' => ['email' => $email, 'password' => 'password']]);
            $this->assertSame(200, $signedIn->getStatusCode(), (string) $signedIn->getBody());
            $this->assertNotNull($jar->getCookieByName('blood_repository_session'));

            $key = (string) Str::uuid();
            $body = ['name' => 'HTTP Fictional Donor', 'blood_type' => 'B+', 'municipality' => 'Digos City'];
            $created = $client->post('/api/v1/donors', ['headers' => ['X-XSRF-TOKEN' => $token(), 'Idempotency-Key' => $key], 'json' => $body]);
            $this->assertSame(201, $created->getStatusCode(), (string) $created->getBody());
            $id = json_decode((string) $created->getBody(), true)['data']['id'];
            $retrieved = $client->get('/api/v1/donors/'.$id);
            $this->assertSame('HTTP Fictional Donor', json_decode((string) $retrieved->getBody(), true)['data']['name']);
            $this->assertSame(1, DB::connection('http_smoke')->table('donors')->count());
            $this->assertSame(1, DB::connection('http_smoke')->table('audit_logs')->where('action', 'donor.created')->count());
            $this->assertSame(200, $client->get('/workspace')->getStatusCode());
            $this->assertStringContainsString('src/connected.js', (string) $client->get('/workspace')->getBody());
            $this->assertStringContainsString('--navy', (string) $client->get('/css/styles.css')->getBody());
            $this->assertSame(200, $client->get('/assets/images/Logo%20-%20Primary.png')->getStatusCode());
            $this->assertSame(204, $client->post('/api/v1/auth/logout', ['headers' => ['X-XSRF-TOKEN' => $token()]])->getStatusCode());
            $this->assertSame(401, $client->get('/api/v1/donors')->getStatusCode());
        } finally {
            $server?->stop(2);
            DB::purge('http_smoke');
            if (is_file($database)) {
                unlink($database);
            }
        }
    }
}
