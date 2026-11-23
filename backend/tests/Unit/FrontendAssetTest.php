<?php

namespace Tests\Unit;

use App\Support\FrontendAssets;
use PHPUnit\Framework\TestCase;

class FrontendAssetTest extends TestCase
{
    public function test_only_existing_public_assets_resolve_with_correct_mime(): void
    {
        $this->assertSame('text/css; charset=utf-8', FrontendAssets::resolve('/css/styles.css')['mime']);
        $this->assertSame('text/javascript; charset=utf-8', FrontendAssets::resolve('/src/api.js')['mime']);
        $this->assertSame('image/png', FrontendAssets::resolve('/assets/images/Logo%20-%20Primary.png')['mime']);
    }

    public function test_traversal_private_paths_and_unapproved_extensions_never_resolve(): void
    {
        foreach (['/.env', '/src/../../backend/.env', '/css/%2e%2e%2fREADME.md', '/src/%252e%252e%252fapi.js', '/src/api.js%00.png', '/src/api.js/extra', '/src/README.md', '/css/missing.css', '/backend/database/database.sqlite', '/workspace', '/api/v1/me'] as $path) {
            $this->assertNull(FrontendAssets::resolve($path), $path);
        }
    }
}
