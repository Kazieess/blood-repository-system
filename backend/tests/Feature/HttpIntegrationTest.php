<?php

namespace Tests\Feature;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('sqlite-only')]
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
            $donation = $client->post('/api/v1/donors/'.$id.'/donations', ['headers' => ['X-XSRF-TOKEN' => $token(), 'Idempotency-Key' => (string) Str::uuid()], 'json' => ['reference' => 'HTTP-QA-EVENT', 'donated_on' => now('Asia/Manila')->toDateString(), 'location' => 'Fictional collection']]);
            $this->assertSame(201, $donation->getStatusCode());
            $bag = $client->post('/api/v1/inventory', ['headers' => ['X-XSRF-TOKEN' => $token(), 'Idempotency-Key' => (string) Str::uuid()], 'json' => ['reference' => 'HTTP-QA-BAG', 'blood_type' => 'B+', 'component' => 'QA Component', 'location' => 'QA Store', 'collected_at' => now()->toISOString(), 'expires_at' => now()->addDay()->toISOString(), 'reason' => 'Fictional stock record']]);
            $this->assertSame(201, $bag->getStatusCode());
            $bagId = json_decode((string) $bag->getBody(), true)['data']['id'];
            $sourceBody = ['version' => 1, 'donation_reference' => 'HTTP-QA-EVENT', 'reason' => 'Fictional source link'];
            $this->assertSame(419, $client->post('/api/v1/inventory/'.$bagId.'/source', ['json' => $sourceBody])->getStatusCode());
            $this->assertSame(200, $client->post('/api/v1/inventory/'.$bagId.'/source', ['headers' => ['X-XSRF-TOKEN' => $token()], 'json' => $sourceBody])->getStatusCode());
            $this->assertSame(200, $client->post('/api/v1/inventory/'.$bagId.'/transitions', ['headers' => ['X-XSRF-TOKEN' => $token()], 'json' => ['version' => 2, 'action' => 'verify', 'verification_confirmed' => true, 'reason' => 'Fictional verification']])->getStatusCode());
            $publication = $client->post('/api/v1/publications', ['headers' => ['X-XSRF-TOKEN' => $token(), 'Idempotency-Key' => (string) Str::uuid()], 'json' => ['location' => 'QA Store', 'public_label' => 'QA Public Center', 'valid_minutes' => 15, 'confirmed' => true, 'reason' => 'Fictional public snapshot']]);
            $this->assertSame(201, $publication->getStatusCode());
            $publicClient = new Client(['base_uri' => 'http://127.0.0.1:'.$port, 'http_errors' => false, 'timeout' => 5]);
            $public = json_decode((string) $publicClient->get('/api/v1/public/availability')->getBody(), true);
            $this->assertSame(1, $public['data'][0]['units']);
            $this->assertEqualsCanonicalizing(['blood_type', 'component', 'location', 'units'], array_keys($public['data'][0]));
            $this->assertSame(200, $client->get('/availability')->getStatusCode());
            $this->assertSame(200, $client->get('/recovery')->getStatusCode());
            $this->assertSame(200, $client->get('/src/availability.js')->getStatusCode());
            $this->assertSame(200, $client->get('/api/v1/reports/inventory')->getStatusCode());
            $this->assertSame(403, $client->get('/api/v1/staff')->getStatusCode());
            $history = $client->get('/api/v1/donors/'.$id.'/donations');
            $this->assertSame(1, json_decode((string) $history->getBody(), true)['completed_count']);
            $grant = $client->put('/api/v1/assignments/'.$id, ['headers' => ['X-XSRF-TOKEN' => $token()], 'json' => ['user_id' => $user->id, 'version' => 1, 'clinical_permissions' => ['screening.read', 'screening.write'], 'reason' => 'Fictional HTTP permission test']]);
            $this->assertSame(200, $grant->getStatusCode());
            $clinical = $client->post('/api/v1/donors/'.$id.'/clinical/screening', ['headers' => ['X-XSRF-TOKEN' => $token(), 'Idempotency-Key' => (string) Str::uuid()], 'json' => ['source_reference' => 'HTTP-QA-SOURCE', 'observed_at' => now()->subMinute()->toISOString(), 'payload' => ['decision' => 'recorded', 'notes' => 'Fictional private HTTP note']]]);
            $this->assertSame(201, $clinical->getStatusCode());
            $this->assertStringNotContainsString('Fictional private HTTP note', DB::connection('http_smoke')->table('clinical_entries')->value('payload'));
            $this->assertSame(403, $client->get('/api/v1/backups')->getStatusCode());
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
